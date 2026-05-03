<?php

namespace App\Models;

use CodeIgniter\Model;

class Partners_model extends Model
{
    protected $table = 'partner_details';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'partner_id',
        'company_name',
        'about',
        'national_id',
        'address',
        'passport',
        'address_id',
        'banner',
        'tax_name',
        'tax_number',
        'bank_name',
        'account_number',
        'account_name',
        'bank_code',
        'swift_code',
        'advance_booking_days',
        'type',
        'number_of_members',
        'admin_commission',
        'visiting_charges',
        'is_approved',
        'service_range',
        'ratings',
        'number_of_ratings',
        'payable_commision',
        'other_images',
        'long_description',
        'at_store',
        'at_doorstep',
        'chat',
        'need_approval_for_the_service',
        'pre_chat',
        'custom_job_categories',
        'slug',
    ];

    private function applyTranslations($partnerData, $translations, $requestedLang, $defaultLang)
    {
        $partnerId = $partnerData['partner_id'];

        $originalCompanyName = $partnerData['company_name'] ?? '';
        $originalPartnerName = $partnerData['partner_name'] ?? '';
        $originalAbout = $partnerData['about'] ?? '';
        $originalLongDescription = $partnerData['long_description'] ?? '';

        $partnerTranslations = $translations[$partnerId] ?? [];

        $requestedTranslation = $this->getBestTranslation($partnerTranslations, $requestedLang, $defaultLang);
        $defaultTranslation   = $this->getBestTranslation($partnerTranslations, $defaultLang, $defaultLang);

        $getBestValue = function ($field, $translation, $mainTableValue, $isUsername = false) {
            $mainValue = $isUsername ? $mainTableValue : ($mainTableValue ?? '');

            if (is_array($translation) && isset($translation[$field]) && !empty($translation[$field])) {
                return trim($translation[$field]);
            }

            return trim($mainValue ?? '');
        };

        $partnerData['company_name'] = $getBestValue('company_name', $defaultTranslation, $originalCompanyName);

        $partnerData['about'] = !empty($defaultTranslation['about'])
            ? $defaultTranslation['about']
            : $originalAbout;

        $partnerData['long_description'] = !empty($defaultTranslation['long_description'])
            ? $defaultTranslation['long_description']
            : $originalLongDescription;

        $partnerData['partner_name'] = $getBestValue('username', $defaultTranslation, $originalPartnerName, true);

        $isValidTranslation = function ($value) {
            return isset($value) && !empty(trim($value));
        };

        if ($isValidTranslation($requestedTranslation['company_name'] ?? null)) {
            $partnerData['translated_company_name'] = trim($requestedTranslation['company_name']);
        } elseif ($isValidTranslation($defaultTranslation['company_name'] ?? null)) {
            $partnerData['translated_company_name'] = trim($defaultTranslation['company_name']);
        } else {
            $partnerData['translated_company_name'] = trim($originalCompanyName);
        }

        if ($isValidTranslation($requestedTranslation['about'] ?? null)) {
            $partnerData['translated_about'] = $requestedTranslation['about'];
        } elseif ($isValidTranslation($defaultTranslation['about'] ?? null)) {
            $partnerData['translated_about'] = $defaultTranslation['about'];
        } else {
            $partnerData['translated_about'] = $originalAbout;
        }

        if ($isValidTranslation($requestedTranslation['long_description'] ?? null)) {
            $partnerData['translated_long_description'] = $requestedTranslation['long_description'];
        } elseif ($isValidTranslation($defaultTranslation['long_description'] ?? null)) {
            $partnerData['translated_long_description'] = $defaultTranslation['long_description'];
        } else {
            $partnerData['translated_long_description'] = $originalLongDescription;
        }

        if ($isValidTranslation($requestedTranslation['username'] ?? null)) {
            $partnerData['translated_partner_name'] = trim($requestedTranslation['username']);
        } elseif ($isValidTranslation($defaultTranslation['username'] ?? null)) {
            $partnerData['translated_partner_name'] = trim($defaultTranslation['username']);
        } else {
            $partnerData['translated_partner_name'] = trim($originalPartnerName);
        }

        return $partnerData;
    }

    private function getBestTranslation($partnerTranslations, $preferredLang, $defaultLang)
    {
        $hasData = function ($translation) {
            return !empty($translation['company_name']) || !empty($translation['username']);
        };

        if (isset($partnerTranslations[$preferredLang])) {
            return $partnerTranslations[$preferredLang];
        }

        if (isset($partnerTranslations[$defaultLang])) {
            return $partnerTranslations[$defaultLang];
        }

        if ($preferredLang === $defaultLang) {
            return [];
        }

        foreach ($partnerTranslations as $langCode => $translation) {
            if ($hasData($translation)) {
                return $translation;
            }
        }

        if (!empty($partnerTranslations)) {
            return reset($partnerTranslations);
        }

        return [];
    }

    private function getRequestedLanguage($languageCode = null, $fromApp = false)
    {
        if ($languageCode) {
            return $languageCode;
        }

        if ($fromApp) {
            $sessionLang = get_current_language();
            if (!empty($sessionLang)) {
                return $sessionLang;
            }
        }

        if (function_exists('get_current_language_from_request')) {
            $headerLang = get_current_language_from_request();
            if ($headerLang) {
                return $headerLang;
            }
        }

        return get_current_language();
    }

    private function isFullUrl($path)
    {
        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    private function cleanPath($path)
    {
        $path = trim((string) $path);
        $path = trim($path, "\"' ");
        return ltrim($path, '/');
    }

    private function resolveDefaultImage()
    {
        return base_url('public/backend/assets/default.png');
    }

    private function resolveProfileImage($fileName)
    {
        if (empty($fileName)) {
            return $this->resolveDefaultImage();
        }

        $fileName = $this->cleanPath($fileName);

        if ($fileName === '' || strtolower($fileName) === 'null') {
            return $this->resolveDefaultImage();
        }

        if ($this->isFullUrl($fileName)) {
            return $fileName;
        }

        if (strpos($fileName, 'public/') === 0) {
            return base_url($fileName);
        }

        if (strpos($fileName, 'backend/assets/') === 0) {
            return base_url('public/' . $fileName);
        }

        if (strpos($fileName, 'uploads/') === 0) {
            return base_url('public/' . $fileName);
        }

        if (strpos($fileName, 'profile/') === 0 || strpos($fileName, 'profiles/') === 0) {
            return base_url('public/backend/assets/' . $fileName);
        }

        return base_url('public/backend/assets/profile/' . $fileName);
    }

    private function resolveBannerImage($fileName)
    {
        if (empty($fileName)) {
            return $this->resolveDefaultImage();
        }

        $fileName = $this->cleanPath($fileName);

        if ($fileName === '' || strtolower($fileName) === 'null') {
            return $this->resolveDefaultImage();
        }

        if ($this->isFullUrl($fileName)) {
            return $fileName;
        }

        if (strpos($fileName, 'public/') === 0) {
            return base_url($fileName);
        }

        if (strpos($fileName, 'backend/assets/') === 0) {
            return base_url('public/' . $fileName);
        }

        if (strpos($fileName, 'uploads/') === 0) {
            return base_url('public/' . $fileName);
        }

        if (strpos($fileName, 'banner/') === 0) {
            return base_url('public/backend/assets/' . $fileName);
        }

        return base_url('public/backend/assets/banner/' . $fileName);
    }

    private function resolveUploadPartnerImage($fileName)
    {
        if (empty($fileName)) {
            return '';
        }

        $fileName = $this->cleanPath($fileName);

        if ($fileName === '' || strtolower($fileName) === 'null') {
            return '';
        }

        if ($this->isFullUrl($fileName)) {
            return $fileName;
        }

        if (strpos($fileName, 'public/uploads/partner/') === 0) {
            return base_url($fileName);
        }

        if (strpos($fileName, 'uploads/partner/') === 0) {
            return base_url('public/' . $fileName);
        }

        if (strpos($fileName, 'partner/') === 0) {
            return base_url('public/uploads/' . $fileName);
        }

        if (strpos($fileName, 'public/') === 0) {
            return base_url($fileName);
        }

        return base_url('public/uploads/partner/' . basename($fileName));
    }

    private function resolveOtherImages($otherImagesRaw)
    {
        if (empty($otherImagesRaw)) {
            return [];
        }

        $images = [];

        if (is_array($otherImagesRaw)) {
            $images = $otherImagesRaw;
        } else {
            $raw = trim((string) $otherImagesRaw);

            if ($raw === '' || strtolower($raw) === 'null') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $images = $decoded;
            } else {
                $decoded = json_decode(stripslashes($raw), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $images = $decoded;
                } else {
                    $images = preg_split('/\s*,\s*/', $raw);
                }
            }
        }

        $finalImages = [];

        foreach ($images as $image) {
            $resolved = $this->resolveUploadPartnerImage($image);
            if (!empty($resolved)) {
                $finalImages[] = $resolved;
            }
        }

        return array_values(array_unique($finalImages));
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $column_name = 'pd.id', $whereIn = [], $additional_data = [], $limit_for_subscription = null, $languageCode = null)
    {
        $currentLang = $this->getRequestedLanguage($languageCode, $from_app);
        $defaultLang = get_default_language();

        $db = \Config\Database::connect();
        $builder = $db->table('partner_details pd');
        $values = ['7'];

        if ($search && $search != '') {
            $search = trim($search);
        }

        $builder->select(' COUNT(DISTINCT pd.id) as `total`')
            ->join('users u', 'pd.partner_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
            ->where('ug.group_id', 3)
            ->whereNotIn('pd.is_approved', $values);

        if ($search && $search != '') {
            $escapedSearch = $db->escapeLikeString($search);

            $builder->groupStart();
            $builder->like('pd.id', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);
            $builder->orLike('u.email', $escapedSearch);
            $builder->orLike('u.phone', $escapedSearch);

            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $builder->orWhere($translationSearchCondition, null, false);
            $builder->groupEnd();
        }

        if (!empty($where)) {
            $builder->where($where);
        }

        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $skipDistanceFilter = false;
            if (!empty($where)) {
                $skipDistanceFilter = array_key_exists('pd.partner_id', $where) || array_key_exists('pd.slug', $where);
            }

            if (!$skipDistanceFilter) {
                $parnter_ids = get_near_partners($additional_data['latitude'], $additional_data['longitude'], $additional_data['max_serviceable_distance'], true);
                if (isset($parnter_ids) && !empty($parnter_ids) && !isset($parnter_ids['error'])) {
                    $builder->whereIn('pd.partner_id', $parnter_ids);
                }
            }
        }

        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] != '') {
            $builder->where('pd.is_approved', $_GET['partner_filter']);
        }
        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] == 'individual_partner') {
            $builder->where('pd.type', 0);
        }
        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] == 'orgenization_partner') {
            $builder->where('pd.type', 1);
        }
        if (!empty($whereIn)) {
            $builder->where('ps.status', 'active')->whereIn($column_name, $whereIn);
        }

        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $latitude  = $additional_data['latitude'];
            $longitude = $additional_data['longitude'];
        }

        $partner_count = $builder->get()->getResultArray();
        $total = $partner_count[0]['total'] ?? 0;

        $dataBuilder = $db->table('partner_details pd');

        if (isset($additional_data['latitude']) && !empty($additional_data['latitude']) && !empty($limit_for_subscription) && isset($limit_for_subscription)) {
            if (!empty($where)) {
                $dataBuilder->where($where);
            }

            if ($search && $search != '') {
                $escapedSearch = $db->escapeLikeString($search);
                $dataBuilder->groupStart();
                $dataBuilder->like('pd.id', $escapedSearch);
                $dataBuilder->orLike('pd.company_name', $escapedSearch);
                $dataBuilder->orLike('u.username', $escapedSearch);
                $dataBuilder->orLike('u.email', $escapedSearch);
                $dataBuilder->orLike('u.phone', $escapedSearch);

                $translationSearchCondition = "EXISTS (
                    SELECT 1 FROM translated_partner_details tpd_search 
                    WHERE tpd_search.partner_id = pd.partner_id 
                    AND (
                        tpd_search.company_name LIKE '%{$escapedSearch}%'
                        OR tpd_search.username LIKE '%{$escapedSearch}%'
                    )
                )";
                $dataBuilder->orWhere($translationSearchCondition, null, false);
                $dataBuilder->groupEnd();
            }

            $dataBuilder->select("
                pd.*,
                u.username as partner_name, 
                u.balance, u.image, u.active, u.email, u.phone, u.country_code, u.city, 
                u.longitude, u.latitude, u.payable_commision,
                ug.user_id, ug.group_id,
                ps.id as partner_subscription_id, 
                ps.status as partner_subscription_status, 
                ps.max_order_limit,

                COALESCE(COUNT(DISTINCT CASE WHEN pd.partner_id AND o.status = 'completed' AND (o.payment_status != 2 OR o.payment_status IS NULL) THEN o.id END), 0) as number_of_orders,

                st_distance_sphere(POINT('$longitude','$latitude'), POINT(u.longitude, u.latitude))/1000 as distance,

                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.discount END) as maximum_discount_percentage,
                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.max_discount_amount END) as maximum_discount_up_to,

                CAST((st_distance_sphere(POINT('$longitude','$latitude'), POINT(u.longitude, u.latitude))/1000) < " .
                $additional_data['max_serviceable_distance'] . " AS CHAR) as is_Available_at_location
            ");
            $dataBuilder
                ->join('users u', 'pd.partner_id = u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->join('orders o', 'o.partner_id = pd.partner_id AND o.parent_id IS NULL', 'left')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id')
                ->join('promo_codes pc', 'pc.partner_id = pd.partner_id', 'left')
                ->where('ug.group_id', 3)
                ->where('pd.is_approved', '1')
                ->groupBy(['pd.partner_id', 'pd.id']);

            $skipDistanceFilter = false;
            if (!empty($where)) {
                $skipDistanceFilter = array_key_exists('pd.partner_id', $where) || array_key_exists('pd.slug', $where);
            }
            if (!$skipDistanceFilter) {
                $dataBuilder->having('distance < ' . $additional_data['max_serviceable_distance']);
            }

            $dataBuilder->where('ps.status', 'active')
                ->groupBy(['pd.partner_id', 'pd.id']);
        } else if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $dataBuilder->select("
                pd.*,
                u.username as partner_name,
                u.balance, u.image, u.active, u.email, u.phone, u.country_code, u.city,
                u.longitude, u.latitude, u.payable_commision,
                ug.user_id, ug.group_id,
                ps.id as partner_subscription_id, 
                ps.status as partner_subscription_status, 
                ps.max_order_limit,

                COALESCE(COUNT(DISTINCT CASE WHEN pd.partner_id AND o.status = 'completed' AND (o.payment_status != 2 OR o.payment_status IS NULL) THEN o.id END), 0) as number_of_orders,

                st_distance_sphere(POINT('$longitude','$latitude'), POINT(u.longitude, u.latitude))/1000 as distance,

                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.discount END) as maximum_discount_percentage,
                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.max_discount_amount END) as maximum_discount_up_to,

                CAST((st_distance_sphere(POINT('$longitude','$latitude'), POINT(u.longitude, u.latitude))/1000) < " .
                $additional_data['max_serviceable_distance'] . " AS CHAR) as is_Available_at_location
            ");

            $dataBuilder
                ->join('users u', 'pd.partner_id = u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->join('orders o', 'o.partner_id = pd.partner_id AND o.parent_id IS NULL', 'left')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
                ->join('promo_codes pc', 'pc.partner_id = pd.partner_id', 'left')
                ->where('ug.group_id', 3)
                ->where('pd.is_approved', '1')
                ->groupBy(['pd.partner_id', 'pd.id']);

            $skipDistanceFilter = false;
            if (!empty($where)) {
                $skipDistanceFilter = array_key_exists('pd.partner_id', $where) || array_key_exists('pd.slug', $where);
            }
            if (!$skipDistanceFilter) {
                $dataBuilder->having('distance < ' . $additional_data['max_serviceable_distance']);
            }
        } else {
            $subQueryOrders = "(SELECT o.partner_id, COUNT(o.id) AS number_of_orders
                    FROM orders o
                    WHERE o.status = 'completed' 
                    AND o.parent_id IS NULL
                    AND (o.payment_status != 2 OR o.payment_status IS NULL)
                    GROUP BY o.partner_id)";

            $subQueryDiscounts = "(SELECT pc.partner_id, 
                              MAX(pc.discount) AS maximum_discount_percentage, 
                              MAX(pc.max_discount_amount) AS maximum_discount_up_to
                       FROM promo_codes pc
                       GROUP BY pc.partner_id)";

            $dataBuilder->select("
                pd.*,
                u.username as partner_name,
                u.balance, u.image, u.active, u.email, u.phone, u.country_code, 
                u.city, u.longitude, u.latitude, u.payable_commision,
                ug.user_id, ug.group_id,
                ps.id as partner_subscription_id, 
                ps.status as partner_subscription_status,

                pt.day, pt.opening_time, pt.closing_time, pt.is_open,

                COALESCE(OrdersSummary.number_of_orders, 0) AS number_of_orders,
                COALESCE(DiscountSummary.maximum_discount_percentage, 0) AS maximum_discount_percentage,
                COALESCE(DiscountSummary.maximum_discount_up_to, 0) AS maximum_discount_up_to,
                '0' as is_Available_at_location
            ");

            $dataBuilder
                ->join('users u', 'pd.partner_id = u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->join("($subQueryOrders) AS OrdersSummary", 'OrdersSummary.partner_id = pd.partner_id', 'left')
                ->join("($subQueryDiscounts) AS DiscountSummary", 'DiscountSummary.partner_id = pd.partner_id', 'left')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
                ->join('partner_timings pt', 'pt.partner_id = pd.partner_id', 'left')
                ->where('ug.group_id', 3)
                ->whereNotIn('pd.is_approved', $values)
                ->groupBy(['pd.partner_id', 'pd.id']);
        }

        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] != '') {
            $dataBuilder->where('pd.is_approved', $_GET['partner_filter']);
        }
        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] == 'individual_partner') {
            $dataBuilder->where('pd.type', 0);
        }
        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] == 'orgenization_partner') {
            $dataBuilder->where('pd.type', 1);
        }

        if ($search && $search != '') {
            $escapedSearch = $db->escapeLikeString($search);
            $dataBuilder->groupStart();

            $dataBuilder->like('pd.id', $escapedSearch);
            $dataBuilder->orLike('pd.company_name', $escapedSearch);
            $dataBuilder->orLike('u.username', $escapedSearch);
            $dataBuilder->orLike('u.email', $escapedSearch);
            $dataBuilder->orLike('u.phone', $escapedSearch);

            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $dataBuilder->orWhere($translationSearchCondition, null, false);
            $dataBuilder->groupEnd();
        }

        if (!empty($whereIn)) {
            $dataBuilder->where('ps.status', 'active')->whereIn($column_name, $whereIn);
        }
        if (!empty($where)) {
            $dataBuilder->where($where);
        }

        if ($sort == 'number_of_orders') {
            $dataBuilder->orderBy($sort, $order)->orderBy('pd.partner_id', 'ASC');
        } else {
            $dataBuilder->orderBy($sort, $order);
        }

        $queryResult = $dataBuilder->limit($limit, $offset)->get();

        if ($queryResult === false) {
            $partner_record = [];
        } else {
            $partner_record = $queryResult->getResultArray();
        }

        $allTranslations = [];
        if (!empty($partner_record)) {
            $partnerIds = array_column($partner_record, 'partner_id');
            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = [];
        $bulkData['total'] = $total;

        if ($from_app == false) {
            $db2 = \Config\Database::connect();
            $builder2 = $db2->table('users u');
            $builder2->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 1)
                ->where(['phone' => $_SESSION['identity']]);
            $user1 = $builder2->get()->getResultArray();
            $permissions = get_permission($user1[0]['id']);
        }

        $rows = [];
        foreach ($partner_record as $row) {
            $tempRow = [];
            $row = $this->applyTranslations($row, $allTranslations, $currentLang, $defaultLang);

            $imageSrc = $this->resolveProfileImage($row['image'] ?? '');

            $sessionEmail = $_SESSION['email'] ?? '';
            $isMasked = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $sessionEmail != 'superadmin@gmail.com';

            $email = $row['email'] ?? '';
            $phone = $row['phone'] ?? '';
            $countryCode = trim($row['country_code'] ?? '');

            if ($countryCode !== '' && $phone !== '') {
                $displayPhone = (strpos($countryCode, '+') === 0 ? $countryCode : '+' . $countryCode) . $phone;
            } else {
                $displayPhone = $phone;
            }

            $maskEmail = function ($value) {
                return strlen($value) > 6 ? 'wrteam.' . substr($value, 6) : 'wrteam.***';
            };

            $maskPhone = function ($value) {
                return strlen($value) > 6 ? 'XXXXX' . substr($value, 6) : 'XXXXX';
            };

            $partner_email = $isMasked ? $maskEmail($email) : $email;

            if (!empty($email)) {
                $contact_detail = $partner_email;
                if (!empty($phone)) {
                    $contact_detail = "<span>{$contact_detail}</span>";
                }
            } else {
                $contact_detail = $isMasked ? $maskPhone($displayPhone) : $displayPhone;
            }

            $display_partner_name = $row['translated_partner_name'] ?? $row['partner_name'] ?? '';
            $partner_company_name = $row['translated_company_name'] ?? $row['company_name'] ?? '';
            $partner_mobile = $isMasked ? $maskPhone($displayPhone) : $displayPhone;

            $profile = '
            <div class="o-media o-media--middle">
                <a href="' . $imageSrc . '" data-lightbox="image-1">
                    <img class="o-media__img images_in_card" 
                         src="' . $imageSrc . '" 
                         alt="' . htmlspecialchars($display_partner_name, ENT_QUOTES) . '">
                </a>
                <a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '">
                    <div class="o-media__body">
                        <div class="provider_name_table">' . $display_partner_name . '</div>
                        <div class="provider_email_table">' . $partner_company_name . '</div>
                        <div class="provider_email_table">
                            ' . $contact_detail . ' (' . $partner_mobile . ')
                        </div>
                    </div>
                </a>
            </div>';

            $status = '';
            $status = '<div class="dropdown ">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary btn-sm px-3"><i class="fas fa-ellipsis-v "></i></button>
          </a>
            <div class="dropdown-menu dropdown-scrollbar custom_dropdown" aria-labelledby="dropdownMenuButton">';
            if ($from_app == false) {
                if ($permissions['update']['partner'] == 1) {
                    $status .= '<a class="dropdown-item" href="' . base_url('/admin/partners/edit_partner/' . $row['partner_id']) . '"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit_provider', 'Edit Provider') . '</a>';
                }
                if ($permissions['delete']['partner'] == 1) {
                    $status .= '<a class="dropdown-item delete_partner" href="#" id="delete_partner"> <i class="fa fa-trash mr-1 text-danger"></i>' . labels('delete_provider', 'Delete Provider') . '</a>';
                }
                if ($permissions['read']['partner'] == 1) {
                    $status .= '</i><a class="dropdown-item" href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"> <i class="fa fa-eye mr-1 text-success"></i>' . labels('view_provider', 'View Provider') . '</a>';
                }
            }
            $status .= ($row['is_approved'] == 1) ?
                '<a class="dropdown-item disapprove_partner" href="#" id="disapprove_partner"> <i class="fas fa-times text-danger mr-1"></i>' . labels('disapprove_provider', 'Disapprove Provider') . '</a>' :
                '<a class="dropdown-item approve_partner" href="#" id="approve_partner" ><i class="fas fa-check text-success mr-1"></i>' . labels('approve_provider', 'Approve Provider') . '</a>';
            $status .= '</div></div>';

            if ($from_app) {
                if (isset($additional_data['customer_id']) && !empty($additional_data['customer_id'])) {
                    $is_bookmarked = is_bookmarked($additional_data['customer_id'], $row['partner_id'])[0]['total'] ?? 0;
                    $tempRow['is_bookmarked'] = ($is_bookmarked == 1) ? '1' : '0';
                }

                $tempRow['image'] = $this->resolveProfileImage($row['image'] ?? '');
            }

            $tempRow['address'] = (!empty($row['address']) && isset($row['address'])) ? $row['address'] : '-';

            if (($row['type'] == 0)) {
                $type = ucfirst(labels('individual', 'Individual'));
            } else {
                $type = ucfirst(labels('organization', 'Organization'));
            }

            $label = ($row['is_approved'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('approved', 'Approved') . "</div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3'>" . labels('disapproved', 'Disapproved') . "</div>";

            $rating_data = $db->query("
                SELECT 
                    COUNT(sr.rating) AS number_of_rating,
                    SUM(sr.rating) AS total_rating,
                    (SUM(sr.rating) / COUNT(sr.rating)) AS average_rating
                FROM services_ratings sr
                LEFT JOIN services s ON sr.service_id = s.id
                WHERE s.user_id = {$row['partner_id']}
                   OR (
                       sr.custom_job_request_id IS NOT NULL
                       AND EXISTS (
                           SELECT 1
                           FROM partner_bids pb
                           WHERE pb.custom_job_request_id = sr.custom_job_request_id
                             AND pb.partner_id = {$row['partner_id']}
                       )
                   )
            ")->getResultArray();

            $tempRow['banner_edit']  = $this->resolveBannerImage($row['banner'] ?? '');
            $tempRow['banner_image'] = $this->resolveBannerImage($row['banner'] ?? '');

            $otherImages = $this->resolveOtherImages($row['other_images'] ?? '');

            $cash_collection_button = '<button class="btn btn-success btn-sm edit_cash_collection" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal"><i class="fa fa-pen" aria-hidden="true"></i> </button> ';

            $tempRow['id'] = $row['id'];
            $tempRow['is_Available_at_location'] = isset($row['is_Available_at_location']) ? (string)$row['is_Available_at_location'] : "0";
            $tempRow['partner_id'] = $row['partner_id'];
            $tempRow['city'] = $row['city'];
            $tempRow['partner_profile'] = $profile;
            $tempRow['company_name'] = $row['company_name'];
            $tempRow['balance'] = $row['balance'];
            $tempRow['longitude'] = $row['longitude'];
            $tempRow['latitude'] = $row['latitude'];
            $tempRow['mobile'] = $partner_mobile;
            $tempRow['about'] = $row['about'];
            $tempRow['long_description'] = $row['long_description'];

            $tempRow['translated_company_name'] = $row['translated_company_name'];
            $tempRow['translated_about'] = $row['translated_about'];
            $tempRow['translated_long_description'] = $row['translated_long_description'];
            $tempRow['translated_partner_name'] = $row['translated_partner_name'];
            $tempRow['address'] = (!empty($row['address']) && isset($row['address'])) ? $row['address'] : '-';

            $disk = fetch_current_file_manager();
            $tempRow['national_id'] = get_file_url($disk, $row['national_id'], 'public/backend/assets/default.png', 'national_id');
            $tempRow['address_id'] = get_file_url($disk, $row['address_id'], 'public/backend/assets/default.png', 'address_id');
            $tempRow['passport'] = get_file_url($disk, $row['passport'], 'public/backend/assets/default.png', 'passport');

            $tempRow['partner_name'] = !empty($row['translated_partner_name']) ? $row['translated_partner_name'] : $row['partner_name'];
            $tempRow['tax_name'] = $row['tax_name'];
            $tempRow['tax_number'] = $row['tax_number'];
            $tempRow['bank_name'] = $row['bank_name'];
            $tempRow['account_number'] = $row['account_number'];
            $tempRow['account_name'] = $row['account_name'];
            $tempRow['bank_code'] = $row['bank_code'];
            $tempRow['swift_code'] = $row['swift_code'];
            $tempRow['number_of_members'] = $row['number_of_members'];
            $tempRow['admin_commission'] = $row['admin_commission'];
            $tempRow['type'] = $type;
            $tempRow['email'] = $partner_email;
            $tempRow['advance_booking_days'] = $row['advance_booking_days'];
            $tempRow['number_of_members'] = $row['number_of_members'];
            $tempRow['ratings'] = ($row['ratings'] !== '' && $row['ratings'] !== null) ? sprintf('%0.1f', (float) $row['ratings']) : '0.0';
            $tempRow['number_of_ratings'] = $rating_data[0]['number_of_rating'] ?? 0;
            $tempRow['visiting_charges'] = $row['visiting_charges'];
            $tempRow['contact_detail'] = $contact_detail;
            $tempRow['is_approved_edit'] = $row['is_approved'];
            $tempRow['payable_commision'] = intval($row['payable_commision']);
            $tempRow['cash_collection_button'] = $cash_collection_button;
            $tempRow['checkbox'] = "  <input type='checkbox' class='select-item checkbox' name='select-item'";
            $tempRow['other_images'] = $otherImages;
            $tempRow['at_doorstep'] = isset($row['at_doorstep']) ? $row['at_doorstep'] : "0";
            $tempRow['at_store'] = isset($row['at_store']) ? $row['at_store'] : "0";
            $tempRow['post_booking_chat'] = isset($row['chat']) ? $row['chat'] : "0";
            $tempRow['pre_booking_chat'] = isset($row['pre_chat']) ? $row['pre_chat'] : "0";
            $tempRow['slug'] = $row['slug'];

            if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
                $tempRow['distance'] = $row['distance'];
            }

            $total_services_of_providers = fetch_details('services', [
                'user_id' => $row['partner_id'],
                'at_store' => $row['at_store'],
                'at_doorstep' => $row['at_doorstep'],
                'status' => 1,
                'approved_by_admin' => 1
            ], ['id']);
            $tempRow['total_services'] = count($total_services_of_providers);

            if (check_partner_availibility($row['partner_id'])) {
                $tempRow['is_available_now'] = true;
            } else {
                $tempRow['is_available_now'] = false;
            }

            $tempRow['status'] = $label;

            if (!empty($rating_data)) {
                $avg = (($rating_data[0]['average_rating'] ?? '') != "") ? sprintf('%0.1f', $rating_data[0]['average_rating']) : '0.0';
                if ($from_app == false) {
                    $tempRow['merged_ratings'] = '<i class="fa-solid fa-star text-warning"></i>' . $avg;
                } else {
                    $tempRow['merged_ratings'] = $avg;
                }

                if (($rating_data[0]['number_of_rating'] ?? 0) != 0) {
                    $tempRow['merged_ratings'] .= '(' . $rating_data[0]['number_of_rating'] . ')';
                }
            }

            $rate_data = get_ratings($row['partner_id']);
            $tempRow['1_star'] = $rate_data[0]['rating_1'];
            $tempRow['2_star'] = $rate_data[0]['rating_2'];
            $tempRow['3_star'] = $rate_data[0]['rating_3'];
            $tempRow['4_star'] = $rate_data[0]['rating_4'];
            $tempRow['5_star'] = $rate_data[0]['rating_5'];

            $partner_timings = fetch_details('partner_timings', ['partner_id' => $row['partner_id']]);
            foreach ($partner_timings as $pt) {
                $tempRow[$pt['day'] . '_is_open'] = $pt['is_open'];
                $tempRow[$pt['day'] . '_opening_time'] = $pt['opening_time'];
                $tempRow[$pt['day'] . '_closing_time'] = $pt['closing_time'];
            }

            if ($from_app == false) {
                $tempRow['discount'] = $row['maximum_discount_percentage'];
                $tempRow['discount_up_to'] = $row['maximum_discount_up_to'];
                $tempRow['is_approved'] = $status;
                $tempRow['created_at'] = $row['created_at'];
            } else {
                if (isset($additional_data['customer_id']) && !empty($additional_data['customer_id'])) {
                    $customer_id = $additional_data['customer_id'];
                    $is_favorite = is_favorite($customer_id, $row['partner_id']);
                    $tempRow['is_favorite'] = ($is_favorite) ? '1' : '0';
                }
                $tempRow['discount'] = $row['maximum_discount_percentage'];
                $tempRow['discount_up_to'] = $row['maximum_discount_up_to'];
                $tempRow['number_of_orders'] = $row['number_of_orders'];
                $tempRow['status'] = $row['is_approved'];
                unset($tempRow['partner_profile']);
                unset($tempRow['contact_detail']);
            }

            $rows[] = $tempRow;
        }

        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $bulkData['rows'] = $rows;
        }

        return $bulkData;
    }

    public function unsettled_commission_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $column_name = 'pd.id', $whereIn = [], $additional_data = [], $languageCode = null)
    {
        $currentLang = $this->getRequestedLanguage($languageCode, $from_app);
        $defaultLang = get_default_language();

        if ($search && $search != '') {
            $search = trim($search);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('partner_details pd');
        $values = ['7'];

        $builder->select(' COUNT(pd.id) as `total` ')
            ->join('users u', 'pd.partner_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 3)
            ->whereNotIn('pd.is_approved', $values);

        if ($search && $search != '') {
            $escapedSearch = $db->escapeLikeString($search);

            $builder->groupStart();
            $builder->like('pd.id', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);
            $builder->orLike('u.email', $escapedSearch);
            $builder->orLike('u.phone', $escapedSearch);

            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $builder->orWhere($translationSearchCondition, null, false);
            $builder->groupEnd();
        }

        if (!empty($where)) {
            $builder->where($where);
        }
        if (!empty($whereIn)) {
            $builder->whereIn($column_name, $whereIn);
        }
        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $parnter_ids = get_near_partners($additional_data['latitude'], $additional_data['longitude'], $additional_data['max_serviceable_distance'], true);
            if (isset($parnter_ids) && !empty($parnter_ids) && !isset($parnter_ids['error'])) {
                $builder->whereIn('pd.partner_id', $parnter_ids);
            }
        }

        $partner_count = $builder->get()->getResultArray();
        $total = $partner_count[0]['total'] ?? 0;

        $dataBuilder = $db->table('partner_details pd');

        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $parnter_ids = get_near_partners($additional_data['latitude'], $additional_data['longitude'], $additional_data['city_id'], true);
            if (isset($parnter_ids) && !empty($parnter_ids) && !isset($parnter_ids['error'])) {
                $dataBuilder->whereIn('pd.partner_id', $parnter_ids);
            }
        }

        $dataBuilder->select("
            pd.*,
            u.username as partner_name,
            u.balance,u.image,u.active,u.email,u.phone,u.country_code,
            ug.user_id,ug.group_id
        ")
            ->join('users u', 'pd.partner_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 3);

        if ($search && $search != '') {
            $escapedSearch = $db->escapeLikeString($search);
            $dataBuilder->groupStart();

            $dataBuilder->like('pd.id', $escapedSearch);
            $dataBuilder->orLike('pd.company_name', $escapedSearch);
            $dataBuilder->orLike('u.username', $escapedSearch);
            $dataBuilder->orLike('u.email', $escapedSearch);
            $dataBuilder->orLike('u.phone', $escapedSearch);

            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%'
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $dataBuilder->orWhere($translationSearchCondition, null, false);
            $dataBuilder->groupEnd();
        }

        if (!empty($where)) {
            $dataBuilder->where($where);
        }
        if (!empty($whereIn)) {
            $dataBuilder->whereIn($column_name, $whereIn);
        }

        $dataBuilder->whereNotIn('pd.is_approved', $values);
        $partner_record = $dataBuilder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();

        $allTranslations = [];
        if (!empty($partner_record)) {
            $partnerIds = array_column($partner_record, 'partner_id');
            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];

        foreach ($partner_record as $row) {
            $tempRow = [];
            $row = $this->applyTranslations($row, $allTranslations, $currentLang, $defaultLang);

            $operations = '<button class="btn btn-success btn-sm pay-out" data-toggle="modal" data-target="#exampleModal"> 
            <i class="fa fa-pencil" aria-hidden="true"></i> 
            </button> ';

            $tempRow['partner_id'] = $row['partner_id'];
            $tempRow['balance'] = $row['balance'];
            $tempRow['company_name'] = $row['company_name'];
            $tempRow['operations'] = $operations;
            $tempRow['partner_name'] = $row['partner_name'];

            $imageSrc = $this->resolveProfileImage($row['image'] ?? '');

            $phone = $row['phone'] ?? '';
            $countryCode = trim($row['country_code'] ?? '');
            $displayPhone = ($countryCode !== '' && $phone !== '')
                ? (strpos($countryCode, '+') === 0 ? $countryCode : '+' . $countryCode) . $phone
                : $phone;

            $profile = '<div class="o-media o-media--middle">
                        <a href="' . $imageSrc . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $row['partner_name'] . '">
                        </a>';
            $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                <div class="provider_name_table" >' . $row['translated_partner_name'] . '</div>
                <div class="provider_email_table">' . $row['translated_company_name'] . '</div>
                <div class="provider_email_table">' . $row['email'] . '(' . htmlspecialchars($displayPhone, ENT_QUOTES) . ')</div>
                </div>
                </div></a>';

            $tempRow['translated_company_name'] = $row['translated_company_name'];
            $tempRow['translated_partner_name'] = $profile;

            if ($from_app == false) {
                $tempRow['created_at'] = $row['created_at'];
            } else {
                $tempRow['status'] = $row['is_approved'];
            }

            $rows[] = $tempRow;
        }

        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $bulkData['rows'] = $rows;
        }

        return $bulkData;
    }

    public function review()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $ratings = new Service_ratings_model();
        $data = $ratings->ratings_list(true, $search, $limit, $offset, $sort, $order, ['s.user_id' => $this->user_details['id']]);
        $bulkData = [];
        $rows = [];
        $tempRow = [];
        foreach ($data['data'] as $row) {
            $tempRow['id'] = $row['id'];
            $tempRow['user_name'] = $row['user_name'];
            $tempRow['profile_image'] = (!empty($row['profile_image']) && isset($row['profile_image'])) ? $row['profile_image'] : '';
            $tempRow['service_name'] = $row['service_name'];
            $tempRow['rating'] = $row['rating'];
            $tempRow['comment'] = $row['comment'];
            $tempRow['rated_on'] = $row['rated_on'];
            $tempRow['images'] = $row['images'];
            $rate_data = get_ratings($row['partner_id']);
            $tempRow['1_star'] = $rate_data[0]['rating_1'];
            $tempRow['2_star'] = $rate_data[0]['rating_2'];
            $tempRow['3_star'] = $rate_data[0]['rating_3'];
            $tempRow['4_star'] = $rate_data[0]['rating_4'];
            $tempRow['5_star'] = $rate_data[0]['rating_5'];
            $rows[] = $tempRow;
        }
        return $bulkData;
    }
}