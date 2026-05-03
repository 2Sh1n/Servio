<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\Flutterwave;
use App\Libraries\JWT;
use App\Libraries\Cashfree;
use App\Libraries\Paypal;
use App\Libraries\Paystack;
use App\Libraries\Razorpay;
use App\Libraries\Stripe;
use App\Libraries\StripeMoney;
use App\Libraries\Xendit;
use App\Models\Orders_model;
use App\Models\Partner_subscription_model;
use App\Models\Partners_model;
use App\Models\Transaction_model;
use App\Models\Users_model;
use Razorpay\Api\Api;

class OrdersApiController extends BaseController
{
    protected $request, $trans, $db, $orders, $data;
    protected Paypal $paypal_lib;
    protected Flutterwave $flutterwave;
    protected Paystack $paystack;
    protected Razorpay $razorpay;
    protected Cashfree $cashfree;
    protected Stripe $stripe;
    protected Xendit $xendit;
    protected JWT $JWT;
    private $builder;

    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/flutterwave",
        "api/v1/invoice-download",
        "api/v1/get_paypal_link",
        "api/v1/paypal_transaction_webview",
        "api/v1/app_payment_status",
        "api/v1/capturePayment",
        "api/v1/paystack_transaction_webview",
        "api/v1/app_paystack_payment_status",
        "api/v1/flutterwave_webview",
        "api/v1/flutterwave_payment_status",
        "api/v1/xendit_payment_status",
    ];

    private $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');

        $this->paypal_lib = new Paypal();
        $this->request = \Config\Services::request();
        $this->flutterwave = new Flutterwave();
        $this->paystack = new paystack();
        $this->razorpay = new Razorpay();
        $this->cashfree = new Cashfree();
        $this->stripe = new Stripe();
        $this->xendit = new Xendit();
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

    private function hasReachedDailyBookingLimit(int $userId): bool
    {
        $settings = get_settings('general_settings', true);
        $timezone = $settings['system_timezone'] ?? date_default_timezone_get();

        $now = new \DateTime('now', new \DateTimeZone($timezone));
        $startOfDay = $now->format('Y-m-d') . ' 00:00:00';
        $endOfDay   = $now->format('Y-m-d') . ' 23:59:59';

        $count = \Config\Database::connect()
            ->table('orders')
            ->where('user_id', $userId)
            ->groupStart()
                ->where('parent_id', null)
                ->orWhere('parent_id', 0)
                ->orWhere('parent_id', '')
            ->groupEnd()
            ->where('created_at >=', $startOfDay)
            ->where('created_at <=', $endOfDay)
            ->where('status !=', 'cancelled')
            ->countAllResults();

        return $count >= 5;
    }

    private function getAlreadyBookedActiveServices(int $userId, array $serviceIds): array
    {
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));

        if (empty($serviceIds)) {
            return [];
        }

        return \Config\Database::connect()
            ->table('order_services os')
            ->select('os.service_id, os.service_title, o.id as order_id, o.status')
            ->join('orders o', 'o.id = os.order_id', 'inner')
            ->where('o.user_id', $userId)
            ->groupStart()
                ->where('o.parent_id', null)
                ->orWhere('o.parent_id', 0)
                ->orWhere('o.parent_id', '')
            ->groupEnd()
            ->whereIn('os.service_id', $serviceIds)
            ->whereNotIn('o.status', ['completed', 'cancelled', 'rejected'])
            ->get()
            ->getResultArray();
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function place_order()
    {
        try {
            $validation = \Config\Services::validation();
            $rules = [
                'promo_code_id' => 'permit_empty',
                'payment_method' => 'required',
                'status' => 'required',
                'date_of_service' => 'required|valid_date[Y-m-d]',
                'starting_time' => 'required',
            ];

            $at_store = $this->request->getVar('at_store');
            if ($at_store == 1) {
                $rules['address_id'] = 'permit_empty|numeric';
            } else {
                $rules['address_id'] = 'required|numeric';
            }

            $validation->setRules($rules);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => ['type' => 'neworder'],
                ];
                return $this->response->setJSON($response);
            }

            $userId = (int) ($this->user_details['id'] ?? 0);
            if ($userId <= 0) {
                return response_helper(labels(USER_NOT_FOUND, 'User not found'), true);
            }

            if ($this->hasReachedDailyBookingLimit($userId)) {
                return response_helper(
                    labels('daily_booking_limit_exceeded', 'You can only place 5 bookings per day.'),
                    true
                );
            }

            if (empty($this->request->getVar('order_id')) || empty($this->request->getVar('custom_job_request_id'))) {
                $cart_data = fetch_cart(true, $this->user_details['id']);
                if (!empty($cart_data)) {
                    $disabled_services = [];
                    $services_to_remove = [];

                    foreach ($cart_data['data'] as $item) {
                        $service_status = fetch_details('services', ['id' => $item['service_id']], ['status', 'title']);
                        if (!empty($service_status) && $service_status[0]['status'] == 0) {
                            $disabled_services[] = $service_status[0]['title'];
                            $services_to_remove[] = $item['service_id'];
                        }
                    }

                    if (!empty($disabled_services)) {
                        foreach ($services_to_remove as $service_id) {
                            delete_details(['service_id' => $service_id, 'user_id' => $this->user_details['id']], 'cart');
                        }

                        $cart_data = fetch_cart(true, $this->user_details['id']);

                        if (empty($cart_data)) {
                            return response_helper(
                                labels(
                                    THE_FOLLOWING_SERVICES_ARE_NOT_AVAILABLE_AND_HAVE_BEEN_REMOVED_FROM_CART,
                                    'The following services are not available and have been removed from cart: ' . implode(', ', $disabled_services)
                                ),
                                true
                            );
                        }

                        return response_helper(
                            labels(
                                THE_FOLLOWING_SERVICES_WERE_REMOVED_FROM_CART_AS_THEY_ARE_NO_LONGER_AVAILABLE,
                                'The following services were removed from cart as they are no longer available: ' . implode(', ', $disabled_services)
                            ),
                            true
                        );
                    }
                }
            }

            if (empty($this->request->getVar('order_id')) && empty($this->request->getVar('custom_job_request_id'))) {
                if (empty($cart_data)) {
                    return response_helper(labels(PLEASE_ADD_SOME_SERVICE_IN_CART, 'Please add some service in cart'), true);
                }
            }

            if (!empty($this->request->getVar('custom_job_request_id'))) {
                $db = \Config\Database::connect();
                $custom_job_data = $db->table('partner_bids pb')
                    ->select('pb.*, cj.*, cj.id as custom_job_id,pd.visiting_charges, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                    ->join('custom_job_requests cj', 'cj.id = pb.custom_job_request_id')
                    ->join('users u', 'u.id = cj.user_id')
                    ->join('partner_details pd', 'pd.partner_id = pb.partner_id')
                    ->join('categories c', 'c.id = cj.category_id')
                    ->where('pb.partner_id', $this->request->getVar('bidder_id'))
                    ->where('cj.id', $this->request->getVar('custom_job_request_id'))
                    ->orderBy('pb.id', 'DESC')
                    ->get()
                    ->getResultArray();
            }

            $db = \Config\Database::connect();

            if ((empty($this->request->getVar('order_id'))) && empty($this->request->getVar('custom_job_request_id'))) {
                $service_ids = $cart_data['service_ids'];
                $quantity = $cart_data['qtys'];
                $total = $cart_data['sub_total'];
            } else if (!empty($this->request->getVar('custom_job_request_id'))) {
                if ($custom_job_data[0]['tax_amount'] == "" || $custom_job_data[0]['tax_amount'] == null) {
                    $total = $custom_job_data[0]['counter_price'];
                } else {
                    $total = $custom_job_data[0]['counter_price'] + $custom_job_data[0]['tax_amount'];
                }
            } else {
                $order = fetch_details('order_services', ['order_id' => $this->request->getPost('order_id')]);
                $service_ids = [];

                foreach ($order as $row) {
                    $service_ids[] = $row['service_id'];
                }

                $all_service_data = [];
                foreach ($service_ids as $row2) {
                    $service_data_array = fetch_details('services', ['id' => $row2]);
                    $service_data = $service_data_array[0];
                    $all_service_data[] = $service_data;
                }

                $quantities = [];
                foreach ($order as $row) {
                    $quantities[] = $row['quantity'];
                }

                $quantity = implode(',', $quantities);
                $total = 0;
                $tax_value = 0;
                $sub_total = 0;
                $duartion = 0;

                $builder = $db->table('order_services os');
                $service_record = $builder
                    ->select('os.id as order_service_id, os.service_id, os.quantity as order_quantity, os.sub_total as order_sub_total, s.*, s.title as service_name, p.username as partner_name, pd.visiting_charges as visiting_charges, cat.name as category_name')
                    ->join('services s', 'os.service_id=s.id', 'left')
                    ->join('users p', 'p.id=s.user_id', 'left')
                    ->join('categories cat', 'cat.id=s.category_id', 'left')
                    ->join('partner_details pd', 'pd.partner_id=s.user_id', 'left')
                    ->where('os.order_id', $this->request->getPost('order_id'))
                    ->get()
                    ->getResultArray();

                foreach ($service_record as $s1) {
                    $order_qty = isset($s1['order_quantity']) ? (int) $s1['order_quantity'] : 1;

                    if (empty($s1['id']) || $s1['service_id'] === '-') {
                        $line_total = (float) str_replace(',', '', $s1['order_sub_total'] ?? '0');
                        $sub_total += $line_total;
                        $duartion += (float) ($s1['duration'] ?? 0) * $order_qty;
                        continue;
                    }

                    $taxPercentageData = fetch_details('taxes', ['id' => $s1['tax_id']], ['percentage']);
                    if (!empty($taxPercentageData)) {
                        $taxPercentage = $taxPercentageData[0]['percentage'];
                    } else {
                        $taxPercentage = 0;
                    }

                    if ($s1['discounted_price'] == "0") {
                        $tax_value = ($s1['tax_type'] == "excluded") ? (($s1['price'] * ($taxPercentage) / 100)) : 0;
                        $price = (float) $s1['price'];
                    } else {
                        $tax_value = ($s1['tax_type'] == "excluded") ? (($s1['discounted_price'] * ($taxPercentage) / 100)) : 0;
                        $price = (float) $s1['discounted_price'];
                    }

                    $sub_total = $sub_total + ($price + $tax_value) * $order_qty;
                    $duartion = $duartion + (float) ($s1['duration'] ?? 0) * $order_qty;
                }

                $total = $sub_total;
            }

            if ($at_store == "1") {
                $visiting_charges = 0;
            } else {
                if (empty($this->request->getPost('order_id')) && empty($this->request->getVar('custom_job_request_id'))) {
                    $visiting_charges = $cart_data['visiting_charges'];
                } else if (!empty($this->request->getVar('custom_job_request_id'))) {
                    $visiting_charges = $custom_job_data[0]['visiting_charges'];
                } else {
                    $builder = $db->table('services s');
                    $extra_data = $builder
                        ->select('SUM(IF(s.discounted_price > 0, (s.discounted_price * os1.quantity), (s.price * os1.quantity))) as subtotal,
                            SUM(os1.quantity) as total_quantity,
                            pd.visiting_charges as visiting_charges,
                            SUM(s.duration * os1.quantity) as total_duration,
                            pd.advance_booking_days as advance_booking_days,
                            pd.company_name as company_name')
                        ->join('order_services os1', 'os1.service_id = s.id')
                        ->join('partner_details pd', 'pd.partner_id=s.user_id')
                        ->where('os1.order_id', $this->request->getPost('order_id'))
                        ->whereIn('s.id', $service_ids)
                        ->get()
                        ->getResultArray();

                    $visiting_charges = $extra_data[0]['visiting_charges'];
                }
            }

            $promo_code = $this->request->getVar('promo_code_id');
            $payment_method = $this->request->getVar('payment_method');
            $address_id = ($at_store == 1) ? 0 : $this->request->getVar('address_id');

            $status = "awaiting";
            $date_of_service = $this->request->getVar('date_of_service');
            $starting_time = $this->request->getVar('starting_time');

            if (preg_match('/^(\d{1,2}):(\d{2})-(\d{2})$/', $starting_time, $matches)) {
                $starting_time = $matches[1] . ':' . $matches[2] . ':' . $matches[3];
            } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $starting_time, $matches)) {
                $starting_time = $matches[1] . ':' . $matches[2] . ':00';
            }

            $order_note = ($this->request->getVar('order_note')) ? $this->request->getVar('order_note') : "";

            if (empty($this->request->getPost('order_id')) && empty($this->request->getPost('custom_job_request_id'))) {
                $minutes = strtotime($starting_time) + ($cart_data['total_duration'] * 60);
            } else if (!empty($this->request->getPost('custom_job_request_id'))) {
                $minutes = strtotime($starting_time) + ($custom_job_data[0]['duration'] * 60);
            } else {
                $minutes = strtotime($starting_time) + ($duartion * 60);
            }

            $ending_time = date('H:i:s', $minutes);

            if ($at_store != 1) {
                if (!exists(['id' => $address_id], 'addresses')) {
                    return response_helper(labels(ADDRESS_NOT_EXIST, 'Address not exist'));
                }
            }

            $final_total = ($total) + ($visiting_charges);

            $ids = [];
            if (empty($this->request->getPost('custom_job_request_id'))) {
                if (empty($this->request->getPost('order_id'))) {
                    $ids = explode(',', $service_ids ?? '');
                } else {
                    $ids = $service_ids;
                }
            }

            if (!empty($this->request->getPost('custom_job_request_id'))) {
                $qtys = 1;
                $partner_id = $custom_job_data[0]['partner_id'];
                $current_date = date('Y-m-d');
                $service_total_duration = $custom_job_data[0]['duration'];
                $duartion = $custom_job_data[0]['duration'];
            } else {
                $qtys = explode(',', $quantity ?? '');
                $service_data = fetch_details('services', [], '', '', '', '', '', 'id', $ids);
                $partner_id = $service_data[0]['user_id'];
                $current_date = date('Y-m-d');
                $service_total_duration = 0;
                $service_duration = 0;

                if (empty($this->request->getPost('order_id'))) {
                    foreach ($cart_data['data'] as $main_data) {
                        $service_duration = ($main_data['servic_details']['duration']) * $main_data['qty'];
                        $service_total_duration = $service_total_duration + $service_duration;
                    }
                } else {
                    $service_total_duration = $duartion;
                }
            }

            if (empty($this->request->getPost('custom_job_request_id')) && !empty($ids)) {
                $alreadyBookedServices = $this->getAlreadyBookedActiveServices($userId, $ids);

                if (!empty($alreadyBookedServices)) {
                    $serviceTitles = array_unique(array_filter(array_column($alreadyBookedServices, 'service_title')));

                    return response_helper(
                        labels(
                            'same_service_already_booked',
                            'You already have an active booking for: ' . implode(', ', $serviceTitles) . '. Please complete the current booking first.'
                        ),
                        true
                    );
                }
            }

            $availability = checkPartnerAvailability(
                $partner_id,
                $date_of_service . ' ' . $starting_time,
                $service_total_duration,
                $date_of_service,
                $starting_time
            );

            $insert_order = "";

            if (isset($availability) && $availability['error'] === false) {
                $location_data = fetch_details('addresses', ['id' => $address_id]);
                $address['mobile'] = isset($location_data) && !empty($location_data) ? $location_data[0]['mobile'] : '';
                $address['address'] = isset($location_data) && !empty($location_data) ? $location_data[0]['address'] : '';
                $address['area'] = isset($location_data) && !empty($location_data) ? $location_data[0]['area'] : '';
                $address['city'] = isset($location_data) && !empty($location_data) ? $location_data[0]['city'] : '';
                $address['state'] = isset($location_data) && !empty($location_data) ? $location_data[0]['state'] : '';
                $address['country'] = isset($location_data) && !empty($location_data) ? $location_data[0]['country'] : '';
                $address['pincode'] = isset($location_data) && !empty($location_data) ? $location_data[0]['pincode'] : '';
                $city_id = isset($location_data) && !empty($location_data) ? $location_data[0]['city'] : '';

                if (!empty($location_data[0])) {
                    $addrRow = $location_data[0];
                    \App\Models\Addresses_model::buildAddressFromCustomFields($addrRow);
                    $finaladdress = $addrRow['address'] ?? '';
                } else {
                    $outputArray = array(
                        $address['address'],
                        $address['area'],
                        $address['city'],
                        $address['state'],
                        $address['country'],
                        $address['pincode'],
                        $address['mobile']
                    );
                    $finaladdress = implode(',', $outputArray);
                }

                $service_total_duration = 0;
                $service_duration = 0;

                if (!empty($this->request->getPost('custom_job_request_id'))) {
                    $service_total_duration = $custom_job_data[0]['duration'];
                    $duartion = $custom_job_data[0]['duration'];
                } else {
                    if (empty($this->request->getPost('order_id'))) {
                        foreach ($cart_data['data'] as $main_data) {
                            $service_duration = ($main_data['servic_details']['duration']) * $main_data['qty'];
                            $service_total_duration = $service_total_duration + $service_duration;
                        }
                    } else {
                        $service_total_duration = $duartion;
                    }
                }

                $time_slots = get_slot_for_place_order($partner_id, $date_of_service, $service_total_duration, $starting_time);
                $timestamp = date('Y-m-d H:i:s');

                if ($time_slots['slot_avaialble']) {
                    $duration_minutes = $service_total_duration;

                    if ($time_slots['suborder']) {
                        $end_minutes = strtotime($starting_time) + ((sizeof($time_slots['order_data']) * 30) * 60);
                        $ending_time = date('H:i:s', $end_minutes);
                        $day = date('l', strtotime($date_of_service));
                        $timings = getTimingOfDay($partner_id, $day);
                        $closing_time = $timings['closing_time'];

                        if ($ending_time > $closing_time) {
                            $ending_time = $closing_time;
                        }

                        $start_timestamp = strtotime($starting_time);
                        $ending_timestamp = strtotime($ending_time);
                        $duration_seconds = $ending_timestamp - $start_timestamp;
                        $duration_minutes = $duration_seconds / 60;
                    }

                    $order = [
                        'partner_id' => $partner_id,
                        'user_id' => $this->user_details['id'],
                        'city' => $city_id,
                        'total' => $total,
                        'payment_method' => $payment_method,
                        'address_id' => isset($address_id) ? $address_id : "0",
                        'visiting_charges' => $visiting_charges,
                        'address' => isset($finaladdress) ? $finaladdress : "",
                        'date_of_service' => $date_of_service,
                        'starting_time' => $starting_time,
                        'ending_time' => $ending_time,
                        'duration' => $duration_minutes,
                        'status' => $status,
                        'remarks' => $order_note,
                        'otp' => random_int(100000, 999999),
                        'order_latitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['lattitude'] : $this->user_details['latitude'],
                        'order_longitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['longitude'] : $this->user_details['longitude'],
                        'created_at' => $timestamp,
                    ];

                    if (!empty($this->request->getPost('custom_job_request_id'))) {
                        $order['custom_job_request_id'] = $custom_job_data[0]['id'];
                    }

                    if (!empty($promo_code)) {
                        $fetch_promococde = fetch_details('promo_codes', ['id' => $promo_code]);
                        $promo_code = validate_promo_code($this->user_details['id'], $fetch_promococde[0]['id'], $total);
                        if ($promo_code['error']) {
                            return $response['message'] = ($promo_code['message']);
                        }

                        $final_total = $promo_code['data'][0]['final_total'] + $visiting_charges;
                        $order['promo_code'] = $promo_code['data'][0]['promo_code'];
                        $order['promo_discount'] = $promo_code['data'][0]['final_discount'];
                        $order['promocode_id'] = $fetch_promococde[0]['id'];
                    }

                    $order['final_total'] = $final_total;
                    $insert_order = insert_details($order, 'orders');
                }

                if ($time_slots['suborder']) {
                    $next_day_date = date('Y-m-d', strtotime($date_of_service . ' +1 day'));
                    $next_day_slots = get_next_days_slots($closing_time, $date_of_service, $partner_id, $service_total_duration, $current_date);
                    $next_day_available_slots = $next_day_slots['available_slots'];
                    $next_Day_minutes = strtotime($next_day_available_slots[0]) + (($service_total_duration - $duration_minutes) * 60);
                    $next_day_ending_time = date('H:i:s', $next_Day_minutes);

                    $sub_order = [
                        'partner_id' => $partner_id,
                        'user_id' => $this->user_details['id'],
                        'city' => $city_id,
                        'total' => $total,
                        'payment_method' => $payment_method,
                        'address_id' => isset($address_id) ? $address_id : "",
                        'visiting_charges' => $visiting_charges,
                        'address' => isset($finaladdress) ? $finaladdress : "",
                        'date_of_service' => $next_day_date,
                        'starting_time' => isset($next_day_available_slots[0]) ? $next_day_available_slots[0] : 00,
                        'ending_time' => $next_day_ending_time,
                        'duration' => $service_total_duration - $duration_minutes,
                        'status' => $status,
                        'remarks' => "sub_order",
                        'otp' => random_int(100000, 999999),
                        'parent_id' => $insert_order['id'],
                        'order_latitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['lattitude'] : $this->user_details['latitude'],
                        'order_longitude' => isset($location_data) && !empty($location_data) ? $location_data[0]['longitude'] : $this->user_details['longitude'],
                        'created_at' => $timestamp,
                    ];

                    if (!empty($this->request->getPost('custom_job_request_id'))) {
                        $sub_order['custom_job_request_id'] = $custom_job_data[0]['id'];
                    }

                    if (!empty($this->request->getVar('promo_code'))) {
                        $fetch_promococde = fetch_details('promo_codes', ['id' => $this->request->getVar('promo_code_id')]);
                        $promo_code = validate_promo_code($this->user_details['id'], $fetch_promococde[0]['id'], $total);
                        if ($promo_code['error']) {
                            return $response['message'] = ($promo_code['message']);
                        }

                        $final_total = $promo_code['data'][0]['final_total'] + $visiting_charges;
                        $sub_order['promo_code'] = $promo_code['data'][0]['promo_code'];
                        $sub_order['promo_discount'] = $promo_code['data'][0]['final_discount'];
                    }

                    $sub_order['final_total'] = $final_total;
                    $sub_order = insert_details($sub_order, 'orders');
                }

                if ($insert_order) {
                    if (!empty($this->request->getPost('custom_job_request_id'))) {
                        if ($custom_job_data[0]['tax_amount'] == "" || $custom_job_data[0]['tax_amount'] == null) {
                            $tax_amount = 0;
                        } else {
                            $tax_amount = $custom_job_data[0]['tax_amount'];
                        }

                        $data = [
                            'order_id' => $insert_order['id'],
                            'service_id' => '-',
                            'service_title' => $custom_job_data[0]['service_title'],
                            'tax_percentage' => $custom_job_data[0]['tax_percentage'] ?? 0,
                            'tax_amount' => $custom_job_data[0]['tax_amount'] ?? 0,
                            'price' => $custom_job_data[0]['counter_price'],
                            'discount_price' => 0,
                            'quantity' => 1,
                            'sub_total' => strval(str_replace(',', '', number_format(strval(($custom_job_data[0]['counter_price'] * 1 + $tax_amount)), 2))),
                            'status' => $status,
                            'custom_job_request_id' => $custom_job_data[0]['id'],
                        ];

                        insert_details($data, 'order_services');

                        $orderId['order_id'] = $insert_order['id'];
                        $orderId['paystack_link'] = ($payment_method == "paystack") ? base_url() . '/api/v1/paystack_transaction_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . number_format(strval($final_total), 2) : "";
                        $orderId['paypal_link'] = ($payment_method == "paypal") ? base_url() . '/api/v1/paypal_transaction_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . number_format(strval($final_total), 2) : "";
                        $orderId['flutterwave'] = ($payment_method == "flutterwave") ? base_url() . '/api/v1/flutterwave_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . number_format(strval($final_total), 2) : "";
                        $orderId['xendit'] = ($payment_method == "xendit") ? $this->xendit_transaction_webview($this->user_details['id'], $insert_order['id'], $final_total, $partner_id, 'order') : "";
                    } else {
                        for ($i = 0; $i < count($ids); $i++) {
                            $service_details = get_taxable_amount($ids[$i]);
                            $quantity_value = isset($qtys[$i]) ? $qtys[$i] : 1;

                            $data = [
                                'order_id' => $insert_order['id'],
                                'service_id' => $ids[$i],
                                'service_title' => $service_details['title'],
                                'tax_percentage' => $service_details['tax_percentage'],
                                'tax_amount' => number_format(($service_details['tax_amount']), 2),
                                'price' => $service_details['price'],
                                'discount_price' => $service_details['discounted_price'],
                                'quantity' => $quantity_value,
                                'sub_total' => strval(str_replace(',', '', number_format(strval(($service_details['taxable_amount'] * ($quantity_value))), 2))),
                                'status' => $status,
                                'tax_type' => $service_details['tax_type'] ?? null,
                            ];

                            insert_details($data, 'order_services');

                            $orderId['order_id'] = $insert_order['id'];
                            $orderId['paystack_link'] = ($payment_method == "paystack") ? base_url() . '/api/v1/paystack_transaction_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . number_format(strval($final_total), 2) : "";
                            $orderId['paypal_link'] = ($payment_method == "paypal") ? base_url() . '/api/v1/paypal_transaction_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . number_format(strval($final_total), 2) : "";
                            $orderId['flutterwave'] = ($payment_method == "flutterwave") ? base_url() . '/api/v1/flutterwave_webview?user_id=' . $this->user_details['id'] . '&order_id=' . $insert_order['id'] . '&amount=' . number_format(strval($final_total), 2) : "";
                            $orderId['xendit'] = ($payment_method == "xendit") ? $this->xendit_transaction_webview($this->user_details['id'], $insert_order['id'], $final_total, $partner_id, 'order') : "";
                        }
                    }

                    if ($payment_method === 'cod') {
                        if (!empty($this->request->getPost('custom_job_request_id'))) {
                            update_custom_job_status($insert_order['id'], 'booked');
                        }

                        $language = get_current_language_from_request();

                        $notificationContext = [
                            'provider_id' => $partner_id,
                            'user_id' => $this->user_details['id'],
                            'booking_id' => $insert_order['id'],
                            'amount' => $final_total,
                        ];

                        try {
                            queue_notification_service(
                                eventType: 'new_booking_received_for_provider',
                                recipients: ['user_id' => $partner_id],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'],
                                    'language' => $language,
                                    'platforms' => ['android', 'ios', 'web', 'provider_panel']
                                ]
                            );

                            queue_notification_service(
                                eventType: 'new_booking_confirmation_to_customer',
                                recipients: ['user_id' => $this->user_details['id']],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'],
                                    'language' => $language,
                                    'platforms' => ['android', 'ios', 'web']
                                ]
                            );
                        } catch (\Throwable $notificationError) {
                            log_message('error', '[NEW_BOOKING] Notification error trace: ' . $notificationError->getTraceAsString());
                        }
                    }

                    $this->checkAndUpdateSubscriptionStatus($partner_id);
                    return response_helper(labels(ORDER_PLACED_SUCCESSFULLY, 'Order Placed successfully'), false, remove_null_values($orderId));
                } else {
                    return response_helper(labels(ORDER_NOT_PLACED, 'order not placed'));
                }
            } else {
                return response_helper($availability['message'], true);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - place_order()');
            return $this->response->setJSON($response);
        }
    }

    public function get_orders()
    {
        try {
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('sort'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'DESC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $download_invoice = ($this->request->getPost('download_invoice') && !empty($this->request->getPost('download_invoice'))) ? $this->request->getPost('download_invoice') : 1;
            $where = $additional_data = [];

            $custom_request_order = $this->request->getPost('custom_request_order');

            if ($custom_request_order !== null && $custom_request_order == "1") {
                $where['o.custom_job_request_id !='] = "";

                if ($this->request->getPost('id') && !empty($this->request->getPost('id'))) {
                    $where['o.id'] = $this->request->getPost('id');
                }
                if ($this->request->getPost('status') && !empty($this->request->getPost('status'))) {
                    $where['o.status'] = $this->request->getPost('status');
                }
                if ($this->user_details['id'] != '') {
                    $where['o.user_id'] = $this->user_details['id'];
                }
                if ($this->request->getPost('slug') && !empty($this->request->getPost('slug'))) {
                    $slug = $this->request->getPost('slug');
                    $get_id = explode('-', $slug);
                    if (count($get_id) == 2 && strtolower($get_id[0]) === 'inv') {
                        $where['o.id'] = $get_id[1];
                    }
                }

                $orders = new Orders_model();
                $order_detail = $orders->custom_booking_list(true, $search, $limit, $offset, $sort, $order, $where, $download_invoice, '', '', '', '', false);

                if (!empty($order_detail['data'])) {
                    return response_helper(labels(CUSTOM_BOOKING_FETCHED_SUCCESSFULLY, 'Custom booking fetched successfully'), false, remove_null_values($order_detail['data']), 200, ['total' => $order_detail['total']]);
                } else {
                    return response_helper(labels(ORDER_NOT_FOUND, 'Order not found'), false, [], 200, ['total' => "0"]);
                }
            } else {
                if ($this->request->getPost('id') && !empty($this->request->getPost('id'))) {
                    $where['o.id'] = $this->request->getPost('id');
                } else {
                    if (empty($this->request->getPost('slug'))) {
                        if ($custom_request_order !== null && $custom_request_order == "0") {
                            $where['o.custom_job_request_id'] = NULL;
                        } elseif ($custom_request_order === null) {
                            $where['o.custom_job_request_id'] = NULL;
                        }
                    }
                }

                if ($this->request->getPost('status') && !empty($this->request->getPost('status'))) {
                    $where['o.status'] = $this->request->getPost('status');
                }
                if ($this->user_details['id'] != '') {
                    $where['o.user_id'] = $this->user_details['id'];
                }
                if ($this->request->getPost('slug') && !empty($this->request->getPost('slug'))) {
                    $slug = $this->request->getPost('slug');
                    $get_id = explode('-', $slug);
                    if (count($get_id) == 2 && strtolower($get_id[0]) === 'inv') {
                        $where['o.id'] = $get_id[1];
                    }
                }

                $orders = new Orders_model();
                $order_detail = $orders->list(true, $search, $limit, $offset, $sort, $order, $where, $download_invoice, '', '', '', '', false);

                if (!empty($order_detail['data'])) {
                    return response_helper(labels(ORDER_FETCHED_SUCCESSFULLY, 'Order fetched successfully'), false, remove_null_values($order_detail['data']), 200, ['total' => $order_detail['total']]);
                } else {
                    return response_helper(labels(ORDER_NOT_FOUND, 'Order not found'), false, [], 200, ['total' => "0"]);
                }
            }
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_orders()');
            return $this->response->setJSON($response);
        }
    }

    public function update_order_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'order_id' => 'required|numeric',
                    'status' => 'required',
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

            $order_id = $this->request->getPost('order_id');
            $customer_id = $this->user_details['id'];
            $status = $this->request->getPost('status');
            $date = $this->request->getPost('date');
            $selected_time = $this->request->getPost('time');

            if ($status == "rescheduled") {
                $validate = validate_status($order_id, $status, $date, $selected_time, null, null, null, $customer_id, get_current_language_from_request());
                $where['o.id'] = $order_id;
                $orders = new Orders_model();
                $order_detail = $orders->list(true, '', 10, 0, 'o.id', 'DESC', $where, '', '', '', '', '', false);
                $response['error'] = $validate['error'];
                $response['message'] = $validate['message'];
                $response['data'] = $order_detail;
                return $this->response->setJSON($response);
            } else {
                $validate = validate_status($order_id, $status, null, null, null, null, null, $customer_id, get_current_language_from_request());
            }

            if ($validate['error']) {
                $response['error'] = true;
                $response['message'] = $validate['message'];
                return $this->response->setJSON($response);
            } else {
                if ($validate['error']) {
                    $response['error'] = true;
                    $response['message'] = $validate['message'];
                    $response['csrfName'] = csrf_token();
                    $response['csrfHash'] = csrf_hash();
                    $response['data'] = array();
                    return $this->response->setJSON($response);
                }

                if ($status == "awaiting") {
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_IS_IN_AWAITING, 'Order is in Awaiting!'),
                    ];
                    return $this->response->setJSON($response);
                }

                if ($status == "confirmed") {
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_IS_CONFIRMED, 'Order is Confirmed!'),
                    ];
                    return $this->response->setJSON($response);
                }

                if ($status == "cancelled") {
                    $orders = new Orders_model();
                    $where['o.id'] = $order_id;
                    $order_detail = $orders->list(true, '', 10, 0, 'o.id', 'DESC', $where, '', '', '', '', '', false);
                    $response = [
                        'error' => false,
                        'message' => labels(BOOKING_IS_CANCELLED, 'Booking is cancelled!'),
                        'data' => $order_detail,
                    ];
                    return $this->response->setJSON($response);
                }

                if ($status == "completed") {
                    $commision = unsettled_commision($this->userId);
                    update_details(['balance' => $commision], ['id' => $this->userId], 'users');
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_COMPLETED_SUCCESSFULLY, 'Order Completed successfully!'),
                    ];
                    return $this->response->setJSON($response);
                }
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - update_order_status()');
            return $this->response->setJSON($response);
        }
    }

    public function razorpay_create_order()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'order_id' => 'required|numeric',
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

            $order_id = $this->request->getPost('order_id');
            if ($this->request->getPost('order_id') && !empty($this->request->getPost('order_id'))) {
                $where['o.id'] = $this->request->getPost('order_id');
            }

            $orders = new Orders_model();
            $order_detail = $orders->list(true, "", null, null, "", "", $where);
            $settings = get_settings('payment_gateways_settings', true);

            if (!empty($order_detail) && !empty($settings)) {
                if ($this->request->getVar('is_additional_charge') == 1) {
                    $price = $order_detail['data'][0]['total_additional_charge'];
                } else {
                    $price = $order_detail['data'][0]['final_total'];
                }

                $currency = $settings['razorpay_currency'];
                $amount = intval($price * 100);
                $create_order = $this->razorpay->create_order($amount, $order_id, $currency);

                if (!empty($create_order)) {
                    $response = [
                        'error' => false,
                        'message' => labels(RAZORPAY_ORDER_CREATED, 'razorpay order created'),
                        'data' => $create_order,
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(RAZORPAY_ORDER_NOT_CREATED, 'razorpay order not created'),
                        'data' => [],
                    ];
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ];
            }

            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - razorpay_create_order()');
            return $this->response->setJSON($response);
        }
    }

    public function cashfree_create_order()
    {
        try {
            $validation = \Config\Services::validation();

            $rules = [
                'order_id' => 'required|numeric',
                'is_additional_charge' => 'permit_empty|in_list[0,1]',
            ];

            $validation->setRules($rules);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $order_id = (int) $this->request->getPost('order_id');

            $orders = new Orders_model();
            $where = [
                'o.id' => $order_id,
                'o.user_id' => $this->user_details['id'] ?? 0,
            ];
            $order_detail = $orders->list(true, "", null, null, "", "", $where);

            if (empty($order_detail) || empty($order_detail['data'][0])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $settings = get_settings('payment_gateways_settings', true);
            $credentials = $this->cashfree->get_credentials();

            if (
                empty($settings) ||
                ($credentials['status'] ?? 'disable') !== 'enable' ||
                empty($credentials['app_id']) ||
                empty($credentials['secret_key'])
            ) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $is_additional_charge = $this->request->getVar('is_additional_charge') == 1;
            $price = $is_additional_charge
                ? ($order_detail['data'][0]['total_additional_charge'] ?? 0)
                : ($order_detail['data'][0]['final_total'] ?? 0);

            if (!is_numeric($price) || (float) $price <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $currency = $settings['cashfree_currency'] ?? ($credentials['currency'] ?? 'INR');
            $user_id = (int) ($this->user_details['id'] ?? 0);
            $user = fetch_details('users', ['id' => $user_id]);
            $user = $user[0] ?? [];

            $customer_name = trim($user['username'] ?? '');
            if ($customer_name !== '') {
                $customer_name = (strlen($customer_name) < 3) ? $user['username'] . '_' . $user_id : $customer_name;
            } else {
                $customer_name = 'Customer_' . $user_id;
            }

            $customer_email = $this->user_details['email'] ?? ($user['email'] ?? '');
            if (empty($customer_email)) {
                $customer_email = 'customer' . $user_id . '@example.com';
            }

            $customer_phone_raw = $this->user_details['phone'] ?? ($user['phone'] ?? '');
            $customer_phone = preg_replace('/\D+/', '', (string) $customer_phone_raw);
            if (empty($customer_phone)) {
                $customer_phone = '9999999999';
            }

            $gateway_order_id = $is_additional_charge
                ? ('additional_charges_' . $order_id . '_' . time())
                : ('order_' . $order_id . '_' . time());

            $return_url_base = !empty($credentials['website_url'])
                ? rtrim($credentials['website_url'], '/')
                : rtrim(base_url(), '/');

            $payload = [
                'order_id' => $gateway_order_id,
                'order_amount' => (float) number_format((float) $price, 2, '.', ''),
                'order_currency' => strtoupper($currency),
                'customer_details' => [
                    'customer_id' => 'user_' . $user_id,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'customer_phone' => $customer_phone,
                ],
                'order_meta' => [
                    'return_url' => $return_url_base . '/payment-status?order_id=' . $order_id . '&payment_status=pending',
                ],
                'order_note' => $is_additional_charge
                    ? ('Additional charges payment for order #' . $order_id)
                    : ('Booking payment for order #' . $order_id),
                'order_tags' => [
                    'order_id' => (string) $order_id,
                    'user_id' => (string) $user_id,
                    'payment_for' => $is_additional_charge ? 'additional_charges' : 'booking',
                ],
            ];

            if ($is_additional_charge) {
                $payload['order_tags']['additional_charges_transaction_id'] = (string) $order_id;
            }

            $create_order = $this->cashfree->create_order($payload);

            if (!empty($create_order['error']) || empty($create_order['payment_session_id'])) {
                $message = $create_order['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $create_order,
                ]);
            }

            if ($is_additional_charge) {
                update_details(['reference' => $gateway_order_id], ['id' => $order_id], 'orders');
            } else {
                $pending_transaction = fetch_details(
                    'transactions',
                    [
                        'order_id' => $order_id,
                        'user_id' => $user_id,
                        'type' => 'cashfree',
                        'status' => 'pending'
                    ],
                    ['id'],
                    1,
                    0,
                    'id',
                    'DESC'
                );

                if (!empty($pending_transaction[0]['id'])) {
                    update_details(['reference' => $gateway_order_id], ['id' => $pending_transaction[0]['id']], 'transactions');
                }
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Cashfree order created'),
                'data' => $create_order,
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') .
                    ' Params :: ' . json_encode($_POST) .
                    ' Issue => ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - cashfree_create_order()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    public function get_cashfree_order_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'order_id' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $order_id = (string) $this->request->getPost('order_id');

            $transaction = fetch_details(
                'transactions',
                ['reference' => $order_id],
                ['id', 'order_id', 'user_id', 'type', 'txn_id', 'amount', 'status', 'message', 'transaction_date', 'currency_code', 'reference'],
                1,
                0,
                'id',
                'DESC'
            );

            if (!empty($transaction[0]) && in_array($transaction[0]['status'], ['success', 'failed'])) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Transaction fetched successfully'),
                    'data' => [
                        'source' => 'local',
                        'transaction' => $transaction[0],
                    ],
                ]);
            }

            $credentials = $this->cashfree->get_credentials();
            if (
                ($credentials['status'] ?? 'disable') !== 'enable' ||
                empty($credentials['app_id']) ||
                empty($credentials['secret_key'])
            ) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'Payment gateway not configured'),
                    'data' => [],
                ]);
            }

            $cf_order = $this->cashfree->fetch_order($order_id);

            if (!empty($cf_order['error'])) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $cf_order['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                    'data' => $cf_order,
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Cashfree order status fetched'),
                'data' => [
                    'source' => 'cashfree',
                    'order' => $cf_order,
                    'transaction' => $transaction[0] ?? null,
                ],
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') .
                    ' Params :: ' . json_encode($_POST) .
                    ' Issue => ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_cashfree_order_status()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    public function create_stripe_payment_intent()
    {
        try {
            $validation = \Config\Services::validation();

            $transaction_id_raw = $this->request->getPost('transaction_id');

            $rules = [
                'transaction_id' => 'permit_empty|numeric',
            ];

            if (empty($transaction_id_raw)) {
                $rules['order_id'] = 'required|numeric';
            } else {
                $rules['order_id'] = 'permit_empty|numeric';
            }

            $validation->setRules($rules);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validation->getErrors(),
                    'data' => [],
                ]);
            }

            $order_id = (int) $this->request->getPost('order_id');
            $transaction_id = !empty($transaction_id_raw) ? (int) $transaction_id_raw : null;

            if (!empty($transaction_id)) {
                $tx = fetch_details('transactions', [
                    'id' => $transaction_id,
                    'user_id' => $this->user_details['id'] ?? 0,
                ]);

                if (empty($tx)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                        'data' => [],
                    ]);
                }

                $order_id = (int) $tx[0]['order_id'];
            }

            $orders = new Orders_model();
            $where = [
                'o.id' => $order_id,
                'o.user_id' => $this->user_details['id'] ?? 0,
            ];

            $order_detail = $orders->list(true, "", null, null, "", "", $where);

            $settings = get_settings('payment_gateways_settings', true);

            if (empty($order_detail) || empty($order_detail['data'][0]) || empty($settings)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $currency = $settings['stripe_currency'] ?? 'USD';
            $is_additional_charge = !empty($transaction_id);

            if ($is_additional_charge) {
                $price = $order_detail['data'][0]['total_additional_charge'] ?? 0;
            } else {
                $price = $order_detail['data'][0]['final_total'] ?? 0;
            }

            if (!is_numeric($price) || (float) $price <= 0) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                    'data' => [],
                ]);
            }

            $amount_minor = StripeMoney::toMinorUnits($price, $currency);

            $metadata = [
                'order_id' => (string) $order_id,
                'user_id' => (string) ($this->user_details['id'] ?? 0),
            ];

            if ($is_additional_charge) {
                $metadata['additional_charges_transaction_id'] = (string) $transaction_id;
            }

            $company_title = getTranslatedSetting('general_settings', 'company_title');

            $description = $is_additional_charge
                ? 'Payment for additional charges - Order #' . $order_id . ' on ' . $company_title
                : 'Payment for Order #' . $order_id . ' on ' . $company_title;

            $intent = $this->stripe->create_payment_intent([
                'amount' => $amount_minor,
                'metadata' => $metadata,
                'description' => $description,
            ]);

            if (isset($intent['error']) || empty($intent['client_secret'])) {
                $message = $intent['error']['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');

                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $intent,
                ]);
            }

            $billing_details = [
                'name'  => $this->user_details['username'] ?? '',
                'email' => $this->user_details['email'] ?? '',
                'phone' => $this->user_details['phone'] ?? '',
            ];

            $order_address = $order_detail['data'][0]['address'] ?? '';
            if (!empty($order_address)) {
                $billing_details['address'] = [
                    'line1' => $order_address,
                ];
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Stripe payment intent created'),
                'data' => $intent,
                'billing_details' => $billing_details,
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') .
                    ' Params :: ' . json_encode($_POST) .
                    ' Issue => ' . $th->getMessage(),
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - create_stripe_payment_intent()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    public function get_stripe_payment_status()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'payment_intent_id' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ]);
            }

            $payment_intent_id = (string) $this->request->getPost('payment_intent_id');
            $intent = $this->stripe->get_payment_intent($payment_intent_id);

            if (isset($intent['error'])) {
                $message = $intent['error']['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong');
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $message,
                    'data' => $intent,
                ]);
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Stripe payment intent fetched'),
                'data' => $intent,
            ]);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce(
                $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_stripe_payment_status()'
            );
            return $this->response->setJSON($response);
        }
    }

    public function invoice_download()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'order_id' => ['rules' => 'required', 'errors' => ['required' => labels('order_id_is_required', 'Order ID is required')]],
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

            $order_id = $this->request->getPost('order_id');
            $userId = $this->user_details['id'];

            $ordersModel = new Orders_model();
            $partnersModel = new Partners_model();
            $usersModel = new Users_model();

            $orders = $ordersModel->where('id', $order_id)->where('user_id', $userId)->get()->getResultArray();

            if (isset($orders) && empty($orders)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    'data' => []
                ]);
            }

            $orderDetails = $ordersModel->invoice($order_id)['order'];
            $partnerId = $orderDetails['partner_id'];
            $partnerDetails = $partnersModel
                ->from('partner_details pd')
                ->select('pd.company_name, pd.address, u.email, u.phone, u.image')
                ->join('users u', 'u.id = pd.partner_id')
                ->where('pd.partner_id', $partnerId)
                ->get()
                ->getResultArray();

            if (!empty($partnerDetails[0])) {
                $translatedData = get_translated_partner_field(partnerId: $partnerId, fieldName: 'company_name', defaultValue: $partnerDetails[0]['company_name']);
                $partnerDetails[0]['translated_company_name'] = $translatedData;

                $partnerDetails[0]['image'] = (!empty($partnerDetails[0]['image']) && file_exists(FCPATH . 'public/backend/assets/profiles/' . basename($partnerDetails[0]['image'])))
                    ? base_url('public/backend/assets/profiles/' . basename($partnerDetails[0]['image']))
                    : '';
            }

            $userDetails = $usersModel->where('id', $userId)->get()->getResultArray();
            $settings = get_settings('general_settings', true);

            $this->data['currency'] = $settings['currency'];
            $this->data['order'] = $orderDetails;
            $this->data['partner_details'] = $partnerDetails[0];
            $this->data['user_details'] = $userDetails[0];
            $this->data['data'] = $settings;

            $currency = $settings['currency'];
            $services = $orderDetails['services'];
            $total = count($services);

            if (!empty($orderDetails)) {
                $i = 0;
                $sum_net_amount = 0;
                $sum_tax_amount = 0;
                $rows = [];

                foreach ($services as &$service) {
                    $original_price = (float) ($service['price'] ?? 0);
                    $discount_price = (float) ($service['discount_price'] ?? 0);
                    $qty = (int) ($service['quantity'] ?? 1);
                    $currency_symbol = $currency;

                    $stored_tax = (float) ($service['tax_amount'] ?? 0);
                    $line_tax = $stored_tax * $qty;

                    $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                    $line_net = $unitPrice * $qty;

                    $sum_net_amount += $service['tax_type'] == 'included' ? $line_net - $line_tax : $service['sub_total'] - $line_tax;
                    $sum_tax_amount += $line_tax;

                    $rows[$i] = [
                        'service_title' => ucwords($service['service_title']),
                        'price' => $currency_symbol . number_format($original_price, 2, '.', ''),
                        'discount' => ($discount_price == 0) ? $currency_symbol . "0.00" : $currency_symbol . number_format(($original_price - $discount_price), 2, '.', ''),
                        'net_amount' => $currency_symbol . number_format($unitPrice, 2, '.', ''),
                        'tax' => ($service['tax_percentage'] ?? '') . '%',
                        'tax_amount' => $currency_symbol . number_format($line_tax, 2, '.', ''),
                        'subtotal' => $currency_symbol . number_format($service['sub_total'], 2, '.', '')
                    ];
                    $i++;
                }

                $array['total'] = $total;
                $array['rows'] = $rows;

                $this->data['order']['total'] = number_format($sum_net_amount, 2, '.', '');
                $this->data['order']['tax'] = number_format($sum_tax_amount, 2, '.', '');
                $sub_total_incl_tax = $sum_net_amount + $sum_tax_amount;
                $visiting_charges = (float) ($orderDetails['visiting_charges'] ?? 0);
                $this->data['order']['sub_total'] = number_format($sub_total_incl_tax, 2, '.', '');
                $this->data['order']['overall_amount'] = number_format($sub_total_incl_tax + $visiting_charges, 2, '.', '');
                $this->data['rows'] = $rows;
                $this->data['currency'] = $currency;

                try {
                    $html = view('backend/admin/pages/invoice_from_api', $this->data);
                    $path = "public/uploads/";
                    $mpdf = new \Mpdf\Mpdf([
                        'tempDir' => $path,
                        'defaultFont' => 'dejavusans',
                        'mode' => 'utf-8',
                    ]);

                    $stylesheet = file_get_contents('public/backend/assets/css/vendor/bootstrap-table.css');
                    $mpdf->WriteHTML($stylesheet, 1);
                    $mpdf->WriteHTML($html);
                    $this->response->setHeader("Content-Type", "application/pdf");
                    $mpdf->Output('order-ID-' . $orderDetails['id'] . "-invoice.pdf", 'I');
                } catch (\Mpdf\MpdfException $e) {
                    print "Creating an mPDF object failed";
                    log_message('error', 'Creating an mPDF object failed with: ' . $e->getMessage());
                }
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - invoice_download()');
            return $this->response->setJSON($response);
        }
    }

    public function get_paypal_link()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'user_id' => 'required|numeric',
                    'order_id' => 'required',
                    'amount' => 'required',
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

            $user_id = $_POST['user_id'];
            $order_id = $_POST['order_id'];
            $amount = $_POST['amount'];

            $response = [
                'error' => false,
                'message' => labels(ORDER_DETAIL_FOUNDED, 'Order Detail Founded !'),
                'data' => base_url('/api/v1/paypal_transaction_webview?' . 'user_id=' . $user_id . '&order_id=' . $order_id . '&amount=' . intval($amount)),
            ];

            $token = $this->paypal_lib->generate_token();
            return $this->response->setJSON($token);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_paypal_link()');
            return $this->response->setJSON($response);
        }
    }

    public function paypal_transaction_webview()
    {
        try {
            header("Content-Type: html");

            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'user_id' => 'required|numeric',
                    'order_id' => 'required',
                    'amount' => 'required',
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

            $user_id = $_GET['user_id'];
            $order_id = $_GET['order_id'];
            $amount = $_GET['amount'];
            $user = fetch_details('users', ['id' => $user_id]);

            if (empty($user)) {
                echo labels(USER_NOT_FOUND, "user not found");
                return false;
            }

            $order_res = fetch_details('orders', ['id' => $order_id]);
            $data['user'] = $user[0];
            $data['payment_type'] = "paypal";
            $encryption = order_encrypt($user_id, $amount, $order_id);

            if (!empty($order_res)) {
                $data['order'] = $order_res[0];
                $payment_gateways_settings = get_settings('payment_gateways_settings', true);

                if ($payment_gateways_settings['paypal_website_url'] != "") {
                    $return_url = $payment_gateways_settings['paypal_website_url'] . "/payment-status?order_id=" . $this->request->getVar('order_id') . "&payment_status=success";
                } else {
                    $return_url = base_url() . '/api/v1/app_payment_status?order_id=' . $encryption . '&payment_status=success';
                }

                if ($payment_gateways_settings['paypal_website_url'] != "") {
                    $cancel_url = $payment_gateways_settings['paypal_website_url'] . "/payment-status?order_id=" . $this->request->getVar('order_id') . "&payment_status=cancelled";
                } else {
                    $cancel_url = base_url() . '/api/v1/app_payment_status?order_id=' . $encryption . '&payment_status=cancelled';
                }

                $notifyURL = base_url() . 'api/webhooks/paypal';
                $txn_id = time() . "-" . rand();

                $userID = $data['user']['id'];
                $order_id = $data['order']['id'];
                $payeremail = $data['user']['email'];

                $this->paypal_lib->add_field('return', $return_url);
                $this->paypal_lib->add_field('cancel_return', $cancel_url);
                $this->paypal_lib->add_field('notify_url', $notifyURL);
                $this->paypal_lib->add_field('item_name', 'Test');

                if (isset($_GET['additional_charges_transaction_id'])) {
                    $this->paypal_lib->add_field('custom', $userID . '|' . $payeremail . '|' . $_GET['additional_charges_transaction_id']);
                } else {
                    $this->paypal_lib->add_field('custom', $userID . '|' . $payeremail);
                }

                $this->paypal_lib->add_field('item_number', $order_id);
                $this->paypal_lib->add_field('amount', $amount);
                $this->paypal_lib->paypal_auto_form();
            } else {
                $data['user'] = $user[0];
                $data['payment_type'] = "paypal";
                $returnURL = base_url() . '/api/v1/app_payment_status';
                $cancelURL = base_url() . '/api/v1/app_payment_status';
                $notifyURL = base_url() . '/api/webhooks/paypal';
                $txn_id = time() . "-" . rand();
                $userID = $data['user']['id'];
                $payeremail = $data['user']['email'];

                $this->paypal_lib->add_field('return', $returnURL);
                $this->paypal_lib->add_field('cancel_return', $cancelURL);
                $this->paypal_lib->add_field('notify_url', $notifyURL);
                $this->paypal_lib->add_field('item_name', 'Online shopping');

                if (isset($_GET['additional_charges_transaction_id'])) {
                    $this->paypal_lib->add_field('custom', $userID . '|' . $payeremail . '|' . $_GET['additional_charges_transaction_id']);
                } else {
                    $this->paypal_lib->add_field('custom', $userID . '|' . $payeremail);
                }

                $this->paypal_lib->add_field('item_number', $order_id);
                $this->paypal_lib->add_field('amount', $amount);
                $this->paypal_lib->paypal_auto_form();
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - paypal_transaction_webview()');
            return $this->response->setJSON($response);
        }
    }

    public function app_payment_status()
    {
        try {
            $paypalInfo = $_GET;

            if (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "completed") {
                $response['error'] = false;
                $response['message'] = labels(PAYMENT_COMPLETED_SUCCESSFULLY, "Payment Completed Successfully");
                $response['data'] = $paypalInfo;
                $response['payment_status'] = "Completed";
            } elseif (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "authorized") {
                $response['error'] = false;
                $response['message'] = labels(YOUR_PAYMENT_IS_HAS_BEEN_AUTHORIZED_SUCCESSFULLY_WE_WILL_CAPTURE_YOUR_TRANSACTION_WITHIN_30_MINUTES_ONCE_WE_PROCESS_YOUR_ORDER_AFTER_SUCCESSFUL_CAPTURE_COINS_WILL_BE_CREDITED_AUTOMATICALLY, "Your payment is has been Authorized successfully. We will capture your transaction within 30 minutes, once we process your order. After successful capture coins wil be credited automatically.");
                $response['data'] = $paypalInfo;
            } elseif (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "Pending") {
                $response['error'] = false;
                $response['message'] = labels(YOUR_PAYMENT_IS_PENDING_AND_IS_UNDER_PROCESS_WE_WILL_NOTIFY_YOU_ONCE_THE_STATUS_IS_UPDATED, "Your payment is pending and is under process. We will notify you once the status is updated.");
                $response['data'] = $paypalInfo;
                $response['payment_status'] = "Pending";
            } else {
                $order_id = order_decrypt($_GET['order_id']);
                update_details(['payment_status' => 2], ['id' => $order_id[2]], 'orders');
                update_details(['status' => 'cancelled'], ['id' => $order_id[2]], 'orders');

                $data = [
                    'transaction_type' => 'transaction',
                    'user_id' => $order_id[0],
                    'partner_id' => "",
                    'order_id' => $order_id[2],
                    'type' => 'paypal',
                    'txn_id' => "",
                    'amount' => $order_id[1],
                    'status' => 'failed',
                    'currency_code' => "",
                    'message' => 'Booking is cancelled',
                ];

                $insert_id = add_transaction($data);
                $response['error'] = true;
                $response['message'] = labels(PAYMENT_CANCELLED_DECLINED, "Payment Cancelled / Declined");
                $response['payment_status'] = "Failed";
                $response['data'] = $_GET;
            }

            print_r(json_encode($response));
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - app_payment_status()');
            return $this->response->setJSON($response);
        }
    }

    public function checkAndUpdateSubscriptionStatus($partnerId)
    {
        try {
            $partnerSubscriptionModel = new Partner_subscription_model();
            $subscriptionData = $partnerSubscriptionModel
                ->where('partner_id', $partnerId)
                ->where('status', 'active')
                ->where('order_type', 'limited')
                ->where('price !=', 0)
                ->first();

            if (!$subscriptionData) {
                return;
            }

            $subscriptionCount = count_orders_towards_subscription_limit($partnerId, $subscriptionData['updated_at'], [], null);
            if ($subscriptionCount >= $subscriptionData['max_order_limit']) {
                $data['status'] = 'deactive';
                $where['partner_id'] = $partnerId;
                $where['status'] = 'active';
                update_details($data, $where, 'partner_subscriptions');
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - checkAndUpdateSubscriptionStatus()');
            return $this->response->setJSON($response);
        }
    }

    public function verify_transaction()
    {
        $validation = service('validation');
        $validation->setRules([
            'order_id' => 'required|numeric',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            $errors = $validation->getErrors();
            $response = [
                'error' => true,
                'message' => $errors,
                'data' => [],
            ];
            return $this->response->setJSON($response);
        }

        $transaction_model = new Transaction_model();
        $order_id = (int) $this->request->getVar('order_id');
        $transaction = fetch_details('transactions', ['order_id' => $order_id, 'user_id' => $this->user_details['id']]);
        $settings = get_settings('payment_gateways_settings', true);

        if (!empty($transaction)) {
            $transaction_id = $transaction[0]['txn_id'];
            $payment_gateways = $transaction[0]['type'];

            if ($payment_gateways == 'razorpay') {
                $razorpay = new Razorpay;
                $credentials = $razorpay->get_credentials();
                $secret = $credentials['secret'];
                $api = new Api($credentials['key'], $secret);
                $data = $api->payment->fetch($transaction_id);
                $status = $data->status;

                if ($status == "captured") {
                    $cart_data = fetch_cart(true, $this->user_details['id']);
                    if (!empty($cart_data)) {
                        foreach ($cart_data['data'] as $row) {
                            delete_details(['id' => $row['id']], 'cart');
                        }
                    }

                    $response = [
                        'error' => true,
                        'message' => labels(VERIFIED, 'verified'),
                        'data' => [],
                    ];
                    return $this->response->setJSON($response);
                }
            }

            if ($payment_gateways == "cashfree") {
                $gateway_order_id = $transaction[0]['reference'] ?? '';
                if (empty($gateway_order_id)) {
                    $response = [
                        'error' => true,
                        'message' => labels(DETAILS_NOT_FOUND, 'details not found'),
                        'data' => [],
                    ];
                    return $this->response->setJSON($response);
                }

                $order_response = $this->cashfree->fetch_order($gateway_order_id);
                $payments_response = $this->cashfree->fetch_order_payments($gateway_order_id);

                $response = [
                    'error' => false,
                    'message' => labels(VERIFIED, 'verified'),
                    'data' => [
                        'order' => $order_response,
                        'payments' => $payments_response,
                    ],
                ];
                return $this->response->setJSON($response);
            }

            if ($payment_gateways == "paystack") {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $transaction[0]['reference'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: Bearer " . $settings['paystack_secret'],
                        "Cache-Control: no-cache",
                    ),
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                unset($curl);

                $response = [
                    'error' => false,
                    'message' => labels(VERIFIED, 'verified'),
                    'data' => json_decode($response),
                ];
                return $this->response->setJSON($response);
            }

            if ($payment_gateways == "paypal") {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api-m.sandbox.paypal.com/v2/payments/captures/' . $transaction[0]['txn_id'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Basic ' . base64_encode($settings['paypal_client_key'] . ':' . $settings['paypal_secret_key']),
                        'Content-Type: application/json',
                        'Cookie: l7_az=ccg14.slc'
                    ),
                ));
                $response1 = curl_exec($curl);
                unset($curl);

                $response = [
                    'error' => false,
                    'message' => labels(VERIFIED, 'verified'),
                    'data' => json_decode($response1),
                ];
                return $this->response->setJSON($response);
            }
        }
    }

    public function capturePayment()
    {
        try {
            $apiEndpoint = 'https://api-m.sandbox.paypal.com';
            $requestData = json_encode([
                "intent" => "CAPTURE",
                "purchase_units" => [],
                "application_context" => [
                    "return_url" => "https://example.com/return",
                    "cancel_url" => "https://example.com/cancel"
                ]
            ]);

            $options = [
                CURLOPT_URL            => $apiEndpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $requestData,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                ],
            ];

            $ch = curl_init();
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            unset($ch);
            echo $response;
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            return $this->response->setJSON($response);
        }
    }

    public function paystack_transaction_webview()
    {
        header("Content-Type: text/html");

        $validation = \Config\Services::validation();
        $validation->setRules(
            [
                'user_id' => 'required|numeric',
                'order_id' => 'required',
                'amount' => 'required',
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

        $user_id = $_GET['user_id'];
        $order_id = $_GET['order_id'];
        $amount = intval(str_replace(',', '', $_GET['amount']));
        $user_data = fetch_details('users', ['id' => $user_id])[0];
        $paystack = new Paystack();
        $paystack_credentials = $paystack->get_credentials();
        $secret_key = $paystack_credentials['secret'];
        $url = "https://api.paystack.co/transaction/initialize";
        $encryption = order_encrypt($user_id, $amount, $order_id);

        $fields = [
            'email' => $user_data['email'],
            'amount' => $amount * 100,
            'currency' => $paystack_credentials['currency'],
            'callback_url' => base_url() . 'api/v1/app_paystack_payment_status?payment_status=Completed',
            'metadata' => [
                'cancel_action' => base_url() . 'api/v1/app_paystack_payment_status?order_id=' . $encryption . '&payment_status=Failed',
                'order_id' => $order_id,
            ]
        ];

        if (isset($_GET['additional_charges_transaction_id'])) {
            $transaction_id = $_GET['additional_charges_transaction_id'];
            $fields['metadata']['additional_charges_transaction_id'] = $transaction_id;
        }

        $fields_string = http_build_query($fields);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $secret_key,
            "Cache-Control: no-cache",
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        unset($ch);
        $result_data = json_decode($result, true);

        if (isset($result_data['data']['authorization_url'])) {
            header('Location: ' . $result_data['data']['authorization_url']);
            exit;
        } else {
            $response = [
                'error' => true,
                'message' => labels(FAILED_TO_INITIALIZE_TRANSACTION, 'Failed to initialize transaction'),
                'data' => $result_data,
            ];
            return $this->response->setJSON($response);
        }
    }

    public function app_paystack_payment_status()
    {
        $data = $_GET;

        if (isset($data['reference']) && isset($data['trxref']) && isset($data['payment_status'])) {
            $response['error'] = false;
            $response['message'] = labels(PAYMENT_COMPLETED_SUCCESSFULLY, 'Payment Completed Successfully');
            $response['payment_status'] = "Completed";
            $response['data'] = $data;
        } elseif (isset($data['order_id']) && isset($data['payment_status'])) {
            $order_id = order_decrypt($_GET['order_id']);
            update_details(['payment_status' => 2], ['id' => $order_id[2]], 'orders');
            update_details(['status' => 'cancelled'], ['id' => $order_id[2]], 'orders');

            $data = [
                'transaction_type' => 'transaction',
                'user_id' => $order_id[0],
                'partner_id' => "",
                'order_id' => $order_id[2],
                'type' => 'paystack',
                'txn_id' => "",
                'amount' => $order_id[1],
                'status' => 'failed',
                'currency_code' => "",
                'message' => 'Booking is cancelled',
            ];

            add_transaction($data);

            $response['error'] = true;
            $response['message'] = labels(PAYMENT_CANCELLED_DECLINED, 'Payment Cancelled / Declined');
            $response['payment_status'] = "Failed";
            $response['data'] = $_GET;
        }

        print_r(json_encode($response));
    }

    public function flutterwave_webview()
    {
        try {
            header("Content-Type: application/json");

            $validation = \Config\Services::validation();
            $validation->setRules([
                'user_id' => 'required|numeric',
                'order_id' => 'required',
                'amount' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }

            $settings = get_settings('general_settings', true);
            $logo = base_url("public/uploads/site/" . $settings['logo']);
            $user_id = $this->request->getVar('user_id');
            $user = fetch_details('users', ['id' => $user_id]);

            if (empty($user)) {
                $response = [
                    'error' => true,
                    'message' => labels(USER_NOT_FOUND, 'User not found!'),
                ];
                return $this->response->setJSON($response);
            }

            $flutterwave = new Flutterwave();
            $flutterwave_credentials = $flutterwave->get_credentials();
            $payment_gateways_settings = get_settings('payment_gateways_settings', true);

            if ($payment_gateways_settings['flutterwave_website_url'] != "") {
                $return_url = $payment_gateways_settings['flutterwave_website_url'] . "/payment-status?order_id=" . $this->request->getVar('order_id');
            } else {
                $return_url = base_url('/api/v1/flutterwave_payment_status');
            }

            $currency = $flutterwave_credentials['currency_code'] ?? "NGN";

            $meta_data = [
                'user_id' => $user_id,
                'order_id' => $this->request->getVar('order_id'),
            ];

            if (isset($_GET['additional_charges_transaction_id'])) {
                $transaction_id = $_GET['additional_charges_transaction_id'];
                $meta_data['additional_charges_transaction_id'] = $transaction_id;
            }

            $company_title = getTranslatedSetting('general_settings', 'company_title');

            $data = [
                'tx_ref' => "eDemand-" . time() . "-" . rand(1000, 9999),
                'amount' => $this->request->getVar('amount'),
                'currency' => $currency,
                'redirect_url' => $return_url,
                'payment_options' => 'card',
                'meta' => $meta_data,
                'customer' => [
                    'email' => (!empty($user[0]['email'])) ? $user[0]['email'] : $settings['support_email'],
                    'phonenumber' => $user[0]['phone'] ?? '',
                    'name' => $user[0]['username'] ?? '',
                ],
                'customizations' => [
                    'title' => $company_title . " Payments",
                    'description' => "Online payments on " . $company_title,
                    'logo' => (!empty($logo)) ? $logo : "",
                ],
            ];

            $payment = $flutterwave->create_payment($data);

            if (!empty($payment)) {
                $payment = json_decode($payment, true);
                if (isset($payment['status']) && $payment['status'] == 'success' && isset($payment['data']['link'])) {
                    $response = [
                        'error' => false,
                        'message' => labels(PAYMENT_LINK_GENERATED_FOLLOW_THE_LINK_TO_MAKE_THE_PAYMENT, 'Payment link generated. Follow the link to make the payment!'),
                        'link' => $payment['data']['link'],
                    ];
                    header('Location: ' . $payment['data']['link']);
                    exit;
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(COULD_NOT_INITIATE_PAYMENT, 'Could not initiate payment. ' . $payment['message']),
                        'link' => "",
                    ];
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(COULD_NOT_INITIATE_PAYMENT_TRY_AGAIN_LATER, 'Could not initiate payment. Try again later!'),
                    'link' => "",
                ];
            }

            print_r(json_encode($response));
        } catch (\Throwable $th) {
            log_message('error', 'Error in Flutterwave Webview: ' . $th->getMessage() . "\n" . $th->getTraceAsString());

            $response = [
                'error' => true,
                'message' => labels(AN_ERROR_OCCURRED_PLEASE_TRY_AGAIN_LATER, 'An error occurred. Please try again later.'),
            ];

            if (ENVIRONMENT === 'development') {
                $response['error_message'] = $th->getMessage();
                $response['error_trace'] = $th->getTraceAsString();
            }

            return $this->response->setJSON($response);
        }
    }

    public function flutterwave_payment_status()
    {
        if (isset($_GET['transaction_id']) && !empty($_GET['transaction_id'])) {
            $transaction_id = $_GET['transaction_id'];
            $flutterwave = new Flutterwave();
            $transaction = $flutterwave->verify_transaction($transaction_id);

            if (!empty($transaction)) {
                $transaction = json_decode($transaction, true);

                if ($transaction['status'] == 'error') {
                    $response['error'] = true;
                    $response['message'] = $transaction['message'];
                    $response['amount'] = 0;
                    $response['status'] = "failed";
                    $response['currency'] = "NGN";
                    $response['transaction_id'] = $transaction_id;
                    $response['reference'] = "";
                    print_r(json_encode($response));
                    return false;
                }

                if ($transaction['status'] == 'success' && $transaction['data']['status'] == 'successful') {
                    $response['error'] = false;
                    $response['message'] = labels(PAYMENT_HAS_BEEN_COMPLETED_SUCCESSFULLY, 'Payment has been completed successfully');
                    $response['amount'] = $transaction['data']['amount'];
                    $response['currency'] = $transaction['data']['currency'];
                    $response['status'] = $transaction['data']['status'];
                    $response['transaction_id'] = $transaction['data']['id'];
                    $response['reference'] = $transaction['data']['tx_ref'];
                    print_r(json_encode($response));
                    return false;
                } else if ($transaction['status'] == 'success' && $transaction['data']['status'] != 'successful') {
                    $response['error'] = true;
                    $response['message'] = labels(PAYMENT_IS, "Payment is ") . $transaction['data']['status'];
                    $response['amount'] = $transaction['data']['amount'];
                    $response['currency'] = $transaction['data']['currency'];
                    $response['status'] = $transaction['data']['status'];
                    $response['transaction_id'] = $transaction['data']['id'];
                    $response['reference'] = $transaction['data']['tx_ref'];

                    update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $transaction['meta']['order_id']], 'orders');

                    $data = [
                        'transaction_type' => 'transaction',
                        'user_id' =>  $transaction['meta']['order_id'],
                        'partner_id' => "",
                        'order_id' =>  $transaction['meta']['order_id'],
                        'type' => 'flutterwave',
                        'txn_id' => "",
                        'amount' => $transaction['data']['amount'],
                        'status' => 'failed',
                        'currency_code' => "",
                        'message' => 'Booking is cancelled',
                    ];

                    $insert_id = add_transaction($data);
                    print_r(json_encode($response));
                    return false;
                }
            } else {
                $response['error'] = true;
                $response['message'] = labels(TRANSACTION_NOT_FOUND, 'Transaction not found');
                print_r(json_encode($response));
            }
        } else {
            $response['error'] = true;
            $response['message'] = labels(INVALID_REQUEST, 'Invalid request!');
            print_r(json_encode($response));
            return false;
        }
    }

    public function xendit_payment_status()
    {
        try {
            $status = $_GET['status'] ?? 'failed';
            $order_id = $_GET['order_id'] ?? '';

            if ($status === 'successful') {
                $response = [
                    'error' => false,
                    'message' => labels(PAYMENT_COMPLETED_SUCCESSFULLY, 'Payment Completed Successfully'),
                    'payment_status' => "Completed",
                    'data' => $_GET
                ];
            } else {
                if (!empty($order_id)) {
                    update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $order_id], 'orders');
                }

                $response = [
                    'error' => true,
                    'message' => labels(PAYMENT_FAILED_OR_CANCELLED, 'Payment Failed or Cancelled'),
                    'payment_status' => "Failed",
                    'data' => $_GET
                ];
            }

            print_r(json_encode($response));
        } catch (\Exception $th) {
            $response = [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'payment_status' => 'Failed'
            ];
            log_the_responce('Xendit Payment Status Error: ' . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - xendit_payment_status()');
            print_r(json_encode($response));
        }
    }

    private function xendit_transaction_webview($user_id, $order_id, $amount, $partner_id, $type, $additional_charges_transaction_id = null)
    {
        try {
            $user = fetch_details('users', ['id' => $user_id]);
            if (empty($user)) {
                echo labels(USER_NOT_FOUND, 'User not found');
                return false;
            }

            $order_res = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_res)) {
                echo labels(ORDER_NOT_FOUND, 'Order not found');
                return false;
            }

            $settings = get_settings('general_settings', true);
            $payment_gateways_settings = get_settings('payment_gateways_settings', true);

            if ($type == 'additional_charges') {
                $external_id = 'additionalCharges_' . $additional_charges_transaction_id . '_' . $user_id . '_' . time();
            } else {
                $external_id = 'order_' . $order_id . '_' . $user_id . '_' . time();
            }

            if (isset($payment_gateways_settings['xendit_website_url']) && !empty($payment_gateways_settings['xendit_website_url'])) {
                $success_url = $payment_gateways_settings['xendit_website_url'] . '/payment-status?status=successful&order_id=' . $order_id;
                $failure_url = $payment_gateways_settings['xendit_website_url'] . '/payment-status?status=failed&order_id=' . $order_id;
            } else {
                $success_url = base_url('api/v1/xendit_payment_status?status=successful&order_id=' . $order_id);
                $failure_url = base_url('api/v1/xendit_payment_status?status=failed&order_id=' . $order_id);
            }

            $company_title = getTranslatedSetting('general_settings', 'company_title');

            $invoice_data = [
                'external_id' => $external_id,
                'amount' => floatval($amount),
                'customer_name' => $user[0]['username'],
                'customer_email' => !empty($user[0]['email']) ? $user[0]['email'] : $settings['support_email'],
                'customer_phone' => $user[0]['phone'] ?? '',
                'success_url' => $success_url,
                'failure_url' => $failure_url,
                'description' => 'Payment for Order #' . $order_id . ' on ' . $company_title,
                'metadata' => [
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                ]
            ];

            $xendit = new Xendit();
            $invoice = $xendit->create_invoice($invoice_data);

            if ($invoice && isset($invoice['invoice_url'])) {
                if ($type == 'order') {
                    $transaction_data = [
                        'transaction_type' => 'transaction',
                        'user_id' => $user_id,
                        'partner_id' => $partner_id,
                        'order_id' => $order_id,
                        'type' => 'xendit',
                        'txn_id' => $external_id,
                        'status' => 'pending',
                        'amount' => 0,
                        'currency_code' => "",
                    ];

                    add_transaction($transaction_data);
                }

                log_the_responce('Xendit invoice created successfully for order: ' . $order_id . ' with external_id: ' . $external_id, 'app/Controllers/api/V1.php - xendit_transaction_webview()');
                return $invoice['invoice_url'];
            } else {
                log_the_responce('Failed to create Xendit invoice for order: ' . $order_id, 'app/Controllers/api/V1.php - xendit_transaction_webview()');
                return false;
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - xendit_transaction_webview()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
        }
    }
}