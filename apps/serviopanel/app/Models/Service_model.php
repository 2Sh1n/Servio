<?php

namespace App\Models;

use CodeIgniter\Model;
use IonAuth\Libraries\IonAuth;
use Mpdf\Tag\Em;
use PDO;

class Service_model extends Model
{
    protected IonAuth $ionAuth;

    protected $table = 'services';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'user_id',
        'category_id',
        'tax_id',
        'tax',
        'title',
        'slug',
        'description',
        'tags',
        'image',
        'price',
        'discounted_price',
        'is_cancelable',
        'cancelable_till',
        'tax_type',
        'number_of_members_required',
        'duration',
        'rating',
        'number_of_ratings',
        'on_site_allowed',
        'max_quantity_allowed',
        'is_pay_later_allowed',
        'status',
        'price_with_tax',
        'tax_value',
        'original_price_with_tax',
        'other_images',
        'long_description',
        'files',
        'faqs',
        'at_store',
        'at_doorstep',
        'approved_by_admin',

    ];
    public $admin_id;
    public function __construct()
    {
        parent::__construct();
        $ionAuth = new \App\Libraries\CustomIonAuth();
        $this->admin_id = ($ionAuth->isAdmin()) ? $ionAuth->user()->row()->id : 0;
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $additional_data = [], $column_name = '', $whereIn = [], $for_new_total = null, $at_store = null, $at_doorstep = null)
    {
        $disk = fetch_current_file_manager();
        $db = \Config\Database::connect();

        // Get current language and default language for translation fallback
        // This enables language-specific display of title and tags in the services listing
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Initialize base query builder
        $builder = $db->table('services s');

        // Prepare search term: trim it
        // Simple approach: trim the search string
        // CodeIgniter's like() method will automatically escape it for safe database queries
        $escapedSearch = '';
        if ($search && $search != '') {
            $escapedSearch = trim($search);
        }

        // Build base query conditions for stats query (with translated fields support)
        // Simple search: search across multiple fields using OR logic
        // This must match the main query search conditions to ensure accurate counts
        $queryConditionsStats = function ($builder) use ($escapedSearch, $where, $whereIn, $column_name, $additional_data, $at_doorstep, $at_store) {
            // Apply search if we have a search term
            // Search across multiple fields using OR logic
            // Include both base fields and translated fields to match main query behavior
            if (!empty($escapedSearch)) {
                $builder->groupStart();
                // Search in base service fields
                $builder->like('s.title', $escapedSearch);
                $builder->orLike('s.description', $escapedSearch);
                $builder->orLike('s.status', $escapedSearch);
                $builder->orLike('s.tags', $escapedSearch);
                $builder->orLike('s.id', $escapedSearch);
                $builder->orLike('s.price', $escapedSearch);
                $builder->orLike('s.discounted_price', $escapedSearch);
                $builder->orLike('s.rating', $escapedSearch);
                $builder->orLike('s.number_of_ratings', $escapedSearch);
                $builder->orLike('s.max_quantity_allowed', $escapedSearch);
                // Search in translated fields to match main query behavior
                // This ensures count matches results when searching in translated fields
                $builder->orLike('tsd.title', $escapedSearch);
                $builder->orLike('tsd.tags', $escapedSearch);
                $builder->groupEnd();
            }

            if (!empty($where)) {
                $builder->where($where);
            }

            if (!empty($whereIn)) {
                $builder->whereIn($column_name, $whereIn);
            }

            if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
                $parnter_ids = get_near_partners(
                    $additional_data['latitude'],
                    $additional_data['longitude'],
                    $additional_data['max_serviceable_distance'],
                    true
                );
                if (!empty($parnter_ids) && !isset($parnter_ids['error'])) {
                    $builder->whereIn('s.user_id', $parnter_ids);
                }
            }

            // Add filter conditions
            if (isset($_GET['service_filter_approve'])  && $_GET['service_filter_approve'] != '') {
                $builder->where('s.approved_by_admin', $_GET['service_filter_approve']);
            }
            if (isset($_GET['service_filter']) && $_GET['service_filter'] != "") {
                $builder->where('s.status', $_GET['service_filter']);
            }
            if (isset($_GET['service_custom_provider_filter']) && $_GET['service_custom_provider_filter'] != "") {
                $builder->where('s.user_id', $_GET['service_custom_provider_filter']);
            }
            if (isset($_GET['service_category_custom_filter']) && $_GET['service_category_custom_filter'] != "") {
                $builder->where('s.category_id', $_GET['service_category_custom_filter']);
            }
            if ($at_store != "") {
                $builder->where('s.at_store', $at_store);
            }

            if ($at_doorstep !== null && $at_doorstep !== '') {
                $builder->where('s.at_doorstep', $at_doorstep);
            }
        };

        // Build query conditions for main query (with translated fields support)
        // Simple search: search across base and translated fields using OR logic
        $queryConditionsMain = function ($builder) use ($escapedSearch, $currentLang, $where, $whereIn, $column_name, $additional_data, $at_doorstep, $at_store) {
            // Apply search if we have a search term
            // Search across multiple fields using OR logic
            if (!empty($escapedSearch)) {
                $builder->groupStart();
                // Search in base service fields
                $builder->like('s.title', $escapedSearch);
                $builder->orLike('s.description', $escapedSearch);
                $builder->orLike('s.status', $escapedSearch);
                $builder->orLike('s.tags', $escapedSearch);
                $builder->orLike('s.id', $escapedSearch);
                $builder->orLike('s.price', $escapedSearch);
                $builder->orLike('s.discounted_price', $escapedSearch);
                $builder->orLike('s.rating', $escapedSearch);
                $builder->orLike('s.number_of_ratings', $escapedSearch);
                $builder->orLike('s.max_quantity_allowed', $escapedSearch);
                $builder->orLike('tsd.title', $escapedSearch);
                $builder->orLike('tsd.tags', $escapedSearch);

                $builder->groupEnd();
            }

            if (!empty($where)) {
                $builder->where($where);
            }

            if (!empty($whereIn)) {
                $builder->whereIn($column_name, $whereIn);
            }

            if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
                $parnter_ids = get_near_partners(
                    $additional_data['latitude'],
                    $additional_data['longitude'],
                    $additional_data['max_serviceable_distance'],
                    true
                );
                if (!empty($parnter_ids) && !isset($parnter_ids['error'])) {
                    $builder->whereIn('s.user_id', $parnter_ids);
                }
            }

            // Add filter conditions
            if (isset($_GET['service_filter_approve'])  && $_GET['service_filter_approve'] != '') {
                $builder->where('s.approved_by_admin', $_GET['service_filter_approve']);
            }
            if (isset($_GET['service_filter']) && $_GET['service_filter'] != "") {
                $builder->where('s.status', $_GET['service_filter']);
            }
            if (isset($_GET['service_custom_provider_filter']) && $_GET['service_custom_provider_filter'] != "") {
                $builder->where('s.user_id', $_GET['service_custom_provider_filter']);
            }
            if (isset($_GET['service_category_custom_filter']) && $_GET['service_category_custom_filter'] != "") {
                $builder->where('s.category_id', $_GET['service_category_custom_filter']);
            }
            if ($at_store != "") {
                $builder->where('s.at_store', $at_store);
            }

            if ($at_doorstep !== null && $at_doorstep !== '') {
                $builder->where('s.at_doorstep', $at_doorstep);
            }
        };

        // Get counts and price ranges in single query
        // Join translated_service_details for stats query if we need to search in translated fields
        // This ensures the stats query works correctly when searching in non-English languages
        $stats = $builder->select('
            COUNT(DISTINCT s.id) as total,
            MAX(s.price) as max_price,
            MIN(s.price) as min_price,
            MIN(s.discounted_price) as min_discount_price,
            MAX(s.discounted_price) as max_discount_price
        ')->join('partner_details pd', 'pd.partner_id = s.user_id', 'left');

        // Join translated_service_details for stats when searching
        // This is needed to ensure the count query works correctly with translated field searches
        // Always join when searching (not just for non-English) to match main query behavior
        // Using DISTINCT in COUNT ensures we don't count duplicate services if there are multiple translation records
        if (!empty($escapedSearch)) {
            $stats->join('translated_service_details tsd', 'tsd.service_id = s.id AND tsd.language_code = "' . $currentLang . '"', 'left');
        }

        $queryConditionsStats($stats);
        $statsResult = $stats->get()->getRowArray();

        $translationFields = [
            'title',
            'description',
            'long_description',
            'tags',
            'faqs'
        ];

        $translatedSelect = implode(", ", array_map(function ($field) {
            return "tsd.$field as translated_$field";
        }, $translationFields));
        // print_r('before main query'); die;
        // Update mainQuery to include ratings data and translated fields
        // Join translated_category_details to get translated category name for current language

        // Calculate is_Available_at_location when latitude/longitude are provided
        // This follows the same pattern as Partners_model to check if service is available at user's location
        // Always returns as string ("0" or "1") for consistency
        $isAvailableAtLocationSelect = '';
        if (
            isset($additional_data['latitude']) && !empty($additional_data['latitude']) &&
            isset($additional_data['longitude']) && !empty($additional_data['longitude']) &&
            isset($additional_data['max_serviceable_distance']) && !empty($additional_data['max_serviceable_distance'])
        ) {
            $latitude = $additional_data['latitude'];
            $longitude = $additional_data['longitude'];
            $maxDistance = $additional_data['max_serviceable_distance'];
            // Calculate if service is available at location based on distance
            // Returns "1" (as string) if within serviceable distance, "0" (as string) otherwise
            // CAST to CHAR ensures it's always returned as a string
            $isAvailableAtLocationSelect = ",
            CAST((st_distance_sphere(POINT('$longitude','$latitude'), POINT(p.longitude, p.latitude))/1000) < $maxDistance AS CHAR) as is_Available_at_location";
        } else {
            // When location is not provided, set is_Available_at_location to "0" as string
            $isAvailableAtLocationSelect = ",
            '0' as is_Available_at_location";
        }

        $mainQuery = $builder->select("
            s.*, 
            c.name as category_name,
            tcd.name as translated_category_name,
            p.username as partner_name,
            c.parent_id,
            pd.need_approval_for_the_service,
            pd.slug as provider_slug,
            $translatedSelect,
            tpd.username as translated_partner_name,
            COUNT(DISTINCT sr.id) as total_ratings,
            SUM(CASE WHEN sr.rating = 5 THEN 1 ELSE 0 END) as rating_5,
            SUM(CASE WHEN sr.rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN sr.rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN sr.rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN sr.rating = 1 THEN 1 ELSE 0 END) as rating_1,
            AVG(sr.rating) as average_rating,
            COUNT(DISTINCT os.id) as total_bookings
            $isAvailableAtLocationSelect
        ")
            ->join('users p', 'p.id = s.user_id', 'left')
            ->join('partner_details pd', 'pd.partner_id = s.user_id', 'left')
            ->join('categories c', 'c.id = s.category_id', 'left')
            ->join('translated_category_details tcd', 'tcd.category_id = c.id AND tcd.language_code = "' . $currentLang . '"', 'left')
            ->join('translated_service_details tsd', 'tsd.service_id = s.id AND tsd.language_code = "' . $currentLang . '"', 'left')
            ->join('translated_partner_details tpd', 'tpd.partner_id = s.user_id AND tpd.language_code = "' . $currentLang . '"', 'left')
            ->join('services_ratings sr', 'sr.service_id = s.id', 'left')
            ->join('order_services os', 'os.service_id = s.id', 'left');

        $queryConditionsMain($mainQuery);

        // Apply ordering and limits
        $records = $mainQuery->groupBy('s.id');

        // Map UI sort keys to actual DB columns when they live outside services table.
        // This keeps the orderBy clause valid even when joined-table fields (like category parent_id) are used.
        $sortColumnMap = [
            'parent_id' => 'c.parent_id',
            'category_name' => 'c.name',
            'partner_name' => 'p.username'
        ];

        // Handle sorting dynamically
        if ($sort === 'average_rating') {
            $records->orderBy('average_rating', $order); // Sorting by the calculated average_rating
        } else if ($sort === 'total_bookings') {
            $records->orderBy('total_bookings', $order);
        } else if (isset($sortColumnMap[$sort])) {
            $records->orderBy($sortColumnMap[$sort], $order); // Use mapped column for joined fields
        } else {
            $records->orderBy("s.$sort", $order); // Default sorting for other columns in services table
        }

        $records = $records->limit($limit, $offset)
            ->get()
            ->getResultArray();


        // Process records
        $rows = [];
        foreach ($records as $row) {
            $tempRow = $this->processServiceRecord($row, $disk, $from_app, $additional_data);
            $rows[] = $tempRow;
        }

        // Return formatted response
        if ($from_app) {
            return $this->formatAppResponse($rows, $statsResult, $for_new_total);
        } else {
            return json_encode(['rows' => $rows, 'total' => $statsResult['total']]);
        }
    }
    private function processServiceRecord($row, $disk, $from_app, $additional_data)
    {
        $db = \Config\Database::connect();
        $tempRow = [];
        if ($disk == 'local_server') {
            $localPath = (!empty($row['image']) && isset($row['image'])) ? base_url($row['image']) : "public/backend/assets/default.png";

            $images = $localPath;
        } else if ($disk == "aws_s3") {
            $images = (!empty($row['image']) && isset($row['image'])) ? fetch_cloud_front_url('services', $row['image']) : "public/backend/assets/default.png";
        } else {
            $images = (!empty($row['image']) && isset($row['image'])) ? base_url($row['image']) : "public/backend/assets/default.png";
        }



        // // Process images
        if ($from_app == false) {
            $images = '<div class="o-media o-media--middle">
            <a  href="' .  $images . '" data-lightbox="image-1"><img class="o-media__img images_in_card"  src="' .  $images . '" data-lightbox="image-1" alt="' .     $row['id'] . '"></a>';
        } else {

            if ($disk == "aws_s3") {
                $images = (!empty($row['image']) && isset($row['image'])) ? fetch_cloud_front_url('services', $row['image']) : "public/backend/assets/default.png";
            } else {
                $images = (!empty($row['image']) && isset($row['image'])) ? base_url($row['image']) : "public/backend/assets/default.png";
            }
        }
        if ($from_app == false) {

            if (!empty($row['other_images'])) {
                $row['other_images'] = array_map(function ($data) use ($row, $disk) {
                    if ($disk === "local_server") {
                        return (!empty($data) && isset($data)) ? base_url($data) : "public/backend/assets/default.png";
                    } elseif ($disk === "aws_s3") {
                        return fetch_cloud_front_url('services', $data);
                    }
                }, json_decode($row['other_images'], true));
            } else {
                $row['other_images'] = [];
            }


            if (!empty($row['files'])) {
                $row['files'] = array_map(function ($data) use ($row, $disk) {
                    if ($disk === "local_server") {
                        return (!empty($data) && isset($data)) ? base_url($data) : "public/backend/assets/default.png";
                    } elseif ($disk === "aws_s3") {
                        return fetch_cloud_front_url('services', $data);
                    }
                }, json_decode($row['files'], true));
            } else {
                $row['files'] = [];
            }

            // Use translated FAQs with fallback logic for admin/partner views
            $faqsData = $this->getTranslatedFaqs($row);
            $row['faqs'] = $faqsData;
        } else {
            // Process other images and files
            foreach (['other_images', 'files'] as $field) {
                if (!empty($row[$field])) {
                    $row[$field] = array_map(function ($data) use ($disk) {
                        return ($disk === "local_server") ? base_url($data) : fetch_cloud_front_url('services', $data);
                    }, json_decode($row[$field], true) ?: []);
                } else {
                    $row[$field] = [];
                }
            }
            // Use translated FAQs with fallback logic for app views
            $faqsData = $this->getTranslatedFaqs($row);
            $row['faqs'] = $faqsData;
        }

        // Get comprehensive ratings data using the same method as Partners_model
        // This ensures consistency between get_service and get_ratings APIs
        $rating_data = $this->getServiceRatingData($row['id'], $db);

        // Process ratings with consistent calculation
        $ratings = [
            'average_rating' => isset($rating_data['average_rating']) ?
                number_format($rating_data['average_rating'], 2) : 0,
            'total_ratings' => $rating_data['total_ratings'] ?? "0",
            'rating_5' => $rating_data['rating_5'] ?? 0,
            'rating_4' => $rating_data['rating_4'] ?? 0,
            'rating_3' => $rating_data['rating_3'] ?? 0,
            'rating_2' => $rating_data['rating_2'] ?? 0,
            'rating_1' => $rating_data['rating_1'] ?? 0
        ];


        if ($from_app == false) {
            $db      = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->whereIn('ug.group_id', [1, 3])
                ->where(['phone' => $_SESSION['identity']]);
            $user1 = $builder->get()->getResultArray();
            $permissions = get_permission($user1[0]['id']);
        }
        // Get tax data (title and percentage from main taxes table)
        $tax_data = fetch_details('taxes', ['id' => $row['tax_id']], ['title', 'percentage']);
        $tax_title = !empty($tax_data) ? $tax_data[0]['title'] : "";
        $tax_percentage = !empty($tax_data) ? $tax_data[0]['percentage'] : "";

        // Get translated tax title for current language (for API/panel display)
        $translated_tax_title = "";
        if (!empty($row['tax_id']) && (int) $row['tax_id'] > 0 && function_exists('get_taxes_with_translated_names')) {
            $translatedTaxList = get_taxes_with_translated_names(['id' => $row['tax_id']], ['id', 'title', 'percentage']);
            $translated_tax_title = (!empty($translatedTaxList) && isset($translatedTaxList[0]['title'])) ? $translatedTaxList[0]['title'] : $tax_title;
        } else {
            $translated_tax_title = $tax_title;
        }

        $taxInfo = [
            'tax_title' => $tax_title,
            'tax_percentage' => $tax_percentage,
            'translated_tax_title' => $translated_tax_title,
        ];

        // Calculate tax values
        $taxData = $this->calculateTaxValues($row);

        // Create status badge
        $status_badge = ($row['status'] == 1) ?
            "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('active', 'Active') . "</div>" :
            "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('deactive', 'Deactive') . "</div>";

        // Create cancelable badge for display
        // This is used in the table display
        $cancelable_badge = ''; // Initialize variable
        if ($from_app == false) {
            $is_cancelable = ($row['is_cancelable'] == "1") ?
                "<span class='badge badge-success'>" . labels('yes', 'Yes') . "</span>" :
                "<span class='badge badge-danger'>" . labels('no', 'No') . "</span>";
            // "<span class='badge badge-danger'>" . labels('not_allowed', 'Not Allowed') . "</span>";

            // Create cancelable_badge for column toggle functionality (similar format to status_badge)
            // This is used by the JavaScript column toggle in partner panel
            $cancelable_badge = ($row['is_cancelable'] == "1") ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('yes', 'Yes') . "</div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('no', 'No') . "</div>";
        } else {
            $is_cancelable = ($row['is_cancelable']);
        }


        $operations = "";
        $operations = '<div class="dropdown">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
            </a>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
        if ($from_app == false) {
            if ($this->ionAuth->isAdmin()) {
                if ($permissions['update']['services'] == 1) {
                    $operations .= '<a class="dropdown-item"href="' . base_url('/admin/services/edit_service/' . $row['id']) . '"><i class="fa fa-pen text-primary mr-1"></i>' . labels('edit_service', 'Edit Service') . '</a>';
                }
                if ($permissions['create']['services'] == 1) {
                    // Only show duplicate option when create permission exists.
                    $operations .= '<a class="dropdown-item"href="' . base_url('/admin/services/duplicate/' . $row['id']) . '"><i class="fas fa-copy text-info mr-1"></i>' . labels('duplicate_service', 'Duplicate Service') . '</a>';
                }
                if ($permissions['delete']['services'] == 1) {
                    $operations .= '<a class="dropdown-item delete" data-id="' . $row['id'] . '" ><i class="fa fa-trash text-danger mr-1"></i>' . labels('delete', 'Delete') . '</a>';
                }
                $operations .= '<a class="dropdown-item" href="' . base_url('/admin/services/service_detail/' . $row['id']) . '" ><i class="fa fa-eye text-primary mr-1"></i>' . labels('view_service', 'View Service') . '</a>';
            } else {
                $operations .= '<a class="dropdown-item"href="' . base_url('/partner/services/edit_service/' . $row['id']) . '"><i class="fa fa-pen text-primary mr-1"></i>' . labels('edit_service', 'Edit Service') . '</a>';
                $operations .= '<a class="dropdown-item"href="' . base_url('/partner/services/duplicate/' . $row['id']) . '"><i class="fas fa-copy text-info mr-1"></i>' . labels('duplicate_service', 'Duplicate Service') . '</a>';
            }

            if ($this->ionAuth->isAdmin() && $row['need_approval_for_the_service'] == 1) {
                // Only admins should see approve/disapprove controls; partners cannot self-approve here.
                $operations .= ($row['approved_by_admin'] == 1) ?
                    '<a class="dropdown-item disapprove_service" href="#" id="disapprove_service"><i class="fas fa-times text-danger mr-1"></i>' . labels('disapprove_service', 'Disapprove Service') . '</a>' :
                    '<a class="dropdown-item approve_service" href="#" id="approve_service" ><i class="fas fa-check text-success mr-1"></i>' . labels('approve_service', 'Approve Service') . '</a>';
            }
        }
        if ($this->ionAuth->isAdmin()) {
        } else if (isset($where['user_id']) && !empty($where['user_id'])) {
            $operations .= '<a class="dropdown-item" href="' . base_url('/partner/services/edit_service/' . $row['id']) . '" ><i class="fa fa-pen text-primary mr-1"></i>' . labels('edit_service', 'Edit Service') . '</a>';
        }

        $operations .= '</div></div>';


        $total_orders = fetch_details('order_services', ['service_id' => $row['id']], 'id');

        // Process translated title and tags - use translated versions when available
        // This follows the same pattern as partners listing for consistent language support
        $displayTitle = !empty($row['translated_title']) ? $row['translated_title'] : $row['title'];
        $displayTags = !empty($row['translated_tags']) ? $row['translated_tags'] : $row['tags'];

        $description = !empty($row['translated_description']) ? $row['translated_description'] : $row['description'];

        // Process translated partner name with fallback logic
        // Priority: current language translation → default language translation → main table username
        $translatedPartnerName = $this->getTranslatedPartnerName($row, $from_app);

        // Process translated FAQs with fallback logic
        // Priority: current language -> default language -> main table
        $displayFaqs = $this->getTranslatedFaqs($row);

        // Get translated category name with fallback
        // Priority: translated_category_name from query (current language) -> category_name from main table
        // The query already joins translated_category_details for current language
        $translatedCategoryName = '';
        if (!empty($row['category_id'])) {
            // Use translated category name from query if available
            if (!empty($row['translated_category_name'])) {
                $translatedCategoryName = trim($row['translated_category_name']);
            }

            // Fallback to main table category name if translation not available
            if (empty($translatedCategoryName) && !empty($row['category_name'])) {
                $translatedCategoryName = trim($row['category_name']);
            }

            // If still empty and we have category_id, try to get default language translation
            if (empty($translatedCategoryName) && !empty($row['category_id'])) {
                try {
                    $categoryModel = new \App\Models\Category_model();
                    $defaultLang = get_default_language();
                    $currentLang = get_current_language();

                    // Only fetch default language if current language is different
                    if ($currentLang !== $defaultLang) {
                        $translatedCategoryName = $categoryModel->getTranslatedCategoryName($row['category_id'], $defaultLang);
                    }
                } catch (\Exception $e) {
                    // Log error but continue with empty value
                    log_message('error', 'Error fetching default language category name: ' . $e->getMessage());
                }
            }
        }

        // Create pay later allowed badge for admin panel (similar to status badge)
        $is_pay_later_allowed_badge = '';
        if ($from_app == false) {
            $is_pay_later_allowed_badge = ($row['is_pay_later_allowed'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('yes', 'Yes') . "</div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('no', 'No') . "</div>";
        }

        // Format is_pay_later_allowed for provider panel (show Yes/No instead of 1/0)
        $is_pay_later_allowed_display = '';
        if ($from_app == false) {
            $is_pay_later_allowed_display = ($row['is_pay_later_allowed'] == 1) ? labels('yes', 'Yes') : labels('no', 'No');
        } else {
            $is_pay_later_allowed_display = ($row['is_pay_later_allowed'] == 1) ? '1' : '0';
        }

        // Merge all data
        // Explicitly include all fields needed for filters and column toggles
        $tempRow = array_merge($row, [
            'title' => $displayTitle, // Use translated title if available
            'tags' => $displayTags,   // Use translated tags if available
            'description' => $description,
            'translated_partner_name' => $translatedPartnerName, // Add translated partner name
            'category_name' => $translatedCategoryName, // Add translated category name
            'image_of_the_service' => $images,
            'status' => ($row['status'] == 1) ? 'active' : 'deactive',
            'status_number' => ($row['status'] == 1) ? '1' : '0',
            'is_pay_later_allowed' => $is_pay_later_allowed_display, // Show Yes/No for provider panel, 1/0 for app
            'is_pay_later_allowed_badge' => $is_pay_later_allowed_badge, // Badge for admin panel
            'status_badge' => $status_badge,
            'is_cancelable' => $is_cancelable, // Badge format for table display (span badge)
            'cancelable_badge' => $cancelable_badge, // Badge format for column toggle (div tag)
            'cancelable' => $row['is_cancelable'], // Raw value (1/0) for JavaScript logic
            'cancelable_till' => $row['cancelable_till'] ?? '', // Cancelable till time in minutes
            'number_of_members_required' => $row['number_of_members_required'] ?? '', // Members required to perform task
            'created_at' => $row['created_at'] ?? '', // Created at timestamp
            'updated_at' => $row['updated_at'] ?? '', // Updated at timestamp
            'total_bookings' => count($total_orders),
            'tax_type' => $row['tax_type'],
            'translated_tax_type' => labels($row['tax_type']),
            // Add is_Available_at_location field - indicates if service is available at user's location
            // This follows the same pattern as Partners_model for consistency
            // Ensure is_Available_at_location is always a string ("0" or "1")
            // Default to "0" if not set
            'is_Available_at_location' => isset($row['is_Available_at_location']) ? (string)$row['is_Available_at_location'] : "0",

        ], $ratings, $taxInfo, $taxData);

        if ($from_app) {
            $tempRow['in_cart_quantity'] = isset($additional_data['user_id']) ?
                in_cart_qty($row['id'], $additional_data['user_id']) : "";
        }
        if ($from_app == false) {

            $tempRow['operations'] = $operations;
            $approved_by_admin_badge = ($row['approved_by_admin'] == 1) ?
                "<div class='  text-emerald-success  ml-3 mr-3 mx-5'>" . labels('yes', 'Yes') . "
        </div>" :
                "<div class=' text-emerald-danger ml-3 mr-3 '>" . labels('no', 'No') . "
        </div>";
            $tempRow['approved_by_admin_badge'] = $approved_by_admin_badge;
        }
        return $tempRow;
    }


    private function calculateTaxValues($row)
    {
        $taxPercentageData = fetch_details('taxes', ['id' => $row['tax_id']], ['percentage']);
        $tempRow = [];
        if (!empty($taxPercentageData)) {
            $taxPercentage = $taxPercentageData[0]['percentage'];
        } else {
            $taxPercentage = 0;
        }
        if ($row['discounted_price'] == "0") {
            if ($row['tax_type'] == "excluded") {
                $tempRow['tax_value'] = number_format((intval(($row['price'] * ($taxPercentage) / 100))), 2);
                $tempRow['price_with_tax']  = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
                $tempRow['original_price_with_tax'] = strval($row['price'] + ($row['price'] * ($taxPercentage) / 100));
            } else {
                $tempRow['tax_value'] = "";
                $tempRow['price_with_tax']  = strval($row['price']);
                $tempRow['original_price_with_tax'] = strval($row['price']);
            }
        } else {
            if ($row['tax_type'] == "excluded") {
                $tempRow['tax_value'] = number_format((intval(($row['discounted_price'] * ($taxPercentage) / 100))), 2);
                $tempRow['price_with_tax']  = strval($row['discounted_price'] + ($row['discounted_price'] * ($taxPercentage) / 100));
                $tempRow['original_price_with_tax'] = strval($row['price'] + ($row['discounted_price'] * ($taxPercentage) / 100));
            } else {
                $tempRow['tax_value'] = "";
                $tempRow['price_with_tax']  = strval($row['discounted_price']);
                $tempRow['original_price_with_tax'] = strval($row['price']);
            }
        }

        return $tempRow;
    }
    // Helper method to format app response
    private function formatAppResponse($rows, $stats, $for_new_total)
    {
        $db = \Config\Database::connect();

        if ($for_new_total) {
            $new_stats = $db->table('services s')
                ->select('COUNT(s.id) as total, MAX(s.price) as max_price, MIN(s.price) as min_price,
                         MIN(s.discounted_price) as min_discount_price, MAX(s.discounted_price) as max_discount_price')
                ->where('s.user_id', $for_new_total)
                ->get()
                ->getRowArray();
        }

        return [
            'total' => $stats['total'] ?? count($rows),
            'min_price' => $stats['min_price'],
            'max_price' => $stats['max_price'],
            'min_discount_price' => $stats['min_discount_price'],
            'max_discount_price' => $stats['max_discount_price'],
            'data' => $rows,
            'new_total' => $new_stats['total'] ?? null,
            'new_min_price' => $new_stats['min_price'] ?? null,
            'new_max_price' => $new_stats['max_price'] ?? null,
            'new_min_discount_price' => $new_stats['min_discount_price'] ?? null,
            'new_max_discount_price' => $new_stats['max_discount_price'] ?? null
        ];
    }

    /**
     * Get translated FAQs with fallback logic
     * Priority: current language -> default language -> main table
     * 
     * @param array $row Service row data with translated fields
     * @return array Processed FAQs array
     */
    private function getTranslatedFaqs($row)
    {
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Try current language first
        if (!empty($row['translated_faqs'])) {
            $faqsData = json_decode($row['translated_faqs'], true);
            if (is_array($faqsData) && !empty($faqsData)) {
                return $this->processFaqsData($faqsData);
            }
        }

        // Try default language if current language failed
        if ($currentLang !== $defaultLang) {
            $db = \Config\Database::connect();
            $defaultFaqs = $db->table('translated_service_details')
                ->select('faqs')
                ->where('service_id', $row['id'])
                ->where('language_code', $defaultLang)
                ->get()
                ->getRowArray();

            if (!empty($defaultFaqs['faqs'])) {
                $faqsData = json_decode($defaultFaqs['faqs'], true);
                if (is_array($faqsData) && !empty($faqsData)) {
                    return $this->processFaqsData($faqsData);
                }
            }
        }

        // Fallback to main table FAQs
        $faqsData = $row['faqs'];
        if (is_string($faqsData)) {
            $faqsData = json_decode($faqsData, true);
        }
        if (is_array($faqsData)) {
            return $this->processFaqsData($faqsData);
        }

        // Return empty array if no FAQs found
        return [];
    }

    /**
     * Process FAQ data from JSON format to structured array
     * 
     * @param array $faqsData Raw FAQ data from database
     * @return array Processed FAQs array
     */
    private function processFaqsData($faqsData)
    {
        $faqs = [];

        if (is_array($faqsData)) {
            foreach ($faqsData as $faqItem) {
                // Handle both old format [question, answer] and new format {question, answer}
                if (is_array($faqItem)) {
                    if (count($faqItem) === 2 && isset($faqItem[0]) && isset($faqItem[1])) {
                        // Old format: [question, answer]
                        $faq = [
                            'question' => $faqItem[0],
                            'answer' => $faqItem[1]
                        ];
                    } elseif (isset($faqItem['question']) && isset($faqItem['answer'])) {
                        // New format: {question, answer}
                        $faq = [
                            'question' => $faqItem['question'],
                            'answer' => $faqItem['answer']
                        ];
                    } else {
                        continue; // Skip invalid FAQ items
                    }

                    // Only add FAQ if either question or answer is not empty
                    if (!empty(trim($faq['question'])) || !empty(trim($faq['answer']))) {
                        $faqs[] = $faq;
                    }
                }
            }
        }

        return $faqs;
    }

    /**
     * Get comprehensive service rating data using the same calculation method as Partners_model
     * This ensures consistency between get_service and get_ratings APIs
     * 
     * @param int $serviceId Service ID to get ratings for
     * @param object $db Database connection object
     * @return array Rating data with total ratings, average rating, and rating breakdown
     */
    private function getServiceRatingData($serviceId, $db)
    {
        // Get the partner_id (user_id) for this service first
        // Use query builder with parameter binding to prevent SQL injection
        $serviceData = $db->table('services')
            ->select('user_id')
            ->where('id', $serviceId)
            ->get()
            ->getRowArray();
        $partnerId = $serviceData['user_id'] ?? null;

        if (!$partnerId) {
            // Return default values if service not found
            return [
                'total_ratings' => 0,
                'average_rating' => 0,
                'rating_5' => 0,
                'rating_4' => 0,
                'rating_3' => 0,
                'rating_2' => 0,
                'rating_1' => 0
            ];
        }

        // Use the SAME logic as Service_ratings_model to ensure consistency
        // This query calculates rating statistics for ALL ratings of the partner who owns this service
        // Use parameter binding with ? placeholders to prevent SQL injection
        $query = "
            SELECT 
                COUNT(sr.rating) AS total_ratings,
                SUM(sr.rating) AS total_rating,
                (SUM(sr.rating) / COUNT(sr.rating)) AS average_rating,
                SUM(CASE WHEN sr.rating = 5 THEN 1 ELSE 0 END) AS rating_5,
                SUM(CASE WHEN sr.rating = 4 THEN 1 ELSE 0 END) AS rating_4,
                SUM(CASE WHEN sr.rating = 3 THEN 1 ELSE 0 END) AS rating_3,
                SUM(CASE WHEN sr.rating = 2 THEN 1 ELSE 0 END) AS rating_2,
                SUM(CASE WHEN sr.rating = 1 THEN 1 ELSE 0 END) AS rating_1
            FROM services_ratings sr
            LEFT JOIN services s ON sr.service_id = s.id
            WHERE (s.user_id = ? OR (sr.custom_job_request_id IS NOT NULL AND EXISTS (SELECT 1 FROM partner_bids pbid WHERE pbid.custom_job_request_id = sr.custom_job_request_id AND pbid.partner_id = ?)))
        ";

        // Execute query with parameter binding to prevent SQL injection
        // Both placeholders are bound to $partnerId for security
        $rating_data = $db->query($query, [$partnerId, $partnerId])->getResultArray();

        // Return the rating data, defaulting to 0 values if no data found
        if (!empty($rating_data) && isset($rating_data[0])) {
            return [
                'total_ratings' => $rating_data[0]['total_ratings'] ?? 0,
                'average_rating' => $rating_data[0]['average_rating'] ?? 0,
                'rating_5' => $rating_data[0]['rating_5'] ?? 0,
                'rating_4' => $rating_data[0]['rating_4'] ?? 0,
                'rating_3' => $rating_data[0]['rating_3'] ?? 0,
                'rating_2' => $rating_data[0]['rating_2'] ?? 0,
                'rating_1' => $rating_data[0]['rating_1'] ?? 0
            ];
        }

        // Return default values if no rating data found
        return [
            'total_ratings' => 0,
            'average_rating' => 0,
            'rating_5' => 0,
            'rating_4' => 0,
            'rating_3' => 0,
            'rating_2' => 0,
            'rating_1' => 0
        ];
    }

    /**
     * Get translated partner name with fallback logic
     * Priority: current language translation → default language translation → main table username
     * 
     * @param array $row Service row data with partner information
     * @param bool $from_app Whether this is from app (affects fallback behavior)
     * @return string Translated partner name with proper fallback
     */
    private function getTranslatedPartnerName($row, $from_app = false)
    {
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // If we have translated partner name from current language, use it
        if (!empty($row['translated_partner_name'])) {
            return trim($row['translated_partner_name']);
        }

        // If current language translation is not available, try default language
        if ($currentLang !== $defaultLang) {
            $db = \Config\Database::connect();
            $defaultTranslation = $db->table('translated_partner_details')
                ->select('username')
                ->where('partner_id', $row['user_id'])
                ->where('language_code', $defaultLang)
                ->get()
                ->getRowArray();

            if (!empty($defaultTranslation['username'])) {
                return trim($defaultTranslation['username']);
            }
        }

        // Fallback to main table username
        return trim($row['partner_name'] ?? '');
    }
}
