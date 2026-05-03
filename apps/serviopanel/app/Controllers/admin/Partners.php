<?php

namespace App\Controllers\admin;

use App\Models\Cash_collection_model;
use App\Models\Orders_model;
use App\Models\Partners_model;
use App\Models\Payment_request_model;
use App\Models\Promo_code_model;
use App\Models\Service_model;
use App\Models\Users_model;
use App\Models\Service_ratings_model;
use App\Models\Settlement_CashCollection_history_model;
use App\Models\Settlement_model;
use App\Models\Seo_model;
use App\Services\PartnerService;
use Config\ApiResponseAndNotificationStrings;
use IonAuth\Models\IonAuthModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\utility\SlugService;
use Exception;

class Partners extends Admin
{
    protected Users_model $users;
    protected Cash_collection_model $cash_collection;
    protected Settlement_model $settle_commission;
    protected $superadmin;
    protected ApiResponseAndNotificationStrings $trans;
    protected Seo_model $seoModel;
    protected PartnerService $partnerService;
    protected $defaultLanguage;
    protected $slugService;

    public $partner,  $validation, $db, $ionAuth, $creator_id;
    public function __construct()
    {
        parent::__construct();
        $this->partner = new Partners_model();
        $this->users = new Users_model();
        $this->cash_collection = new Cash_collection_model();
        $this->settle_commission = new Settlement_model();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        $this->trans = new ApiResponseAndNotificationStrings();
        $this->seoModel = new Seo_model();
        $this->partnerService = new PartnerService();
        $this->defaultLanguage = get_default_language();
        $this->slugService = new SlugService();
        helper('ResponceServices');
    }
    public function index()
    {
        helper('function');
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partners');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/admin/template', $this->data);
    }
    public function add_partner()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $permission = is_permitted($this->creator_id, 'create', 'partner');
            if (!$permission) {
                return NoPermission();
            }
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('add_providers', 'Add Provider') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'add_partner');
                $partner_details = !empty(fetch_details('partner_details', ['partner_id' => $this->userId])) ? fetch_details('partner_details', ['partner_id' => $this->userId])[0] : [];
                $partner_timings = !empty(fetch_details('partner_timings', ['partner_id' => $this->userId])) ? fetch_details('partner_timings', ['partner_id' => $this->userId]) : [];
                $this->data['data'] = fetch_details('users', ['id' => $this->userId])[0];
                $settings = get_settings('general_settings', true);
                if (empty($settings)) {
                    $_SESSION['toastMessage'] = labels(FIRST_ADD_CURRENCY_AND_BASIC_DETAILS_IN_GENERAL_SETTINGS, 'Please first add currency and basic details in general settings');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/general-settings')->withCookies();
                }
                $this->data['currency'] = $settings['currency'];
                $this->data['allow_pre_booking_chat'] = $settings['allow_pre_booking_chat'] ?? 0;
                $this->data['allow_post_booking_chat'] = $settings['allow_post_booking_chat'] ?? 0;
                $this->data['partner_details'] = $partner_details;
                $this->data['partner_timings'] = $partner_timings;
                $this->data['city_name'] = fetch_details('cities', [], ['id', 'name']);

                // Fetch subscriptions with translations for current language
                $subscriptionModel = new \App\Models\Subscription_model();
                $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1]);
                $this->data['subscription_details'] = $subscription_details;

                // Prepare country code data for the view (for new partners, use default)
                $country_code_data = prepare_country_code_data('');
                $this->data['country_codes'] = $country_code_data['country_codes'];
                $this->data['selected_country_code'] = $country_code_data['selected_country_code'];

                // fetch languages
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                $cfDefs = $this->loadCustomFieldDefinitions($languages);
                $this->data['documents_custom_fields'] = $cfDefs['documents'];
                $this->data['bank_details_custom_fields'] = $cfDefs['bank_details'];
                $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - add_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 20;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Get current language for translations
            $current_language = get_current_language();

            print_r(json_encode($this->partner->list(false, $search, $limit, $offset, $sort, $order, [], 'pd.id', [], [], null, $current_language)));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function view_partner()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $data = fetch_details('partner_details', ['partner_id' => $partner_id]);
            if (empty($data)) {
                return redirect('admin/partners');
            }
            $settings = get_settings('general_settings', true);
            $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
            $partner_details = $data[0];
            $user_details = fetch_details('users', ['id' => $partner_id])[0];
            setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'view_partner');
            $this->data['partner_details'] = $partner_details;
            $this->data['personal_details'] = $user_details;
            return view('backend/admin/template', $this->data);
        } catch (Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - view_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function edit_partner()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $data = fetch_details('partner_details', ['partner_id' => $partner_id]);
            if (empty($data)) {
                return redirect('admin/partners');
            }
            $partner_details = $data[0];
            $user_details = fetch_details('users', ['id' => $partner_id])[0];
            $settings = get_settings('general_settings', true);
            $partner_timings = fetch_details('partner_timings', ['partner_id' => $partner_id], '', '', '', '', 'ASC');
            $this->data['currency'] = $settings['currency'];
            $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
            $this->data['national_id_verification_status'] = $settings['national_id_verification_status'] ?? 0;
            $this->data['address_id_verification_status'] = $settings['address_id_verification_status'] ?? 0;
            $this->data['passport_required_status'] = $settings['passport_required_status'] ?? 0;
            $this->data['national_id_required_status'] = $settings['national_id_required_status'] ?? 0;
            $this->data['address_id_required_status'] = $settings['address_id_required_status'] ?? 0;
            setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'edit_partner');

            // fetch languages early so loadCustomFieldDefinitions can use them
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            $cfDefs = $this->loadCustomFieldDefinitions($languages);

            $allCfIds = array_map(static fn ($f) => $f['id'], array_merge($cfDefs['documents'], $cfDefs['bank_details']));
            $customFieldValues = $this->getPartnerCustomFieldValuesById($partner_id, $allCfIds);

            $disk = fetch_current_file_manager();

            $partner_details['banner'] = get_file_url($disk, $partner_details['banner'], 'public/backend/assets/default.png', 'banner');

            foreach (array_merge($cfDefs['documents'], $cfDefs['bank_details']) as $cfField) {
                if ($cfField['field_type'] === 'file') {
                    $cfId = $cfField['id'];
                    $rawVal = $customFieldValues[$cfId] ?? '';
                    if ($rawVal !== '') {
                        $customFieldValues[$cfId] = get_file_url($disk, $rawVal, 'public/backend/assets/default.png', 'custom_fields');
                    }
                }
            }

            if (!empty($partner_details['other_images'])) {
                $decodedImages = json_decode($partner_details['other_images'], true);
                $updatedImages = [];
                foreach ($decodedImages as $data) {
                    $updatedImages[] = get_file_url($disk, $data, 'public/backend/assets/default.png', 'partner');
                }
                $partner_details['other_images'] = $updatedImages;
            } else {
                $partner_details['other_images'] = [];
            }

            $user_details['image'] = get_file_url($disk,  $user_details['image'],  'public/backend/assets/default.png',  'profile');
            $this->data['partner_details'] = $partner_details;
            $this->data['custom_field_values'] = $customFieldValues;
            $this->data['personal_details'] = $user_details;
            $this->data['partner_timings'] = $partner_timings;
            $this->data['allow_pre_booking_chat'] = ($settings['allow_pre_booking_chat']) ?? 0;
            $this->data['allow_post_booking_chat'] = ($settings['allow_post_booking_chat']) ?? 0;

            // First get the active partner subscription record
            $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $partner_id, 'status' => 'active']);

            // Then fetch the subscription details with translations from the main subscriptions table
            $active_subscription_details = [];
            if (!empty($active_partner_subscription)) {
                $subscriptionModel = new \App\Models\Subscription_model();
                $subscription_with_translations = $subscriptionModel->getWithTranslation(
                    $active_partner_subscription[0]['subscription_id'],
                    get_current_language()
                );

                if ($subscription_with_translations) {
                    // Keep partner subscription as base
                    $active_subscription_details[0] = $active_partner_subscription[0];

                    // Add translations under a separate namespace
                    $active_subscription_details[0]['translations'] = $subscription_with_translations;
                } else {
                    $active_subscription_details[0] = $active_partner_subscription[0];
                }
            }

            $symbol =   get_currency();
            $this->data['currency'] = $symbol;
            $this->data['active_subscription_details'] = $active_subscription_details;
            $this->data['partner_id'] = $partner_id;

            // Fetch available subscriptions with translations for current language
            $subscriptionModel = new \App\Models\Subscription_model();
            $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1]);
            $this->data['subscription_details'] = $subscription_details;

            $this->seoModel->setTableContext('providers');
            $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($partner_id, 'full');
            $this->data['partner_seo_settings'] = $seo_settings;

            // Prepare country code data for the view
            $user_country_code = $user_details['country_code'] ?? '';
            $country_code_data = prepare_country_code_data($user_country_code);
            $this->data['country_codes'] = $country_code_data['country_codes'];
            $this->data['selected_country_code'] = $country_code_data['selected_country_code'];

            // ($languages is already fetched and set above via loadCustomFieldDefinitions)

            // Load translated partner details using PartnerService
            $partnerService = new PartnerService();
            $translatedData = $partnerService->getPartnerWithTranslations($partner_id);

            if ($translatedData['success']) {
                // Merge translated data with partner details for each language
                $mergedPartnerDetails = $partner_details;

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    if (isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Create language-specific partner details
                        $mergedPartnerDetails['translated_' . $languageCode] = [
                            'username' => $isDefault ? $user_details['username'] : ($translation['username'] ?? ''),
                            'company_name' => $translation['company_name'] ?? $partner_details['company_name'],
                            'about' => $translation['about'] ?? $partner_details['about'],
                            'long_description' => $translation['long_description'] ?? $partner_details['long_description']
                        ];
                    } else {
                        // If no translation exists, create with default values
                        $mergedPartnerDetails['translated_' . $languageCode] = [
                            'username' => $isDefault ? $user_details['username'] : '',
                            'company_name' => $isDefault ? $partner_details['company_name'] : '',
                            'about' => $isDefault ? $partner_details['about'] : '',
                            'long_description' => $isDefault ? $partner_details['long_description'] : ''
                        ];
                    }
                }

                $this->data['partner_details'] = $mergedPartnerDetails;
            }

            // Load SEO translations and merge with main SEO settings
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($partner_id);

            // Always merge SEO translations with main SEO settings (even if no translations exist)
            $mergedSeoSettings = $seo_settings;

            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefault = $language['is_default'] == 1;

                // Find SEO translation for this language
                $seoTranslation = null;
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        if ($translation['language_code'] === $languageCode) {
                            $seoTranslation = $translation;
                            break;
                        }
                    }
                }

                if ($seoTranslation) {
                    // Create language-specific SEO settings from translation
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $seoTranslation['seo_title'] ?? '',
                        'description' => $seoTranslation['seo_description'] ?? '',
                        'keywords' => $seoTranslation['seo_keywords'] ?? '',
                        'schema_markup' => $seoTranslation['seo_schema_markup'] ?? ''
                    ];
                } else {
                    // If no SEO translation exists, use base table data for default language, empty for others
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $isDefault ? ($seo_settings['title'] ?? '') : '',
                        'description' => $isDefault ? ($seo_settings['description'] ?? '') : '',
                        'keywords' => $isDefault ? ($seo_settings['keywords'] ?? '') : '',
                        'schema_markup' => $isDefault ? ($seo_settings['schema_markup'] ?? '') : ''
                    ];
                }
            }

            $this->data['partner_seo_settings'] = $mergedSeoSettings;

            // Pass custom-field definitions and labels to the view.
            $this->data['documents_custom_fields']      = $cfDefs['documents'];
            $this->data['bank_details_custom_fields']   = $cfDefs['bank_details'];
            $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - edit_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function insert_partner()
    {
        try {
            helper('function');
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            if (!is_permitted($this->creator_id, 'create', 'partner')) {
                return NoPermission();
            }

            //Validate inputs
            $validationRules = $this->getValidationRules();

            // Dynamically add validation rules for all visible + required file-type custom fields.
            // This replaces the old hardcoded passport/national_id/address_id verification checks.
            $visibleFileCustomFields = $this->getVisibleFileCustomFields();
            foreach ($visibleFileCustomFields as $cfRow) {
                if (!$cfRow['required']) {
                    continue;
                }
                $cfId = (int) $cfRow['id'];
                $inputName = 'cf_' . $cfId;
                $fileConfig = $cfRow['file_config'];
                $maxSizeKb = (int)(($fileConfig['max_size_mb'] ?? 2) * 1024);
                $mimeTypes = $this->extensionsToMimeTypes($fileConfig['allowed_types'] ?? []);
                $label = 'Custom Field ' . $cfId;

                $rules = "uploaded[{$inputName}]|max_size[{$inputName},{$maxSizeKb}]";
                $errors = [
                    'uploaded' => labels('please_upload_a_valid_custom_field_document', "Please upload a valid document"),
                    'max_size' => labels('custom_field_file_size_exceeds_limit', "File size should not exceed " . ($fileConfig['max_size_mb'] ?? 2) . "MB"),
                ];
                if (!empty($mimeTypes)) {
                    $rules .= "|mime_in[{$inputName}," . implode(',', $mimeTypes) . "]";
                    $errors['mime_in'] = labels('custom_field_must_be_a_valid_file', "File must be a valid file type");
                }
                $validationRules[$inputName] = [
                    'rules' => $rules,
                    'errors' => $errors,
                ];
            }

            $this->validation->setRules($validationRules);
            if (!$this->validation->withRequest($this->request)->run()) {
                return ErrorResponse($this->validation->getErrors(), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Password strength: must match frontend indicator (number, uppercase, special, min length)
            $passwordStrengthErrors = validate_password_strength($this->request->getPost('password'));
            if (!empty($passwordStrengthErrors)) {
                return ErrorResponse($passwordStrengthErrors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Extract loginType early to determine which field must be unique
            $loginType = strtolower(trim((string) $this->request->getPost('login_type')));
            if (!in_array($loginType, ['phone', 'email'], true)) {
                $loginType = 'phone';
            }

            $db = \Config\Database::connect();
            if ($loginType == 'phone') {
                // Check for duplicate phone number
                $builder = $db->table('users u')
                    ->select('u.*, ug.group_id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', 3)
                    ->where('phone', $this->request->getPost('phone'));
                if ($builder->countAllResults() > 0) {
                    return ErrorResponse(labels(PHONE_NUMBER_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, 'Phone number already exists please use another one'), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                // Check for duplicate email address
                $builder = $db->table('users u')
                    ->select('u.*, ug.group_id')
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->where('ug.group_id', 3)
                    ->where('email', strtolower($this->request->getPost('email')));
                if ($builder->countAllResults() > 0) {
                    return ErrorResponse(labels(EMAIL_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, 'Email already exists please use another one'), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Get the default language from database for validation
            $defaultLanguage = 'en'; // fallback
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            // Validate default language translated fields manually
            $postData = $this->request->getPost();
            $defaultLanguageErrors = [];

            // Check if default language fields are present and not empty
            // The form now sends data as objects: company_name[en], about_provider[en], etc.

            // Check if data is in new format (as objects)
            if (isset($postData['company_name']) && is_array($postData['company_name'])) {
                // Check username
                $usernameValue = $postData['username'][$defaultLanguage] ?? null;
                if (empty($usernameValue)) {
                    $defaultLanguageErrors[] = labels(USERNAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Username is required for default language");
                }

                // Check company name
                $companyNameValue = $postData['company_name'][$defaultLanguage] ?? null;
                if (empty($companyNameValue)) {
                    $defaultLanguageErrors[] = labels(COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Company name is required for default language");
                }

                // Check about field
                $aboutValue = $postData['about_provider'][$defaultLanguage] ?? null;
                if (empty($aboutValue)) {
                    $defaultLanguageErrors[] = labels(ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "About provider is required for default language");
                }

                // Check description field
                $descriptionValue = $postData['long_description'][$defaultLanguage] ?? null;
                if (empty($descriptionValue)) {
                    $defaultLanguageErrors[] = labels(DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Description is required for default language");
                }
            } else {
                // Fallback: Check old format (field[language])
                $usernameField = 'username[' . $defaultLanguage . ']';
                $companyNameField = 'company_name[' . $defaultLanguage . ']';
                $aboutField = 'about_provider[' . $defaultLanguage . ']';
                $descriptionField = 'long_description[' . $defaultLanguage . ']';

                // Check username
                $usernameValue = $postData[$usernameField] ?? null;
                if (empty($usernameValue)) {
                    $defaultLanguageErrors[] = labels(USERNAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Username is required for default language");
                }

                // Check company name
                $companyNameValue = $postData[$companyNameField] ?? null;
                if (empty($companyNameValue)) {
                    $defaultLanguageErrors[] = labels(COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Company name is required for default language");
                }

                // Check about field
                $aboutValue = $postData[$aboutField] ?? null;
                if (empty($aboutValue)) {
                    $defaultLanguageErrors[] = labels(ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "About provider is required for default language");
                }

                // Check description field
                $descriptionValue = $postData[$descriptionField] ?? null;
                if (empty($descriptionValue)) {
                    $defaultLanguageErrors[] = labels(DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Description is required for default language");
                }
            }

            if (!empty($defaultLanguageErrors)) {
                return ErrorResponse($defaultLanguageErrors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Preserve up to 7 decimal places to match register method validation
            $latitude = number_format($this->request->getPost('partner_latitude'), 7, '.', '');
            $longitude = number_format($this->request->getPost('partner_longitude'), 7, '.', '');

            // Validate coordinates
            $this->validateCoordinates(
                $latitude,
                $longitude
            );

            // Handle file uploads
            $uploadPaths = [
                'profile' => [
                    'file' => $this->request->getFile('image'),
                    'path' => 'public/backend/assets/profile/',
                    'error' => labels(FAILED_TO_CREATE_PROFILE_FOLDERS, 'Failed to create profile folders'),
                    'folder' => 'profile',
                ],
                'banner' => [
                    'file' => $this->request->getFile('banner_image'),
                    'path' => 'public/backend/assets/banner/',
                    'error' => labels(FAILED_TO_CREATE_BANNER_FOLDERS, 'Failed to create banner folders'),
                    'folder' => 'banner',
                ],
            ];

            // Dynamically add upload paths for all visible file-type custom fields.
            foreach ($visibleFileCustomFields as $cfRow) {
                $cfId = (int) $cfRow['id'];
                $inputName = 'cf_' . $cfId;
                $uploadPaths[$inputName] = [
                    'file' => $this->request->getFile($inputName),
                    'path' => 'public/uploads/custom_fields/',
                    'error' => labels('failed_to_create_custom_fields_folders', "Failed to create custom fields folders"),
                    'folder' => 'custom_fields',
                ];
            }

            $uploadedFiles = [];
            foreach ($uploadPaths as $key => $config) {
                $uploadedFiles[$key] = $this->uploadFile(
                    $config['file'],
                    $config['path'],
                    $config['error'],
                    $config['folder']
                );
            }

            // Prepare user data - handle existing image for duplicate provider scenario
            $existingImage = $this->request->getPost('existing_image');
            if (!empty($uploadedFiles['profile']['url'])) {
                // New image uploaded
                $image = $uploadedFiles['profile']['url'];
                if ($uploadedFiles['profile']['disk'] === 'local_server') {
                    $image = 'public/backend/assets/profile/' . $image;
                }
            } elseif (!empty($existingImage)) {
                // Use existing image from duplicate provider
                $image = $existingImage;
            } else {
                // No image provided and no existing image
                $image = '';
            }

            // Get default language values for main table storage
            $defaultUsername = $this->request->getPost('username[' . $defaultLanguage . ']') ?? $this->request->getPost('username') ?? '';
            $defaultCompanyName = $this->request->getPost('company_name[' . $defaultLanguage . ']') ?? $this->request->getPost('company_name') ?? '';
            $defaultAbout = $this->request->getPost('about_provider[' . $defaultLanguage . ']') ?? $this->request->getPost('about_provider') ?? '';
            $defaultLongDescription = $this->request->getPost('long_description[' . $defaultLanguage . ']') ?? $this->request->getPost('long_description') ?? '';


            // loginType already extracted and validated above for duplicate checking

            $userData = [
                'username' => $defaultUsername,
                'password' => $this->request->getPost('password'),
                'email' => strtolower($this->request->getPost('email')),
                'latitude' => $this->request->getPost('partner_latitude'),
                'longitude' => $this->request->getPost('partner_longitude'),
                'phone' => $this->request->getPost('phone'),
                'country_code' => $this->request->getPost('country_code'),
                'city' => $this->request->getPost('city'),
                'image' => $image,
                'is_approved' => $this->request->getPost('is_approved') ? 1 : 0,
                'active' => 1,
                'loginType' => $loginType,
            ];

            // Save user
            $partnerId = $this->saveUser($userData);

            // Handle other images
            $existingImages = $this->request->getPost('existing_other_images') ?? [];
            $removeFlags = $this->request->getPost('remove_other_images') ?? [];
            $uploadedOtherImages = array_filter($existingImages, function ($index) use ($removeFlags) {
                return !isset($removeFlags[$index]) || $removeFlags[$index] !== '1';
            }, ARRAY_FILTER_USE_KEY);

            $otherImagesConfig = [
                'path' => 'public/uploads/partner/',
                'error' => labels(FAILED_TO_UPLOAD_OTHER_IMAGES, 'Failed to upload other images'),
                'folder' => 'partner',
            ];
            $multipleFiles = $this->request->getFiles('filepond');

            if (isset($multipleFiles['other_service_image_selector'])) {
                foreach ($multipleFiles['other_service_image_selector'] as $file) {
                    if ($file->isValid()) {
                        $result = $this->uploadFile($file, $otherImagesConfig['path'], $otherImagesConfig['error'], $otherImagesConfig['folder']);

                        $uploadedOtherImages[] = $result['disk'] === 'local_server'
                            ? 'public/uploads/partner/' . $result['url']
                            : $result['url'];
                    }
                }
            }

            // Prepare partner data with SEO settings - handle existing banner image for duplicate provider scenario
            $existingBannerImage = $this->request->getPost('existing_banner_image');
            if (!empty($uploadedFiles['banner']['url'])) {
                // New banner image uploaded
                $banner = $uploadedFiles['banner']['url'];
                if ($uploadedFiles['banner']['disk'] === 'local_server') {
                    $banner = 'public/backend/assets/banner/' . $banner;
                }
            } elseif (!empty($existingBannerImage)) {
                // Use existing banner image from duplicate provider
                $banner = $existingBannerImage;
            } else {
                // No banner image provided and no existing banner image
                $banner = '';
            }
            // Resolve uploaded file paths for all visible file-type custom fields.
            $uploadedFileCustomFieldValues = [];
            foreach ($visibleFileCustomFields as $cfRow) {
                $cfId = (int) $cfRow['id'];
                $inputName = 'cf_' . $cfId;
                if (!isset($uploadedFiles[$inputName])) {
                    continue;
                }
                $url = $uploadedFiles[$inputName]['url'] ?? '';
                if ($url !== '' && ($uploadedFiles[$inputName]['disk'] ?? '') === 'local_server') {
                    $url = 'public/uploads/custom_fields/' . $url;
                }
                $uploadedFileCustomFieldValues[$cfId] = $url;
            }

            $inputSlug = $this->request->getPost('provider_slug');
            $resolvedSlug = $this->slugService->resolve(
                currentSlug: null,
                inputSlug: $inputSlug,
                fallbackName: $defaultCompanyName,
                table: 'partner_details'
            );

            $partnerData = [
                'partner_id' => $partnerId,
                'company_name' => trim($defaultCompanyName), // Store default language value in main table
                'about' => trim($defaultAbout), // Store default language value in main table
                'long_description' => trim($defaultLongDescription), // Store default language value in main table
                'banner' => $banner,
                'address' => trim($this->request->getPost('address')),
                'advance_booking_days' => $this->request->getPost('advance_booking_days'),
                'admin_commission' => 0,
                'type' => $this->request->getPost('type'),
                'number_of_members' => $this->request->getPost('number_of_members'),
                'visiting_charges' => $this->request->getPost('visiting_charges'),
                'is_approved' => $this->request->getPost('is_approved') ? 1 : 0,
                'other_images' => !empty($uploadedOtherImages) ? json_encode($uploadedOtherImages) : '',
                'at_store' => $this->request->getPost('at_store') ? 1 : 0,
                'at_doorstep' => $this->request->getPost('at_doorstep') ? 1 : 0,
                'need_approval_for_the_service' => $this->request->getPost('need_approval_for_the_service') ? 1 : 0,
                'chat' => $this->request->getPost('chat') ? 1 : 0,
                'pre_chat' => $this->request->getPost('pre_chat') ? 1 : 0,
                'slug' => $resolvedSlug,
            ];

            // Save partner
            $this->savePartner($partnerId, $partnerData);

            // Persist all visible custom field values into `partner_custom_fields`.
            $customFieldValuesByKey = $this->collectCustomFieldValuesFromPost($uploadedFileCustomFieldValues);
            $this->upsertPartnerCustomFields($partnerId, $customFieldValuesByKey);

            // Handle translated fields using PartnerService
            $postData = $this->request->getPost();

            // Transform form data to translated_fields structure
            $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

            // Add translated_fields to postData for PartnerService
            $postData['translated_fields'] = $translatedFields;

            $translationResult = $this->partnerService->handlePartnerCreationWithTranslations($postData, $partnerData, $partnerId, $defaultLanguage);

            if (!$translationResult['success']) {
                // If translation saving fails, we should handle this appropriately
                // For now, we'll log the error but continue with the process
                log_message('error', 'Failed to save partner translations: ' . implode(', ', $translationResult['errors']));
            }

            // Save SEO settings (validation handled by Seo_model)
            $this->saveSeoSettings($partnerId);

            // Save partner timings
            $this->savePartnerTimings(
                $partnerId,
                $this->request->getPost('start_time'),
                $this->request->getPost('end_time'),
                $this->request->getPost()
            );

            // Assign user to group
            $this->assignUserGroup($partnerId);

            return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data Saved Successfully"), false, ['partner_id' => $partnerId], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - insert_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function deactivate_partner()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $permission = is_permitted($this->creator_id, 'update', 'partner');
            if (!$permission) {
                return NoPermission();
            }
            $partner_id = $this->request->getPost('partner_id');
            $partner_details = fetch_details('users', ['id' => $partner_id])[0];
            $operation =  $this->ionAuth->deactivate($partner_id);
            if ($operation) {
                return successResponse(labels(SUCCESSFULLY_DISABLED, "successfully disabled"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(UNSUCCESSFUL_ATTEMPT_TO_DISABLE_THE_USER, "unsuccessful attempt to disable the user"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - deactivate_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function activate_partner()
    {
        try {
            $permission = is_permitted($this->creator_id, 'update', 'partner');
            if (!$permission) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $partner_id = $this->request->getPost('partner_id');
            $operation =  $this->ionAuth->activate($partner_id);
            if ($operation) {
                return successResponse(labels(SUCCESSFULLY_ACTIVATED, "successfully activated"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(UNSUCCESSFUL_ATTEMPT_TO_DISABLE_THE_USER, "unsuccessful attempt to disable the user"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - activate_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function approve_partner()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            try {
                $permission = is_permitted($this->creator_id, 'update', 'partner');
                if (!$permission) {
                    return NoPermission();
                }
                if (!$this->isLoggedIn || !$this->userIsAdmin) {
                    return redirect('admin/login');
                }
                $partner_id = $this->request->getPost('partner_id');
                $builder = $this->db->table('partner_details');
                $partner_approval = $builder->set('is_approved', 1)->where('partner_id', $partner_id)->update();

                // Send notifications when partner is approved
                if ($partner_approval) {
                    $notificationContext = [
                        'provider_id' => $partner_id
                    ];

                    try {
                        queue_notification_service(
                            eventType: 'provider_approved',
                            recipients: ['user_id' => $partner_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'language' => $this->defaultLanguage,
                                'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                            ]
                        );
                        // log_message('info', '[PROVIDER_APPROVED] Notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the approval process
                        // log_message('error', '[PROVIDER_APPROVED] Notification error: ' . $notificationError->getMessage());
                        log_message('error', '[PROVIDER_APPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                    }
                    return successResponse(labels(PROVIDER_APPROVED, "Provider approved"), false, [$partner_approval], [], 200, csrf_token(), csrf_hash());
                } else {
                    return successResponse(labels(COULD_NOT_APPROVE_PROVIDER, "Could not approve provider"), false, [], [], 200, csrf_token(), csrf_hash());
                }
            } catch (\Exception $th) {

                return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - approve_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function disapprove_partner()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            try {
                $permission = is_permitted($this->creator_id, 'update', 'partner');
                if (!$permission) {
                    return NoPermission();
                }
                if (!$this->isLoggedIn || !$this->userIsAdmin) {
                    return redirect('admin/login');
                }
                $partner_id = $this->request->getPost('partner_id');
                $builder = $this->db->table('partner_details');
                $partner_approval = $builder->set('is_approved', 0)->where('partner_id', $partner_id)->update();

                // Send notifications when partner is disapproved
                if ($partner_approval) {
                    $notificationContext = [
                        'provider_id' => $partner_id
                    ];
                    
                    try {
                        queue_notification_service(
                            eventType: 'provider_disapproved',
                            recipients: ['user_id' => $partner_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'language' => $this->defaultLanguage,
                                'platforms' => ['android', 'ios', 'provider_panel'],
                                'type' => 'provider_request_status',
                                'data' => ['status' => 'reject', 'type_id' => (string)$partner_id]
                            ]
                        );
                    } catch (\Throwable $notificationError) {
                        log_message('error', '[PROVIDER_DISAPPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                    }

                    // For SMS template testing we now send a direct, one-off SMS using NotificationService.
                    // This uses the `payment_reminder` SMS template and bypasses notification preferences
                    // so that the test SMS is always delivered, even if the user has disabled SMS.
                    // The template variables are hard-coded here for simple, repeatable testing.
                    // try {
                    //     $notificationService = new NotificationService();

                    //     // Hard-coded test context for the payment_reminder SMS template.
                    //     // You can safely change these values while testing different messages.
                    //     $paymentReminderContext = [
                    //         'customer_name'  => 'Test Customer',
                    //         'service_name'   => 'Test Service',
                    //         'provider_id'    => $partner_id,
                    //     ];

                    //     // Send SMS directly without using the queue.
                    //     // - eventType `payment_reminder` must match `sms_templates.type`.
                    //     // - channels => ['sms'] restricts this to SMS only.
                    //     // - user_ids + bypass_preference_check => true ensures preferences are bypassed
                    //     //   inside NotificationService::sendToMultipleRecipients() for SMS.
                    //     $notificationService->send(
                    //         'payment_reminder',
                    //         ['user_id' => (int)$partner_id],
                    //         $paymentReminderContext,
                    //         [
                    //             'channels'                => ['sms'],
                    //             'language'                => $this->defaultLanguage,
                    //             'user_ids'                => [(int)$partner_id],
                    //             'bypass_preference_check' => true,
                    //         ]
                    //     );
                    // } catch (\Throwable $notificationError) {
                    //     // Log error but don't fail the disapproval process
                    //     log_message('error', '[PROVIDER_DISAPPROVED_TEST_SMS] Notification error trace: ' . $notificationError->getTraceAsString());
                    // }

                    return successResponse(labels(PROVIDER_DISAPPROVED, "Provider disapproved"), false, [$partner_approval], [], 200, csrf_token(), csrf_hash());
                } else {
                    return successResponse(labels(COULD_NOT_DISAPPROVE_PROVIDER, "Could not disapprove provider"), false, [$partner_approval], [], 200, csrf_token(), csrf_hash());
                }
            } catch (\Exception $th) {

                return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - disapprove_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_partner()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = is_permitted($this->creator_id, 'delete', 'partner');
            if (!$permission) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $partner_id = $this->request->getPost('partner_id');
            $service_details = fetch_details('services', ['user_id' => $partner_id]);
            $partner_timing_details = fetch_details('partner_timings', ['partner_id' => $partner_id]);
            $partner_details = fetch_details('partner_details', ['partner_id' => $partner_id]);
            $user_details = fetch_details('users', ['id' => $partner_id]);
            $user_group_details = fetch_details('users_groups', ['user_id' => $partner_id]);
            if (!empty($service_details)) {
                $builder = $this->db->table('services');
                $builder->delete(['user_id' => $partner_id]);
            }
            if (!empty($partner_timing_details)) {
                $builder = $this->db->table('partner_timings');
                $builder->delete(['partner_id' => $partner_id]);
            }
            if (!empty($user_group_details)) {
                $builder = $this->db->table('users_groups');
                $builder->delete(['user_id' => $partner_id]);
            }
            delete_details(['user_id' => $partner_id], 'users_tokens');

            $disk = fetch_current_file_manager();

            // Clean up SEO settings and images before deleting partner
            $this->seoModel->cleanupSeoData($partner_id, 'providers');

            if (!empty($partner_details)) {
                if (!empty($partner_details[0]['banner'])) {
                    delete_file_based_on_server('banner', $partner_details[0]['banner'],  $disk);
                }

                // Document KYC files are stored in partner_custom_fields (file-type, documents group).
                // Delete files from storage first, then remove field rows.
                if ($this->db->tableExists('custom_fields') && $this->db->tableExists('partner_custom_fields')) {
                    $documentFieldRows = $this->db->table('custom_fields')
                        ->select('id')
                        ->where('field_group', 'documents')
                        ->where('field_type', 'file')
                        ->get()
                        ->getResultArray();

                    $documentFieldIds = array_map('intval', array_column($documentFieldRows, 'id'));

                    if (!empty($documentFieldIds)) {
                        $partnerCustomFieldRows = $this->db->table('partner_custom_fields')
                            ->select(['custom_field_id', 'value'])
                            ->where('partner_id', $partner_id)
                            ->whereIn('custom_field_id', $documentFieldIds)
                            ->get()
                            ->getResultArray();

                        foreach ($partnerCustomFieldRows as $customFieldRow) {
                            $fileValue = (string) ($customFieldRow['value'] ?? '');
                            if ($fileValue !== '') {
                                delete_file_based_on_server('custom_fields', $fileValue, $disk);
                            }
                        }

                        $this->db->table('partner_custom_fields')
                            ->where('partner_id', $partner_id)
                            ->whereIn('custom_field_id', $documentFieldIds)
                            ->delete();
                    }
                }

                // Legacy fallback (pre-custom-fields data model)
                if (!empty($partner_details[0]['address_id'])) {
                    delete_file_based_on_server('address_id', $partner_details[0]['address_id'],  $disk);
                }
                if (!empty($partner_details[0]['passport'])) {
                    delete_file_based_on_server('passport', $partner_details[0]['passport'], $disk);
                }
                if (!empty($partner_details[0]['national_id'])) {
                    delete_file_based_on_server('national_id', $partner_details[0]['national_id'], $disk);
                }
                $builder = $this->db->table('partner_details');
                $builder->delete(['partner_id' => $partner_id]);
            }
            if (!empty($user_details)) {
                $builder = $this->db->table('users');
                $partner_approval = $builder->delete(['id' => $partner_id]);
                if ($partner_approval) {
                    return successResponse(labels(PROVIDER_REMOVED, "Provider Removed"), false, [$partner_approval], [], 200, csrf_token(), csrf_hash());
                } else {
                    return successResponse(labels(COULD_NOT_DELETE_PROVIDER, "Could not Delete provider"), false, [$partner_approval], [], 200, csrf_token(), csrf_hash());
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - delete_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function payment_request()
    {
        try {
            helper('function');
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            setPageInfo($this->data, labels(PROVIDERS, 'Providers') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'payment_request');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/admin/template', $this->data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function payment_request_list()
    {
        try {
            $payment_requests = new Payment_request_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'p.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $data = $payment_requests->list(false, $search, $limit, $offset, $sort, $order);
            return $data;
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function pay_partner()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                $errorMessage = labels($result['message'], "Modification in demo version is not allowed.");
                return ErrorResponse($errorMessage, true, [], [], 200, csrf_token(), csrf_hash());
            }
            $admin_id =  $this->userId;
            $pr_id = $this->request->getPost('request_id');
            $user_id = $this->request->getPost('user_id');
            $reason = $this->request->getPost('reason');
            $amount = $this->request->getPost('amount');
            $status = $this->request->getPost('status');
            $partner_details  = fetch_details('users', ['id' => $user_id]);
            $admin_details  = fetch_details('users', ['id' => $admin_id]);
            if ($status == 1) {
                if (!empty($partner_details)) {
                    $update_request = update_details(
                        ['remarks' => $reason, 'status' => $status],
                        ['id' => $pr_id],
                        'payment_request'
                    );
                    $update_balance =  (int)$admin_details[0]['balance'] + $amount;
                    $update_admin = update_details(
                        ['balance' => $update_balance],
                        ['id' => $admin_id],
                        'users'
                    );
                    add_settlement_cashcollection_history($reason, 'settled_by_payment_request', date('Y-m-d'), date('H:i:s'), $amount, $user_id, '', $pr_id, '', $amount, '');
                    if ($update_admin) {
                        // Send notifications when withdrawal request is approved
                        try {
                            // Prepare context data for notification templates
                            // This context will be used to populate template variables like [[amount]], [[currency]], [[provider_name]], etc.
                            $notificationContext = [
                                'provider_id' => $user_id,
                                'user_id' => $user_id,
                                'amount' => $amount
                            ];

                            // Queue all notifications (FCM, Email, SMS) using NotificationService
                            // NotificationService automatically handles:
                            // - Translation of templates based on user language
                            // - Variable replacement in templates
                            // - Notification settings checking for each channel
                            // - Fetching user email/phone/FCM tokens
                            // - Unsubscribe status checking for email
                            queue_notification_service(
                                eventType: 'withdraw_request_approved',
                                recipients: ['user_id' => $user_id],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                    'language' => $this->defaultLanguage,
                                    'platforms' => ['android', 'ios', 'provider_panel'], // Provider platforms for FCM
                                    'type' => 'withdraw_request', // Notification type for app routing
                                    'data' => [
                                        'status' => 'approve',
                                        'type_id' => (string)$user_id,
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                    ]
                                ]
                            );

                            // log_message('info', '[WITHDRAW_REQUEST_APPROVED] Notification result: ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            // Log error but don't fail the withdrawal approval
                            // log_message('error', '[WITHDRAW_REQUEST_APPROVED] Notification error: ' . $notificationError->getMessage());
                            log_message('error', '[WITHDRAW_REQUEST_APPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                        }
                        return successResponse(labels(DEBITED_AMOUNT, "debited amount $amount"), false, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            } else {
                $update_balance =  (int)$partner_details[0]['balance'] + $amount;
                $update_id = update_details(['balance' => $update_balance], ['id' => $user_id], 'users');
                update_details(
                    [
                        'remarks' => $reason,
                        'status' => $status
                    ],
                    ['id' => $pr_id],
                    'payment_request'
                );
                if ($update_id) {
                    // Send notifications when withdrawal request is disapproved
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[amount]], [[currency]], [[provider_name]], etc.
                        $notificationContext = [
                            'provider_id' => $user_id,
                            'user_id' => $user_id,
                            'amount' => $amount
                        ];

                        // Queue all notifications (FCM, Email, SMS) using NotificationService
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        $result = queue_notification_service(
                            eventType: 'withdraw_request_disapproved',
                            recipients: ['user_id' => $user_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'language' => $this->defaultLanguage,
                                'platforms' => ['android', 'ios', 'provider_panel'], // Provider platforms for FCM
                                'type' => 'withdraw_request', // Notification type for app routing
                                'data' => [
                                    'status' => 'reject',
                                    'type_id' => (string)$user_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[WITHDRAW_REQUEST_DISAPPROVED] Notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the withdrawal disapproval
                        log_message('error', '[WITHDRAW_REQUEST_DISAPPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                    }
                    return successResponse(labels(REJECTION_OCCURRED, "Rejection occurred"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - pay_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_request()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            try {
                $id = $this->request->getPost('id');
                $builder = $this->db->table('payment_request')->delete(['id' => $id]);
                if ($builder) {
                    return successResponse(labels(DELETED_PAYMENT_REQUEST_SUCCESS, "Deleted payment request success"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(COULD_NOT_DELETE_PAYMENT_REQUEST, "Couldnt delete payment request"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } catch (\Exception $th) {
                return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - delete_request()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            print_r(json_encode($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function banking_details()
    {
        try {
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $db = \Config\Database::connect();

            $cityRow = fetch_details('users', ['id' => $partner_id], 'city');
            $city = !empty($cityRow) ? ($cityRow[0]['city'] ?? '') : '';

            $db = \Config\Database::connect();
            $customFields = $db->table('custom_fields')
                ->select(['id', 'field_label', 'field_group'])
                ->whereIn('field_group', ['documents', 'bank_details'])
                ->where('visible', 1)
                ->orderBy('sort_order', 'ASC')
                ->get()
                ->getResultArray();

            $cfIds = array_column($customFields, 'id');
            $valuesById = !empty($cfIds) ? $this->getPartnerCustomFieldValuesById((int) $partner_id, array_map('intval', $cfIds)) : [];

            $row = [
                'partner_id' => (int) $partner_id,
                'name' => $city,
            ];
            foreach ($customFields as $cf) {
                $label = $cf['field_label'] ?? ('Custom Field ' . $cf['id']);
                $row[$label] = $valuesById[(int) $cf['id']] ?? '';
            }

            $total = 1;
            $rows = [$row];
            $bulkData['total'] = $total;
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - banking_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function timing_details()
    {
        try {
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $db      = \Config\Database::connect();
            $builder = $db->table('partner_timings pt');
            $count = $builder->select('COUNT(pt.id) as total')
                ->where('pt.partner_id', $partner_id)->get()->getResultArray();
            $total = $count[0]['total'];
            $tempRow = array();
            $data =  $builder->select('pt.*,')
                ->where('pt.partner_id', $partner_id)->get()->getResultArray();
            $rows = [];
            foreach ($data as $row) {
                $label = ($row['is_open'] == 1) ?
                    '<div class="badge badge-success projects-badge">' . labels('open', 'Open') . '</div>' :
                    '<div class="badge badge-danger projects-badge">' . labels('closed', 'Closed') . '</div>';
                $tempRow['partner_id'] = $row['partner_id'];
                $label_new = ($row['is_open'] == 1) ?
                    "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('open', 'Open') . "
                    </div>" :
                    "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('closed', 'Closed') . "
                    </div>";
                $tempRow['partner_id'] = $row['partner_id'];
                $tempRow['day'] = labels($row['day'], $row['day']);
                $tempRow['opening_time'] = $row['opening_time'];
                $tempRow['closing_time'] = $row['closing_time'];
                $tempRow['is_open'] = $label;
                $tempRow['is_open_new'] = $label_new;
                $rows[] = $tempRow;
            }
            $bulkData['total'] = $total;
            $bulkData['rows'] = $rows;
            return json_encode($bulkData);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - timing_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function service_details()
    {
        try {
            $uri = service('uri');
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $service_model = new Service_model();
            $where['s.user_id'] = $uri->getSegments()[3];
            $services =  $service_model->list(false, $search, $limit, $offset, $sort, $order, $where);
            return ($services);
        } catch (\Exception $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - service_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function settle_commission()
    {
        try {
            helper('function');
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            setPageInfo($this->data, labels(COMMISSION_SETTLEMENT, 'Commission Settlement') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'manage_commission');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/admin/template', $this->data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - settle_commission()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function cash_collection()
    {
        try {
            helper('function');
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            setPageInfo($this->data, labels('cash_collection', 'Cash Collection') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'cash_collection');
            return view('backend/admin/template', $this->data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cash_collection()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function commission_list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Get current language for translations
            // This ensures translations are returned for the currently selected language in admin panel
            $current_language = get_current_language();

            // Pass current language to model method to ensure proper translation fallback:
            // current language → default language → base table
            return json_encode($this->partner->unsettled_commission_list(false, $search, $limit, $offset, $sort, $order, [], 'pd.id', [], [], $current_language));
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - commission_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function cash_collection_list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            // Decode URL-encoded search term to handle spaces and special characters
            // This ensures "BrightFix%20Services" becomes "BrightFix Services"
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? urldecode($_GET['search']) : '';
            // Trim the decoded search term to remove any extra whitespace
            $search = trim($search);

            // Get current language for translations
            $current_language = get_current_language();

            // Exclude partners whose payable_commision is zero or negative to keep the table focused on actionable cash collections.
            $positiveCommissionFilter = ['u.payable_commision >' => 0];
            $data = json_encode($this->partner->list(false, $search, $limit, $offset, $sort, $order, $positiveCommissionFilter, 'pd.id', [], [], null, $current_language));
            print_r($data);
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cash_collection_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function commission_pay_out()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $partner_id = $this->request->getPost('partner_id');
            $amount = $this->request->getPost('amount');

            // Validate required fields
            if (empty($partner_id) || empty($amount)) {
                return ErrorResponse(labels(PLEASE_ENTER_COMMISSION, 'Please enter commission'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get partner balance and details
            $current_balance = fetch_details('users', ['id' => $partner_id], ['balance', 'email'])[0];
            $partner_data = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name'])[0];

            // Validate partner exists
            if (empty($current_balance)) {
                return ErrorResponse(labels(USER_NOT_FOUND, 'User not found'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            // Check if balance is 0 or less - prevent any settlement
            if ($current_balance['balance'] <= 0) {
                return ErrorResponse(labels(CANNOT_WITHDRAW_WHEN_BALANCE_IS_0_OR_LESS, 'Cannot withdraw when balance is 0 or less'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Additional validation: ensure amount is not negative
            if ($amount < 0) {
                return ErrorResponse(labels(AMOUNT_MUST_BE_GREATER_THAN_0, 'Amount must be greater than 0'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Log the settlement attempt for debugging
            log_message('info', "Commission settlement attempt - Partner ID: {$partner_id}, Amount: {$amount}, Current Balance: {$current_balance['balance']}");

            $rules = [
                'amount' => [
                    'rules' => 'required|numeric|greater_than[0]|less_than_equal_to[' . $current_balance['balance'] . ']',
                    'errors' => [
                        'required' => labels(PLEASE_ENTER_COMMISSION, 'Please enter commission'),
                        'numeric' => labels(PLEASE_ENTER_A_NUMERIC_VALUE_FOR_COMMISSION, 'Please enter a numeric value for commission'),
                        'greater_than' => labels(AMOUNT_MUST_BE_GREATER_THAN_0, 'Amount must be greater than 0'),
                        'less_than_equal_to' => labels(AMOUNT_MUST_BE_LESS_THAN_OR_EQUAL_TO_CURRENT_BALANCE, 'Amount must be less than or equal to current balance')
                    ]
                ]
            ];
            $this->validation->setRules($rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors  = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Final validation: double-check balance before processing
            $final_balance_check = fetch_details('users', ['id' => $partner_id], ['balance'])[0];
            if ($final_balance_check['balance'] <= 0) {
                return ErrorResponse(labels(CANNOT_WITHDRAW_WHEN_BALANCE_IS_0_OR_LESS, 'Cannot withdraw when balance is 0 or less'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            if ($amount > $final_balance_check['balance']) {
                return ErrorResponse(labels(AMOUNT_MUST_BE_LESS_THAN_OR_EQUAL_TO_CURRENT_BALANCE, 'Amount must be less than or equal to current balance'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $updated_balance = $final_balance_check['balance'] - $amount;
            $update = update_details(['balance' => $updated_balance], ['id' => $partner_id], 'users');
            $t = time();
            $data = [
                'transaction_type' => 'transaction',
                'user_id' => $this->userId,
                'partner_id' => $partner_id,
                'order_id' =>  "TXN-$t",
                'type' => 'fund_transfer',
                'txn_id' => '',
                'amount' =>  $amount,
                'status' => 'success',
                'currency_code' => NULL,
                'message' => 'commission settled'
            ];
            $settlement_history = [
                'provider_id' => $partner_id,
                'message' =>   $this->request->getPost('message'),
                'amount' =>  $amount,
                'status' => 'credit',
                'date' => date("Y-m-d H:i:s"),
            ];
            insert_details($settlement_history, 'settlement_history');
            add_settlement_cashcollection_history('Settled By admin', 'settled_by_settlement', date('d-m-t'), date('h:i'), $amount, $partner_id, '', '', '', $amount, '');
            if ($update) {
                if (add_transaction($data)) {
                    // Send notifications when payment settlement is completed
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[amount]], [[currency]], [[provider_name]], etc.
                        $notificationContext = [
                            'provider_id' => $partner_id,
                            'user_id' => $partner_id,
                            'amount' => $amount
                        ];

                        // Queue all notifications (FCM, Email, SMS) using NotificationService
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'payment_settlement',
                            recipients: ['user_id' => $partner_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'language' => $this->defaultLanguage,
                                'platforms' => ['android', 'ios', 'provider_panel'], // Provider platforms for FCM
                                'type' => 'settlement', // Notification type for app routing
                                'data' => [
                                    'type_id' => (string)$partner_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[PAYMENT_SETTLEMENT] Notification result: ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the payment settlement
                        log_message('error', '[PAYMENT_SETTLEMENT] Notification error trace: ' . $notificationError->getTraceAsString());
                    }
                    return successResponse(labels(COMMISSION_SETTLED_SUCCESSFULLY, "Commission Settled Successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(UNSUCCESSFUL_WHILE_ADDING_TRANSACTION, "Unsuccessful while adding transaction"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse(labels(UNSUCCESSFUL_WHILE_UPDATING_SETTLING_STATUS, "Unsuccessful while Updating settling status"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - commission_pay_out()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function view_ratings()
    {
        try {
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $ratings_model = new Service_ratings_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            return json_encode($ratings_model->ratings_list(false, $search, $limit, $offset, $sort, $order, ['s.user_id' => $partner_id]));
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - view_ratings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function delete_rating()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $id = $this->request->getPost('id');
            $data = $this->db->table('services_ratings')->delete(['id' => $id]);
            if ($data) {
                return successResponse(labels(RATING_DELETED_SUCCESSFULLY, "Rating deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(UNSUCCESSFUL_IN_DELETION_OF_RATING, "Unsuccessful in deletion of rating"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - delete_rating()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update_partner()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }

            // Initialize PartnerService for handling translated fields
            $partnerService = new \App\Services\PartnerService();
            if (isset($_POST) && !empty($_POST)) {
                helper('function');
                $config = new \Config\IonAuth();
                $tables  = $config->tables;
                // Get the default language from database
                $defaultLanguage = 'en'; // fallback
                $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

                foreach ($languages as $language) {
                    if ($language['is_default'] == 1) {
                        $defaultLanguage = $language['code'];
                        break;
                    }
                }

                // Validate default language translated fields manually
                $postData = $this->request->getPost();
                $defaultLanguageErrors = [];

                // Check if data is in new format (as objects)
                if (isset($postData['company_name']) && is_array($postData['company_name'])) {
                    // Check username
                    $usernameValue = $postData['username'][$defaultLanguage] ?? null;
                    if (empty($usernameValue)) {
                        $defaultLanguageErrors[] = labels(USERNAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Username is required for default language");
                    }

                    // Check company name
                    $companyNameValue = $postData['company_name'][$defaultLanguage] ?? null;
                    if (empty($companyNameValue)) {
                        $defaultLanguageErrors[] = labels(COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Company name is required for default language");
                    }

                    // Check about field
                    $aboutValue = $postData['about_provider'][$defaultLanguage] ?? null;
                    if (empty($aboutValue)) {
                        $defaultLanguageErrors[] = labels(ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "About provider is required for default language");
                    }

                    // Check description field
                    $descriptionValue = $postData['long_description'][$defaultLanguage] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels(DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Description is required for default language");
                    }
                } else {
                    // Fallback: Check old format (field[language])
                    $usernameField = 'username[' . $defaultLanguage . ']';
                    $companyNameField = 'company_name[' . $defaultLanguage . ']';
                    $aboutField = 'about_provider[' . $defaultLanguage . ']';
                    $descriptionField = 'long_description[' . $defaultLanguage . ']';

                    // Check username
                    $usernameValue = $postData[$usernameField] ?? null;
                    if (empty($usernameValue)) {
                        $defaultLanguageErrors[] = labels(USERNAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Username is required for default language");
                    }

                    // Check company name
                    $companyNameValue = $postData[$companyNameField] ?? null;
                    if (empty($companyNameValue)) {
                        $defaultLanguageErrors[] = labels(COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Company name is required for default language");
                    }

                    // Check about field
                    $aboutValue = $postData[$aboutField] ?? null;
                    if (empty($aboutValue)) {
                        $defaultLanguageErrors[] = labels(ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "About provider is required for default language");
                    }

                    // Check description field
                    $descriptionValue = $postData[$descriptionField] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels(DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Description is required for default language");
                    }
                }

                if (!empty($defaultLanguageErrors)) {
                    return ErrorResponse($defaultLanguageErrors, true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Set validation rules for non-translated fields only
                $validationRules = [
                    'email' => ["rules" => 'required|trim', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_EMAIL, "Please enter providers email"),]],
                    'address' => ["rules" => 'required|trim', "errors" => ["required" => labels(PLEASE_ENTER_ADDRESS, "Please enter address"),]],
                    'type' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_PROVIDERS_TYPE, "Please select providers type"),]],
                    'visiting_charges' => ["rules" => 'required|numeric', "errors" => ["required" => labels(PLEASE_ENTER_VISITING_CHARGES, "Please enter visiting charges"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_VISITING_CHARGES, "Please enter numeric value for visiting charges")]],
                    'advance_booking_days' => ["rules" => 'required|numeric|greater_than_equal_to[1]', "errors" => ["required" => labels(PLEASE_ENTER_ADVANCE_BOOKING_DAYS, "Please enter advance booking days"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_ADVANCE_BOOKING_DAYS, "Please enter numeric advance booking days"), "greater_than_equal_to" => labels(ADVANCE_BOOKING_DAYS_MUST_BE_AT_LEAST_1, "Advance booking days must be at least 1")]],
                    'start_time' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_WORKING_DAYS, "Please enter providers working days"),]],
                    'end_time' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_WORKING_PROPERLY, "Please enter providers working properly"),]],
                    'provider_slug' => ["rules" => 'required|trim', "errors" => ["required" => labels(PLEASE_ENTER_PROVIDERS_SLUG, "Please enter providers slug"),]],
                ];

                $this->validation->setRules($validationRules);
                if (!$this->validation->withRequest($this->request)->run()) {
                    $errors = $this->validation->getErrors();
                    return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Validate default language translated fields manually
                $postData = $this->request->getPost();
                $defaultLanguageErrors = [];

                // Check if default language fields are present and not empty
                // The form now sends data as objects: company_name[en], about_provider[en], etc.

                // Check if data is in new format (as objects)
                if (isset($postData['company_name']) && is_array($postData['company_name'])) {
                    // Check company name
                    $companyNameValue = $postData['company_name'][$defaultLanguage] ?? null;
                    if (empty($companyNameValue)) {
                        $defaultLanguageErrors[] = labels(COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Company name is required for default language");
                    }

                    // Check about field
                    $aboutValue = $postData['about_provider'][$defaultLanguage] ?? null;
                    if (empty($aboutValue)) {
                        $defaultLanguageErrors[] = labels(ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "About provider is required for default language");
                    }

                    // Check description field
                    $descriptionValue = $postData['long_description'][$defaultLanguage] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels(DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Description is required for default language");
                    }
                } else {
                    // Fallback: Check old format (field[language])
                    $companyNameField = 'company_name[' . $defaultLanguage . ']';
                    $aboutField = 'about_provider[' . $defaultLanguage . ']';
                    $descriptionField = 'long_description[' . $defaultLanguage . ']';

                    // Check company name
                    $companyNameValue = $postData[$companyNameField] ?? null;
                    if (empty($companyNameValue)) {
                        $defaultLanguageErrors[] = labels(COMPANY_NAME_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Company name is required for default language");
                    }

                    // Check about field
                    $aboutValue = $postData[$aboutField] ?? null;
                    if (empty($aboutValue)) {
                        $defaultLanguageErrors[] = labels(ABOUT_PROVIDER_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "About provider is required for default language");
                    }

                    // Check description field
                    $descriptionValue = $postData[$descriptionField] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels(DESCRIPTION_IS_REQUIRED_FOR_DEFAULT_LANGUAGE, "Description is required for default language");
                    }
                }

                if (!empty($defaultLanguageErrors)) {
                    return ErrorResponse($defaultLanguageErrors, true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Preserve up to 7 decimal places to match register method validation
                $latitude = number_format($this->request->getPost('partner_latitude'), 7, '.', '');
                $longitude = number_format($this->request->getPost('partner_longitude'), 7, '.', '');

                $this->validateCoordinates(
                    $latitude,
                    $longitude
                );

                $disk = fetch_current_file_manager();

                // Dynamically create folders and upload paths for all visible file-type custom fields.
                $visibleFileCustomFields = $this->getVisibleFileCustomFields();

                $folders = [
                    'public/backend/assets/profile/' => labels(FAILED_TO_CREATE_PROFILE_FOLDERS, "Failed to create profile folders"),
                    'public/backend/assets/banner/' => labels(FAILED_TO_CREATE_BANNER_FOLDERS, "Failed to create banner folders"),
                    'public/uploads/custom_fields/' => labels('failed_to_create_custom_fields_folders', "Failed to create custom fields folders"),
                ];

                foreach ($folders as $path => $errorMessage) {
                    if (!create_folder($path)) {
                        return ErrorResponse($errorMessage, true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                $data = fetch_details('users', ['id' => $this->request->getPost('partner_id')], ['image'])[0];
                $partnerId = (int)$this->request->getPost('partner_id');
                $IdProofs = fetch_details(
                    'partner_details',
                    ['partner_id' => $partnerId],
                    ['other_images', 'banner', 'company_name', 'slug']
                )[0];

                // Dynamically fetch old values for all visible file-type custom fields.
                $fileFieldIds = array_map(fn ($cf) => (int) $cf['id'], $visibleFileCustomFields);
                $oldDocValues = !empty($fileFieldIds) ? $this->getPartnerCustomFieldValuesById($partnerId, $fileFieldIds) : [];
                $old_banner = $IdProofs['banner'];
                $old_image = $data['image'];

                $paths = [
                    'image' => ['file' => $this->request->getFile('image'), 'path' => 'public/backend/assets/profile/', 'error' => labels(FAILED_TO_CREATE_PROFILE_FOLDERS, 'Failed to create profile folders'), 'folder' => 'profile', 'old_file' => $old_image, 'disk' =>  $disk,],
                    'banner_image' => ['file' => $this->request->getFile('banner_image'), 'path' => 'public/backend/assets/banner/', 'error' => labels(FAILED_TO_CREATE_BANNER_FOLDERS, 'Failed to create banner folders'), 'folder' => 'banner', 'old_file' => $old_banner, 'disk' =>  $disk,],
                ];

                // Dynamically add upload paths for all visible file-type custom fields.
                foreach ($visibleFileCustomFields as $cfRow) {
                    $cfId = (int) $cfRow['id'];
                    $inputName = 'cf_' . $cfId;
                    $paths[$inputName] = [
                        'file' => $this->request->getFile($inputName),
                        'path' => 'public/uploads/custom_fields/',
                        'error' => labels('failed_to_create_custom_fields_folders', "Failed to create custom fields folders"),
                        'folder' => 'custom_fields',
                        'old_file' => $oldDocValues[$cfId] ?? '',
                        'disk' => $disk,
                    ];
                }
                // Process single file uploads
                $uploadedFiles = [];
                foreach ($paths as $key => $config) {
                    if (!empty($_FILES[$key]) && isset($_FILES[$key])) {


                        $file = $config['file'];
                        $path = './' . $config['path'];
                        if ($file && $file->isValid()) {

                            if (!empty($config['old_file'])) {
                                delete_file_based_on_server($config['folder'], $config['old_file'], $config['disk']);
                            }
                            $result = upload_file($config['file'], $config['path'], $config['error'], $config['folder']);
                            if ($result['error'] == false) {
                                $uploadedFiles[$key] = [
                                    'url' => $result['file_name'],
                                    'disk' => $result['disk']
                                ];
                            } else {
                                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        } else {
                            $uploadedFiles[$key] = [
                                'url' => $config['old_file'],
                                'disk' => $config['disk']
                            ];
                        }
                    } else {
                        $uploadedFiles[$key] = [
                            'url' => $config['old_file'],
                            'disk' => $config['disk']
                        ];
                    }
                }

                $multipleFiles = $this->request->getFiles('filepond');
                $uploadedOtherImages = [];
                $old_other_images_array = json_decode($IdProofs['other_images'], true);
                $other_images_disk = $disk;

                // Process existing other images that the user wants to keep or remove
                $existing_other_images = $this->request->getPost('existing_other_images');
                $remove_other_images_flags = $this->request->getPost('remove_other_images');

                // Start with existing images that are not marked for removal
                if (!empty($existing_other_images) && !empty($remove_other_images_flags)) {
                    foreach ($existing_other_images as $index => $image_path) {
                        // Skip images marked for removal
                        if (isset($remove_other_images_flags[$index]) && $remove_other_images_flags[$index] === "1") {
                            // Delete the image if needed
                            $clean_path = $image_path;
                            // Make sure we have a clean path without the base URL for file deletion
                            if (strpos($image_path, 'http') === 0) {
                                $base_url = base_url();
                                if (strpos($image_path, $base_url) === 0) {
                                    $clean_path = substr($image_path, strlen($base_url));
                                }
                            }
                            delete_file_based_on_server('partner', $clean_path, $other_images_disk);
                            continue;
                        }

                        // Keep this image - ensure we're storing paths without base URL
                        $clean_path = $image_path;
                        if (strpos($image_path, 'http') === 0) {
                            $base_url = base_url();
                            if (strpos($image_path, $base_url) === 0) {
                                $clean_path = substr($image_path, strlen($base_url));
                            }
                        }
                        $uploadedOtherImages[] = $clean_path;
                    }
                } else if (!empty($old_other_images_array) && empty($existing_other_images)) {
                    // If no form interaction with existing images, keep all original images
                    $uploadedOtherImages = $old_other_images_array;
                }

                // Now handle new image uploads
                if (isset($multipleFiles['other_service_image_selector_edit'])) {
                    foreach ($multipleFiles['other_service_image_selector_edit'] as $file) {
                        if ($file->isValid()) {
                            $result = upload_file($file, 'public/uploads/partner/', labels(FAILED_TO_UPLOAD_OTHER_IMAGES, 'Failed to upload other images'), 'partner');
                            if ($result['error'] == false) {
                                $uploadedOtherImages[] = $result['disk'] === "local_server"
                                    ? 'public/uploads/partner/' . $result['file_name']
                                    : $result['file_name'];
                                $other_images_disk = $result['disk']; // Update disk
                            } else {
                                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        }
                    }
                }

                $other_images = !empty($uploadedOtherImages) ? json_encode($uploadedOtherImages) : "[]";
                if (empty($uploadedOtherImages) && !empty($IdProofs['other_images']) && empty($existing_other_images)) {
                    $other_images = $IdProofs['other_images']; // Keep original if no changes
                }

                $banner = $uploadedFiles['banner_image']['url'] ?? $old_banner;
                $image = $uploadedFiles['image']['url'] ?? $old_image;

                if (isset($uploadedFiles['banner_image']['disk']) && $uploadedFiles['banner_image']['disk'] == 'local_server') {
                    if (isset($uploadedFiles['banner_image']['url']) && $uploadedFiles['banner_image']['url'] != "") {
                        $uploadedFiles['banner_image']['url'] = preg_replace('#(public/backend/assets/banner/)+#', '', $uploadedFiles['banner_image']['url']);
                        $banner = 'public/backend/assets/banner/' . $uploadedFiles['banner_image']['url'];
                    } else {
                        $banner = "";
                    }
                }

                // Dynamically resolve uploaded file paths for all visible file-type custom fields.
                $uploadedFileCustomFieldValues = [];
                foreach ($visibleFileCustomFields as $cfRow) {
                    $cfId = (int) $cfRow['id'];
                    $inputName = 'cf_' . $cfId;
                    $oldVal = $oldDocValues[$cfId] ?? '';
                    $url = $uploadedFiles[$inputName]['url'] ?? $oldVal;
                    $fileDisk = $uploadedFiles[$inputName]['disk'] ?? '';

                    if ($fileDisk === 'local_server') {
                        if ($url !== null && $url !== '') {
                            // Strip any known prefix so we have just the filename,
                            // then re-add the canonical custom_fields/ path.
                            $filename = basename($url);
                            $url = 'public/uploads/custom_fields/' . $filename;
                        } else {
                            $url = '';
                        }
                    }
                    $uploadedFileCustomFieldValues[$cfId] = $url;
                }

                // Update partner details
                $partnerIDS = [
                    'banner' => $banner,
                ];
                if ($partnerIDS) {
                    update_details(
                        $partnerIDS,
                        ['partner_id' => $this->request->getPost('partner_id')],
                        'partner_details',
                        false
                    );
                }
                $phone = $_POST['phone'];
                $country_code = $_POST['country_code'];

                // Enforce loginType-based identity: do not allow changing the field used for sign-in.
                // Fetch existing user identity and loginType; when editing, keep identity fields immutable per loginType.
                $existingUser = fetch_details('users', ['id' => $this->request->getPost('partner_id')], ['loginType', 'phone', 'country_code', 'email']);
                $currentLoginType = (isset($existingUser[0]['loginType']) && in_array($existingUser[0]['loginType'], ['phone', 'email'], true))
                    ? $existingUser[0]['loginType']
                    : 'phone';
                $email = $this->request->getPost('email');
                if ($currentLoginType === 'phone') {
                    $phone = $existingUser[0]['phone'] ?? $phone;
                    $country_code = $existingUser[0]['country_code'] ?? $country_code;
                } else {
                    $email = $existingUser[0]['email'] ?? $email;
                }

                // Always default to the previously stored image when no new upload happens.
                // We also need to normalize absolute URLs that may come from duplicate provider flows.
                $image = $uploadedFiles['image']['url'] ?? $old_image;
                if (empty($image) && $this->request->getFile('image') && $this->request->getFile('image')->getName()) {
                    $image = 'public/backend/assets/profile/' . $this->request->getFile('image')->getName();
                }

                // Convert absolute URLs (e.g. from duplicate provider) back to relative paths so edits do not break the stored image.
                if (!empty($image) && preg_match('#^https?://#i', $image)) {
                    $baseUrl = rtrim(base_url(), '/');
                    if (strpos($image, $baseUrl) === 0) {
                        $image = ltrim(substr($image, strlen($baseUrl)), '/');
                    }
                }

                if (isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server' && !empty($image) && !preg_match('#^https?://#i', $image)) {
                    $image = preg_replace('#^public/backend/assets/profile/#', '', $image);
                    $image = 'public/backend/assets/profile/' . $image;
                }
                // Get default language username for users table
                $defaultUsername = $this->request->getPost('username[' . $defaultLanguage . ']') ?? $this->request->getPost('username') ?? '';

                $userData = [
                    'username' => $defaultUsername,
                    'email' => $email,
                    'phone' => $phone,
                    'country_code' => $country_code,
                    'image' =>  $image,
                    'latitude' => $this->request->getPost('partner_latitude'),
                    'longitude' => $this->request->getPost('partner_longitude'),
                    'city' => $this->request->getPost('city'),
                    'loginType' => $currentLoginType,
                ];
                $userData = sanitizeInput($userData);
                if ($userData) {
                    update_details($userData, ['id' => $this->request->getPost('partner_id')], 'users');
                }
                $is_approved = isset($_POST['is_approved']) ? "1" : "0";

                // Get default language values for main table storage
                $defaultCompanyName = $this->request->getPost('company_name[' . $defaultLanguage . ']') ?? $this->request->getPost('company_name') ?? '';
                $defaultAbout = $this->request->getPost('about_provider[' . $defaultLanguage . ']') ?? $this->request->getPost('about_provider') ?? '';
                $defaultLongDescription = $this->request->getPost('long_description[' . $defaultLanguage . ']') ?? $this->request->getPost('long_description') ?? '';

                $existingSlug = $IdProofs['slug'] ?? '';
                $resolvedSlug = $this->slugService->resolve(
                    currentSlug: $existingSlug,
                    inputSlug: trim($this->request->getPost('provider_slug') ?? ''),
                    fallbackName: $defaultCompanyName,
                    table: 'partner_details',
                    excludeId: $this->request->getPost('partner_id')
                );

                if ($this->slugService->isLegacySlug($existingSlug)) {
                    $resolvedSlug = $this->slugService->generate(
                        $defaultCompanyName,
                        $defaultCompanyName,
                        'partner_details',
                        $this->request->getPost('partner_id')
                    );
                }
                // Process partner data with translations using PartnerService
                // (Translation processing will be handled later in the method)

                // Partner details including default language values in main table
                $partner_details = [
                    'company_name' => trim($defaultCompanyName), // Store default language value in main table
                    'about' => trim($defaultAbout), // Store default language value in main table
                    'long_description' => trim($defaultLongDescription), // Store default language value in main table
                    'type' => $this->request->getPost('type'),
                    'visiting_charges' => $this->request->getPost('visiting_charges'),
                    'advance_booking_days' => $this->request->getPost('advance_booking_days'),
                    'number_of_members' => $this->request->getPost('number_of_members'),
                    'is_approved' => $is_approved,
                    'other_images' => $other_images,
                    'address' => $this->request->getPost('address'),
                    'slug' => $resolvedSlug,
                    'at_store' => (isset($_POST['at_store'])) ? 1 : 0,
                    'at_doorstep' => (isset($_POST['at_doorstep'])) ? 1 : 0,
                    'need_approval_for_the_service' => (isset($_POST['need_approval_for_the_service'])) ? 1 : 0,
                    'chat' => (isset($_POST['chat'])) ? 1 : 0,
                    'pre_chat' => (isset($_POST['pre_chat'])) ? 1 : 0,
                ];

                // Update partner details (non-translated fields)
                if (!empty($partner_details)) {
                    update_details($partner_details, ['partner_id' =>  $this->request->getPost('partner_id')], 'partner_details', false);
                }

                // Persist all visible custom field values into `partner_custom_fields`.
                $customFieldValuesByKey = $this->collectCustomFieldValuesFromPost($uploadedFileCustomFieldValues);
                $this->upsertPartnerCustomFields($partnerId, $customFieldValuesByKey);

                // Handle translated fields using PartnerService
                $postData = $this->request->getPost();

                // Transform form data to translated_fields structure
                $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

                // Add translated_fields to postData for PartnerService
                $postData['translated_fields'] = $translatedFields;

                $translationResult = $partnerService->handlePartnerUpdateWithTranslations($postData, $partner_details, $this->request->getPost('partner_id'), $defaultLanguage);

                if (!$translationResult['success']) {
                    // If translation saving fails, we should handle this appropriately
                    // For now, we'll log the error but continue with the process
                    log_message('error', 'Failed to save partner translations: ' . implode(', ', $translationResult['errors']));
                }
                $days = [
                    0 => 'monday',
                    1 => 'tuesday',
                    2 => 'wednesday',
                    3 => 'thursday',
                    4 => 'friday',
                    5 => 'saturday',
                    6 => 'sunday'
                ];
                for ($i = 0; $i < count($_POST['start_time']); $i++) {
                    $partner_timing = [];
                    $partner_timing['day'] = $days[$i];
                    if (isset($_POST['start_time'][$i])) {
                        $partner_timing['opening_time'] = $_POST['start_time'][$i];
                    }
                    if (isset($_POST['end_time'][$i])) {
                        $partner_timing['closing_time'] = $_POST['end_time'][$i];
                    }
                    $partner_timing['is_open'] = (isset($_POST[$days[$i]])) ? 1 : 0;
                    $timing_data = fetch_details('partner_timings', ['partner_id' => $this->request->getPost('partner_id'), 'day' => $days[$i]]);
                    if (count($timing_data) > 0) {
                        update_details($partner_timing, ['partner_id' => $this->request->getPost('partner_id'), 'day' => $days[$i]], 'partner_timings');
                    } else {
                        $partner_timing['partner_id'] = $this->request->getPost('partner_id');
                        insert_details($partner_timing, 'partner_timings');
                    }
                }

                $this->saveSeoSettings($this->request->getPost('partner_id'));

                return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], ['redirect_url' => base_url('admin/partners')], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            // Log the error for debugging purposes
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - update_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function cash_collection_deduct()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $partner_id = $this->request->getPost('partner_id');
            $amount = $this->request->getPost('amount');
            $message = $this->request->getPost('message');
            $current_balance = fetch_details('users', ['id' => $partner_id], ['payable_commision', 'email'])[0];
            // Enforce positive amounts along with existing bounds so COD settlements never record zero or negative entries.
            // Also validate that message field is required for cash collection records.
            $this->validation->setRules(
                [
                    'amount' => [
                        "rules" => 'required|numeric|greater_than[0]|less_than_equal_to[' . $current_balance['payable_commision'] . ']',
                        "errors" => [
                            "required" => labels(PLEASE_ENTER_COMMISSION, "Please enter commission"),
                            "numeric" => labels(PLEASE_ENTER_A_NUMERIC_VALUE_FOR_COMMISSION, "Please enter numeric value for commission"),
                            "greater_than" => labels('amount_can_not_be_zero', "Amount must be greater than zero"),
                            "less_than" => labels(AMOUNT_MUST_BE_LESS_THAN_CURRENT_PAYABLE_COMMISSION, "Amount must be less than current payable commision"),
                        ]
                    ],
                    'message' => [
                        "rules" => 'required|min_length[1]',
                        "errors" => [
                            "required" => labels('message_required', "Message is required"),
                            "min_length" => labels('message_required', "Message is required"),
                        ]
                    ],
                ],
            );
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors  = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }
            $cash_collecetion_data = [
                'user_id' => $this->userId,
                'message' => $message,
                'status' => 'admin_cash_recevied',
                'commison' => intval($amount),
                'partner_id' => $partner_id,
                'date' => date("Y-m-d"),
            ];
            insert_details($cash_collecetion_data, 'cash_collection');
            $updated_balance = $current_balance['payable_commision'] - intval($amount);
            $update = update_details(['payable_commision' => $updated_balance], ['id' => $partner_id], 'users');
            add_settlement_cashcollection_history($message, 'cash_collection_by_admin', date('Y-m-d'), date('h:i:s'), $amount, $partner_id, '', '', '', $amount, '');
            if ($update) {
                return successResponse(labels(SUCCESSFULLY_COLLECTED_COMMISSION, "Successfully collected commision"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(UNSUCCESSFUL_WHILE_UPDATING_SETTING_STATUS, "Unsuccessful while Updating settling status"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cash_collection_deduct()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function cash_collection_history()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('cash_collection', 'Cash Collection') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'cash_collection_history');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/admin/template', $this->data);
    }
    public function settle_commission_history()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels(COMMISSION_SETTLEMENT, 'Commision Settlement') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'commision_history');

        // get currency symbole
        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/admin/template', $this->data);
    }
    public function manage_commission_history_list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        print_r(json_encode($this->settle_commission->list(false, $search, $limit, $offset, $sort, $order)));
    }
    public function cash_collection_history_list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        // Decode URL-encoded search term to handle spaces and special characters
        // This ensures "BrightFix%20Services" becomes "BrightFix Services"
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? urldecode($_GET['search']) : '';
        // Trim the decoded search term to remove any extra whitespace
        $search = trim($search);
        print_r(json_encode($this->cash_collection->list(false, $search, $limit, $offset, $sort, $order)));
    }
    public function payment_request_multiple_update()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $db      = \Config\Database::connect();
            $builder = $db->table('payment_request');
            $count = true;
            for ($i = 0; $i < count($_POST['request_ids']); $i++) {
                $payment_request = fetch_details('payment_request', ['id' => $_POST['request_ids'][$i]]);
                foreach ($payment_request as $row) {
                    if (($row['status'] != $_POST['status'])) {
                        if (($row['status'] == "0" && ($_POST['status'] == "1" || $_POST['status'] == "2" || $_POST['status'] == "3"))) {
                            $builder->where('id', $row['id']);
                            $builder->update(['status' => $_POST['status']]);
                            $count = false;
                        } else if (($row['status'] == "1" && $_POST['status'] == "3")) {
                            $builder->where('id', $row['id']);
                            $builder->update(['status' => $_POST['status']]);
                            $count = false;
                        }
                    }
                    if ($count == true) {
                        return ErrorResponse('Cannot Update', true, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        return successResponse(labels(BULK_UPDATE_SUCCESSFULLY, "Bulk update successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request_multiple_update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function payment_request_settement_status()
    {
        try {
            $db     = \Config\Database::connect();
            $builder = $db->table('payment_request');
            $builder->where('id', $_POST['id']);
            $builder->update(['status' => '3']);
            return successResponse(labels(PAYMENT_REQUEST_SETTLED_SUCCESSFULLY, "Payment Request Settled Succssfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - payment_request_settement_status()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function bulk_commission_settelement()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (empty($_POST['request_ids'])) {
                return ErrorResponse(labels('select_provider', "Select Provider"), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $db      = \Config\Database::connect();
            $builder = $db->table('users');
            $count = true;
            for ($i = 0; $i < count($_POST['request_ids']); $i++) {
                $user_details = fetch_details('users', ['id' => $_POST['request_ids'][$i]]);
                if ($user_details[0]['balance'] > 0) {
                    $count = false;
                    $data = [
                        'balance' => 0,
                    ];
                    $builder->where('id', $_POST['request_ids'][$i]);
                    $builder->update($data);
                    $settlement_history = [
                        'provider_id' => $_POST['request_ids'][$i],
                        'message' =>   $this->request->getPost('message'),
                        'amount' =>  $user_details[0]['balance'],
                        'status' => 'credit',
                        'date' => date("Y-m-d H:i:s"),
                    ];
                    insert_details($settlement_history, 'settlement_history');
                }
            }
            if ($count == true) {
                return ErrorResponse('Cannot Update', true, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return successResponse(labels(BULK_UPDATE_SUCCESSFULLY, "Bulk update successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - bulk_commission_settelement()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function bulk_cash_collection()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $db      = \Config\Database::connect();
            $builder = $db->table('users');
            $count = true;
            for ($i = 0; $i < count($_POST['request_ids']); $i++) {
                $user_details = fetch_details('users', ['id' => $_POST['request_ids'][$i]]);
                if ($user_details[0]['payable_commision'] > 0) {
                    $count = false;
                    $builder->where('id', $_POST['request_ids'][$i]);
                    $builder->update(['payable_commision' => 0]);
                    $cash_collecetion_data = [
                        'user_id' => $this->userId,
                        'message' => $this->request->getPost('message'),
                        'status' => 'admin_cash_recevied',
                        'commison' => intval($user_details[0]['payable_commision']),
                        'partner_id' => $_POST['request_ids'][$i],
                        'date' => date("Y-m-d"),
                    ];
                    insert_details($cash_collecetion_data, 'cash_collection');
                }
            }
            if ($count == true) {
                return ErrorResponse(labels(CANNOT_UPDATE, "Cannot Update"), true, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return successResponse(labels(BULK_UPDATE_SUCCESSFULLY, "Bulk update successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Exception $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - bulk_cash_collection()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function provider_details()
    {
        helper('function');
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('provider_details', 'Provider Details') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'provider_details');
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }
    public function general_outlook()
    {
        try {
            $uri = service('uri');
            helper('function');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $language = get_current_language();
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id], 'pd.id', [], [], null, $language)));

            $db = \Config\Database::connect();
            $id =  $uri->getSegments()[3];
            // Count only successful bookings - exclude cancelled orders and failed payments
            // Failed payment bookings should not be counted in provider statistics
            $builder = $db->table('orders o');
            $order_count = $builder->select('count(DISTINCT(o.id)) as total')
                ->where(['o.partner_id' => $id])
                ->where('o.status !=', 'cancelled')
                ->where('(o.payment_status != 2 OR o.payment_status IS NULL)')
                ->get()->getResultArray();
            $total_services = $db->table('services s')->select('count(s.id) as `total`')->where(['user_id' => $id])->get()->getResultArray()[0]['total'];
            $total_balance = unsettled_commision($id);
            $total_promocodes = $db->table('promo_codes p')->select('count(p.id) as `total`')->where(['partner_id' => $id])->get()->getResultArray()[0]['total'];
            $provider_total_earning_chart = provider_total_earning_chart($id);
            $provider_already_withdraw_chart = provider_already_withdraw_chart($id);
            $provider_pending_withdraw_chart = provider_pending_withdraw_chart($id);
            $provider_withdraw_chart = provider_withdraw_chart($id);
            $where['partner_id'] =  $uri->getSegments()[3];
            $db = \Config\Database::connect();
            $id = $partner_id;
            $promo_codes = $db->table('promo_codes')->where(['partner_id' => $id])->where('start_date >', date('Y-m-d'))->orderBy('id', 'DESC')->limit(5, 0)->get()->getResultArray();
            $promocode_dates = array();
            $tempRow = array();
            $promocode_dates = array();
            foreach ($promo_codes as $promo_code) {
                $date = explode('-', $promo_code['start_date']);
                $newDate = $date[1] . '-' . $date[2];
                $newDate = explode(' ', $newDate);
                $newDate = $newDate[0];
                $tempRow['start_date'] = $newDate;
                $tempRow['promo_code'] = $promo_code['promo_code'];
                $tempRow['end_date'] = $promo_code['end_date'];
                $promocode_dates[] = $tempRow;
            }
            $ratings = new Service_ratings_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 0;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $partner_id_for_rating = $uri->getSegments()[3];
            $where_for_rating = ["(s.user_id = {$partner_id_for_rating}) OR (pb.partner_id = {$partner_id_for_rating} AND sr.custom_job_request_id IS NOT NULL)"];
            $data = $ratings->ratings_list(true, $search, $limit, $offset, $sort, $order, $where_for_rating);
            $total_review = $data['total'];
            $total_ratings = $db->table('partner_details p')->select('count(p.ratings) as `total`')->where(['id' => $id])->get()->getResultArray()[0]['total'];
            $already_withdraw = $db->table('payment_request p')->select('sum(p.amount) as total')->where(['user_id' => $id, "status" => 1])->get()->getResultArray()[0]['total'];
            $pending_withdraw = $db->table('payment_request p')->select('sum(p.amount) as total')->where(['user_id' => $id, "status" => 0])->get()->getResultArray()[0]['total'];
            $total_withdraw_request = $db->table('payment_request p')->select('count(p.id) as `total`')->where(['user_id' => $id])->get()->getResultArray()[0]['total'];
            $number_or_ratings = $db->table('partner_details p')->select('count(p.number_of_ratings) as `total`')->where(['id' => $id])->get()->getResultArray()[0]['total'];
            $income = $db->table('orders o')->select('count(o.id) as `total`')->where(['user_id' => $id])->where("created_at >= DATE(now()) - INTERVAL 7 DAY")->get()->getResultArray()[0]['total'];
            $symbol =   get_currency();
            $this->data['total_services'] = $total_services;
            $this->data['total_orders'] = $order_count[0]['total'];
            $this->data['total_balance'] =  number_format($total_balance, 2, ".", "");
            $this->data['total_ratings'] = $total_ratings;
            $this->data['total_review'] = $total_review;
            $this->data['number_of_ratings'] = $number_or_ratings;
            $this->data['currency'] = $symbol;
            $this->data['total_promocodes'] = $total_promocodes;
            $this->data['already_withdraw'] = $already_withdraw;
            $this->data['pending_withdraw'] = $pending_withdraw;
            $this->data['total_withdraw_request'] = $total_withdraw_request;
            $this->data['promocode_dates'] = $promocode_dates;
            $this->data['provider_total_earning_chart'] = $provider_total_earning_chart;
            $this->data['provider_already_withdraw_chart'] = $provider_already_withdraw_chart;
            $this->data['provider_pending_withdraw_chart'] = $provider_pending_withdraw_chart;
            $this->data['provider_withdraw_chart'] = $provider_withdraw_chart;
            $this->data['income'] = number_format($income, 2, ".", "");
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_general_outlook', 'Provider General Outlook') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_general_outlook');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - general_outlook()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_company_information()
    {
        try {
            helper('function');
            $uri = service('uri');
            helper('function');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            // Get current language code to ensure translations use the selected language
            // This ensures the model uses the currently selected language instead of default
            $currentLanguageCode = get_current_language();

            // Pass the current language code to the model so it uses the correct translations
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id], 'pd.id', [], [], null, $currentLanguageCode)));

            // Add verification status variables for conditional display of ID fields
            $settings = get_settings('general_settings', true);
            $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
            $this->data['national_id_verification_status'] = $settings['national_id_verification_status'] ?? 0;
            $this->data['address_id_verification_status'] = $settings['address_id_verification_status'] ?? 0;
            $this->data['passport_required_status'] = $settings['passport_required_status'] ?? 0;
            $this->data['national_id_required_status'] = $settings['national_id_required_status'] ?? 0;
            $this->data['address_id_required_status'] = $settings['address_id_required_status'] ?? 0;

            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_company_information', 'Provider Company Information') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_company_information');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_company_information()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_service_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_service_list', 'Provider Service List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_service_list');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_service_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_order_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $segments = $uri->getSegments();
            $partner_id = end($segments);
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_booking_list', 'Provider Booking List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_order_list');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_order_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_order_details_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $orders_model = new Orders_model();
            $where = ['o.partner_id' => $partner_id];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            return $orders_model->list(false, $search, $limit, $offset, $sort, $order, $where, '', '', '', '', '', '');
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_order_details_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_promocode_details()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
            $partner_data = $this->db->table('users u')
                ->select('u.id,u.username,pd.company_name')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->where('is_approved', '1')
                ->get()->getResultArray();
            $this->data['partner_name'] = $partner_data;
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_promocode_list', 'Provider Promocode List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_promocode_details');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_promocode_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_promocode_details_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $promocode_model = new Promo_code_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pc.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where['pc.partner_id'] = $partner_id;

            // Get current language for translations
            $current_language = get_current_language();

            // Fetch promocodes with translations for current language
            $promo_codes = $promocode_model->list(false, $search, $limit, $offset, $sort, $order, $where, $current_language);

            return $promo_codes;
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_promocode_details_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_review_details()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->isLoggedIn && $this->userIsAdmin) {
                helper('function');
                $uri = service('uri');
                $partner_id = $uri->getSegments()[3];
                $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
                $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
                $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
                $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
                $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
                $this->data['partner'] = (($this->partner->list(false, $search, $limit, $offset, $sort, $order, ["pd.partner_id " => $partner_id])));
                $rate_data = get_ratings($partner_id);
                $db      = \Config\Database::connect();

                $average_rating = $db->table('services_ratings sr')
                    ->select('
                    (SUM(sr.rating) / COUNT(sr.rating)) as average_rating
                ')
                    ->join('services s', 'sr.service_id = s.id', 'left')
                    ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
                    ->join('partner_bids pd', 'pd.custom_job_request_id = cj.id', 'left')
                    ->where("(s.user_id = {$partner_id}) OR (pd.partner_id = {$partner_id})")
                    ->get()->getResultArray();
                $ratingData = array();
                $rows = array();
                $tempRow = array();
                foreach ($average_rating as $row) {
                    $tempRow['average_rating'] = (isset($row['average_rating']) &&  $row['average_rating'] != "") ?  number_format($row['average_rating'], 2) : 0;
                }
                foreach ($rate_data as $row) {
                    $tempRow['total_ratings'] = (isset($row['total_ratings']) && $row['total_ratings'] != "") ? $row['total_ratings'] : 0;
                    $tempRow['rating_5_percentage'] = (isset($row['rating_5']) && $row['rating_5'] != "") ? (($row['rating_5'] * 100) / $row['total_ratings']) : 0;
                    $tempRow['rating_4_percentage'] = (isset($row['rating_4']) && $row['rating_4'] != "") ? (($row['rating_4'] * 100) / $row['total_ratings'])  : 0;
                    $tempRow['rating_3_percentage'] = (isset($row['rating_3']) && $row['rating_3'] != "") ? (($row['rating_3'] * 100) / $row['total_ratings']) : 0;
                    $tempRow['rating_2_percentage'] = (isset($row['rating_2']) && $row['rating_2'] != "") ? (($row['rating_2'] * 100) / $row['total_ratings']) : 0;
                    $tempRow['rating_1_percentage'] = (isset($row['rating_1']) && $row['rating_1'] != "") ? (($row['rating_1'] * 100) / $row['total_ratings']) : 0;
                    $tempRow['rating_5'] = (isset($row['rating_5']) && $row['rating_5'] != "") ? ($row['rating_5']) : 0;
                    $tempRow['rating_4'] = (isset($row['rating_4']) && $row['rating_4'] != "") ?  ($row['rating_4'])  : 0;
                    $tempRow['rating_3'] = (isset($row['rating_3']) && $row['rating_3'] != "") ?  ($row['rating_3']) : 0;
                    $tempRow['rating_2'] = (isset($row['rating_2']) && $row['rating_2'] != "") ?  ($row['rating_2']) : 0;
                    $tempRow['rating_1'] = (isset($row['rating_1']) && $row['rating_1'] != "") ? ($row['rating_1']) : 0;
                    $rows[] = $tempRow;
                }
                $ratingData = $rows;
                $this->data['ratingData'] = $ratingData;
                setPageInfo($this->data, labels('provider_review_list', 'Provider Review List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_review_details');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_review_list', 'Provider Review List') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_review_details');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_review_details()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_review_details_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $ratings_model = new Service_ratings_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'pd.id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where_for_rating = ["(s.user_id = {$partner_id}) OR (pb.partner_id = {$partner_id} AND sr.custom_job_request_id IS NOT NULL)"];
            return json_encode($ratings_model->ratings_list(false, $search, $limit, $offset, $sort, $order, $where_for_rating));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_review_details_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_fetch_sales()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            if (!$this->isLoggedIn) {
                return redirect('admin/login');
            } else {
                $sales[] = array();
                $db = \Config\Database::connect();
                $month_res = $db->table('orders')
                    ->select('SUM(final_total) AS total_sale,DATE_FORMAT(created_at,"%b") AS month_name ')
                    ->where('partner_id', $partner_id)
                    ->where('status', 'completed')
                    ->groupBy('year(CURDATE()),MONTH(created_at)')
                    ->orderBy('year(CURDATE()),MONTH(created_at)')
                    ->get()->getResultArray();
                $month_wise_sales['total_sale'] = array_map('intval', array_column($month_res, 'total_sale'));
                $month_wise_sales['month_name'] = array_column($month_res, 'month_name');
                $sales = $month_wise_sales;
                print_r(json_encode($sales));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_fetch_sales()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_subscription()
    {
        try {
            helper('function');
            $uri = service('uri');
            $db      = \Config\Database::connect();
            $builder = $db->table('partner_subscriptions ps');
            $partner_id = $uri->getSegments()[3];

            // First get the active partner subscription record
            $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $partner_id, 'status' => 'active']);

            // Then fetch the subscription details with translations from the main subscriptions table
            $active_subscription_details = [];
            if (!empty($active_partner_subscription)) {
                $subscriptionModel = new \App\Models\Subscription_model();
                $subscription_with_translations = $subscriptionModel->getWithTranslation(
                    $active_partner_subscription[0]['subscription_id'],
                    get_current_language()
                );

                if ($subscription_with_translations) {
                    // Keep partner subscription as base
                    $active_subscription_details[0] = $active_partner_subscription[0];

                    // Add translations under a separate namespace
                    $active_subscription_details[0]['translations'] = $subscription_with_translations;
                } else {
                    $active_subscription_details[0] = $active_partner_subscription[0];
                }
            }

            $symbol =   get_currency();
            $this->data['currency'] = $symbol;
            $this->data['active_subscription_details'] = $active_subscription_details;
            $this->data['partner_id'] = $partner_id;


            // Fetch available subscriptions with translations for current language
            $subscriptionModel = new \App\Models\Subscription_model();
            $subscription_details = $subscriptionModel->getAllWithTranslations(get_current_language(), ['status' => 1]);
            $this->data['subscription_details'] = $subscription_details;

            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('provider_subscription', 'Provider Subscription') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_subscription');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_subscription()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function assign_subscription_to_partner()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            $errorMessage = labels($result['message'], 'Modification in demo version is not allowed.');
            session()->setFlashdata('error', $errorMessage);
            return redirect()->to('admin/partners/partner_subscription/' . $_POST['partner_id']);
            // return $this->response->setJSON($result);
        }
        try {
            $partner_id = $_POST['partner_id'];
            $subscription_id = $_POST['subscription_id'];
            $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);
            $db      = \Config\Database::connect();
            $is_already_subscribe_builder = $db->table('partner_subscriptions')
                ->where(['partner_id' => $partner_id, 'status' => 'active']);
            $active_subscriptions = $is_already_subscribe_builder->get()->getResult();
            if (!empty($active_subscriptions) && !empty($active_subscriptions[0])) {
                $subscriptionToDelete = $active_subscriptions[0];
                $db->table('partner_subscriptions')
                    ->where('id', $subscriptionToDelete->id)
                    ->delete();
            }
            $price = calculate_subscription_price($subscription_details[0]['id']);
            $purchaseDate = date('Y-m-d');
            $subscriptionDuration = $subscription_details[0]['duration'];
            if ($subscriptionDuration == "unlimited") {
                $subscriptionDuration = 0;
            }
            $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days')); // Add the duration to the purchase date
            $partner_subscriptions = [
                'partner_id' =>  $partner_id,
                'subscription_id' => $subscription_id,
                'is_payment' => "1",
                'status' => "active",
                'purchase_date' => date('Y-m-d'),
                'expiry_date' =>  $expiryDate,
                'name' => $subscription_details[0]['name'],
                'description' => $subscription_details[0]['description'],
                'duration' => $subscription_details[0]['duration'],
                'price' => $subscription_details[0]['price'],
                'discount_price' => $subscription_details[0]['discount_price'],
                'publish' => $subscription_details[0]['publish'],
                'order_type' => $subscription_details[0]['order_type'],
                'max_order_limit' => $subscription_details[0]['max_order_limit'],
                'service_type' => $subscription_details[0]['service_type'],
                'max_service_limit' => $subscription_details[0]['max_service_limit'],
                'tax_type' => $subscription_details[0]['tax_type'],
                'tax_id' => $subscription_details[0]['tax_id'] ?? 0,
                'is_commision' => $subscription_details[0]['is_commision'],
                'commission_threshold' => $subscription_details[0]['commission_threshold'],
                'commission_percentage' => $subscription_details[0]['commission_percentage'],
                'transaction_id' => '0',
                'tax_percentage' => $price[0]['tax_percentage']
            ];
            if ($subscription_details[0]['is_commision'] == "yes") {
                $commission = $subscription_details[0]['commission_percentage'];
            } else {
                $commission = 0;
            }
            update_details(['admin_commission' => $commission], ['partner_id' => $partner_id], 'partner_details');
            $data = insert_details($partner_subscriptions, 'partner_subscriptions');

            // Send notifications when subscription is changed/assigned
            // NotificationService handles FCM, Email, and SMS notifications using templates
            // This unified approach sends notifications to the provider
            try {
                // Prepare context data for notification templates
                // This context will be used to populate template variables like [[subscription_name]], [[subscription_price]], etc.
                $notificationContext = [
                    'provider_id' => $partner_id,
                    'subscription_id' => $subscription_id,
                    'subscription_name' => $subscription_details[0]['name'],
                    'subscription_price' => $subscription_details[0]['discount_price'] > 0 ? $subscription_details[0]['discount_price'] : $subscription_details[0]['price'],
                    'subscription_duration' => $subscription_details[0]['duration'] == 'unlimited' ? 'Unlimited' : $subscription_details[0]['duration'] . ' days',
                    'purchase_date' => $purchaseDate,
                    'expiry_date' => $expiryDate
                ];

                // Queue all notifications (FCM, Email, SMS) to provider
                // NotificationService automatically handles:
                // - Translation of templates based on user language
                // - Variable replacement in templates
                // - Notification settings checking for each channel
                // - Fetching user email/phone/FCM tokens
                // - Unsubscribe status checking for email
                $language = get_current_language_from_request();
                queue_notification_service(
                    eventType: 'subscription_changed',
                    recipients: ['user_id' => $partner_id],
                    context: $notificationContext,
                    options: [
                        'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                        'language' => $language,
                        'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                    ]
                );

                // log_message('info', '[SUBSCRIPTION_CHANGED] Notification queued, job ID: ' . ($result ?: 'N/A'));
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the subscription assignment
                log_message('error', '[SUBSCRIPTION_CHANGED] Notification error trace: ' . $notificationError->getTraceAsString());
            }

            $errorMessage = labels(ASSIGNED_SUBSCRIPTION_SUCCESSFULLY, 'Assigned Subscription successfully');
            session()->setFlashdata('success', $errorMessage);
            return redirect()->to('admin/partners/partner_subscription/' . $partner_id);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - assign_subscription_to_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function assign_subscription_to_partner_from_edit_provider()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            $errorMessage = labels($result['message'], 'Modification in demo version is not allowed.');
            session()->setFlashdata('error', $errorMessage);
            return redirect()->to('admin/partners/partner_subscription/' . $_POST['partner_id']);
        }
        try {
            $partner_id = $_POST['partner_id'];
            $subscription_id = $_POST['subscription_id'];
            $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);
            $db      = \Config\Database::connect();
            $is_already_subscribe_builder = $db->table('partner_subscriptions')
                ->where(['partner_id' => $partner_id, 'status' => 'active']);
            $active_subscriptions = $is_already_subscribe_builder->get()->getResult();
            if (!empty($active_subscriptions) && !empty($active_subscriptions[0])) {
                $subscriptionToDelete = $active_subscriptions[0];
                $db->table('partner_subscriptions')
                    ->where('id', $subscriptionToDelete->id)
                    ->delete();
            }
            $price = calculate_subscription_price($subscription_details[0]['id']);
            $purchaseDate = date('Y-m-d');
            $subscriptionDuration = $subscription_details[0]['duration'];
            if ($subscriptionDuration == "unlimited") {
                $subscriptionDuration = 0;
            }
            $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days')); // Add the duration to the purchase date
            $partner_subscriptions = [
                'partner_id' =>  $partner_id,
                'subscription_id' => $subscription_id,
                'is_payment' => "1",
                'status' => "active",
                'purchase_date' => date('Y-m-d'),
                'expiry_date' =>  $expiryDate,
                'name' => $subscription_details[0]['name'],
                'description' => $subscription_details[0]['description'],
                'duration' => $subscription_details[0]['duration'],
                'price' => $subscription_details[0]['price'],
                'discount_price' => $subscription_details[0]['discount_price'],
                'publish' => $subscription_details[0]['publish'],
                'order_type' => $subscription_details[0]['order_type'],
                'max_order_limit' => $subscription_details[0]['max_order_limit'],
                'service_type' => $subscription_details[0]['service_type'],
                'max_service_limit' => $subscription_details[0]['max_service_limit'],
                'tax_type' => $subscription_details[0]['tax_type'],
                'tax_id' => $subscription_details[0]['tax_id'],
                'is_commision' => $subscription_details[0]['is_commision'],
                'commission_threshold' => $subscription_details[0]['commission_threshold'],
                'commission_percentage' => $subscription_details[0]['commission_percentage'],
                'transaction_id' => '0',
                'tax_percentage' => $price[0]['tax_percentage']
            ];
            if ($subscription_details[0]['is_commision'] == "yes") {
                $commission = $subscription_details[0]['commission_percentage'];
            } else {
                $commission = 0;
            }
            update_details(['admin_commission' => $commission], ['partner_id' => $partner_id], 'partner_details');
            $data = insert_details($partner_subscriptions, 'partner_subscriptions');

            // Send notifications when subscription is changed/assigned
            // NotificationService handles FCM, Email, and SMS notifications using templates
            // This unified approach sends notifications to the provider
            try {
                // Prepare context data for notification templates
                // This context will be used to populate template variables like [[subscription_name]], [[subscription_price]], etc.
                $notificationContext = [
                    'provider_id' => $partner_id,
                    'subscription_id' => $subscription_id,
                    'subscription_name' => $subscription_details[0]['name'],
                    'subscription_price' => $subscription_details[0]['discount_price'] > 0 ? $subscription_details[0]['discount_price'] : $subscription_details[0]['price'],
                    'subscription_duration' => $subscription_details[0]['duration'] == 'unlimited' ? 'Unlimited' : $subscription_details[0]['duration'] . ' days',
                    'purchase_date' => $purchaseDate,
                    'expiry_date' => $expiryDate
                ];

                // Queue all notifications (FCM, Email, SMS) to provider
                // NotificationService automatically handles:
                // - Translation of templates based on user language
                // - Variable replacement in templates
                // - Notification settings checking for each channel
                // - Fetching user email/phone/FCM tokens
                // - Unsubscribe status checking for email
                $language = get_current_language_from_request();
                queue_notification_service(
                    eventType: 'subscription_changed',
                    recipients: ['user_id' => $partner_id],
                    context: $notificationContext,
                    options: [
                        'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                        'language' => $language,
                        'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                    ]
                );

                // log_message('info', '[SUBSCRIPTION_CHANGED] Notification result (edit): ' . json_encode($result));
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the subscription assignment
                log_message('error', '[SUBSCRIPTION_CHANGED] Notification error trace (edit): ' . $notificationError->getTraceAsString());
            }

            $errorMessage = labels(ASSIGNED_SUBSCRIPTION_SUCCESSFULLY, 'Assigned Subscription successfully');
            $response = [
                'error' => true,
                'message' => labels(ASSIGNED_SUBSCRIPTION_SUCCESSFULLY, 'Assigned Subscription successfully'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - assign_subscription_to_partner_from_edit_provider()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function cancel_subscription_plan()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }
        try {
            $partner_id = $_POST['partner_id'];
            $db = \Config\Database::connect();

            $is_already_subscribe_builder = $db->table('partner_subscriptions')
                ->where(['partner_id' => $partner_id, 'status' => 'active']);
            $active_subscriptions = $is_already_subscribe_builder->get()->getResult();

            if (!empty($active_subscriptions) && !empty($active_subscriptions[0])) {
                $subscriptionToDelete = $active_subscriptions[0];

                // Get subscription details before deactivating for notification
                $subscriptionName = $subscriptionToDelete->name ?? 'Subscription';
                $subscriptionId = $subscriptionToDelete->subscription_id ?? null;

                // Send notifications when subscription is removed/cancelled
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // This unified approach sends notifications to the provider
                try {
                    // Prepare context data for notification templates
                    // This context will be used to populate template variables like [[subscription_name]], [[subscription_id]], etc.
                    $notificationContext = [
                        'provider_id' => $partner_id,
                        'subscription_id' => $subscriptionId,
                        'subscription_name' => $subscriptionName
                    ];

                    // Queue all notifications (FCM, Email, SMS) to provider
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    $language = get_current_language_from_request();
                    queue_notification_service(
                        eventType: 'subscription_removed',
                        recipients: ['user_id' => $partner_id],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                            'language' => $language,
                            'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                        ]
                    );

                    // log_message('info', '[SUBSCRIPTION_REMOVED] Notification result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the subscription cancellation
                    log_message('error', '[SUBSCRIPTION_REMOVED] Notification error trace: ' . $notificationError->getTraceAsString());
                }

                $data['status'] = 'deactive';
                $res = update_details($data, ['id' => $subscriptionToDelete->id], 'partner_subscriptions', true);
            }
            $errorMessage = labels(SUBSCRIPTION_CANCELLED_SUCCESSFULLY, 'Subscription Cancelled Successfully');
            session()->setFlashdata('success', $errorMessage);
            return redirect()->to('admin/partners/partner_subscription/' . $partner_id);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cancel_subscription_plan()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function cancel_subscription_plan_from_edit_partner()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }
        try {
            $partner_id = $_POST['partner_id'];
            $db = \Config\Database::connect();
            $partner_exists = $db->table('users')->where('id', $partner_id)->countAllResults();
            if ($partner_exists == 0) {
                throw new \Exception(labels(PARTNER_NOT_FOUND, "Partner not found"));
            }
            $is_already_subscribe_builder = $db->table('partner_subscriptions')
                ->where(['partner_id' => $partner_id, 'status' => 'active']);
            $active_subscriptions = $is_already_subscribe_builder->get()->getResult();

            if (!empty($active_subscriptions) && !empty($active_subscriptions[0])) {
                $subscriptionToDelete = $active_subscriptions[0];

                // Get subscription details before deactivating for notification
                $subscriptionName = $subscriptionToDelete->name ?? 'Subscription';
                $subscriptionId = $subscriptionToDelete->subscription_id ?? null;

                // Send notifications when subscription is removed/cancelled
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // This unified approach sends notifications to the provider
                try {
                    // Prepare context data for notification templates
                    // This context will be used to populate template variables like [[subscription_name]], [[subscription_id]], etc.
                    $notificationContext = [
                        'provider_id' => $partner_id,
                        'subscription_id' => $subscriptionId,
                        'subscription_name' => $subscriptionName
                    ];

                    // Queue all notifications (FCM, Email, SMS) to provider
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    $language = get_current_language_from_request();
                    queue_notification_service(
                        eventType: 'subscription_removed',
                        recipients: ['user_id' => $partner_id],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                            'language' => $language,
                            'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                        ]
                    );

                    // log_message('info', '[SUBSCRIPTION_REMOVED] Notification result (edit): ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the subscription cancellation
                    log_message('error', '[SUBSCRIPTION_REMOVED] Notification error trace (edit): ' . $notificationError->getTraceAsString());
                }

                $data['status'] = 'deactive';
                $res = update_details($data, ['id' => $subscriptionToDelete->id], 'partner_subscriptions', true);
            }

            $response = [
                'error' => true,
                'message' => labels(SUBSCRIPTION_CANCELLED_SUCCESSFULLY, 'Subscription Cancelled Successfully'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - cancel_subscription_plan_from_edit_partner()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function all_subscription_list()
    {
        try {
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('all_subscription', 'All Subscription') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'all_subscription_list');
                $symbol =   get_currency();
                $this->data['currency'] = $symbol;
                $uri = service('uri');
                $partner_id = $uri->getSegments()[3];
                $this->data['partner_id'] = $partner_id;
                $subscription_details = fetch_details('subscriptions', ['status' => 1]);
                $this->data['subscription_details'] = $subscription_details;
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('unauthorised');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - all_subscription_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_settlement_and_cash_collection_history()
    {
        try {
            helper('function');
            $uri = service('uri');
            $db      = \Config\Database::connect();
            $builder = $db->table('settlement_cashcollection_history ps');
            $partner_id = $uri->getSegments()[3];
            $active_subscription_details = fetch_details('settlement_cashcollection_history', ['provider_id' => $partner_id]);
            $symbol =   get_currency();
            $this->data['currency'] = $symbol;
            $this->data['active_subscription_details'] = $active_subscription_details;
            $this->data['partner_id'] = $partner_id;
            $subscription_details = fetch_details('subscriptions', ['status' => 1]);
            $this->data['subscription_details'] = $subscription_details;
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('settlement_and_cash_collection_history', 'Settlement And Cash Collection History') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'partner_settlement_cashcollection_history');
                $partner_data = $this->db->table('users u')
                    ->select('u.id,u.username,pd.company_name')
                    ->join('partner_details pd', 'pd.partner_id = u.id')
                    ->where('u.id',  $partner_id)
                    ->get()->getResultArray();
                $this->data['partner'] = $partner_data;
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_settlement_and_cash_collection_history()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function partner_settlement_and_cash_collection_history_list()
    {
        try {
            helper('function');
            $uri = service('uri');
            $partner_id = $uri->getSegments()[3];
            $Settlement_CashCollection_history_model = new Settlement_CashCollection_history_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = ['sc.provider_id' => $partner_id];
            $data = $Settlement_CashCollection_history_model->list($where, 'no', false, $limit, $offset, $sort, $order, $search);
            return $data;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - partner_settlement_and_cash_collection_history_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function all_settlement_cashcollection_history()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('booking_payment_management', 'Booking Payment Management') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'all_settlement_cashcollection_history');

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }
    public function all_settlement_cashcollection_history_list()
    {
        try {
            $Settlement_CashCollection_history_model = new Settlement_CashCollection_history_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = [];
            $data = $Settlement_CashCollection_history_model->list($where, 'yes', false, $limit, $offset, $sort, $order, $search);
            return $data;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - all_settlement_cashcollection_history_list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function duplicate()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            // Check if user has create permission for duplicating providers
            $permission = is_permitted($this->creator_id, 'create', 'partner');
            if (!$permission) {
                // Redirect with session message instead of returning JSON response
                // This ensures proper UI alert is shown instead of raw JSON
                $session = \Config\Services::session();
                if ($session) {
                    $_SESSION['toastMessage'] = labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to take this action');
                    $_SESSION['toastMessageType'] = 'error';
                    $session->markAsFlashdata('toastMessage');
                    $session->markAsFlashdata('toastMessageType');
                }
                return redirect()->to(base_url('admin/partners'));
            }
            if ($this->isLoggedIn && $this->userIsAdmin) {
                setPageInfo($this->data, labels('duplicate_provider', 'Duplicate Provider') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'duplicate_provider');
                $uri = service('uri');
                $partner_id = $uri->getSegments()[3];
                $partner_details = (fetch_details('partner_details', ['partner_id' => $partner_id]))[0];
                $partner_timings = (fetch_details('partner_timings', ['partner_id' => $partner_id]));
                $this->data['data'] = fetch_details('users', ['id' => $this->userId])[0];

                $partner_image = fetch_details('users', ['id' => $partner_id])[0]['image'];
                $disk = fetch_current_file_manager();
                $partner_details['partner_image'] = get_file_url($disk, $partner_image);
                $partner_details['banner'] = get_file_url($disk, $partner_details['banner'] ?? '');

                $settings = get_settings('general_settings', true);
                if (empty($settings)) {
                    $_SESSION['toastMessage'] = labels(PLEASE_FIRST_ADD_CURRENCY_AND_BASIC_DETAILS_IN_GENERAL_SETTINGS, 'Please first add currency and basic details in general settings');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/general-settings')->withCookies();
                }
                $this->data['currency'] = $settings['currency'];
                $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
                $this->data['national_id_verification_status'] = $settings['national_id_verification_status'] ?? 0;
                $this->data['address_id_verification_status'] = $settings['address_id_verification_status'] ?? 0;
                $this->data['passport_required_status'] = $settings['passport_required_status'] ?? 0;
                $this->data['national_id_required_status'] = $settings['national_id_required_status'] ?? 0;
                $this->data['address_id_required_status'] = $settings['address_id_required_status'] ?? 0;
                $this->data['partner_details'] = $partner_details;
                $this->data['partner_timings'] = $partner_timings;
                $user_details = fetch_details('users', ['id' => $partner_id])[0];
                $this->data['personal_details'] = $user_details;
                $this->data['city_name'] = fetch_details('cities', [], ['id', 'name']);
                $subscription_details = fetch_details('subscriptions', ['status' => 1]);
                $this->data['subscription_details'] = $subscription_details;

                $this->seoModel->setTableContext('providers');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($partner_id, 'full');
                $this->data['partner_seo_settings'] = $seo_settings;

                // fetch languages
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                // Fetch translation data for prefilling form fields when duplicating partner
                // This ensures that all language fields are prefilled with existing translation data
                $this->fetchAndSetTranslationData($partner_id, $languages);

                // Pass custom-field definitions and labels to the view.
                $cfDefs = $this->loadCustomFieldDefinitions($languages);
                $this->data['documents_custom_fields']         = $cfDefs['documents'];
                $this->data['bank_details_custom_fields']      = $cfDefs['bank_details'];
                $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

                // Fetch existing custom field values so the view can show previews (PDF links, images).
                $allCfIds = array_map(static fn ($f) => $f['id'], array_merge($cfDefs['documents'], $cfDefs['bank_details']));
                $customFieldValues = $this->getPartnerCustomFieldValuesById($partner_id, $allCfIds);

                // Resolve file URLs for file-type custom fields.
                foreach (array_merge($cfDefs['documents'], $cfDefs['bank_details']) as $cfField) {
                    if ($cfField['field_type'] === 'file') {
                        $cfId = $cfField['id'];
                        $rawVal = $customFieldValues[$cfId] ?? '';
                        if ($rawVal !== '') {
                            $customFieldValues[$cfId] = get_file_url($disk, $rawVal, 'public/backend/assets/default.png', 'custom_fields');
                        }
                    }
                }

                $this->data['partner_details'] = $partner_details;
                $this->data['custom_field_values'] = $customFieldValues;

                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partners.php - duplicate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function bulk_import()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        // Bulk import requires at least create permission (for insert) or update permission (for update)
        // Check if user has either create or update permission
        $hasCreate = is_permitted($this->creator_id, 'create', 'partner');
        $hasUpdate = is_permitted($this->creator_id, 'update', 'partner');

        if (!$hasCreate && !$hasUpdate) {
            // Redirect with session message instead of returning JSON response
            // This ensures proper UI alert is shown instead of raw JSON
            $session = \Config\Services::session();
            if ($session) {
                $_SESSION['toastMessage'] = labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to use bulk import');
                $_SESSION['toastMessageType'] = 'error';
                $session->markAsFlashdata('toastMessage');
                $session->markAsFlashdata('toastMessageType');
            }
            return redirect()->to(base_url('admin/partners'));
        }
        setPageInfo($this->data, labels('bulk_provider_update', 'Bulk Provider Update') . ' | ' . labels(ADMIN_PANEL, 'Admin Panel'), 'bulk_add_partners');
        return view('backend/admin/template', $this->data);
    }
    public function downloadSampleForInsert()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            $_SESSION['toastMessage'] = $result['message'];
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/partners/bulk_import')->withCookies();
        }
        try {
            $service = new \App\Services\provider\ProviderBulkImportService();
            $sample  = $service->generateInsertSample();
            $tmpPath = $service->writeSampleExcel($sample['headers'], $sample['rows'], 'insert');

            return $this->response->download($tmpPath, null)
                ->setFileName('providers_sample_without_data.xlsx');
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partner.php - bulk_import_provider_sample_file_download()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function bulk_import_provider_upload()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }

        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            return ErrorResponse(labels('please_upload_a_valid_file', "Please upload a valid file"), true, [], [], 200, csrf_token(), csrf_hash());
        }

        // Determine mode from headers (presence of 'ID' column means update)
        // Copy to temp path with .xlsx extension so Xlsx reader can open it as a zip
        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk_') . '.xlsx';
        copy($file->getTempName(), $tmpPath);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmpPath);
        $headerRow = $spreadsheet->getActiveSheet()
            ->rangeToArray('A1:' . $spreadsheet->getActiveSheet()->getHighestColumn() . '1')[0];
        $headerRow = array_map(fn($h) => strtolower(trim($h ?? '')), $headerRow);
        $spreadsheet->disconnectWorksheets();
        @unlink($tmpPath);

        $mode = in_array('id', $headerRow) ? 'update' : 'insert';

        // Permission check
        $requiredPermission = $mode === 'insert' ? 'create' : 'update';
        if (!is_permitted($this->creator_id, $requiredPermission, 'partner')) {
            return ErrorResponse(
                labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to ' . $requiredPermission . ' providers'),
                true, [], [], 200, csrf_token(), csrf_hash()
            );
        }

        try {
            $service = new \App\Services\provider\ProviderBulkImportService();
            return $service->handle($file, $mode);
        } catch (\Throwable $e) {
            throw $e;
            log_the_responce($e, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partner.php - bulk_import_provider_upload()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function ProviderAddInstructions()
    {
        try {
            $filePath = (FCPATH . '/public/uploads/site/Provider-Add-Instructions.pdf');
            $fileName = 'Provider-Add-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels(CANNOT_DOWNLOAD, 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/partners')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partner.php - bulk_import_provider_sample_instruction_file_download()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function ProviderUpdateInstructions()
    {
        try {
            $filePath = (FCPATH . '/public/uploads/site/Provider-Update-Instructions.pdf');
            $fileName = 'Provider-Update-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels(CANNOT_DOWNLOAD, 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/partners')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partner.php - bulk_import_provider_sample_instruction_file_download()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function downloadSampleForUpdate()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            $_SESSION['toastMessage'] = $result['message'];
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/partners/bulk_import')->withCookies();
        }
        try {
            $service = new \App\Services\provider\ProviderBulkImportService();
            $sample  = $service->generateUpdateSample();

            if (empty($sample['rows'])) {
                http_response_code(400);
                echo json_encode(["message" => labels(DATA_NOT_FOUND, 'Data not found')]);
                return;
            }

            $tmpPath = $service->writeSampleExcel($sample['headers'], $sample['rows'], 'update');

            return $this->response->download($tmpPath, null)
                ->setFileName('providers_sample_with_data.xlsx');
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Partner.php - downloadSampleForUpdate()');
            http_response_code(500);
            echo json_encode(["message" => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong')]);
        }
    }

    // Private Helper functions start

    /**
     * Fetch translation data for partner and organize it for form prefilling
     * 
     * @param int $partnerId Partner ID to fetch translations for
     * @param array $languages Available languages array
     */
    private function fetchAndSetTranslationData(int $partnerId, array $languages): void
    {
        try {
            // Initialize PartnerService to fetch translations
            $partnerService = new \App\Services\PartnerService();

            // Get all translations for this partner
            $translationResult = $partnerService->getPartnerWithTranslations($partnerId);

            if ($translationResult['success']) {
                $translatedData = $translationResult['translated_data'];

                // Organize translation data by language code for easy access in view
                $organizedTranslations = [];

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    // Get translation data for this language
                    $languageTranslations = $translatedData[$languageCode] ?? [];

                    // For default language, use main table data as fallback
                    if ($isDefault) {
                        $organizedTranslations[$languageCode] = [
                            'username' => $this->data['personal_details']['username'] ?? '', // Always from users table for default language
                            'company_name' => $languageTranslations['company_name'] ?? $this->data['partner_details']['company_name'] ?? '',
                            'about' => $languageTranslations['about'] ?? $this->data['partner_details']['about'] ?? '',
                            'long_description' => $languageTranslations['long_description'] ?? $this->data['partner_details']['long_description'] ?? ''
                        ];
                    } else {
                        // For non-default languages, use translation data only
                        $organizedTranslations[$languageCode] = [
                            'username' => $languageTranslations['username'] ?? '',
                            'company_name' => $languageTranslations['company_name'] ?? '',
                            'about' => $languageTranslations['about'] ?? '',
                            'long_description' => $languageTranslations['long_description'] ?? ''
                        ];
                    }
                }

                // Load SEO translations and add to organized translations
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
                $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($partnerId);

                // Always process SEO data for all languages (even if no translations exist)
                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    // Find SEO translation for this language
                    $seoTranslation = null;
                    if (!empty($seoTranslations)) {
                        foreach ($seoTranslations as $translation) {
                            if ($translation['language_code'] === $languageCode) {
                                $seoTranslation = $translation;
                                break;
                            }
                        }
                    }

                    if ($seoTranslation) {
                        // Add SEO translation data to organized translations
                        $organizedTranslations[$languageCode]['seo_title'] = $seoTranslation['seo_title'] ?? '';
                        $organizedTranslations[$languageCode]['seo_description'] = $seoTranslation['seo_description'] ?? '';
                        $organizedTranslations[$languageCode]['seo_keywords'] = $seoTranslation['seo_keywords'] ?? '';
                        $organizedTranslations[$languageCode]['seo_schema_markup'] = $seoTranslation['seo_schema_markup'] ?? '';
                    } else {
                        // If no SEO translation exists, use base table data for default language, empty for others
                        if ($isDefault) {
                            $organizedTranslations[$languageCode]['seo_title'] = $this->data['partner_seo_settings']['title'] ?? '';
                            $organizedTranslations[$languageCode]['seo_description'] = $this->data['partner_seo_settings']['description'] ?? '';
                            $organizedTranslations[$languageCode]['seo_keywords'] = $this->data['partner_seo_settings']['keywords'] ?? '';
                            $organizedTranslations[$languageCode]['seo_schema_markup'] = $this->data['partner_seo_settings']['schema_markup'] ?? '';
                        } else {
                            $organizedTranslations[$languageCode]['seo_title'] = '';
                            $organizedTranslations[$languageCode]['seo_description'] = '';
                            $organizedTranslations[$languageCode]['seo_keywords'] = '';
                            $organizedTranslations[$languageCode]['seo_schema_markup'] = '';
                        }
                    }
                }

                // Set the organized translation data for the view
                $this->data['partner_translations'] = $organizedTranslations;
            } else {
                // If translation fetching fails, set empty translations
                $this->data['partner_translations'] = [];
                log_message('error', 'Failed to fetch partner translations for duplicate: ' . implode(', ', $translationResult['errors']));
            }
        } catch (\Exception $e) {
            // If any exception occurs, set empty translations and log the error
            $this->data['partner_translations'] = [];
            log_message('error', 'Exception while fetching partner translations for duplicate: ' . $e->getMessage());
        }
    }

    /**
     * Get validation rules for partner creation/duplication
     * Handles conditional validation for image uploads based on existing images
     * 
     * @return array Validation rules
     */
    private function getValidationRules(): array
    {
        // Check if existing images are provided (for duplicate provider scenario)
        // This allows users to duplicate providers without re-uploading images
        $existingImage = $this->request->getPost('existing_image');
        $existingBannerImage = $this->request->getPost('existing_banner_image');

        $validationRules = [
            'city' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_ENTER_CITY, 'Please Enter City')],
            ],
            'address' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_ENTER_ADDRESS, 'Address is required')],
            ],
            'partner_latitude' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_CHOOSE_PROVIDER_LOCATION, 'Please choose provider location')],
            ],
            'partner_longitude' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PLEASE_ENTER_VALID_LONGITUDE, 'Longitude is required')],
            ],
            'type' => [
                'rules' => 'required',
                'errors' => ['required' => labels(PLEASE_SELECT_PROVIDERS_TYPE, 'Provider type is required')],
            ],
            'number_of_members' => [
                'rules' => 'required|numeric',
                'errors' => [
                    'required' => labels(NUMBER_OF_MEMBERS_IS_REQUIRED, 'Number of members is required'),
                    'numeric' => labels(NUMBER_OF_MEMBERS_MUST_BE_A_NUMBER, 'Number of members must be a number'),
                ],
            ],
            'visiting_charges' => [
                'rules' => 'required|numeric',
                'errors' => [
                    'required' => labels(VISITING_CHARGES_IS_REQUIRED, 'Visiting charges is required'),
                    'numeric' => labels(VISITING_CHARGES_MUST_BE_A_NUMBER, 'Visiting charges must be a number'),
                ],
            ],
            'advance_booking_days' => [
                'rules' => 'required|numeric|greater_than_equal_to[1]',
                'errors' => [
                    'required' => labels(ADVANCE_BOOKING_DAYS_IS_REQUIRED, 'Advance booking days is required'),
                    'numeric' => labels(ADVANCE_BOOKING_DAYS_MUST_BE_A_NUMBER, 'Advance booking days must be a number'),
                    'greater_than_equal_to' => labels(ADVANCE_BOOKING_DAYS_MUST_BE_AT_LEAST_1, 'Advance booking days must be at least 1'),
                ],
            ],
            'start_time' => [
                'rules' => 'required',
                'errors' => ['required' => labels(START_TIME_IS_REQUIRED, 'Start time is required')],
            ],
            'end_time' => [
                'rules' => 'required',
                'errors' => ['required' => labels(END_TIME_IS_REQUIRED, 'End time is required')],
            ],
            'email' => [
                'rules' => 'required|trim|valid_email',
                'errors' => ['required' => labels(EMAIL_IS_REQUIRED, 'Email is required')],
            ],
            'phone' => [
                'rules' => 'required|numeric',
                'errors' => [
                    'required' => labels(PHONE_NUMBER_IS_REQUIRED, 'Phone number is required'),
                    'numeric' => labels(PHONE_NUMBER_MUST_BE_A_NUMBER, 'Phone number must be a number'),
                ],
            ],
            'provider_slug' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PROVIDER_SLUG_IS_REQUIRED, 'Provider slug is required')],
            ],
            'password' => [
                'rules' => 'required|trim',
                'errors' => ['required' => labels(PASSWORD_IS_REQUIRED, 'Password is required')],
            ],
        ];

        // Only require image upload if no existing image is provided
        if (empty($existingImage)) {
            $validationRules['image'] = [
                'rules' => 'uploaded[image]',
                'errors' => ['uploaded' => labels(PROFILE_PICTURE_IS_REQUIRED, 'Profile picture is required')],
            ];
        }

        // Only require banner image upload if no existing banner image is provided
        if (empty($existingBannerImage)) {
            $validationRules['banner_image'] = [
                'rules' => 'uploaded[banner_image]',
                'errors' => ['uploaded' => labels(BANNER_IMAGE_IS_REQUIRED, 'Banner image is required')],
            ];
        }

        return $validationRules;
    }

    private function validateCoordinates(string $latitude, string $longitude): void
    {
        // Match register method: latitude -90 to 90, longitude -180 to 180, max 7 decimal places
        if (!preg_match('/^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$/', $latitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LATITUDE, 'Please enter valid latitude'));
        }
        if (!preg_match('/^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$/', $longitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LONGITUDE, 'Please enter a valid longitude'));
        }
    }

    private function uploadFile($file, string $path, string $errorMessage, string $folder): array
    {
        // If no file is provided or file is invalid, return empty result for optional fields
        if (!$file || !$file->isValid()) {
            return [
                'url' => '',
                'disk' => '',
            ];
        }

        // Process valid file upload
        $result = upload_file($file, $path, $errorMessage, $folder);
        if ($result['error']) {
            throw new Exception($result['message']);
        }

        return [
            'url' => $result['file_name'],
            'disk' => $result['disk'],
        ];
    }

    private function upsertPartnerCustomFields(int $partnerId, array $valuesById): void
    {
        if ($partnerId <= 0 || empty($valuesById)) {
            return;
        }

        $db = \Config\Database::connect();

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

            if ($rawValue === '' || $rawValue === [] || $rawValue === null) {
                $deletionIds[] = $customFieldId;
                continue;
            }

            $valuesSql[] = '(' . (int) $partnerId . ',' . (int) $customFieldId . ',' . $db->escape((string) $rawValue) . ')';
        }

        if (!empty($deletionIds)) {
            $db->table('partner_custom_fields')
                ->where('partner_id', (int) $partnerId)
                ->whereIn('custom_field_id', array_values(array_unique($deletionIds)))
                ->delete();
        }

        if (empty($valuesSql)) {
            return;
        }

        $sql = 'INSERT INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
            . implode(',', $valuesSql)
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';

        $db->query($sql);
    }

    /**
     * Build the documents/bank_details custom-field definitions and their
     * per-language labels.  Extracted so add_partner(), edit_partner(), and
     * duplicate() can all share the same logic without code duplication.
     *
     * @param  array $languages  Rows from the `languages` table, each having
     *                           at least: id, code, is_default, language.
     * @return array{documents: array, bank_details: array, labels_by_language: array}
     */
    private function loadCustomFieldDefinitions(array $languages): array
    {
        $documentsCustomFields   = [];
        $bankDetailsCustomFields = [];
        $customFieldLabelsByLanguage = [];

        $languageCodes       = [];
        $defaultLanguageCode = '';
        foreach ($languages as $langRow) {
            $code = (string) ($langRow['code'] ?? '');
            if ($code !== '') {
                $languageCodes[] = $code;
            }
            if (!empty($langRow['is_default']) && $code !== '') {
                $defaultLanguageCode = $code;
            }
        }
        if ($defaultLanguageCode === '') {
            $defaultLanguageCode = (string) get_default_language();
        }
        $languageCodes = array_values(array_unique($languageCodes));

        if ($this->db->tableExists('custom_fields')) {
            $toBool = static function ($v): bool {
                if (is_bool($v)) { return $v; }
                if (is_int($v))  { return $v === 1; }
                $s = strtolower(trim((string) $v));
                return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
            };

            $customFieldRows = $this->db->table('custom_fields')
                ->select(['id', 'field_label', 'field_type', 'field_group', 'file_config', 'required', 'visible', 'sort_order'])
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $customFieldIds = array_values(array_unique(array_map(
                static fn ($r) => (int) ($r['id'] ?? 0),
                array_filter($customFieldRows, static fn ($r) => (int) ($r['id'] ?? 0) > 0)
            )));

            $translationsByFieldId = [];
            if (!empty($customFieldIds) && $this->db->tableExists('translated_custom_fields') && !empty($languageCodes)) {
                $translationRows = $this->db->table('translated_custom_fields tcf')
                    ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                    ->join('languages l', 'l.id = tcf.language_id')
                    ->whereIn('tcf.custom_field_id', $customFieldIds)
                    ->whereIn('l.code', $languageCodes)
                    ->get()
                    ->getResultArray();

                foreach ($translationRows as $tr) {
                    $fieldId  = (int) ($tr['custom_field_id'] ?? 0);
                    $langCode = (string) ($tr['language_code'] ?? '');
                    if ($fieldId <= 0 || $langCode === '') { continue; }
                    $translationsByFieldId[$fieldId][$langCode] = (string) ($tr['field_label'] ?? '');
                }
            }

            foreach ($customFieldRows as $field) {
                $fieldId       = (int) ($field['id'] ?? 0);
                $fieldLabelBase = (string) ($field['field_label'] ?? '');
                $fieldType     = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                $fieldGroup    = strtolower(trim((string) ($field['field_group'] ?? '')));

                if (!in_array($fieldGroup, ['documents', 'bank_details'], true)) { continue; }

                $required  = $toBool($field['required'] ?? 0);
                $visible   = $toBool($field['visible'] ?? 0);
                $sortOrder = (int) ($field['sort_order'] ?? 0);

                if (!$visible)               { continue; }
                if ($fieldId <= 0) { continue; }

                $customFieldLabelsByLanguage[$fieldId] = [];
                foreach ($languageCodes as $langCode) {
                    $label = $translationsByFieldId[$fieldId][$langCode]
                        ?? ($defaultLanguageCode !== '' ? ($translationsByFieldId[$fieldId][$defaultLanguageCode] ?? null) : null)
                        ?? $fieldLabelBase;
                    $customFieldLabelsByLanguage[$fieldId][$langCode] = $label;
                }

                $fileConfigRaw = (string) ($field['file_config'] ?? '');
                $fileConfig    = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];

                $entry = [
                    'id'          => $fieldId,
                    'field_label' => $fieldLabelBase,
                    'field_type'  => $fieldType,
                    'field_group' => $fieldGroup,
                    'file_config' => $fileConfig,
                    'required'    => $required ? 1 : 0,
                    'sort_order'  => $sortOrder,
                ];

                if ($fieldGroup === 'documents') {
                    $documentsCustomFields[] = $entry;
                } else {
                    $bankDetailsCustomFields[] = $entry;
                }
            }

            usort($documentsCustomFields,   static fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
            usort($bankDetailsCustomFields, static fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        }

        return [
            'documents'          => $documentsCustomFields,
            'bank_details'       => $bankDetailsCustomFields,
            'labels_by_language' => $customFieldLabelsByLanguage,
        ];
    }

    private function getPartnerCustomFieldValuesById(int $partnerId, array $fieldIds): array
    {
        if ($partnerId <= 0 || empty($fieldIds)) {
            return [];
        }

        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), fn ($id) => $id > 0));
        if (empty($fieldIds)) {
            return [];
        }

        $db = \Config\Database::connect();

        $valueRows = $db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', (int) $partnerId)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($valueRows as $row) {
            $out[(int) $row['custom_field_id']] = $row['value'] ?? null;
        }
        return $out;
    }

    private function saveUser(array $userData): int
    {
        // Hash the password before inserting
        $ion_auth = new IonAuthModel();
        $userData['password'] = $ion_auth->hashPassword($userData['password']);

        // Insert user into database using Users model
        $insert_id = $this->users->insert($userData);

        // Verify insertion was successful
        if (!$insert_id) {
            log_message('error', "Failed to insert user: " . json_encode($userData) . " Model Error: " . json_encode($this->users->errors()));
            throw new Exception(labels(USER_CREATION_FAILED_PLEASE_TRY_AGAIN, 'User creation failed! Please try again'));
        }

        return $insert_id;
    }

    private function savePartner(int $partnerId, array $partnerData): void
    {
        if (!$this->partner->insert($partnerData)) {
            throw new Exception(labels(PARTNER_CREATION_FAILED_PLEASE_TRY_AGAIN, 'Partner creation failed! Please try again'));
        }
    }

    private function savePartnerTimings(int $partnerId, array $startTimes, array $endTimes, array $postData): void
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $i => $day) {
            $partner_timing = [
                'day' => $day,
                'opening_time' => $startTimes[$i] ?? '',
                'closing_time' => $endTimes[$i] ?? '',
                'is_open' => isset($postData[$day]) ? 1 : 0,
                'partner_id' => $partnerId,
            ];
            insert_details($partner_timing, 'partner_timings');
        }
    }

    private function assignUserGroup(int $partnerId): void
    {
        if (!exists(['user_id' => $partnerId, 'group_id' => 3], 'users_groups')) {
            insert_details(['user_id' => $partnerId, 'group_id' => 3], 'users_groups');
        }
    }

    /**
     * Transform form data to translated_fields structure
     * 
     * @param array $postData POST data from the form
     * @param string $defaultLanguage Default language code
     * @return array Translated fields structure
     */
    private function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage): array
    {
        $translatedFields = [
            'username' => [],
            'company_name' => [],
            'about_provider' => [],
            'long_description' => [],
            'seo_title' => [],
            'seo_description' => [],
            'seo_keywords' => [],
            'seo_schema_markup' => []
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['company_name']) && is_array($postData['company_name'])) {
            // Copy the data directly since it's already in the right structure
            $translatedFields['username'] = $postData['username'] ?? [];
            $translatedFields['company_name'] = $postData['company_name'] ?? [];
            $translatedFields['about_provider'] = $postData['about_provider'] ?? [];
            $translatedFields['long_description'] = $postData['long_description'] ?? [];
            $translatedFields['seo_title'] = $postData['meta_title'] ?? [];
            $translatedFields['seo_description'] = $postData['meta_description'] ?? [];

            // Process keywords data properly - handle array structure with JSON strings
            $metaKeywords = $postData['meta_keywords'] ?? [];
            $processedKeywords = [];
            foreach ($metaKeywords as $langCode => $keywordsData) {
                if (is_array($keywordsData)) {
                    // Handle array format (like from Tagify)
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        // Single JSON string in array format - keep as is for parseKeywords to handle
                        $processedKeywords[$langCode] = $keywordsData[0];
                    } else {
                        // Multiple values, join them
                        $processedKeywords[$langCode] = implode(',', $keywordsData);
                    }
                } else {
                    // Direct string value
                    $processedKeywords[$langCode] = $keywordsData;
                }
            }
            $translatedFields['seo_keywords'] = $processedKeywords;

            $translatedFields['seo_schema_markup'] = $postData['schema_markup'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process username
            $usernameField = 'username[' . $languageCode . ']';
            $usernameValue = $postData[$usernameField] ?? null;
            if (!empty($usernameValue)) {
                $translatedFields['username'][$languageCode] = trim($usernameValue);
            }

            // Process company_name
            $companyNameField = 'company_name[' . $languageCode . ']';
            $companyNameValue = $postData[$companyNameField] ?? null;
            if (!empty($companyNameValue)) {
                $translatedFields['company_name'][$languageCode] = trim($companyNameValue);
            }

            // Process about_provider
            $aboutField = 'about_provider[' . $languageCode . ']';
            $aboutValue = $postData[$aboutField] ?? null;
            if (!empty($aboutValue)) {
                $translatedFields['about_provider'][$languageCode] = trim($aboutValue);
            }

            // Process long_description
            $descriptionField = 'long_description[' . $languageCode . ']';
            $descriptionValue = $postData[$descriptionField] ?? null;
            if (!empty($descriptionValue)) {
                $translatedFields['long_description'][$languageCode] = trim($descriptionValue);
            }

            // Process SEO fields (meta_ prefixed from form)
            $seoTitleField = 'meta_title[' . $languageCode . ']';
            if (array_key_exists($seoTitleField, $postData)) {
                // Record even empty strings so cleared values overwrite previous data
                $seoTitleValue = $postData[$seoTitleField];
                $translatedFields['seo_title'][$languageCode] = trim((string)$seoTitleValue);
            }

            $seoDescriptionField = 'meta_description[' . $languageCode . ']';
            if (array_key_exists($seoDescriptionField, $postData)) {
                // Preserve intent when user submits blank description during edits
                $seoDescriptionValue = $postData[$seoDescriptionField];
                $translatedFields['seo_description'][$languageCode] = trim((string)$seoDescriptionValue);
            }

            $seoKeywordsField = 'meta_keywords[' . $languageCode . ']';
            if (array_key_exists($seoKeywordsField, $postData)) {
                $seoKeywordsValue = $postData[$seoKeywordsField];
                if (is_array($seoKeywordsValue)) {
                    // Handle array format from Tagify while allowing empty arrays to clear data
                    $translatedFields['seo_keywords'][$languageCode] = implode(',', $seoKeywordsValue);
                } else {
                    $translatedFields['seo_keywords'][$languageCode] = trim((string)$seoKeywordsValue);
                }
            }

            $seoSchemaField = 'schema_markup[' . $languageCode . ']';
            if (array_key_exists($seoSchemaField, $postData)) {
                // Ensure blank schema submissions replace previous schema content
                $seoSchemaValue = $postData[$seoSchemaField];
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string)$seoSchemaValue);
            }
        }

        return $translatedFields;
    }

    private function saveSeoSettings(int $partnerId): void
    {
        // Get default language for SEO data
        $defaultLanguage = get_default_language();

        // Get all POST data and transform it to translated fields structure
        $postData = $this->request->getPost();

        // Transform form data to translated fields structure
        $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

        // Extract default language SEO data
        $defaultSeoTitle = '';
        $defaultSeoDescription = '';
        $defaultSeoKeywords = '';
        $defaultSeoSchema = '';

        // Log extraction process for each field
        if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
            $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
            $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
            $keywordsData = $translatedFields['seo_keywords'][$defaultLanguage];

            // Handle different data structures for keywords
            if (is_array($keywordsData)) {
                // If it's an array, it might contain JSON strings or direct values
                if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                    // Single JSON string in array format
                    $defaultSeoKeywords = $keywordsData[0];
                } else {
                    // Multiple values, join them
                    $defaultSeoKeywords = implode(',', $keywordsData);
                }
            } else {
                // Direct string value
                $defaultSeoKeywords = $keywordsData;
            }
        }

        if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
            $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
        }

        // Parse meta keywords (Tagify or comma-separated)
        $keywords = $defaultSeoKeywords ? $this->parseKeywords($defaultSeoKeywords) : '';

        // Build SEO data array
        $seoData = [
            'title'         => $defaultSeoTitle,
            'description'   => $defaultSeoDescription,
            'keywords'      => $keywords,
            'schema_markup' => $defaultSeoSchema,
            'partner_id'    => $partnerId,
        ];

        // Check if any SEO field is filled (excluding partner_id)
        $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $partnerId);

        // Check if all SEO fields are intentionally cleared
        $allFieldsCleared = empty($seoData['title']) &&
            empty($seoData['description']) &&
            empty($seoData['keywords']) &&
            empty($seoData['schema_markup']);

        // Handle SEO image upload
        $seoImage = $this->request->getFile('meta_image');
        $hasImage = $seoImage && $seoImage->isValid();

        // Use Seo_model for provider context
        $this->seoModel->setTableContext('providers');
        $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

        $newSeoData = $seoData;
        if ($hasImage) {
            try {
                $uploadResult = $this->uploadFile(
                    $seoImage,
                    'public/uploads/seo_settings/provider_seo_settings/',
                    labels(FAILED_TO_UPLOAD_SEO_IMAGE, 'Failed to upload SEO image'),
                    'seo_settings'
                );
                $newSeoData['image'] = $uploadResult['url'];
            } catch (\Throwable $t) {
                throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed: ' . $t->getMessage()));
            }
        } else {
            $newSeoData['image'] = $existingSettings['image'] ?? '';
        }

        // If no existing settings, create new if data or image exists
        if (!$existingSettings) {
            if ($hasSeoData || $hasImage) {
                $result = $this->seoModel->createSeoSettings($newSeoData);
                if (!empty($result['error'])) {
                    $errors = $result['validation_errors'] ?? [];
                    throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                }
            }

            // Process SEO translations after creating base SEO settings
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // If existing settings exist and all fields are cleared (and no new image), delete the record
        // BUT: If there's an existing image, we should NOT delete the record even if all other fields are empty
        // This preserves the SEO record structure for future use
        if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
            $result = $this->seoModel->delete($existingSettings['id']);
            if ($result) {
                // Clean up old image if it exists
                if (!empty($existingSettings['image'])) {
                    $disk = fetch_current_file_manager();
                    delete_file_based_on_server('provider_seo_settings', $existingSettings['image'], $disk);
                }
            }
            // Also clean up SEO translations when deleting base SEO settings
            $this->cleanupSeoTranslations($partnerId);
            return;
        }

        // Force clearing removed SEO fields
        $emptyDefaults = [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'schema_markup' => '',
            'image' => $existingSettings['image'] ?? '' // keep old image only if not changed
        ];

        foreach ($emptyDefaults as $key => $defaultVal) {
            if (!array_key_exists($key, $newSeoData) || empty($newSeoData[$key])) {
                $newSeoData[$key] = $defaultVal;
            }
        }

        // Compare existing and new settings
        $settingsChanged = false;
        foreach ($newSeoData as $key => $value) {
            $existingValue = $existingSettings[$key] ?? '';
            $newValue = $value ?? '';
            if ($existingValue !== $newValue) {
                $settingsChanged = true;
                break;
            }
        }
        if (!$settingsChanged) {
            // Even if base SEO settings haven't changed, process translations
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // Update existing settings with new data
        $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
        if (!empty($result['error'])) {
            $errors = $result['validation_errors'] ?? [];
            throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
        }

        // Process SEO translations after updating base SEO settings
        $this->processSeoTranslations($partnerId, $translatedFields);
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

    /**
     * Remove SEO image for a partner
     * This method handles AJAX requests to remove SEO images
     * @return \CodeIgniter\HTTP\Response
     */
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $partnerId = $this->request->getPost('partner_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$partnerId) {
                return ErrorResponse(labels(PARTNER_ID_IS_REQUIRED, "Partner ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Set SEO model context for providers
            $this->seoModel->setTableContext('providers');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

            if (!$existingSettings) {
                return ErrorResponse(labels(SEO_SETTINGS_NOT_FOUND_FOR_THIS_PARTNER, "SEO settings not found for this partner"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if there's an image to remove
            if (empty($existingSettings['image'])) {
                return ErrorResponse(labels(NO_SEO_IMAGE_FOUND_TO_REMOVE, "No SEO image found to remove"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Store the image name for cleanup
            $imageToDelete = $existingSettings['image'];

            // Prepare update data - remove image but keep other fields
            $updateData = [
                'title' => $existingSettings['title'] ?? '',
                'description' => $existingSettings['description'] ?? '',
                'keywords' => $existingSettings['keywords'] ?? '',
                'schema_markup' => $existingSettings['schema_markup'] ?? '',
                'image' => '', // Clear the image field
                'partner_id' => $partnerId
            ];

            // Check if all other SEO fields are empty
            $hasOtherSeoData = !empty($updateData['title']) ||
                !empty($updateData['description']) ||
                !empty($updateData['keywords']) ||
                !empty($updateData['schema_markup']);

            // If all other fields are empty, we should NOT delete the record
            // Instead, we keep the record with empty image but preserve the structure
            // This ensures the SEO record exists for future use
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $updateData);

            if (!empty($result['error'])) {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Clean up the image file from storage
            if (!empty($imageToDelete)) {
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('provider_seo_settings', $imageToDelete, $disk);
            }

            return successResponse(labels(SEO_IMAGE_REMOVED_SUCCESSFULLY, "SEO image removed successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Partners.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something went wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get language-specific headers for translatable fields
     * 
     * Helper method to generate column headers for multi-language support in bulk operations
     * Example: "Company Name (en)", "Company Name (ar)"
     * 
     * @return array Array with language headers and languages data
     */
    private function getLanguageHeaders(): array
    {
        // Fetch all active languages from the database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        // Define translatable fields
        // These fields will have language-specific columns in the Excel file
        $translatableFields = [
            'Username',
            'Company Name',
            'About Provider',
            'Description'
        ];

        // Define SEO translatable fields
        // These fields will have language-specific columns in the Excel file for SEO settings
        $seoTranslatableFields = [
            'SEO Title',
            'SEO Description',
            'SEO Keywords',
            'SEO Schema Markup'
        ];

        $languageHeaders = [];
        $seoLanguageHeaders = [];

        // Generate headers for each translatable field and language
        foreach ($translatableFields as $field) {
            foreach ($languages as $language) {
                // Format: "Company Name (en)", "About Provider (ar)", etc.
                $languageHeaders[] = $field . ' (' . $language['code'] . ')';
            }
        }

        // Generate headers for each SEO translatable field and language
        // These will be added at the end of all columns
        foreach ($seoTranslatableFields as $field) {
            foreach ($languages as $language) {
                // Format: "SEO Title (en)", "SEO Description (ar)", etc.
                $seoLanguageHeaders[] = $field . ' (' . $language['code'] . ')';
            }
        }

        return [
            'headers' => $languageHeaders,
            'seo_headers' => $seoLanguageHeaders,
            'languages' => $languages,
            'translatable_fields' => $translatableFields,
            'seo_translatable_fields' => $seoTranslatableFields
        ];
    }

    /**
     * Fetch visible custom field definitions for bulk import/export.
     * Returns fields from bank_details and documents groups, ordered by group then sort_order.
     */
    private function getCustomFieldsForBulk(): array
    {
        $db = \Config\Database::connect();
        return $db->table('custom_fields')
            ->select(['id', 'field_label', 'field_type', 'field_group'])
            ->whereIn('field_group', ['bank_details', 'documents'])
            ->where('visible', 1)
            ->orderBy('field_group', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get existing translations for a partner
     * 
     * Retrieves all translations from the database for export in bulk update
     * Falls back to main table data if translations are not available
     * 
     * @param int $partnerId Partner ID
     * @param array $languages Available languages
     * @return array Translation data organized by language code
     */
    private function getExistingTranslations(int $partnerId, array $languages): array
    {
        $translations = [];

        try {
            // First, fetch main table data as fallback
            // This data will be used when translations are not available
            $mainTableData = fetch_details('partner_details', ['partner_id' => $partnerId], ['company_name', 'about', 'long_description']);
            $userData = fetch_details('users', ['id' => $partnerId], ['username']);

            // If no partner found, return empty translations
            if (empty($mainTableData)) {
                return $translations;
            }

            // Extract main table values for fallback
            $fallbackData = [
                'username' => !empty($userData) ? ($userData[0]['username'] ?? '') : '',
                'company_name' => $mainTableData[0]['company_name'] ?? '',
                'about' => $mainTableData[0]['about'] ?? '',
                'long_description' => $mainTableData[0]['long_description'] ?? ''
            ];

            // Now try to get translations from the translations table
            $partnerService = new \App\Services\PartnerService();
            $translationResult = $partnerService->getPartnerWithTranslations($partnerId);

            // Organize by language code
            // For each language, use translation if available, otherwise use main table data
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Check if translations exist for this language
                if ($translationResult['success'] && isset($translationResult['translated_data'][$languageCode])) {
                    $translatedData = $translationResult['translated_data'][$languageCode];

                    // Use translation if not empty, otherwise fall back to main table
                    $translations[$languageCode] = [
                        'username' => !empty($translatedData['username'])
                            ? $translatedData['username']
                            : $fallbackData['username'],
                        'company_name' => !empty($translatedData['company_name'])
                            ? $translatedData['company_name']
                            : $fallbackData['company_name'],
                        'about' => !empty($translatedData['about'])
                            ? $translatedData['about']
                            : $fallbackData['about'],
                        'long_description' => !empty($translatedData['long_description'])
                            ? $translatedData['long_description']
                            : $fallbackData['long_description']
                    ];
                } else {
                    // No translations found for this language, use main table data
                    $translations[$languageCode] = $fallbackData;
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to get existing translations: ' . $e->getMessage());
        }

        return $translations;
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

            // Use provided translated fields or get from POST request (fallback)
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');

                // If translated fields are provided as JSON string, decode it
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }
            }

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
                    throw new Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new Exception('Exception in processSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function cleanupSeoTranslations(int $partnerId): void
    {
        try {

            // Load the SEO translation model
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

            // Delete all SEO translations for this partner
            $result = $seoTranslationModel->deletePartnerSeoTranslations($partnerId);
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new Exception('Exception in cleanupSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
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
     * Fetch all visible file-type custom fields (documents + bank_details groups).
     *
     * Each row includes: id, field_type, field_group, required (bool),
     * file_config (decoded array).
     */
    private function getVisibleFileCustomFields(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $rows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group', 'required', 'file_config'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->where('field_type', 'file')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $fileConfigRaw = (string) ($row['file_config'] ?? '');
            $row['file_config'] = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];
            $row['required'] = (int) ($row['required'] ?? 0) === 1;
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Convert file extensions (e.g. ['.jpg', '.pdf']) to MIME types for CI4 validation.
     */
    private function extensionsToMimeTypes(array $extensions): array
    {
        $map = [
            '.jpg'  => 'image/jpeg',
            '.jpeg' => 'image/jpeg',
            '.png'  => 'image/png',
            '.gif'  => 'image/gif',
            '.webp' => 'image/webp',
            '.bmp'  => 'image/bmp',
            '.tif'  => 'image/tiff',
            '.tiff' => 'image/tiff',
            '.svg'  => 'image/svg+xml',
            '.pdf'  => 'application/pdf',
        ];

        $mimes = [];
        foreach ($extensions as $ext) {
            $ext = strtolower(trim((string) $ext));
            if (isset($map[$ext])) {
                $mimes[] = $map[$ext];
            }
        }
        return array_values(array_unique($mimes));
    }

    private function collectCustomFieldValuesFromPost(array $uploadedFileValues = []): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $customFieldRows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->get()
            ->getResultArray();

        $valuesById = [];
        foreach ($customFieldRows as $fieldRow) {
            $cfId = (int) ($fieldRow['id'] ?? 0);
            $fieldType = strtolower(trim((string) ($fieldRow['field_type'] ?? 'text')));

            if ($cfId <= 0) {
                continue;
            }

            $inputName = 'cf_' . $cfId;

            if ($fieldType === 'file') {
                if (array_key_exists($cfId, $uploadedFileValues)) {
                    $valuesById[$cfId] = $uploadedFileValues[$cfId];
                }
                continue;
            }

            $valuesById[$cfId] = $this->request->getPost($inputName) ?? '';
        }

        return $valuesById;
    }
}
