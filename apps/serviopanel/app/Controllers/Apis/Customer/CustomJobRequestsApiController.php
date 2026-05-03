<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;

class CustomJobRequestsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1"
    ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        } else {
            $token = verify_app_request();
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function make_custom_job_request()
    {
        try {

            $validation = \Config\Services::validation();
            $validation->setRules([
                'category_id'               => 'required',
                'service_title'             => 'required',
                'service_short_description' => 'required',
                'min_price'                 => 'required',
                'max_price'                 => 'required',
                'requested_start_date'      => 'required|valid_date[Y-m-d]',
                'requested_start_time'      => 'required',
                'requested_end_date'        => 'required|valid_date[Y-m-d]',
                'requested_end_time'        => 'required',
                'latitude'        => 'required',
                'longitude'        => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }
            $today = date('Y-m-d');
            $startDate = $this->request->getVar('requested_start_date');
            $endDate = $this->request->getVar('requested_end_date');
            if ($startDate < $today) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(PLEASE_SELECT_AN_UPCOMING_START_DATE, 'Please select an upcoming start date!'),
                ]);
            }
            if ($endDate < $today) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(PLEASE_SELECT_AN_UPCOMING_END_DATE, 'Please select an upcoming end date!'),
                ]);
            }
            $user_id = $this->user_details['id'];
            $data = [
                'user_id'                   => $user_id,
                'category_id'               => $this->request->getVar('category_id'),
                'service_title'             => $this->request->getVar('service_title'),
                'service_short_description' => $this->request->getVar('service_short_description'),
                'min_price'                 => $this->request->getVar('min_price'),
                'max_price'                 => $this->request->getVar('max_price'),
                'requested_start_date'      => $startDate,
                'requested_start_time'      => $this->request->getVar('requested_start_time'),
                'requested_end_date'        => $endDate,
                'requested_end_time'        => $this->request->getVar('requested_end_time'),
                'status'                    => 'pending'
            ];
            $insert = insert_details($data, 'custom_job_requests');
            if ($insert) {
                // Send notification to related providers (existing functionality)
                send_notification_to_related_providers($this->request->getVar('category_id'), $insert, $this->request->getVar('latitude'), $this->request->getVar('longitude'));

                // Send template-based notifications to admin and providers
                try {
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Starting notification process for custom_job_request_id: ' . $insert['id']);

                    // Get customer information
                    $customerData = fetch_details('users', ['id' => $user_id], ['username']);
                    $customerName = !empty($customerData) ? $customerData[0]['username'] : 'Customer';
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Customer name: ' . $customerName . ', Customer ID: ' . $user_id);

                    // Get category information
                    $categoryData = fetch_details('categories', ['id' => $this->request->getVar('category_id')], ['name']);
                    $categoryName = !empty($categoryData) ? $categoryData[0]['name'] : 'Category';
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Category name: ' . $categoryName . ', Category ID: ' . $this->request->getVar('category_id'));

                    // Get currency from settings
                    $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

                    // Prepare context data for the notification template
                    $context = [
                        'customer_name' => $customerName,
                        'customer_id' => $user_id,
                        'custom_job_request_id' => $insert['id'],
                        'service_title' => $this->request->getVar('service_title'),
                        'service_short_description' => $this->request->getVar('service_short_description'),
                        'category_name' => $categoryName,
                        'category_id' => $this->request->getVar('category_id'),
                        'min_price' => number_format($this->request->getVar('min_price'), 2),
                        'max_price' => number_format($this->request->getVar('max_price'), 2),
                        'currency' => $currency,
                        'requested_start_date' => $startDate,
                        'requested_start_time' => $this->request->getVar('requested_start_time'),
                        'requested_end_date' => $endDate,
                        'requested_end_time' => $this->request->getVar('requested_end_time')
                    ];
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Context prepared: ' . json_encode($context));

                    // Queue notification to admin users (group_id = 1) using user_groups filter.
                    // In demo mode, limit FCM tokens per language to the last 20, while keeping
                    // full user_ids for DB/email/SMS notifications.
                    $isDemoMode = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0;
                    $fcmDemoTokenLimit = $isDemoMode ? 20 : 0;

                    queue_notification_service(
                        eventType: 'new_custom_job_request',
                        recipients: [],
                        context: $context,
                        options: [
                            'user_groups' => [1], // Admin group
                            'channels' => ['fcm', 'email', 'sms'], // All channels - service will check preferences
                            'fcm_demo_token_limit' => $fcmDemoTokenLimit,
                        ]
                    );
                    // log_message('info', '[NEW_CUSTOM_JOB_REQUEST] Notification result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // log_message('error', '[NEW_CUSTOM_JOB_REQUEST] Notification error: ' . $notificationError->getMessage());
                    log_message('error', '[NEW_CUSTOM_JOB_REQUEST] Notification error trace: ' . $notificationError->getTraceAsString());
                }
            }
            $response = $insert ?
                ['error' => false, 'message' => labels(REQUEST_SUCCESSFUL, 'Request successful!')] :
                ['error' => true, 'message' => labels(REQUEST_FAILED, 'Request failed!')];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - make_custom_job_request()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function fetch_my_custom_job_requests()
    {
        try {
            $db = \Config\Database::connect();
            $disk = fetch_current_file_manager();

            // Fetch single custom job request by id when id param is passed
            if (!empty($this->request->getPost('id'))) {
                $job_id = (int) $this->request->getPost('id');
                $data = $db->table('custom_job_requests cj')
                    ->select('cj.*, c.name as category_name, c.parent_id as category_parent_id, c.image as category_image')
                    ->join('categories c', 'c.id = cj.category_id', 'left')
                    ->where('cj.user_id', $this->user_details['id'])
                    ->where('cj.id', $job_id)
                    ->get()
                    ->getResultArray();
                $total = count($data);
                foreach ($data as $index => $row) {
                    $data[$index]['translated_status'] = getTranslatedValue($row['status'], 'panel');
                    if ($disk == 'local_server') {
                        $localPath = base_url('/public/uploads/categories/' . $row['category_image']);
                        $category_image = check_exists($localPath) ? $localPath : '';
                    } else if ($disk == "aws_s3") {
                        $category_image = fetch_cloud_front_url('categories', $row['category_image']);
                    } else {
                        $category_image = $row['category_image'];
                    }
                    $data[$index]['total_bids'] = 0;
                    $data[$index]['bidders'] = [];
                    $data[$index]['category_image'] = $category_image;
                    $biddersBuilder = $db->table('partner_bids pb')
                        ->select('pd.banner as provider_image')
                        ->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left')
                        ->where('pb.custom_job_request_id', $row['id'])
                        ->get()
                        ->getResultArray();
                    foreach ($biddersBuilder as $index1 => $bidRow) {
                        if ($disk == "local_server") {
                            $biddersBuilder[$index1]['provider_image'] = (file_exists($bidRow['provider_image'])) ? base_url($bidRow['provider_image']) : base_url('public/backend/assets/profiles/default.png');
                        } else if ($disk == "aws_s3") {
                            $biddersBuilder[$index1]['provider_image'] = fetch_cloud_front_url('banner', $bidRow['provider_image']);
                        } else {
                            $biddersBuilder[$index1]['provider_image'] = base_url('public/backend/assets/profiles/default.png');
                        }
                    }
                    $data[$index]['total_bids'] = count($biddersBuilder);
                    $data[$index]['bidders'] = $biddersBuilder;
                }
                if (!empty($data)) {
                    $data = update_category_names_in_query_results($data);
                    return response_helper(labels(MY_CUSTOM_JOBS_FETCHED_SUCCESSFULLY, 'My Custom Jobs fetched successfully'), false, $data, 200, ['total' => $total]);
                }
                return response_helper(labels(MY_CUSTOM_JOBS_NOT_FOUND, 'My Custom Jobs not found'), false);
            }

            // Default: list custom job requests with pagination
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = !empty($this->request->getPost('offset')) ? $this->request->getPost('offset') : 0;
            $sort = !empty($this->request->getPost('sort')) ? $this->request->getPost('sort') : 'id';
            $order = !empty($this->request->getPost('order')) ? $this->request->getPost('order') : 'DESC';
            $builder = $db->table('custom_job_requests cj');
            $total = $builder->select('COUNT(id) as total')->where('user_id', $this->user_details['id'])->get()->getRowArray()['total'];
            $builder->select('cj.*, c.name as category_name, c.parent_id as category_parent_id,c.image as category_image');
            $data = $builder
                ->join('categories c', 'c.id = cj.category_id', 'left')
                ->orderBy($sort, $order)
                ->limit($limit, $offset)
                ->where('cj.user_id', $this->user_details['id'])
                ->get()
                ->getResultArray();

            foreach ($data as $index => $row) {
                $data[$index]['translated_status'] = getTranslatedValue($row['status'], 'panel');
                if ($disk == 'local_server') {
                    $localPath = base_url('/public/uploads/categories/' . $row['category_image']);
                    if (check_exists($localPath)) {
                        $category_image = $localPath;
                    } else {
                        $category_image = '';
                    }
                } else if ($disk == "aws_s3") {
                    $category_image = fetch_cloud_front_url('categories', $row['category_image']);
                } else {
                    $category_image = $row['category_image'];
                }
                $data[$index]['total_bids'] = 0;
                $data[$index]['bidders'] = [];
                $data[$index]['category_image'] = $category_image;
                $biddersBuilder = $db->table('partner_bids pb')
                    ->select('pd.banner as provider_image')
                    ->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left')
                    ->where('pb.custom_job_request_id', $row['id'])
                    ->get()
                    ->getResultArray();
                foreach ($biddersBuilder as $index1 => $row) {
                    if ($disk == "local_server") {
                        $biddersBuilder[$index1]['provider_image'] = (file_exists($row['provider_image'])) ? base_url($row['provider_image']) : base_url('public/backend/assets/profiles/default.png');
                    } else if ($disk == "aws_s3") {
                        $biddersBuilder[$index1]['provider_image'] = fetch_cloud_front_url('banner', $row['provider_image']);
                    } else {
                        $biddersBuilder[$index1]['provider_image'] = base_url('public/backend/assets/profiles/default.png');
                    }
                }
                $data[$index]['total_bids'] = count($biddersBuilder);
                $data[$index]['bidders'] = $biddersBuilder;
            }
            if (!empty($data)) {
                // Update category names with translations
                $data = update_category_names_in_query_results($data);

                return response_helper(labels(MY_CUSTOM_JOBS_FETCHED_SUCCESSFULLY, 'My Custom Jobs fetched successfully'), false, $data, 200, ['total' => $total]);
            } else {
                return response_helper(labels(MY_CUSTOM_JOBS_NOT_FOUND, 'My Custom Jobs not found'), false);
            }
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - fetch_my_custom_job_requests()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public function fetch_custom_job_bidders()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'custom_job_request_id' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = !empty($this->request->getPost('offset')) ? $this->request->getPost('offset') : 0;
            $sort = !empty($this->request->getPost('sort')) ? $this->request->getPost('sort') : 'id';
            $order = !empty($this->request->getPost('order')) ? $this->request->getPost('order') : 'DESC';
            $db = \Config\Database::connect();
            $totalBuilder = $db->table('partner_bids pb')
                ->select('COUNT(pb.id) as total_bidders')
                ->where('pb.custom_job_request_id', $this->request->getPost('custom_job_request_id'))
                ->get()
                ->getRowArray();
            $total = $totalBuilder['total_bidders'];
            $biddersBuilder = $db->table('partner_bids pb')
                ->select('pb.*, pd.company_name as company_name,u.username as provider_name,pd.advance_booking_days,pd.visiting_charges, pd.banner as provider_image,pd.at_store,pd.at_doorstep,u.payable_commision')
                ->join('partner_details pd', 'pd.partner_id = pb.partner_id', 'left')
                ->join('users u', 'u.id = pd.partner_id')
                ->where('pb.custom_job_request_id', $this->request->getPost('custom_job_request_id'))
                ->orderBy($sort, $order)
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();
            $check_payment_gateway = get_settings('payment_gateways_settings', true);
            $disk = fetch_current_file_manager();
            foreach ($biddersBuilder as $index => $row) {
                // Add translation support for partner company names
                if (!empty($row['partner_id'])) {
                    $partnerData = [
                        'company_name' => $row['company_name'] ?? '',
                        'about' => '',
                        'long_description' => '',
                        'username' => $row['username'] ?? ''
                    ];
                    $translatedData = $this->getTranslatedPartnerData($row['partner_id'], $partnerData);
                    $biddersBuilder[$index]['company_name'] = $translatedData['company_name'];
                    $biddersBuilder[$index]['translated_company_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                    $biddersBuilder[$index]['translated_username'] = $translatedData['translated_username'] ?? $translatedData['username'];
                }
                $rating_data = $db->table('services_ratings sr')
                    ->select('
                    COUNT(sr.rating) as number_of_rating,
                    SUM(sr.rating) as total_rating,
                    (SUM(sr.rating) / COUNT(sr.rating)) as average_rating
                    ')
                    ->join('services s', 'sr.service_id = s.id', 'left')
                    ->join('custom_job_requests cj', 'sr.custom_job_request_id = cj.id', 'left')
                    ->join('partner_bids pd', 'pd.custom_job_request_id = cj.id', 'left')
                    ->where("(s.user_id = {$row['partner_id']}) OR (pd.partner_id = {$row['partner_id']})")
                    ->get()->getResultArray();
                $biddersBuilder[$index]['rating'] = (($rating_data[0]['average_rating'] != "") ? sprintf('%0.1f', $rating_data[0]['average_rating']) : '0.0');
                if ($disk == "local_server") {
                    $biddersBuilder[$index]['provider_image'] = (file_exists($row['provider_image'])) ? base_url($row['provider_image']) : base_url('public/backend/assets/profiles/default.png');
                } else if ($disk == "aws_s3") {
                    $biddersBuilder[$index]['provider_image'] = fetch_cloud_front_url('banner', $row['provider_image']);
                } else {
                    $biddersBuilder[$index]['provider_image'] =  base_url('public/backend/assets/profiles/default.png');
                }
                $total_orders = $db->table('orders o')->where('partner_id', $row['partner_id'])->where('status', 'completed')->select('count(o.id) as `total`')->where('o.parent_id  IS NULL')->get()->getResultArray()[0]['total'];
                $biddersBuilder[$index]['total_orders'] = $total_orders;
                $biddersBuilder[$index]['is_online_payment_allowed'] = $check_payment_gateway['payment_gateway_setting'];
                $active_partner_subscription = fetch_details('partner_subscriptions', ['partner_id' => $row['partner_id'], 'status' => 'active']);
                if (!empty($active_partner_subscription)) {
                    if ($active_partner_subscription[0]['is_commision'] == "yes") {
                        $commission_threshold = $active_partner_subscription[0]['commission_threshold'];
                    } else {
                        $commission_threshold = 0;
                    }
                } else {
                    $commission_threshold = 0;
                }
                if ($check_payment_gateway['cod_setting'] == 1 && $check_payment_gateway['payment_gateway_setting'] == 0) {
                    $biddersBuilder[$index]['is_pay_later_allowed'] = 1;
                } else if ($check_payment_gateway['cod_setting'] == 0) {
                    $biddersBuilder[$index]['is_pay_later_allowed'] = 0;
                } else {
                    $payable_commission_of_provider = $biddersBuilder[$index]['payable_commision'];
                    if (($payable_commission_of_provider >= $commission_threshold) && $commission_threshold != 0) {
                        $biddersBuilder[$index]['is_pay_later_allowed'] = 0;
                    } else {
                        $biddersBuilder[$index]['is_pay_later_allowed'] = 1;
                    }
                }
                if ($biddersBuilder[$index]['tax_amount'] == "") {
                    $biddersBuilder[$index]['final_total'] =  $biddersBuilder[$index]['counter_price'];
                } else {
                    $biddersBuilder[$index]['final_total'] =  $biddersBuilder[$index]['counter_price'] + ($biddersBuilder[$index]['tax_amount']);
                }
            }
            $data['bidders'] = $biddersBuilder;
            $custom_job = $db->table('custom_job_requests cj')
                ->select('cj.*,c.name as category_name,c.image as category_image')
                ->join('categories c', 'c.id = cj.category_id', 'left')
                ->where('cj.id', $this->request->getPost('custom_job_request_id'))
                ->get()
                ->getResultArray();
            $disk = fetch_current_file_manager();
            foreach ($custom_job as &$job) { // Use a reference to update the array directly
                if ($disk == 'local_server') {
                    $localPath = base_url('/public/uploads/categories/' . $job['category_image']);
                    if (check_exists($localPath)) {
                        $job['category_image'] = $localPath;
                    } else {
                        $job['category_image'] = '';
                    }
                } else if ($disk == "aws_s3") {
                    $job['category_image'] = fetch_cloud_front_url('categories', $job['category_image']);
                } else {
                    $job['category_image'] = $job['category_image'];
                }
            }
            unset($job); // Unset the reference to avoid unintended side effects

            // Update category names with translations
            $custom_job = update_category_names_in_query_results($custom_job);

            $data['custom_job'] = !empty($custom_job[0]) ? $custom_job[0] : [];
            if (!empty($data)) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels(BIDDERS_FETCHED_SUCCESSFULLY, 'Bidders fetched successfully'),
                    'data'    => $data,
                    'total'   => $total,
                    'status'  => 200
                ]);
            } else {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels(NO_BIDDERS_FOUND, 'No bidders found'),
                    'data'    => [],
                    'total'   => 0,
                    'status'  => 200
                ]);
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - fetch_custom_job_bidders()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    public  function  cancle_custom_job_request()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'custom_job_request_id' => 'required',
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }
            $custom_job = fetch_details('custom_job_requests', ['id' => $this->request->getPost('custom_job_request_id')]);
            if ($custom_job[0]['status'] != "pending") {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(YOU_CAN_NOT_CANCEL_SERVICE, 'You can not cancle service'),
                    'data'    => [],
                ]);
            }
            $update = update_details(['status' => 'cancelled'], ['id' => $this->request->getPost('custom_job_request_id')], 'custom_job_requests');
            if ($update) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels(CUSTOM_JOB_REQUEST_CANCELLED_SUCCESSFULLY, 'Custom Job Request cancelled successfully'),
                    'status'  => 200
                ]);
            }
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - cancle_custom_job_request()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    //Private helper methods
    /**
     * Get translated partner data based on language preference
     * 
     * @param int $partnerId Partner ID
     * @param array $partnerData Original partner data from main table
     * @return array Partner data with translations
     */
    private function getTranslatedPartnerData(int $partnerId, array $partnerData): array
    {
        // Validate partner ID to prevent errors
        if (empty($partnerId) || $partnerId <= 0) {
            log_message('error', 'Invalid partner ID provided to getTranslatedPartnerData: ' . $partnerId);
            return $partnerData; // Return original data if partner ID is invalid
        }

        return get_translated_partner_data_for_api($partnerId, $partnerData);
    }
}
