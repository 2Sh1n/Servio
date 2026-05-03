<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use DateTime;

class ProviderAvailabilityApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/provider_check_availability",
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

    public function get_available_slots()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'partner_id' => [
                        'rules'  => 'required|numeric',
                        'errors' => [
                            'required' => labels(PARTNER_ID_IS_REQUIRED, 'Partner ID is required'),
                            'numeric'  => labels(PARTNER_ID_MUST_BE_A_NUMBER, 'Partner ID must be a number'),
                        ],
                    ],
                    'date' => [
                        'rules'  => 'required|valid_date[Y-m-d]',
                        'errors' => [
                            'required'   => labels(DATE_IS_REQUIRED, 'Date is required'),
                            'valid_date' => labels(DATE_MUST_BE_IN_THE_FORMAT_YYYY_MM_DD, 'Date must be in the format YYYY-MM-DD'),
                        ],
                    ],
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $days = [
                'Mon' => 'monday',
                'Tue' => 'tuesday',
                'Wed' => 'wednesday',
                'Thu' => 'thursday',
                'Fri' => 'friday',
                'Sat' => 'saturday',
                'Sun' => 'sunday',
            ];
            $partner_id = $this->request->getPost('partner_id');
            $date = $this->request->getPost('date');
            $time = $this->request->getPost('date');
            $date = new DateTime($date);
            $date = $date->format('Y-m-d');
            $day = date('D', strtotime($date));
            $whole_day = $days[$day];
            $partner_data = fetch_details('partner_details', ['partner_id' => $partner_id], ['advance_booking_days']);
            $cart_data = fetch_cart(true, $this->user_details['id']);
            $duration = 0;
            if ($this->request->getPost('order_id')) {
                $order = fetch_details('order_services', ['order_id' => $this->request->getPost('order_id')]);
                $service_ids = [];
                foreach ($order as $row) {
                    $service_ids[] = $row['service_id'];
                }
                $total_duration = 0;
                foreach ($service_ids as $row) {
                    $service_data = fetch_details('services', ['id' => $row])[0];
                    $total_duration = $total_duration + $service_data['duration'];
                }
                $time_slots = get_available_slots($partner_id, $date, isset($total_duration) ? $total_duration : 0); //working
            } else if ($this->request->getPost('custom_job_request_id')) {
                $custom_job_data = fetch_details('partner_bids', ['partner_id' => $this->request->getPost('partner_id'), 'custom_job_request_id' => $this->request->getPost('custom_job_request_id')]);
                $time_slots = get_available_slots($partner_id, $date, isset($custom_job_data[0]['duration']) ? $custom_job_data[0]['duration'] : 0); //working
            } else {
                $time_slots = get_available_slots($partner_id, $date, isset($cart_data['total_duration']) ? $cart_data['total_duration'] : 0); //working
            }
            $available_slots = $busy_slots = $time_slots['all_slots'] = [];
            if (isset($time_slots['available_slots']) && !empty($time_slots['available_slots'])) {
                $available_slots = array_map(function ($time_slot) {
                    return ["time" => $time_slot, "is_available" => 1];
                }, $time_slots['available_slots']);
            }
            if (isset($time_slots['busy_slots']) && !empty($time_slots['busy_slots'])) {
                $busy_slots = array_map(function ($time_slot) {
                    return ["time" => $time_slot, "is_available" => 0];
                }, $time_slots['busy_slots']);
            }
            $time_slots['all_slots'] = array_merge($available_slots, $busy_slots);
            array_sort_by_multiple_keys($time_slots['all_slots'], ["time" => SORT_ASC]);
            if ($this->request->getPost('custom_job_request_id')) {
                $remaining_duration = isset($custom_job_data[0]['duration']) ? $custom_job_data[0]['duration'] : 0;
            } else {
                $remaining_duration = isset($cart_data['total_duration']) ? $cart_data['total_duration'] : 0;
            }
            $day = date('l', strtotime($date));
            $timings = getTimingOfDay($partner_id, $day);
            if (empty($timings)) {
                $response = [
                    'error' => true,
                    'message' => labels(PROVIDER_IS_CLOSED, 'Provider is closed!'),
                    'data' => [],
                ];
                return $this->response->setJSON(remove_null_values($response));
            }
            $closing_time = $timings['closing_time'];
            $current_date = date('Y-m-d');
            if ($this->request->getPost('custom_job_request_id')) {
                $next_day_slots = get_next_days_slots($closing_time, $date, $partner_id, isset($custom_job_data[0]['duration']) ? $custom_job_data[0]['duration'] : 0, $current_date);
            } else {
                $next_day_slots = get_next_days_slots($closing_time, $date, $partner_id, isset($cart_data['total_duration']) ? $cart_data['total_duration'] : 0, $current_date);
            }
            if (count($next_day_slots) > 0) {
                $remaining_duration = $remaining_duration - 30;
                $number_of_slot = $remaining_duration / 30;
                $last_slot = count($time_slots['all_slots']) - 1;
                $loop_count = count($time_slots['all_slots']);
                for ($i = $loop_count - 1; $i >= max(0, $loop_count - $number_of_slot); $i--) {
                    if ($time_slots['all_slots'][$i]['is_available'] == "1") {
                        $time_slots['all_slots'][$i]['message'] = labels(ORDER_SCHEDULED_FOR_THE_MULTIPLE_DAYS, 'Order scheduled for the multiple days');
                    }
                }
            }
            $partner_timing = fetch_details('partner_timings', ['partner_id' => $partner_id, "day" => $whole_day]);
            if (!empty($partner_data) && $partner_data[0]['advance_booking_days'] > 0) {
                $allowed_advanced_booking_days = $partner_data[0]['advance_booking_days'];
                $current_date = new DateTime();
                $max_available_date = $current_date->modify("+ $allowed_advanced_booking_days day")->format('Y-m-d');
                if ($date > $max_available_date) {
                    $response = [
                        'error' => true,
                        'message' => labels(YOU_CAN_NOT_CHOOSE_DATE_BEYOND_AVAILABLE_BOOKING_DAYS_WHICH_IS, "You'can not choose date beyond available booking days which is ") . $allowed_advanced_booking_days . labels(DAYS, " days"),
                        'data' => [],
                    ];
                    return $this->response->setJSON(remove_null_values($response));
                }
            } else if (!empty($partner_data) && $partner_data[0]['advance_booking_days'] == 0) {
                $current_date = new DateTime();
                if ($date > $current_date->format('Y-m-d')) {
                    $response = [
                        'error' => true,
                        'message' => labels(ADVANCED_BOOKING_FOR_THIS_PARTNER_IS_NOT_AVAILABLE, "Advanced Booking for this partner is not available"),
                        'data' => [],
                    ];
                    return $this->response->setJSON(remove_null_values($response));
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_PARTNER_FOUND, "No Partner Found"),
                    'data' => [],
                ];
                return $this->response->setJSON(remove_null_values($response));
            }
            if (!empty($time_slots)) {
                $response = [
                    'error' => $time_slots['error'],
                    'message' => ($time_slots['error'] == false) ? 'Found Time slots' : $time_slots['message'],
                    'data' => [
                        'all_slots' => (!empty($time_slots) && $time_slots['error'] == false) ? $time_slots['all_slots'] : [],
                    ],
                ];
                return $this->response->setJSON(remove_null_values($response));
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_SLOT_IS_AVAILABLE_ON_THIS_DATE, 'No slot is available on this date!'),
                    'data' => [],
                ];
                return $this->response->setJSON(remove_null_values($response));
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_available_slots()');
            return $this->response->setJSON($response);
        }
    }

    public function check_available_slot()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'partner_id' => 'required|numeric',
                    'date' => 'required|valid_date[Y-m-d]',
                    'time' => 'required',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $partner_id = $this->request->getPost('partner_id');
            $date = $this->request->getPost('date');
            $time = $this->request->getPost('time');

            // Fix invalid time format - convert "12:40-00" to "12:40:00"
            if (preg_match('/^(\d{1,2}):(\d{2})-(\d{2})$/', $time, $matches)) {
                $time = $matches[1] . ':' . $matches[2] . ':' . $matches[3];
            } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
                $time = $matches[1] . ':' . $matches[2] . ':00';
            }
            if ($this->request->getPost('order_id')) {
                if ($this->request->getPost('custom_job_request_id')) {
                    $custom_job_data = fetch_details('partner_bids', ['partner_id' => $this->request->getPost('partner_id'), 'custom_job_request_id' => $this->request->getPost('custom_job_request_id')]);
                    if (empty($custom_job_data)) {
                        return response_helper(labels(THERE_IS_NO_DATA, "There is no data"), true);
                    }
                    $service_total_duration = $custom_job_data[0]['duration'];
                } else {
                    $order = fetch_details('order_services', ['order_id' => $this->request->getPost('order_id')]);
                    $service_ids = [];
                    foreach ($order as $row) {
                        $service_ids[] = $row['service_id'];
                    }
                    $service_total_duration = 0;
                    foreach ($service_ids as $row) {
                        $service_data = fetch_details('services', ['id' => $row])[0];
                        $service_total_duration = $service_total_duration + $service_data['duration'];
                    }
                }
            } else if ($this->request->getPost('custom_job_request_id')) {
                $custom_job_data = fetch_details('partner_bids', ['partner_id' => $this->request->getPost('partner_id'), 'custom_job_request_id' => $this->request->getPost('custom_job_request_id')]);
                if (empty($custom_job_data)) {
                    return response_helper(labels(THERE_IS_NO_DATA, "There is no data"), true);
                }
                $service_total_duration = $custom_job_data[0]['duration'];
            } else {
                if ($this->request->getPost('is_reorder') == 1) {
                    $cart_data = fetch_cart(true, $this->user_details['id'], '', 0, 0, 'c.id', 'Desc', [], [], 'yes', $this->request->getPost('order_id'));
                } else {
                    $cart_data = fetch_cart(true, $this->user_details['id']);
                }
                if (empty($cart_data)) {
                    return response_helper(labels(PLEASE_ADD_SOME_SERVICE_IN_CART, "Please add some service in cart"), true);
                }
                $service_total_duration = 0;
                $service_duration = 0;
                foreach ($cart_data['data'] as $main_data) {
                    $service_duration = ($main_data['servic_details']['duration']) * $main_data['qty'];
                    $service_total_duration = $service_total_duration + $service_duration;
                }
            }
            $data = checkPartnerAvailability($partner_id, $date . ' ' . $time, $service_total_duration, $date, $time);
            return $this->response->setJSON($data);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - check_available_slot()');
            return $this->response->setJSON($response);
        }
    }

    public function provider_check_availability()
    {
        try {
            $db = \Config\Database::connect();
            $customer_latitude = $this->request->getPost('latitude');
            $customer_longitude = $this->request->getPost('longitude');
            $settings = get_settings('general_settings', true);
            $general_settings = fetch_details('settings', ['variable' => 'general_settings']);
            $builder = $db->table('users u');
            $sql_distance = $having = '';
            $distance = $settings['max_serviceable_distance'];
            if ($this->request->getPost('is_checkout_process') == '1') {
                $limit = $this->request->getPost('limit') ?: 10;
                $offset = $this->request->getPost('offset') ?: 0;
                $sort = $this->request->getPost('sort') ?: 'id';
                $order = $this->request->getPost('order') ?: 'ASC';
                $search = $this->request->getPost('search') ?: '';
                $where = [];
                if (!empty($this->request->getPost('order_id'))) {
                    $order_details = fetch_details('orders', ['id' => ($this->request->getPost('order_id')), 'user_id' => $this->user_details['id']]);
                } else {
                    $cart_details = fetch_cart(true, $this->user_details['id'], $search, $limit, $offset, $sort, $order, $where);
                }

                if (!empty($this->request->getPost('order_id'))) {
                    $provider_data = fetch_details('users', ['id' => $order_details[0]['partner_id']]);
                } else if (!empty($this->request->getPost('custom_job_request_id'))) {
                    $provider_data = fetch_details('users', ['id' => $this->request->getPost('bidder_id')]);
                } else {
                    // print_r($cart_details);
                    // exit;
                    $provider_data = fetch_details('users', ['id' => $cart_details['provider_id']]);
                }
                $provider_latitude = !empty($provider_data[0]['latitude']) ? $provider_data[0]['latitude'] : 0;
                $provider_longitude = !empty($provider_data[0]['longitude']) ? $provider_data[0]['longitude'] : 0;
                $provider_id = !empty($provider_data[0]['id']) ? $provider_data[0]['id'] : 0;

                $customer_longitude = (float) $customer_longitude; // Ensure it's a float
                $customer_latitude = (float) $customer_latitude;   // Ensure it's a float
                $partners = $builder->select("
                                            u.username,
                                            u.city,
                                            u.latitude,
                                            u.longitude,
                                            u.id,
                                            p.company_name,
                                            u.image,
                                            ST_DISTANCE_SPHERE(
                                                POINT(u.longitude, u.latitude), 
                                                POINT($customer_longitude, $customer_latitude)
                                            ) / 1000 AS distance
                                        ")
                    ->join('users_groups ug', 'ug.user_id = u.id')
                    ->join('partner_details p', 'p.partner_id = u.id')
                    ->where('p.is_approved', '1')
                    ->where('ug.group_id', '3')
                    ->where('u.id', $provider_id)
                    ->having('distance <', $distance)  // Fixed `having`
                    ->orderBy('distance')
                    ->get()
                    ->getResultArray();

                foreach ($partners as &$partner) {
                    if (!empty($partner['image'])) {
                        $partner['image'] = base_url() . '/' . $partner['image'];
                    }
                }
                if (!empty($partners)) {
                    $response = [
                        'error' => false,
                        'message' => labels(PROVIDER_IS_AVAILABLE, "Provider is available"),
                        "data" => $partners
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(PROVIDER_IS_NOT_AVAILABLE, "Provider is not available"),
                    ];
                }
            } else {
                // Build the SELECT statement as a string to avoid Query Builder parsing issues with complex subqueries
                // This ensures the subquery is properly formatted and not mangled by CodeIgniter's Query Builder
                $selectString = "u.username, u.city, u.latitude, u.longitude, p.company_name, u.image, u.id, 
                    ST_DISTANCE_SPHERE(POINT({$customer_longitude}, {$customer_latitude}), POINT(u.longitude, u.latitude)) / 1000 as distance,
                    (SELECT COUNT(*) FROM orders o WHERE o.partner_id = u.id AND o.parent_id IS NULL AND o.created_at > ps.purchase_date AND (o.payment_status != 2 OR o.payment_status IS NULL)) as number_of_orders, 
                    ps.max_order_limit, ps.order_type";

                $partners = $builder->select($selectString)
                    ->join('users_groups ug', 'ug.user_id=u.id')
                    ->join('partner_subscriptions ps', 'ps.partner_id = u.id', 'left')
                    ->join('partner_details p', 'p.partner_id=u.id')
                    ->where('ps.status', 'active')
                    ->where('ug.group_id', '3')
                    ->having('(number_of_orders < max_order_limit OR number_of_orders = 0 OR order_type = "unlimited")')
                    ->having('distance < ' . $distance)
                    ->orderBy('distance')
                    ->get()->getResultArray();
                foreach ($partners as &$partner) {
                    // Add translation support for partner company names
                    if (!empty($partner['id'])) {
                        $partnerData = [
                            'company_name' => $partner['company_name'] ?? '',
                            'about' => '',
                            'long_description' => '',
                            'username' => $partner['username'] ?? ''
                        ];
                        $translatedData = get_translated_partner_data_for_api($partner['id'], $partnerData);
                        $partner['company_name'] = $translatedData['company_name'];
                        $partner['translated_company_name'] = $translatedData['translated_company_name'] ?? $translatedData['company_name'];
                        $partner['translated_username'] = $translatedData['translated_username'] ?? $translatedData['username'];
                    }

                    if (!empty($partner['image'])) {
                        $partner['image'] = base_url() . '/' . $partner['image'];
                    }
                }
                if (!empty($partners)) {
                    $response = [
                        'error' => false,
                        'message' => labels(PROVIDERS_ARE_AVAILABLE, "Providers are available"),
                        "data" => $partners
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(PROVIDERS_ARE_NOT_AVAILABLE, "Providers are not available"),
                    ];
                }
            }
            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - provider_check_availability()');
            return $this->response->setJSON($response);
        }
    }
}
