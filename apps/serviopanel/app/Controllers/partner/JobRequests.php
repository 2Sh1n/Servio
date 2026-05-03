<?php

namespace App\Controllers\partner;

use App\Models\Partners_model;
use Config\ApiResponseAndNotificationStrings;

class JobRequests extends Partner
{
    protected $validation;

    public  $validations, $db, $trans;
    protected Partners_model $partner;
    protected $data;

    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
        helper('ResponceServices');
        $this->trans = new ApiResponseAndNotificationStrings();
    }
    public function index()
    {
        if ($this->isLoggedIn) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }

            $db = \Config\Database::connect();

            $categories = get_categories_with_translated_names();
            $custom_job_categories = fetch_details('partner_details', ['partner_id' => $this->userId], ['custom_job_categories', 'is_accepting_custom_jobs']);
            $partner_categoried_preference = !empty($custom_job_categories) &&
                isset($custom_job_categories[0]['custom_job_categories']) &&
                !empty($custom_job_categories[0]['custom_job_categories']) ?
                json_decode($custom_job_categories[0]['custom_job_categories']) : [];
            $symbol =   get_currency();
            $partner_id = $this->userId;
            $db = \Config\Database::connect();
            $builder = $db->table('custom_job_requests cj')
                ->select('cj.*, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                ->join('users u', 'u.id = cj.user_id')
                ->join('categories c', 'c.id = cj.category_id')
                ->where('cj.status', 'pending')
                ->where("(SELECT COUNT(1) FROM partner_bids pb WHERE pb.custom_job_request_id = cj.id AND pb.partner_id = $partner_id) = 0");

            if (!empty($partner_categoried_preference)) {
                $builder->whereIn('cj.category_id', $partner_categoried_preference);
            }

            $builder->orderBy('cj.id', 'DESC');


            $custom_job_requests = $builder->get()->getResultArray();


            $filteredJobs = [];
            foreach ($custom_job_requests as $row) {
                if (!empty($row['image']) && !check_exists($row['image'])) {
                    $row['image_url'] = base_url($row['image']);
                    $row['has_image'] = true;
                } else {
                    $initial = strtoupper(substr($row['username'] ?? '', 0, 1) ?: 'U');
                    $bgColor = '#' . substr(md5($row['id']), 0, 6);

                    $row['fallback'] = [
                        'initial' => $initial,
                        'bgColor' => $bgColor,
                        'username' => $row['username'] ?? '',
                    ];
                    $row['has_image'] = false;
                }
                // Add human-readable time difference for created_at
                $row['time_ago'] = $this->getHumanTimeDiff($row['created_at']);

                $check = fetch_details('custom_job_provider', ['partner_id' => $partner_id, 'custom_job_request_id' => $row['id']]);

                if (!empty($check)) {
                    $filteredJobs[] = $row;
                }
            }
            $custom_job_requests = $filteredJobs;
            if (!empty($partner_categoried_preference)) {

                $custom_job_requests =  $custom_job_requests;
            } else {
                $custom_job_requests = [];
            }

            // Translate category names for custom job requests
            // Collect all unique category IDs from custom job requests
            $customJobCategoryIds = array_unique(array_column($custom_job_requests, 'category_id'));
            if (!empty($customJobCategoryIds)) {
                // Get translated category names using the Category model
                $categoryModel = new \App\Models\Category_model();
                $translatedCustomJobCategoryNames = $categoryModel->getTranslatedCategoryNames($customJobCategoryIds);

                // Update category_name in each custom job request with translated version
                foreach ($custom_job_requests as &$request) {
                    if (isset($request['category_id']) && isset($translatedCustomJobCategoryNames[$request['category_id']])) {
                        $request['category_name'] = $translatedCustomJobCategoryNames[$request['category_id']];
                    }
                }
            }


            $applied_jobs = $db->table('partner_bids pb')
                // Important: keep pb.created_at as "created_at" so it matches
                // the get_custom_job_requests API (applied_jobs uses bid timestamp).
                // We explicitly list required cj.* fields to avoid overriding created_at.
                ->select('pb.*, cj.id as custom_job_id, cj.service_title, cj.service_short_description, cj.min_price, cj.max_price, cj.requested_start_date, cj.requested_start_time, cj.requested_end_date, cj.requested_end_time, cj.status, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image,')
                ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id')
                ->join('users u', 'u.id = cj.user_id')
                ->join('categories c', 'c.id = cj.category_id')
                ->where('pb.partner_id', $partner_id)
                ->orderBy('pb.id', 'DESC')
                ->get()
                ->getResultArray();
            foreach ($applied_jobs as &$request) {
                if (!empty($request['image']) && !check_exists($request['image'])) {
                    $request['image_url'] = base_url($request['image']);
                    $request['has_image'] = true;
                } else {
                    $request['has_image'] = false;
                    $initial = strtoupper(substr($request['username'] ?? '', 0, 1) ?: 'U');
                    $bgColor = '#' . substr(md5($request['id']), 0, 6);
                    $request['fallback'] = [
                        'initial' => $initial,
                        'bgColor' => $bgColor,
                        'username' => $request['username'] ?? '',
                    ];
                    $request['has_image'] = false;
                }
                // Add human-readable time difference for created_at
                $request['time_ago'] = $this->getHumanTimeDiff($request['created_at']);
            }

            // Translate category names for applied jobs
            // Collect all unique category IDs from applied jobs
            $categoryIds = array_unique(array_column($applied_jobs, 'category_id'));
            if (!empty($categoryIds)) {
                // Reuse the Category model instance if it exists, otherwise create a new one
                if (!isset($categoryModel)) {
                    $categoryModel = new \App\Models\Category_model();
                }
                $translatedCategoryNames = $categoryModel->getTranslatedCategoryNames($categoryIds);

                // Update category_name in each applied job with translated version
                foreach ($applied_jobs as &$request) {
                    if (isset($request['category_id']) && isset($translatedCategoryNames[$request['category_id']])) {
                        $request['category_name'] = $translatedCategoryNames[$request['category_id']];
                    }
                }
            }

            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;

            $this->data['is_accepting_custom_jobs'] = $custom_job_categories[0]['is_accepting_custom_jobs'];

            $this->data['applied_jobs'] = $applied_jobs;
            $this->data['currency'] = $symbol;
            $this->data['custom_job_requests'] = $custom_job_requests;
            $this->data['categories_name'] = $categories;
            $this->data['custom_job_categories'] = $partner_categoried_preference;


            setPageInfo($this->data, labels('job_requests', 'Job Request\'s') . ' | ' . labels('provider_panel', 'Provider Panel'), 'job_requests');
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function manage_category_preference()
    {
        if (empty($_POST['category_id'])) {
            return ErrorResponse(labels(SELECT_AT_LEAST_ONE_CATEGORY, "Select at least one category"), true, [], [], 200, csrf_token(), csrf_hash());
        }
        $selected_categories = $_POST['category_id'];
        update_details(
            ['custom_job_categories' => json_encode($selected_categories)],
            ['partner_id' => $this->userId],
            'partner_details',
            false
        );
        return successResponse("Category Preference set successfully!", false, [], [], 200, csrf_token(), csrf_hash());
    }
    public function make_bid()
    {


        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
            $response['error'] = true;
            $response['message'] = DEMO_MODE_ERROR;
            $response['csrfName'] = csrf_token();
            $response['csrfHash'] = csrf_hash();
            return $this->response->setJSON($response);
        }
        $this->validation->setRules(
            [
                'counter_price' => [
                    "rules" => 'required',
                    "errors" => [
                        "required" => labels(PLEASE_ENTER_COUNTER_PRICE, "Please enter counter  price")
                    ]
                ],
                'cover_note' => [
                    "rules" => 'required',
                    "errors" => [
                        "required" => labels(PLEASE_ENTER_COVER_NOTE, "Please enter cover note")
                    ]
                ],
                'duration' => [
                    "rules" => 'required',
                    "errors" => [
                        "required" => labels(PLEASE_ENTER_DURATION, "Please enter duration")
                    ]
                ],

            ],
        );
        if (!$this->validation->withRequest($this->request)->run()) {
            $errors  = $this->validation->getErrors();
            return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
        }

        $subscriptionStatus = fetch_details(table:'partner_subscriptions', where:['partner_id' => $this->userId], fields:['status'], limit:1, sort:'id', order:'DESC');

        if ($subscriptionStatus[0]['status'] !== 'active') {
            return ErrorResponse(labels('you_do_not_have_an_active_subscription', "You do not have an active subscription"), true, [], [], 200, csrf_token(), csrf_hash());
        }   

        // ------------------------------------------------------------------
        // Prevent duplicate bids from the same provider on the same job.
        // This mirrors the API behaviour (apply_for_custom_job) so that
        // even if the partner panel form is double-submitted quickly,
        // we do not create multiple partner_bids rows.
        // ------------------------------------------------------------------
        $existingBid = fetch_details(
            'partner_bids',
            [
                'partner_id'            => $this->userId,
                'custom_job_request_id' => $_POST['id'],
            ]
        );

        if (!empty($existingBid)) {
            return ErrorResponse(
                labels('you_already_applied_for_this_job', "You have already applied for this job."),
                true,
                [],
                [],
                200,
                csrf_token(),
                csrf_hash()
            );
        }


        $data['partner_id'] = $this->userId;
        $data['counter_price'] = $_POST['counter_price'];
        $data['note'] = $_POST['cover_note'];
        $data['duration'] = $_POST['duration'];
        $data['custom_job_request_id'] = $_POST['id'];
        $data['status'] = 'pending';


        if (isset($_POST['tax_id']) && $_POST['tax_id'] != "") {

            $data['tax_id'] = $_POST['tax_id'] ?? "";
            $tax_details = fetch_details('taxes', ['id' => $_POST['tax_id']]);
            $data['tax_id'] = $tax_details[0]['id'];
            $data['tax_percentage'] = $tax_details[0]['percentage'];
            $data['tax_amount'] = ($_POST['counter_price'] * $tax_details[0]['percentage']) / 100;
        } else {
            $data['tax_id'] = "";
            $data['tax_percentage'] = "";
            $data['tax_amount'] = 0;
        }



        // Insert bid into database
        $insertResult = insert_details($data, 'partner_bids');

        // Check if bid was inserted successfully
        if ($insertResult['error']) {
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }

        $bidId = $insertResult['id'];

        // Get custom job request details for notification
        $fetch_custom_job_Data = fetch_details('custom_job_requests', ['id' => $_POST['id']]);
        if (empty($fetch_custom_job_Data)) {
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }

        $custom_job_request = $fetch_custom_job_Data[0];
        $customer_id = $custom_job_request['user_id'];

        // Send notification to customer using queue notification service
        // This unified approach handles FCM, Email, and SMS notifications using templates
        try {
            // Get provider name with translation support
            $provider_id = $this->userId;
            $providerName = get_translated_partner_field($provider_id, 'company_name');
            if (empty($providerName)) {
                $partner_data = fetch_details('partner_details', ['partner_id' => $provider_id], ['company_name']);
                $providerName = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
            }

            // Get customer details
            $customer_details = fetch_details('users', ['id' => $customer_id], ['username', 'email']);
            $customer_name = !empty($customer_details) && !empty($customer_details[0]['username']) ? $customer_details[0]['username'] : 'Customer';

            // Get category name
            $category_id = $custom_job_request['category_id'] ?? null;
            $category_name = '';
            if (!empty($category_id)) {
                $category_data = fetch_details('categories', ['id' => $category_id], ['name']);
                $category_name = !empty($category_data) && !empty($category_data[0]['name']) ? $category_data[0]['name'] : '';
            }

            // Get currency from settings
            $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

            // Prepare context data for notification templates
            // This context will be used to populate template variables like [[provider_name]], [[counter_price]], etc.
            $notificationContext = [
                'custom_job_request_id' => (string)$_POST['id'],
                'service_title' => $custom_job_request['service_title'] ?? '',
                'service_short_description' => $custom_job_request['service_short_description'] ?? '',
                'provider_id' => (string)$provider_id,
                'provider_name' => $providerName,
                'bid_id' => (string)$bidId,
                'counter_price' => number_format($data['counter_price'], 2),
                'currency' => $currency,
                'duration' => (string)$data['duration'],
                'cover_note' => $data['note'] ?? '',
                'customer_id' => (string)$customer_id,
                'customer_name' => $customer_name,
                'category_name' => $category_name
            ];

            // Queue all notifications (FCM, Email, SMS) to customer using NotificationService
            // NotificationService automatically handles:
            // - Translation of templates based on user language
            // - Variable replacement in templates (including counter_price)
            // - Notification settings checking for each channel
            // - Fetching user email/phone/FCM tokens
            // - Unsubscribe status checking for email
            queue_notification_service(
                eventType: 'bid_on_custom_job_request',
                recipients: ['user_id' => $customer_id],
                context: $notificationContext,
                options: [
                    'channels' => ['fcm', 'email', 'sms'], // All channels
                    'language' => get_current_language(),
                    'platforms' => ['android', 'ios', 'web'], // Customer platforms
                    'type' => 'bid', // Notification type for app routing
                    'data' => [
                        'custom_job_request_id' => (string)$_POST['id'],
                        'bid_id' => (string)$bidId,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to' => 'job_details_screen' // Redirect to job details screen
                    ]
                ]
            );
        } catch (\Throwable $notificationError) {
            // Log error but don't fail the bid creation
            // log_message('error', '[BID_ON_CUSTOM_JOB_REQUEST] Notification error in make_bid: ' . $notificationError->getMessage());
            log_message('error', '[BID_ON_CUSTOM_JOB_REQUEST] Notification error trace: ' . $notificationError->getTraceAsString());
        }

        // Prepare event data for custom_job_applied tracking
        $eventData = [
            'clarity_event' => 'custom_job_applied',
            'job_request_id' => $_POST['id'],
            'counter_price' => $_POST['counter_price'],
            'duration' => $_POST['duration']
        ];

        return successResponse(labels(YOUR_BID_HAS_BEEN_PLACED_SUCCESSFULLY, "Your bid has been placed successfully."), false, $eventData, [], 200, csrf_token(), csrf_hash());
    }

    public  function manage_accepting_custom_jobs()
    {


        $update =    update_details(['is_accepting_custom_jobs' => $_POST['custom_job_value']], ['partner_id' => $this->userId], 'partner_details');
        if ($update) {
            return successResponse(labels(YOUR_SETTING_HAS_BEEN_SUCCESSFULLY, "Your setting has been successfully."), false, [], [], 200, csrf_token(), csrf_hash());
        } else {

            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Calculate human-readable time difference from UTC database timestamp.
     * 
     * This method properly handles:
     * - Database timestamps stored in UTC
     * - Conversion to local timezone
     * - Accurate time difference calculation
     * - Translatable labels for better UX
     * 
     * @param string|null $date UTC timestamp from database
     * @return string Human-readable time difference (e.g., "2 hours ago", "just now")
     */
    private function getHumanTimeDiff(?string $date): string
    {
        if (!$date) return '';

        try {
            // Parse date as UTC (database stores timestamps in UTC)
            $utcDate = new \DateTime($date, new \DateTimeZone('UTC'));

            // Convert to local timezone for accurate difference calculation
            $localTz = new \DateTimeZone(date_default_timezone_get());
            $utcDate->setTimezone($localTz);

            // Get current time in local timezone
            $now = new \DateTime('now', $localTz);

            // Calculate difference in seconds
            $diff = $now->getTimestamp() - $utcDate->getTimestamp();

            // Handle edge cases
            if ($diff < 0) return labels('just_now', 'just now');
            if ($diff < 10) return labels('just_now', 'just now');
            if ($diff < 60) return labels('a_few_seconds_ago', 'a few seconds ago');
            if ($diff < 120) return labels('a_minute_ago', 'a minute ago');
            if ($diff < 3600) return $this->formatTimeAgo(floor($diff / 60), labels('minute', 'minute'));
            if ($diff < 7200) return labels('an_hour_ago', 'an hour ago');
            if ($diff < 86400) return $this->formatTimeAgo(floor($diff / 3600), labels('hour', 'hour'));
            if ($diff < 172800) return labels('yesterday', 'yesterday');
            if ($diff < 604800) return $this->formatTimeAgo(floor($diff / 86400), labels('day', 'day'));
            if ($diff < 2592000) return $this->formatTimeAgo(floor($diff / 604800), labels('week', 'week'));
            if ($diff < 31536000) return $this->formatTimeAgo(floor($diff / 2592000), labels('month', 'month'));

            return $this->formatTimeAgo(floor($diff / 31536000), labels('year', 'year'));
        } catch (\Exception $e) {
            // Log error and return empty string on failure
            log_message('error', 'getHumanTimeDiff error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Format time value with proper singular/plural handling.
     * 
     * @param int $value Time value (e.g., 2, 5, 1)
     * @param string $unit Time unit label (e.g., "hour", "minute")
     * @return string Formatted string (e.g., "1 hour ago", "5 hours ago")
     */
    private function formatTimeAgo(int $value, string $unit): string
    {
        return $value === 1
            ? "1 $unit ago"
            : "$value {$unit}s ago";
    }
}
