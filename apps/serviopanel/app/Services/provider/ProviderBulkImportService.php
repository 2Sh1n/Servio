<?php

namespace App\Services\provider;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Config\Database;
use App\Services\utility\SlugService;
use App\Models\Seo_model;

class ProviderBulkImportService
{
    protected $db;
    protected Seo_model $seoModel;
    protected SlugService $slugService;

    protected int $chunkSize = 50;
    protected bool $debug = true;

    /**
     * Header name (spreadsheet) → DB column for partner_details table.
     * Only fields present in the uploaded file will be included in the payload.
     */
    private const PROVIDER_FIELD_MAP = [
        'Type'                      => 'type',
        'Visiting Charge'           => 'visiting_charges',
        'Advance Booking Days'      => 'advance_booking_days',
        'Number of Members'         => 'number_of_members',
        'At Store'                  => 'at_store',
        'At Doorstep'               => 'at_doorstep',
        'Need Approval for Service' => 'need_approval_for_the_service',
        'Address'                   => 'address',
        'Is Approved'               => 'is_approved',
        'Banner Image'              => 'banner',
    ];

    /**
     * Header name (spreadsheet) → DB column for users table.
     */
    private const USER_FIELD_MAP = [
        'Email'        => 'email',
        'Phone'        => 'phone',
        'Country Code' => 'country_code',
        'Password'     => 'password',
        'City'         => 'city',
        'Latitude'     => 'latitude',
        'Longitude'    => 'longitude',
        'Image'        => 'image',
        'Login Type'   => 'loginType',
    ];

    /**
     * Headers that must be present for insert mode.
     */
    private const REQUIRED_HEADERS_INSERT = [
        'Type',
        'Email',
        'Phone',
        'Country Code',
        'Password',
        'Login Type',
    ];

    /**
     * Headers that must be present for update mode.
     */
    private const REQUIRED_HEADERS_UPDATE = [
        'User ID',
    ];

    public function __construct()
    {
        $this->db = Database::connect();
        $this->slugService = new SlugService();
        $this->seoModel = new Seo_model();
    }

    /* ============================================================
     | ENTRY POINT
     ============================================================ */

    public function handle($file, string $mode)
    {
        // Copy to a temp file with .xlsx extension so the Xlsx reader can open it as a zip
        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk_') . '.xlsx';
        copy($file->getTempName(), $tmpPath);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        [$headers, $rows] = $this->parseCsv($sheet);
        $spreadsheet->disconnectWorksheets();
        @unlink($tmpPath);

        $missing = $this->validateRequiredHeaders($headers, $mode);
        if (!empty($missing)) {
            return ErrorResponse(
                'Missing required columns: ' . implode(', ', $missing),
                true, [], [], 200, csrf_token(), csrf_hash()
            );
        }

        $languages = fetch_details('languages', [], ['id', 'code', 'is_default']);
        $defaultLang = array_filter($languages, fn($lang) => $lang['is_default'] == 1)[0]['code'] ?? 'en';

        // Fetch visible custom field definitions once for all chunks
        $customFields = $this->loadCustomFieldDefinitions();

        foreach (array_chunk($rows, $this->chunkSize) as $index => $chunk) {
            // $this->debug("Processing chunk #" . ($index + 1));

            $this->processChunk(
                $chunk,
                $headers,
                $languages,
                $defaultLang,
                $mode,
                $customFields
            );
        }

        return successResponse("Providers imported successfully");
    }

    /* ============================================================
     | CORE CHUNK HANDLER
     ============================================================ */

    private function processChunk(array $rows, array $headers, array $languages, string $defaultLang, string $mode, array $customFields)
    {
        $this->db->transStart();

        foreach ($rows as $row) {

            $translations = $this->extractTranslationsFromRow($row, $headers, $languages);
            $seoTranslations = $this->extractSeoTranslationsFromRow($row, $headers, $languages);

            $partnerIdRaw = $this->getValue($row, $headers, 'User ID');
            $partnerId = ($partnerIdRaw !== '' && $partnerIdRaw !== null) ? (int) $partnerIdRaw : null;

            $slug = $this->slugService->resolve(
                $partnerId ? $this->getExistingSlug($partnerId) : null,
                null,
                $translations[$defaultLang]['company_name'] ?? 'provider',
                'partner_details',
                $partnerId
            );

            $provider = $this->buildProviderPayload($row, $headers, $translations, $defaultLang, $slug);
            $user = $this->buildUserPayload($row, $headers, $translations, $defaultLang);

            // --- Validations ---

            // Latitude/Longitude range validation
            if (isset($user['latitude'])) {
                $lat = (float) $user['latitude'];
                if (!is_numeric($user['latitude']) || $lat < -90 || $lat > 90) {
                    $this->db->transRollback();
                    return ErrorResponse(labels(PLEASE_ENTER_VALID_LATITUDE, "Please enter valid latitude"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }
            if (isset($user['longitude'])) {
                $lng = (float) $user['longitude'];
                if (!is_numeric($user['longitude']) || $lng < -180 || $lng > 180) {
                    $this->db->transRollback();
                    return ErrorResponse(labels(PLEASE_ENTER_VALID_LONGITUDE, "Please enter valid longitude"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Password strength validation (insert only)
            if ($mode === 'insert' && isset($user['password'])) {
                $passwordErrors = validate_password_strength($user['password']);
                if (!empty($passwordErrors)) {
                    $this->db->transRollback();
                    return ErrorResponse(implode(', ', $passwordErrors), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Login type uniqueness check (insert only)
            if ($mode === 'insert') {
                $loginType = $user['loginType'] ?? 'phone';
                $uniqueError = $this->checkLoginTypeUniqueness($user, $loginType);
                if ($uniqueError) {
                    $this->db->transRollback();
                    return ErrorResponse($uniqueError, true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Collect other images from any Other Image[N] columns
            $otherImages = $this->collectOtherImages($row, $headers);
            if (!empty($otherImages)) {
                $provider['other_images'] = json_encode($otherImages);
            }

            // $this->debug('Prepared payload', compact('provider', 'user'));

            if ($mode === 'insert') {
                $partnerId = $this->insertBatch($user, $provider);
            } else {
                $this->updateBatch($partnerId, $user, $provider);
            }

            // Save partner timings only if any day columns are present
            if ($this->hasTimingHeaders($headers)) {
                $timings = $this->buildTimingsPayload($row, $headers, $partnerId);
                $this->savePartnerTimings($timings, $partnerId, $mode);
            }

            // Save custom field values (bank_details + documents)
            $customFieldValues = $this->collectCustomFieldValues($row, $headers, $customFields);
            if (!empty($customFieldValues)) {
                $this->upsertPartnerCustomFields((int) $partnerId, $customFieldValues);
            }

            $this->saveBulkPartnerTranslations($partnerId, $translations);

            // SEO Meta Image is a single non-translated field
            $seoMetaImage = $this->hasHeader($headers, 'SEO Meta Image')
                ? trim((string) $this->getValue($row, $headers, 'SEO Meta Image'))
                : null;

            $this->saveBulkPartnerSeoSettings($partnerId, $seoTranslations, $languages, $seoMetaImage);
        }

        $this->db->transComplete();
    }

    /* ============================================================
     | INSERT / UPDATE
     ============================================================ */

    private function insertBatch(array $user, array $provider): int
    {
        $user['password'] = (new \IonAuth\Models\IonAuthModel())
            ->hashPassword($user['password']);
        $user['ip_address'] = \Config\Services::request()->getIPAddress();
        $user['created_on'] = time();
        $user['created_at'] = date('Y-m-d H:i:s');
        $user['updated_at'] = date('Y-m-d H:i:s');

        $this->db->table('users')->insert($user);
        $userId = $this->db->insertID();

        $this->db->table('users_groups')->insert([
            'user_id' => $userId,
            'group_id' => 3
        ]);

        $provider['partner_id'] = $userId;
        $provider['created_at'] = date('Y-m-d H:i:s');
        $provider['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('partner_details')->insert($provider);

        return $userId;
    }

    private function updateBatch(int $partnerId, array $user, array $provider)
    {
        // Password is not updated during bulk update
        unset($user['password']);

        // Only run updates if there are fields to update beyond the always-set keys
        if (count($user) > 1) { // more than just 'active'
            $user['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('users')->update($user, ['id' => $partnerId]);
        }
        if (count($provider) > 1) { // more than just 'slug'
            $provider['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('partner_details')->update($provider, ['partner_id' => $partnerId]);
        }
    }

    /**
     * Check login type uniqueness for a new provider.
     * Email login: email must be unique among providers.
     * Phone login: phone + country_code must be unique among providers.
     */
    private function checkLoginTypeUniqueness(array $user, string $loginType): ?string
    {
        $builder = $this->db->table('users u')
            ->select('u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 3);

        if ($loginType === 'email') {
            $email = $user['email'] ?? '';
            if ($email === '') {
                return labels('please_enter_email', 'Email is required for email login type');
            }
            $builder->where('u.email', $email)
                    ->where('u.loginType', 'email');
            $exists = $builder->countAllResults() > 0;
            if ($exists) {
                return $email . ' - ' . labels('email_already_exists', 'Email already exists for a provider');
            }
        } else {
            $phone = $user['phone'] ?? '';
            $countryCode = $user['country_code'] ?? '';
            if ($phone === '') {
                return labels('please_enter_phone', 'Phone is required for phone login type');
            }
            $builder->where('u.phone', $phone)
                    ->where('u.country_code', $countryCode)
                    ->where('u.loginType', 'phone');
            $exists = $builder->countAllResults() > 0;
            if ($exists) {
                return $phone . ' - ' . labels(PHONE_NUMBER_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, 'Phone number already exists please use another one');
            }
        }

        return null;
    }

    /* ============================================================
     | PAYLOAD BUILDERS
     ============================================================ */

    private function buildProviderPayload($row, $headers, $translations, $defaultLang, $slug)
    {
        $payload = ['slug' => $slug];

        // Map only the columns that exist in the uploaded file
        foreach (self::PROVIDER_FIELD_MAP as $headerName => $dbColumn) {
            if (!$this->hasHeader($headers, $headerName)) {
                continue;
            }

            $value = $this->getValue($row, $headers, $headerName);

            // Special handling per field
            if ($dbColumn === 'is_approved') {
                $value = in_array($value, ['0', '1', 0, 1], true) ? $value : 0;
            }
            if ($dbColumn === 'number_of_members') {
                $type = (int) $this->getValue($row, $headers, 'Type');
                $value = ($type === 1) ? $value : '1';
            }

            $payload[$dbColumn] = $value;
        }

        // Translation-derived fields: include only when translation data exists
        $defaultTranslation = $translations[$defaultLang] ?? [];
        if (!empty($defaultTranslation['company_name'])) {
            $payload['company_name'] = $defaultTranslation['company_name'];
        }
        if (!empty($defaultTranslation['about'])) {
            $payload['about'] = $defaultTranslation['about'];
        }
        if (!empty($defaultTranslation['long_description'])) {
            $payload['long_description'] = $defaultTranslation['long_description'];
        }

        return $payload;
    }

    private function buildUserPayload($row, $headers, $translations, $defaultLang)
    {
        $payload = ['active' => 1];

        // Map only the columns that exist in the uploaded file
        foreach (self::USER_FIELD_MAP as $headerName => $dbColumn) {
            if (!$this->hasHeader($headers, $headerName)) {
                continue;
            }

            $value = $this->getValue($row, $headers, $headerName);

            // Special handling per field
            if ($dbColumn === 'email') {
                $value = strtolower(trim($value));
            }
            if ($dbColumn === 'country_code') {
                $value = strpos($value, '+') === 0 ? $value : '+' . $value;
            }
            if ($dbColumn === 'latitude' || $dbColumn === 'longitude') {
                $value = is_numeric($value) ? number_format((float) $value, 7, '.', '') : $value;
            }
            if ($dbColumn === 'loginType') {
                $value = strtolower(trim($value));
                $value = in_array($value, ['email', 'phone']) ? $value : 'phone';
            }

            $payload[$dbColumn] = $value;
        }

        // Username from translations (default language)
        $defaultTranslation = $translations[$defaultLang] ?? [];
        if (!empty($defaultTranslation['username'])) {
            $payload['username'] = $defaultTranslation['username'];
        }

        return $payload;
    }

    /* ============================================================
     | OTHER IMAGES
     ============================================================ */

    /**
     * Collect other image paths from any Other Image[N] columns present in the file.
     */
    private function collectOtherImages(array $row, array $headers): array
    {
        $images = [];
        foreach ($headers as $headerName => $colIndex) {
            if (preg_match('/^other image\[(\d+)\]$/i', $headerName, $matches)) {
                $value = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
                if ($value !== '') {
                    $images[(int) $matches[1]] = $value;
                }
            }
        }
        ksort($images);
        return array_values($images);
    }

    /* ============================================================
     | PARTNER TIMINGS
     ============================================================ */

    private function buildTimingsPayload(array $row, array $headers, int $partnerId): array
    {
        $days = [
            'Monday'    => 'monday',
            'Tuesday'   => 'tuesday',
            'Wednesday' => 'wednesday',
            'Thursday'  => 'thursday',
            'Friday'    => 'friday',
            'Saturday'  => 'saturday',
            'Sunday'    => 'sunday',
        ];

        $timings = [];
        foreach ($days as $dayName => $dayValue) {
            // Only include this day if at least one of its columns is present
            $hasStart = $this->hasHeader($headers, $dayName . ' Start Time');
            $hasEnd   = $this->hasHeader($headers, $dayName . ' End Time');
            $hasOpen  = $this->hasHeader($headers, $dayName . ' Is Open');

            if (!$hasStart && !$hasEnd && !$hasOpen) {
                continue;
            }

            $timing = [
                'partner_id' => $partnerId,
                'day'        => $dayValue,
            ];

            if ($hasStart) {
                $timing['opening_time'] = $this->getValue($row, $headers, $dayName . ' Start Time');
            }
            if ($hasEnd) {
                $timing['closing_time'] = $this->getValue($row, $headers, $dayName . ' End Time');
            }
            if ($hasOpen) {
                $timing['is_open'] = $this->getValue($row, $headers, $dayName . ' Is Open');
            }

            $timings[] = $timing;
        }

        return $timings;
    }

    private function savePartnerTimings(array $timings, int $partnerId, string $mode): void
    {
        foreach ($timings as $timing) {
            if ($mode === 'insert') {
                $this->db->table('partner_timings')->insert($timing);
            } else {
                // Only update fields that were present in the spreadsheet
                $updateData = array_diff_key($timing, array_flip(['partner_id', 'day']));
                if (!empty($updateData)) {
                    $this->db->table('partner_timings')->update($updateData, [
                        'partner_id' => $partnerId,
                        'day'        => $timing['day'],
                    ]);
                }
            }
        }
    }

    /* ============================================================
     | CUSTOM FIELDS
     ============================================================ */

    /**
     * Fetch visible custom field definitions for bank_details and documents groups.
     */
    private function loadCustomFieldDefinitions(): array
    {
        return $this->db->table('custom_fields')
            ->select(['id', 'field_label', 'field_type', 'field_group'])
            ->whereIn('field_group', ['bank_details', 'documents'])
            ->where('visible', 1)
            ->orderBy('field_group', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function collectCustomFieldValues(array $row, array $headers, array $customFields): array
    {
        // Build a lookup of custom field IDs that exist in the DB
        $validIds = [];
        foreach ($customFields as $cf) {
            $validIds[(int) $cf['id']] = true;
        }

        // Scan headers for CF_{id} prefixed columns
        $values = [];
        foreach ($headers as $headerName => $colIndex) {
            if (preg_match('/^cf_(\d+)\s*:/i', $headerName, $matches)) {
                $cfId = (int) $matches[1];
                if (isset($validIds[$cfId]) && isset($row[$colIndex])) {
                    $values[$cfId] = trim((string) $row[$colIndex]);
                }
            }
        }
        return $values;
    }

    private function upsertPartnerCustomFields(int $partnerId, array $valuesById): void
    {
        if ($partnerId <= 0 || empty($valuesById)) {
            return;
        }

        $valuesSql = [];
        $deletionIds = [];

        foreach ($valuesById as $customFieldId => $rawValue) {
            $customFieldId = (int) $customFieldId;
            if ($customFieldId <= 0) {
                continue;
            }

            if (is_array($rawValue)) {
                $rawValue = json_encode($rawValue, JSON_UNESCAPED_SLASHES);
            }

            if ($rawValue === '' || $rawValue === null) {
                $deletionIds[] = $customFieldId;
                continue;
            }

            $valuesSql[] = '(' . (int) $partnerId . ',' . (int) $customFieldId . ',' . $this->db->escape((string) $rawValue) . ')';
        }

        if (!empty($deletionIds)) {
            $this->db->table('partner_custom_fields')
                ->where('partner_id', $partnerId)
                ->whereIn('custom_field_id', $deletionIds)
                ->delete();
        }

        if (!empty($valuesSql)) {
            $sql = 'INSERT INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
                . implode(',', $valuesSql)
                . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';

            $this->db->query($sql);
        }
    }

    /* ============================================================
     | SAMPLE FILE GENERATION
     ============================================================ */

    /**
     * Generate headers and a single sample row for bulk insert.
     *
     * @return array{headers: array, rows: array<array>}
     */
    public function generateInsertSample(): array
    {
        $langData      = $this->getLanguageHeaders();
        $languages     = $langData['languages'];
        $customFields  = $this->loadCustomFieldDefinitions();

        // Core headers (language headers will be prepended, SEO appended)
        $headers = [
            'Type', 'Visiting Charge', 'Advance Booking Days', 'Number of Members',
            'At Store', 'At Doorstep', 'Need Approval for Service', 'Address', 'Is Approved',
            'Email', 'Phone', 'Country Code', 'Password', 'Login Type',
            'City', 'Latitude', 'Longitude',
            'Monday Start Time', 'Monday End Time', 'Monday Is Open',
            'Tuesday Start Time', 'Tuesday End Time', 'Tuesday Is Open',
            'Wednesday Start Time', 'Wednesday End Time', 'Wednesday Is Open',
            'Thursday Start Time', 'Thursday End Time', 'Thursday Is Open',
            'Friday Start Time', 'Friday End Time', 'Friday Is Open',
            'Saturday Start Time', 'Saturday End Time', 'Saturday Is Open',
            'Sunday Start Time', 'Sunday End Time', 'Sunday Is Open',
            'Image', 'Banner Image',
            'Other Image[1]', 'Other Image[2]',
        ];

        // Dynamic custom field headers
        foreach ($customFields as $cf) {
            $headers[] = 'CF_' . $cf['id'] . ': ' . ($cf['field_label'] ?? 'Custom Field');
        }

        // Prepend language headers, append SEO Meta Image + per-language SEO headers
        array_splice($headers, 0, 0, $langData['headers']);
        $headers[] = 'SEO Meta Image';
        $headers = array_merge($headers, $langData['seo_headers']);

        // --- Sample row ---
        $row = [];

        // Translation sample values (grouped by field, then by language)
        $fieldSamples = ['SampleUser', 'Sample Company', 'About this provider', 'Detailed description of the provider'];
        foreach ($fieldSamples as $sample) {
            foreach ($languages as $lang) {
                $row[] = $sample . ' (' . $lang['code'] . ')';
            }
        }

        // Core fields
        $row = array_merge($row, [
            '1', '60', '365', '3', '1', '1', '0',
            'test123 , near test', '1',
            'sample_company@gmail.com', '4848945845', '91', '12345678', 'phone',
            'Test', '28.743580', '45.623705',
            '09:00:00', '18:00:00', '1', '09:00:00', '18:00:00', '1',
            '09:00:00', '18:00:00', '1', '09:00:00', '18:00:00', '1',
            '09:00:00', '18:00:00', '1', '09:00:00', '18:00:00', '1',
            '09:00:00', '18:00:00', '1',
            'public/backend/assets/profile/test.png',
            'public/backend/assets/banner/test.png',
            'public/uploads/partner/test1.png',
            'public/uploads/partner/test2.png',
        ]);

        // Custom field sample values
        foreach ($customFields as $cf) {
            $row[] = $cf['field_type'] === 'file'
                ? 'public/uploads/sample_file.png'
                : 'Sample ' . $cf['field_label'];
        }

        // SEO Meta Image (non-translated, single column)
        $row[] = 'public/uploads/seo_settings/sample_image.png';

        // Per-language SEO sample values
        $seoSamples = ['Sample SEO Title', 'Sample SEO Description', 'keyword1, keyword2, keyword3', '{"@type":"LocalBusiness"}'];
        foreach ($seoSamples as $sample) {
            foreach ($languages as $lang) {
                $row[] = $sample . ' (' . $lang['code'] . ')';
            }
        }

        return ['headers' => $headers, 'rows' => [$row]];
    }

    /**
     * Generate headers and data rows for bulk update (pre-filled with existing provider data).
     *
     * @return array{headers: array, rows: array<array>}
     */
    public function generateUpdateSample(): array
    {
        $langData     = $this->getLanguageHeaders();
        $languages    = $langData['languages'];
        $customFields = $this->loadCustomFieldDefinitions();

        // Core headers
        $headers = [
            'ID', 'User ID',
            'Type', 'Visiting Charge', 'Advance Booking Days', 'Number of Members',
            'At Store', 'At Doorstep', 'Need Approval for Service', 'Address', 'Is Approved',
            'Email', 'Phone', 'Country Code', 'Login Type',
            'City', 'Latitude', 'Longitude',
            'Monday Start Time', 'Monday End Time', 'Monday Is Open',
            'Tuesday Start Time', 'Tuesday End Time', 'Tuesday Is Open',
            'Wednesday Start Time', 'Wednesday End Time', 'Wednesday Is Open',
            'Thursday Start Time', 'Thursday End Time', 'Thursday Is Open',
            'Friday Start Time', 'Friday End Time', 'Friday Is Open',
            'Saturday Start Time', 'Saturday End Time', 'Saturday Is Open',
            'Sunday Start Time', 'Sunday End Time', 'Sunday Is Open',
            'Image', 'Banner Image',
        ];

        // Dynamic custom field headers
        foreach ($customFields as $cf) {
            $headers[] = 'CF_' . $cf['id'] . ': ' . ($cf['field_label'] ?? 'Custom Field');
        }

        // Insert language headers after 'User ID' (position 2)
        array_splice($headers, 2, 0, $langData['headers']);

        // Fetch all providers to determine max other images
        $providers = fetch_details('partner_details', [], [], '', 0, 'id', 'ASC');
        if (empty($providers)) {
            return ['headers' => $headers, 'rows' => []];
        }

        $maxOtherImages = 0;
        foreach ($providers as $p) {
            $imgs = json_decode($p['other_images'] ?? '', true);
            if (is_array($imgs)) {
                $maxOtherImages = max($maxOtherImages, count($imgs));
            }
        }
        for ($i = 1; $i <= $maxOtherImages; $i++) {
            $headers[] = "Other Image[$i]";
        }

        // Append SEO Meta Image (non-translated) then per-language SEO headers
        $headers[] = 'SEO Meta Image';
        $headers = array_merge($headers, $langData['seo_headers']);

        // Find default language
        $defaultLangCode = null;
        foreach ($languages as $lang) {
            if (isset($lang['is_default']) && $lang['is_default'] == 1) {
                $defaultLangCode = $lang['code'];
                break;
            }
        }

        $cfIds = array_map('intval', array_column($customFields, 'id'));
        $translationModel    = new \App\Models\TranslatedPartnerDetails_model();
        $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

        $allRows = [];

        foreach ($providers as $provider) {
            $partnerId   = (int) $provider['partner_id'];
            $otherImages = json_decode($provider['other_images'] ?? '', true);
            $user        = fetch_details('users', ['id' => $partnerId], ['username', 'email', 'phone', 'country_code', 'loginType', 'city', 'latitude', 'longitude', 'image']);
            $timings     = fetch_details('partner_timings', ['partner_id' => $partnerId]);

            if (empty($user) || empty($timings)) {
                continue;
            }
            $user = $user[0];

            // Translations
            $dbTranslations  = $translationModel->where('partner_id', $partnerId)->findAll();
            $translationsByLang = [];
            foreach ($dbTranslations as $t) {
                $translationsByLang[$t['language_code']] = $t;
            }

            // SEO data
            $this->seoModel->setTableContext('providers');
            $baseSeo         = $this->seoModel->getSeoSettingsByReferenceId($partnerId);
            $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($partnerId);

            // Base table fallback
            $base = [
                'username'         => $user['username'] ?? '',
                'company_name'     => $provider['company_name'] ?? '',
                'about'            => $provider['about'] ?? '',
                'long_description' => $provider['long_description'] ?? '',
            ];

            $rowData = [
                'ID'      => $provider['id'],
                'User ID' => $partnerId,
            ];

            // Translation fields with fallback
            $translationFields = [
                'Username'     => 'username',
                'Company Name' => 'company_name',
                'About Provider' => 'about',
                'Description'  => 'long_description',
            ];
            foreach ($translationFields as $headerLabel => $dbField) {
                foreach ($languages as $language) {
                    $code      = $language['code'];
                    $isDefault = ($language['is_default'] ?? 0) == 1;
                    $value     = '';

                    if (isset($translationsByLang[$code]) && !empty($translationsByLang[$code][$dbField])) {
                        $value = $translationsByLang[$code][$dbField];
                    } elseif ($isDefault) {
                        $value = $base[$dbField];
                    }

                    if ($dbField === 'long_description' && $value !== '') {
                        $value = strip_tags(htmlspecialchars_decode(stripslashes($value)), '<p><br>');
                    }

                    $rowData[$headerLabel . ' (' . $code . ')'] = $value;
                }
            }

            // Core provider/user fields
            $rowData['Type']                      = $provider['type'];
            $rowData['Visiting Charge']           = $provider['visiting_charges'];
            $rowData['Advance Booking Days']      = $provider['advance_booking_days'];
            $rowData['Number of Members']         = $provider['number_of_members'];
            $rowData['At Store']                  = $provider['at_store'];
            $rowData['At Doorstep']               = $provider['at_doorstep'];
            $rowData['Need Approval for Service'] = $provider['need_approval_for_the_service'];
            $rowData['Address']                   = $provider['address'];
            $rowData['Is Approved']               = $provider['is_approved'];
            $rowData['Email']                     = $user['email'];
            $rowData['Phone']                     = $user['phone'];
            $rowData['Country Code']              = $user['country_code'];
            $rowData['Login Type']                = $user['loginType'] ?? 'phone';
            $rowData['City']                      = $user['city'];
            $rowData['Latitude']                  = $user['latitude'];
            $rowData['Longitude']                 = $user['longitude'];

            // Timings
            if (!empty($timings)) {
                foreach ($timings as $timing) {
                    $day = ucfirst(strtolower($timing['day']));
                    $rowData[$day . ' Start Time'] = $timing['opening_time'];
                    $rowData[$day . ' End Time']   = $timing['closing_time'];
                    $rowData[$day . ' Is Open']    = $timing['is_open'];
                }
            }

            $rowData['Image']        = $user['image'] ?? '';
            $rowData['Banner Image'] = $provider['banner'] ?? '';

            // Custom field values
            $cfValues = $this->getPartnerCustomFieldValuesById($partnerId, $cfIds);
            foreach ($customFields as $cf) {
                $headerLabel = 'CF_' . $cf['id'] . ': ' . ($cf['field_label'] ?? 'Custom Field');
                $rowData[$headerLabel] = $cfValues[(int) $cf['id']] ?? '';
            }

            // Other images
            if (is_array($otherImages)) {
                foreach ($otherImages as $idx => $img) {
                    $rowData['Other Image[' . ($idx + 1) . ']'] = $img ?? '';
                }
            }
            for ($i = count($otherImages ?? []); $i < $maxOtherImages; $i++) {
                $rowData['Other Image[' . ($i + 1) . ']'] = '';
            }

            // SEO Meta Image (non-translated)
            $rowData['SEO Meta Image'] = $baseSeo['image'] ?? '';

            // SEO translation data
            foreach ($languages as $language) {
                $code      = $language['code'];
                $isDefault = ($language['is_default'] ?? 0) == 1;

                $seoTrans = null;
                foreach ($seoTranslations as $st) {
                    if (($st['language_code'] ?? '') === $code) {
                        $seoTrans = $st;
                        break;
                    }
                }

                $rowData['SEO Title (' . $code . ')']         = $seoTrans['seo_title'] ?? ($isDefault ? ($baseSeo['title'] ?? '') : '');
                $rowData['SEO Description (' . $code . ')']   = $seoTrans['seo_description'] ?? ($isDefault ? ($baseSeo['description'] ?? '') : '');
                $rowData['SEO Keywords (' . $code . ')']      = $seoTrans['seo_keywords'] ?? ($isDefault ? ($baseSeo['keywords'] ?? '') : '');
                $rowData['SEO Schema Markup (' . $code . ')'] = $seoTrans['seo_schema_markup'] ?? ($isDefault ? ($baseSeo['schema_markup'] ?? '') : '');
            }

            $allRows[] = $rowData;
        }

        return ['headers' => $headers, 'rows' => $allRows];
    }

    /**
     * Fetch language headers for multi-language support in bulk import/export.
     */
    private function getLanguageHeaders(): array
    {
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], '', '0', 'id', 'ASC');

        $translatableFields    = ['Username', 'Company Name', 'About Provider', 'Description'];
        $seoTranslatableFields = ['SEO Title', 'SEO Description', 'SEO Keywords', 'SEO Schema Markup'];

        $languageHeaders    = [];
        $seoLanguageHeaders = [];

        foreach ($translatableFields as $field) {
            foreach ($languages as $lang) {
                $languageHeaders[] = $field . ' (' . $lang['code'] . ')';
            }
        }
        foreach ($seoTranslatableFields as $field) {
            foreach ($languages as $lang) {
                $seoLanguageHeaders[] = $field . ' (' . $lang['code'] . ')';
            }
        }

        return [
            'headers'                  => $languageHeaders,
            'seo_headers'              => $seoLanguageHeaders,
            'languages'                => $languages,
            'translatable_fields'      => $translatableFields,
            'seo_translatable_fields'  => $seoTranslatableFields,
        ];
    }

    /**
     * Fetch custom field values for a partner by field IDs.
     */
    private function getPartnerCustomFieldValuesById(int $partnerId, array $fieldIds): array
    {
        if ($partnerId <= 0 || empty($fieldIds)) {
            return [];
        }

        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), fn($id) => $id > 0));
        if (empty($fieldIds)) {
            return [];
        }

        $rows = $this->db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', $partnerId)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['custom_field_id']] = $row['value'] ?? null;
        }
        return $out;
    }

    /* ============================================================
     | EXCEL WRITER (DATA + INSTRUCTIONS)
     ============================================================ */

    /**
     * Write sample data and auto-generated instructions to an Excel file.
     *
     * @param array  $headers  Column headers
     * @param array  $rows     Data rows (each row is an indexed array matching headers)
     * @param string $mode     'insert' or 'update'
     * @return string           Temp file path to the generated .xlsx
     */
    public function writeSampleExcel(array $headers, array $rows, string $mode): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // --- Sheet 1: Data ---
        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle('Data');

        // Write headers
        foreach ($headers as $col => $header) {
            $coord = Coordinate::stringFromColumnIndex($col + 1) . '1';
            $dataSheet->setCellValue($coord, $header);
        }
        // Bold header row
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $dataSheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

        // Write data rows
        foreach ($rows as $rowIdx => $row) {
            $values = is_array($row) ? array_values($row) : [$row];
            foreach ($values as $col => $value) {
                $coord = Coordinate::stringFromColumnIndex($col + 1) . ($rowIdx + 2);
                $dataSheet->setCellValue($coord, $value);
            }
        }

        // --- Sheet 2: Instructions ---
        $instrSheet = $spreadsheet->createSheet();
        $instrSheet->setTitle('Instructions');
        $instructions = $this->buildInstructionsData($headers, $mode);

        $instrSheet->setCellValue('A1', 'Column');
        $instrSheet->setCellValue('B1', 'Required');
        $instrSheet->setCellValue('C1', 'Description');
        $instrSheet->setCellValue('D1', 'Example / Notes');
        $instrSheet->getStyle('A1:D1')->getFont()->setBold(true);

        foreach ($instructions as $i => $instr) {
            $r = $i + 2;
            $instrSheet->setCellValue("A{$r}", $instr['column']);
            $instrSheet->setCellValue("B{$r}", $instr['required']);
            $instrSheet->setCellValue("C{$r}", $instr['description']);
            $instrSheet->setCellValue("D{$r}", $instr['example']);
        }

        // Auto-size instruction columns
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $instrSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Protect the instructions sheet (read-only, no password required)
        $instrSheet->getProtection()->setSheet(true);

        // Set Data sheet as active
        $spreadsheet->setActiveSheetIndex(0);

        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk_') . '.xlsx';
        $writer  = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmpPath);
        $spreadsheet->disconnectWorksheets();

        return $tmpPath;
    }

    /**
     * Build instruction rows dynamically from the actual headers.
     */
    private function buildInstructionsData(array $headers, string $mode): array
    {
        $isInsert = $mode === 'insert';

        // Known field descriptions
        $fieldInfo = [
            'ID'                          => ['No',  'Auto-populated for update reference', 'Do not change this value'],
            'User ID'                     => ['Yes', 'The provider\'s user ID (for update matching)', 'Do not change this value'],
            'Type'                        => [$isInsert ? 'Yes' : 'No', 'Provider type: 1 = Company, 0 = Individual', '1'],
            'Visiting Charge'             => ['No',  'Charge for visiting customers', '60'],
            'Advance Booking Days'        => ['No',  'Max days in advance a booking can be made', '365'],
            'Number of Members'           => ['No',  'Team size (only for Company type=1, otherwise set 0)', '3'],
            'At Store'                    => ['No',  '1 = Provides service at store, 0 = No', '1'],
            'At Doorstep'                 => ['No',  '1 = Provides doorstep service, 0 = No', '1'],
            'Need Approval for Service'   => ['No',  '1 = Services need admin approval, 0 = No', '0'],
            'Address'                     => ['No',  'Provider\'s address', 'test123, near test'],
            'Is Approved'                 => ['No',  '0 = Disapproved, 1 = Approved (do not use 7)', '1'],
            'Email'                       => [$isInsert ? 'Yes' : 'No', 'Provider\'s email address (must be unique)', 'provider@example.com'],
            'Phone'                       => [$isInsert ? 'Yes' : 'No', 'Phone number (must be unique)', '4848945845'],
            'Country Code'                => [$isInsert ? 'Yes' : 'No', 'Phone country code (without +)', '91'],
            'Password'                    => [$isInsert ? 'Yes' : 'No', 'Plain text password (hashed on import, must meet password strength rules)', 'Test@123'],
            'Login Type'                  => [$isInsert ? 'Yes' : 'No', 'Login method: "email" or "phone". Email login: email must be unique. Phone login: phone+country code must be unique.', 'phone'],
            'City'                        => ['No',  'Provider\'s city', 'Mumbai'],
            'Latitude'                    => ['No',  'Latitude (-90 to 90, trimmed to 7 decimal places)', '28.7435800'],
            'Longitude'                   => ['No',  'Longitude (-180 to 180, trimmed to 7 decimal places)', '45.6237050'],
            'Image'                       => ['No',  'Profile image path relative to project root', 'public/backend/assets/profile/test.png'],
            'Banner Image'                => ['No',  'Banner image path relative to project root', 'public/backend/assets/banner/test.png'],
            'SEO Meta Image'              => ['No',  'SEO meta image path relative to project root', 'public/uploads/seo_settings/sample_image.png'],
        ];

        // Day timing descriptions
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $day) {
            $fieldInfo[$day . ' Start Time'] = ['No', "Opening time for $day (HH:MM:SS)", '09:00:00'];
            $fieldInfo[$day . ' End Time']   = ['No', "Closing time for $day (HH:MM:SS)", '18:00:00'];
            $fieldInfo[$day . ' Is Open']    = ['No', "1 = Open on $day, 0 = Closed", '1'];
        }

        $instructions = [];

        foreach ($headers as $header) {
            // Known static field
            if (isset($fieldInfo[$header])) {
                $info = $fieldInfo[$header];
                $instructions[] = [
                    'column'      => $header,
                    'required'    => $info[0],
                    'description' => $info[1],
                    'example'     => $info[2],
                ];
                continue;
            }

            // Language translation column: "Field Name (code)"
            if (preg_match('/^(Username|Company Name|About Provider|Description)\s+\((\w+)\)$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => $m[1] === 'Company Name' ? 'Recommended' : 'No',
                    'description' => $m[1] . ' in ' . strtoupper($m[2]) . ' language',
                    'example'     => 'Sample ' . $m[1] . ' (' . $m[2] . ')',
                ];
                continue;
            }

            // SEO column: "SEO Field (code)"
            if (preg_match('/^(SEO .+)\s+\((\w+)\)$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => 'No',
                    'description' => $m[1] . ' in ' . strtoupper($m[2]) . ' language',
                    'example'     => 'Sample value for ' . $m[1],
                ];
                continue;
            }

            // Custom field: "CF_123: Label"
            if (preg_match('/^CF_(\d+):\s*(.+)$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => 'No',
                    'description' => 'Custom field "' . trim($m[2]) . '" (ID: ' . $m[1] . '). The CF_ prefix with the ID is used for mapping — do not change it.',
                    'example'     => 'Value or file path',
                ];
                continue;
            }

            // Other Image[N]
            if (preg_match('/^Other Image\[(\d+)\]$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => 'No',
                    'description' => 'Additional gallery image #' . $m[1] . '. You may add more Other Image[N] columns as needed.',
                    'example'     => 'public/uploads/partner/photo.png',
                ];
                continue;
            }

            // Fallback
            $instructions[] = [
                'column'      => $header,
                'required'    => 'No',
                'description' => '',
                'example'     => '',
            ];
        }

        return $instructions;
    }

    /* ============================================================
     | CSV HELPERS
     ============================================================ */

    private function parseCsv($sheet)
    {
        $headers = [];
        foreach ($sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1')[0] as $i => $h) {
            if ($h === null || trim((string) $h) === '') {
                continue;
            }
            $headers[strtolower(trim((string) $h))] = $i;
        }

        $rows = array_filter(array_slice($sheet->toArray(), 1), fn($r) => !empty(array_filter($r)));

        return [$headers, $rows];
    }

    private function hasHeader(array $headers, string $key): bool
    {
        return isset($headers[strtolower(trim($key))]);
    }

    private function hasTimingHeaders(array $headers): bool
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $day) {
            if ($this->hasHeader($headers, $day . ' Start Time')
                || $this->hasHeader($headers, $day . ' End Time')
                || $this->hasHeader($headers, $day . ' Is Open')
            ) {
                return true;
            }
        }
        return false;
    }

    private function validateRequiredHeaders(array $headers, string $mode): array
    {
        $required = ($mode === 'insert')
            ? self::REQUIRED_HEADERS_INSERT
            : self::REQUIRED_HEADERS_UPDATE;

        $missing = [];
        foreach ($required as $header) {
            if (!$this->hasHeader($headers, $header)) {
                $missing[] = $header;
            }
        }
        return $missing;
    }

    private function getValue(array $row, array $headers, string $key)
    {
        $key = strtolower(trim($key));
        return isset($headers[$key]) && isset($row[$headers[$key]])
            ? trim((string)($row[$headers[$key]] ?? ''))
            : '';
    }

    private function getExistingSlug($partnerId)
    {
        $row = fetch_details('partner_details', ['partner_id' => $partnerId], ['slug']);
        return $row[0]['slug'] ?? null;
    }

    // /* ============================================================
    //  | DEBUG
    //  ============================================================ */

    // private function debug(string $message, array $context = [])
    // {
    //     if ($this->debug) {
    //         log_message('debug', '[PROVIDER IMPORT] ' . $message . ' ' . json_encode($context));
    //     }
    // }

    /**
     * Extract translation data from Excel row
     * 
     * Processes translation columns from Excel row and organizes them by language and field
     * 
     * @param array $row Excel row data
     * @param array $headers Excel column headers
     * @param array $languages Available languages
     * @return array Translation data organized by language code
     */
    private function extractTranslationsFromRow(array $row, array $headers, array $languages): array
    {
        $translations = [];

        // Initialize translation structure for each language
        foreach ($languages as $language) {
            $translations[$language['code']] = [
                'username' => null,
                'company_name' => null,
                'about' => null,
                'long_description' => null
            ];
        }

        // Map of field names (lowercase) to database columns
        $fieldMapping = [
            'username'       => 'username',
            'company name'   => 'company_name',
            'about provider' => 'about',
            'description'    => 'long_description'
        ];

        // Build a set of valid language codes for quick lookup
        $validLangCodes = array_column($languages, 'code');

        // Headers map is [lowercase_header_name => column_index]
        foreach ($headers as $headerName => $colIndex) {
            // Match translation columns: "company name (en)", "about provider (ar)", etc.
            if (preg_match('/^(.+?)\s*\(([a-z]{2,3})\)$/i', $headerName, $matches)) {
                $fieldName = strtolower(trim($matches[1]));
                $languageCode = strtolower(trim($matches[2]));

                if (isset($fieldMapping[$fieldName]) && in_array($languageCode, $validLangCodes) && isset($row[$colIndex])) {
                    $translations[$languageCode][$fieldMapping[$fieldName]] = $row[$colIndex];
                }
            }
        }

        return $translations;
    }

    /**
     * Extract SEO translations from Excel row
     * 
     * Processes SEO translation columns from Excel row and organizes them by language and field
     * 
     * @param array $row Excel row data
     * @param array $headers Excel column headers
     * @param array $languages Available languages
     * @return array SEO translation data organized by language code
     */
    private function extractSeoTranslationsFromRow(array $row, array $headers, array $languages): array
    {
        $seoTranslations = [];

        // Initialize SEO translation structure for each language
        foreach ($languages as $language) {
            $seoTranslations[$language['code']] = [
                'seo_title' => null,
                'seo_description' => null,
                'seo_keywords' => null,
                'seo_schema_markup' => null
            ];
        }

        // Map of SEO field names (lowercase) to database columns
        $seoFieldMapping = [
            'seo title'         => 'seo_title',
            'seo description'   => 'seo_description',
            'seo keywords'      => 'seo_keywords',
            'seo schema markup' => 'seo_schema_markup'
        ];

        // Build a set of valid language codes for quick lookup
        $validLangCodes = array_column($languages, 'code');

        // Headers map is [lowercase_header_name => column_index]
        foreach ($headers as $headerName => $colIndex) {
            if (empty($headerName)) {
                continue;
            }
            // Match SEO translation columns: "seo title (en)", "seo description (ar)", etc.
            if (preg_match('/^(.+?)\s*\(([a-z]{2,3})\)$/i', $headerName, $matches)) {
                $fieldName = strtolower(trim($matches[1]));
                $languageCode = strtolower(trim($matches[2]));

                if (isset($seoFieldMapping[$fieldName]) && in_array($languageCode, $validLangCodes) && isset($row[$colIndex])) {
                    $dbField = $seoFieldMapping[$fieldName];
                    $value = $row[$colIndex];

                    if ($dbField === 'seo_keywords' && !empty($value)) {
                        $value = $this->parseKeywords($value);
                    }

                    $seoTranslations[$languageCode][$dbField] = !empty($value) ? trim((string)$value) : null;
                }
            }
        }

        return $seoTranslations;
    }

    /**
     * Save partner translations for bulk operations
     * 
     * Saves translation data to the translated_partner_details table
     * 
     * @param int $partnerId Partner ID
     * @param array $translations Translation data organized by language code
     * @return bool Success status
     */
    private function saveBulkPartnerTranslations(int $partnerId, array $translations): bool
    {
        try {
            // Use PartnerService to save translations
            $partnerService = new \App\Services\PartnerService();

            // Process each language
            foreach ($translations as $languageCode => $translationData) {
                // Only save if at least one field has data
                $hasData = false;
                foreach ($translationData as $value) {
                    if (!empty($value)) {
                        $hasData = true;
                        break;
                    }
                }

                if ($hasData) {
                    // Save translations for this language
                    $result = $partnerService->saveTranslations($partnerId, $languageCode, $translationData);

                    if (!$result) {
                        log_message('error', "Failed to save translations for partner {$partnerId} in language {$languageCode}");
                        return false;
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Exception in saveBulkPartnerTranslations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save SEO settings for bulk provider operations
     * 
     * Saves base SEO settings (default language) to partners_seo_settings table
     * and all language SEO translations to translated_partner_seo_settings table
     * 
     * @param int $partnerId Partner ID
     * @param array $seoTranslations SEO translation data organized by language code
     * @param array $languages Available languages
     * @return bool Success status
     */
    private function saveBulkPartnerSeoSettings(int $partnerId, array $seoTranslations, array $languages, ?string $seoMetaImage = null): bool
    {
        try {
            // Get default language
            $defaultLanguage = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            if (empty($defaultLanguage)) {
                log_message('error', "No default language found for saving SEO settings for partner {$partnerId}");
                return false;
            }

            // Extract default language SEO data for base SEO settings
            $defaultSeoTitle = '';
            $defaultSeoDescription = '';
            $defaultSeoKeywords = '';
            $defaultSeoSchema = '';

            if (!empty($seoTranslations[$defaultLanguage]['seo_title'])) {
                $defaultSeoTitle = trim($seoTranslations[$defaultLanguage]['seo_title']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_description'])) {
                $defaultSeoDescription = trim($seoTranslations[$defaultLanguage]['seo_description']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_keywords'])) {
                $defaultSeoKeywords = trim($seoTranslations[$defaultLanguage]['seo_keywords']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_schema_markup'])) {
                $defaultSeoSchema = trim($seoTranslations[$defaultLanguage]['seo_schema_markup']);
            }

            // Resolve SEO image: use provided value, fall back to existing
            $this->seoModel->setTableContext('providers');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);
            $resolvedImage = ($seoMetaImage !== null && $seoMetaImage !== '')
                ? $seoMetaImage
                : ($existingSettings['image'] ?? '');

            // Check if any SEO field is filled
            $hasSeoData = !empty($defaultSeoTitle) ||
                !empty($defaultSeoDescription) ||
                !empty($defaultSeoKeywords) ||
                !empty($defaultSeoSchema) ||
                !empty($resolvedImage);

            // Only save base SEO settings if there's data
            if ($hasSeoData) {
                // Build SEO data array
                $seoData = [
                    'title'         => $defaultSeoTitle,
                    'description'   => $defaultSeoDescription,
                    'keywords'      => $defaultSeoKeywords,
                    'schema_markup' => $defaultSeoSchema,
                    'partner_id'    => $partnerId,
                    'image'         => $resolvedImage,
                ];

                if ($existingSettings) {
                    // Update existing settings
                    $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to update SEO settings for partner {$partnerId}: " . $result['message']);
                        return false;
                    }
                } else {
                    // Create new settings
                    $result = $this->seoModel->createSeoSettings($seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to create SEO settings for partner {$partnerId}: " . $result['message']);
                        return false;
                    }
                }
            }

            // Process SEO translations for all languages
            // Restructure data from lang[field] to field[lang] format for processSeoTranslations
            $restructuredSeoData = [];
            $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

            foreach ($seoFields as $field) {
                $restructuredSeoData[$field] = [];
                foreach ($seoTranslations as $languageCode => $fields) {
                    $restructuredSeoData[$field][$languageCode] = $fields[$field] ?? '';
                }
            }

            // Save SEO translations using the existing processSeoTranslations method
            $this->processSeoTranslations($partnerId, $restructuredSeoData);

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Exception in saveBulkPartnerSeoSettings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process SEO translations for partner if provided in the request
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function processSeoTranslations(int $partnerId, ?array $translatedFields = null): void
    {
        try {

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {

                // Load the SEO translation model
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($partnerId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new \Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new \Exception('Exception in processSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Restructure translated fields for SEO model
     * Convert from field[lang] format to lang[field] format
     * 
     * @param array $translatedFields Translated fields in field[lang] format
     * @return array Restructured data in lang[field] format
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];

        // SEO fields we want to process
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Get all available languages from the translated fields
        $languages = [];
        foreach ($seoFields as $field) {
            if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                $languages = array_merge($languages, array_keys($translatedFields[$field]));
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                $value = $translatedFields[$field][$languageCode] ?? '';

                if ($field === 'seo_keywords') {
                    $restructured[$languageCode][$field] = !empty($value)
                        ? $this->parseKeywords($value)
                        : '';
                } else {
                    $restructured[$languageCode][$field] = $value !== null
                        ? $value
                        : '';
                }
            }
            // Keep the language entry even if every field is empty.
            // This lets the translation model overwrite stale values with blanks.
        }


        return $restructured;
    }

    /**
     * Parses meta keywords input from Tagify.
     */
    private function parseKeywords($input): string
    {
        // If input is empty, return empty string
        if (empty($input)) {
            return '';
        }

        // If input is a string, it might be JSON or comma-separated
        if (is_string($input)) {
            // Check if it's a JSON string
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Treat as comma-separated string
            return trim($input);
        }

        // If input is an array
        if (is_array($input)) {
            // Handle case where array contains a single JSON string (e.g., ['[{value: "tag1"}, {value: "tag2"}]'])
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
            $tags = array_map(function ($item) {
                return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
            }, $input);
            return implode(',', $tags);
        }

        // Fallback: return empty string for unexpected input
        return '';
    }
}
