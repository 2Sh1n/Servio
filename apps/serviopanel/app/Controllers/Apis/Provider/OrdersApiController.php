<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Orders_model;
use App\Models\Users_model;
use App\Models\Partners_model;
use DateTime;

class OrdersApiController extends BaseController
{
    protected $request, $db, $data;
    protected $user_details = [];
   
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->db = \Config\Database::connect();
       
        $token = verify_app_request();
      
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error' => true,
                'message' => $token['message'],
                'status' => 401,
            ]));
            die();
        }
    }

    public function get_orders()
    {
        try {
            $orders_model = new Orders_model();
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $status = $this->request->getPost('status') ?: 0;
            $partner_id = $this->request->getPost('partner_id') ?: $this->user_details['id'];
            $download_invoice = ($this->request->getPost('download_invoice') && !empty($this->request->getPost('download_invoice'))) ? $this->request->getPost('download_invoice') : 1;

            // Fetch only Custom Job Request Orders
            if (!empty($this->request->getPost('custom_request_orders'))) {
                $where['o.custom_job_request_id !='] = "";
                $where['o.partner_id'] = $partner_id;
                if (!empty($this->request->getPost('status'))) {
                    $where['o.status'] = $status;
                }
                $orders = $orders_model->custom_booking_list(true, $search, $limit, $offset, $sort, $order, $where, $download_invoice);
            }
            // Fetch Both Custom Job Request Orders & Normal Bookings
            elseif (!empty($this->request->getPost('fetch_both_bookings'))) {
                // Fetch Custom Job Requests
                $custom_where = [
                    'o.custom_job_request_id !=' => '',
                    'o.partner_id' => $partner_id
                ];
                if (!empty($status)) {
                    $custom_where['o.status'] = $status;
                }
                $custom_orders = $orders_model->custom_booking_list(true, $search, $limit, $offset, $sort, $order, $custom_where, $download_invoice);

                // Fetch Normal Bookings
                $normal_where = [
                    'o.partner_id' => $partner_id,
                    'o.status' => $status,
                    'o.custom_job_request_id' => NULL
                ];
                $normal_orders = $orders_model->list(true, $search, $limit, $offset, $sort, $order, $normal_where, '', '', '', '', '', true);

                // Merge Results
                $orders['data'] = array_merge($custom_orders['data'] ?? [], $normal_orders['data'] ?? []);
                $total = ($custom_orders['total'] ?? 0) + ($normal_orders['total'] ?? 0);
            }
            // Fetch Only Normal Bookings
            else {
                $where = [
                    'o.partner_id' => $this->user_details['id'],
                    'o.status' => $status,
                    'o.custom_job_request_id' => NULL
                ];
                if ($this->request->getPost('id') && !empty($this->request->getPost('id'))) {
                    $where['o.id'] = $this->request->getPost('id');
                }

                $orders = $orders_model->list(true, $search, $limit, $offset, $sort, $order, $where, '', '', '', '', '', true);
            }


            // Remove total key if present
            if (isset($orders['total'])) {
                $total = $orders['total'];
                unset($orders['total']);
            }

            // Add translation support for service data in orders
            if (!empty($orders['data'])) {
                foreach ($orders['data'] as &$order) {
                    if (!empty($order['order_services'])) {
                        foreach ($order['order_services'] as &$service) {
                            // Get service details for translation fallback
                            $serviceFallbackData = [
                                'title' => $service['title'] ?? '',
                                'description' => $service['description'] ?? '',
                                'long_description' => $service['long_description'] ?? '',
                                'tags' => $service['tags'] ?? '',
                                'faqs' => $service['faqs'] ?? ''
                            ];

                            // Get translated data for this service based on Content-Language header
                            $translatedServiceData = get_translated_service_data_for_api($service['service_id'], $serviceFallbackData);

                            // Merge translated data with the service data
                            if (!empty($translatedServiceData)) {
                                $service = array_merge($service, $translatedServiceData);
                            }
                        }
                    }
                }
            }

            // Response
            if (!empty($orders) && $total != 0) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(ORDERS_FETCHED_SUCCESSFULLY, 'Orders fetched successfully.'),
                    'total' => strval($total),
                    'data' => $orders
                ]);
            } else {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'data' => []
                ]);
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . ' Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_orders()');
            return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }

    public function delete_orders()
    {
        try {
            $validation =  \Config\Services::validation();
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
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $order_id = $this->request->getPost('order_id');
            $partner_id = $this->user_details['id'];
            $orders = fetch_details('orders', ['id' => $order_id, 'partner_id' => $partner_id]);
            if (empty($orders)) {
                $response = [
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No, Order Found'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $db      = \Config\Database::connect();
            $builder = $db->table('orders')->delete(['id' => $order_id, 'partner_id' => $partner_id]);
            if ($builder) {
                $builder = $db->table('order_services')->delete(['order_id' => $order_id]);
                if ($builder) {
                    $response = [
                        'error' => false,
                        'message' => labels(ORDER_DELETED_SUCCESSFULLY, 'Order deleted successfully!'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(ORDER_DOES_NOT_EXIST, 'Order does not exist!'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(ORDER_NOT_FOUND, 'Order Not Found'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - delete_orders()');
            return $this->response->setJSON($response);
        }
    }

    public function update_order_status()
    {
        try {
            $validation =  \Config\Services::validation();
            $validation->setRules(
                [
                    'order_id' => 'required|numeric',
                    'customer_id' => 'required|numeric',
                    'status' => 'required',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $order_id = $this->request->getPost('order_id');
            $status = $this->request->getPost('status');
            $customer_id = $this->request->getPost('customer_id');
            $date = $this->request->getPost('date');
            $selected_time = $this->request->getPost('time');
            $otp = $this->request->getPost('otp');
            $work_complete_files = $this->request->getFiles('work_complete_files');
            $work_started_files = $this->request->getFiles('work_started_files');
            $disk = fetch_current_file_manager();
            if ($status == "rescheduled") {
                // Pass the actor (provider) user_id so notifications are routed correctly.
                // - Provider updates => notify admin + customer (not provider).
                $res =  validate_status($order_id, $status, $date, $selected_time, null, null, null, $this->user_details['id'], get_current_language_from_request());
            } else {
                if ($status == "completed") {
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res = validate_status($order_id, $status, '', '', $otp, isset($work_complete_files) ? $work_complete_files : "", null, $this->user_details['id'], get_current_language_from_request());
                } elseif ($status == "started") {
                    $work_started_files_data = [];
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res = validate_status($order_id, $status, '', '', '', isset($work_started_files) ? $work_started_files : "", null, $this->user_details['id'], get_current_language_from_request());
                    $order_data = fetch_details('orders', ['id' => $order_id]);
                    if (!empty($order_data)) {
                        if (!empty($order_data[0]['work_started_proof'])) {
                            $work_started_files_data = json_decode($order_data[0]['work_started_proof'], true);
                            foreach ($work_started_files_data as &$data) {
                                if ($disk == "local_server") {
                                    $data = base_url($data);
                                } else if ($disk == "aws_s3") {
                                    $data = fetch_cloud_front_url('provider_work_evidence', $data);
                                } else {
                                    $data = base_url($data);
                                }
                            }
                        }
                    }
                } else if ($status == "booking_ended") {
                    $additional_charges = $this->request->getPost('additional_charges');
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res =  validate_status($order_id, $status, '', '', '', isset($work_complete_files) ? $work_complete_files : "", $additional_charges, $this->user_details['id'], get_current_language_from_request());
                    $work_completed_files_data = [];
                    $order_data = fetch_details('orders', ['id' => $order_id]);
                    if (!empty($order_data)) {
                        if (!empty($order_data[0]['work_completed_proof'])) {
                            $work_completed_files_data = json_decode($order_data[0]['work_completed_proof'], true);
                            foreach ($work_completed_files_data as &$data) {
                                if ($disk == "local_server") {
                                    $data = base_url($data);
                                } else if ($disk == "aws_s3") {
                                    $data = fetch_cloud_front_url('provider_work_evidence', $data);
                                } else {
                                    $data = base_url($data);
                                }
                            }
                        }
                    }
                } else if ($status == "cancelled") {
                    $res =  validate_status($order_id, $status, '', '', '', '', '', $this->user_details['id'], get_current_language_from_request());
                } else {
                    // Pass the actor (provider) user_id so notifications are routed correctly.
                    $res =  validate_status($order_id, $status, null, null, null, null, null, $this->user_details['id'], get_current_language_from_request());
                }
            }

            if ($res['error']) {
                $response['error'] = true;
                $response['message'] = $res['message'];
                $response['data'] = array();
                return $this->response->setJSON($response);
            }
            if ($status == "rescheduled") {
                $user_no = fetch_details('users', ['id' => $customer_id], 'phone')[0]['phone'];
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_RESCHEDULED_SUCCESSFULLY, 'Order rescheduled successfully!'),
                    'contact' => labels("you_can_call_on") . ' ' . $user_no . ' ' . labels("number_to_reschedule"),
                ];
                return $this->response->setJSON($response);
            }
            $custom_notification = fetch_details('notifications',  ['type' => "customer_order_started"]);
            if ($status == "awaiting") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_IS_IN_AWAITING, 'Order is in Awaiting!'),
                ];
            }
            if ($status == "confirmed") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_IS_CONFIRMED, 'Order is Confirmed!'),
                ];
            }
            if ($status == "cancelled") {
                $response = [
                    'error' => false,
                    'message' => labels(BOOKING_IS_CANCELLED, 'Booking is cancelled!'),
                ];
            }
            if ($status == "completed") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_COMPLETED_SUCCESSFULLY, 'Order Completed successfully!'),
                ];
            }
            if ($status == "started") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_STARTED_SUCCESSFULLY, 'Order Started successfully!'),
                    'data' =>   $work_started_files_data,
                ];
            }
            if ($status == "booking_ended") {
                $response = [
                    'error' => false,
                    'message' => labels(ORDER_ENDED_SUCCESSFULLY, 'Order ended successfully!'),
                    'data' => $work_completed_files_data
                ];
            }
            //custom notification message
            if ($status == 'awaiting') {
                $type = ['type' => "customer_order_awaiting"];
            } elseif ($status == 'confirmed') {
                $type = ['type' => "customer_order_confirmed"];
            } elseif ($status == 'rescheduled') {
                $type = ['type' => "customer_order_rescheduled"];
            } elseif ($status == 'cancelled') {
                $type = ['type' => "customer_order_cancelled"];
            } elseif ($status == 'started') {
                $type = ['type' => "customer_order_started"];
            } elseif ($status == 'completed') {
                $type = ['type' => "customer_order_completed"];
            } elseif ($status == 'booking_ended') {
                $type = ['type' => "customer_order_completed"];
            }

            $settings = get_settings('general_settings', true);
            $app_name = get_company_title_with_fallback($settings);
            $user_res = fetch_details('users', ['id' => $customer_id], 'username,fcm_id,platform');
            $customer_msg = (!empty($custom_notification)) ? $custom_notification[0]['message'] :  'Hello Dear ' . $user_res[0]['username'] . ' order status updated to ' . $status . ' for your order ID #' . $order_id . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '';
            $fcm_ids = array();

            return $this->response->setJSON($response);
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - update_order_status()');
            return $this->response->setJSON($response);
        }
    }

    public function verify_booking_otp()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules([
                'user_id' => ['rules' => 'required', 'errors' => ['required' => labels('user_id_is_required', 'User ID is required')]],
                'otp' => ['rules' => 'required', 'errors' => ['required' => labels('otp_is_required', 'OTP is required')]],
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }

            $user_id = $this->request->getPost('user_id');
            $otp = $this->request->getPost('otp');

            $ordersModel = new Orders_model();
            $orderData = $ordersModel->select('otp')->where('user_id', $user_id)->where('otp', $otp)->get()->getResultArray();

            if (!empty($orderData)) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(OTP_VERIFIED, 'OTP verified'),
                    'data' => ['status' => true, 'otp' => $otp]
                ]);
            }

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(OTP_NOT_VERIFIED, 'OTP not verified'),
                'data' => ['status' => false, 'otp' => $otp]
            ]);
        } catch (\Throwable $th) {
            log_message('error', 'Error in app/Controllers/partner/api/V1.php - verify_booking_otp():' . $th->getTraceAsString());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => []
            ]);
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
            $partnerId = $this->user_details['id'];


            $ordersModel = new Orders_model();
            $partnersModel = new Partners_model();
            $usersModel = new Users_model();

            $orders  = $ordersModel->where('id', $order_id)->where('partner_id', $partnerId)->get()->getResultArray();
            if (isset($orders) && empty($orders)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    'data' => []
                ]);
            }

            $orderDetails = $ordersModel->invoice($order_id)['order'];
            $partnerDetails = $partnersModel->from('partner_details pd')->select('pd.company_name, pd.address, u.email, u.phone, u.image')->join('users u', 'u.id = pd.partner_id')->where('pd.partner_id', $partnerId)->get()->getResultArray();

            // Add translation support for partner details in invoice
            if (!empty($partnerDetails[0])) {
                $translatedData = get_translated_partner_field(partnerId: $partnerId, fieldName: 'company_name', defaultValue: $partnerDetails[0]['company_name']);
                $partnerDetails[0]['translated_company_name'] = $translatedData;

                $partnerDetails[0]['image'] = (!empty($partnerDetails[0]['image']) && file_exists(FCPATH . 'public/backend/assets/profiles/' . basename($partnerDetails[0]['image']))) ? base_url('public/backend/assets/profiles/' . basename($partnerDetails[0]['image'])) : '';
            }

            $userId = $orderDetails['user_id'];

            $userDetails = $usersModel->where('id', $userId)->get()->getResultArray();

            $settings = get_settings('general_settings', true);

            $this->data['currency'] = $settings['currency'];
            $this->data['order'] = $orderDetails;
            $this->data['partner_details'] = $partnerDetails[0];
            $this->data['user_details'] = $userDetails[0];
            $this->data['data'] = $settings;

            $currency = $settings['currency'];

            $services = $orderDetails['services'];
            $total =  count($services);

            if (!empty($orderDetails)) {
                $i = 0;
                // Use stored values from order_services: tax_amount is stored per unit, no recalculation
                $sum_net_amount = 0;
                $sum_tax_amount = 0;

                foreach ($services as &$service) {
                    $original_price = (float) ($service['price'] ?? 0);
                    $discount_price = (float) ($service['discount_price'] ?? 0);
                    $qty = (int) ($service['quantity'] ?? 1);
                    $currency_symbol = $currency;

                    // Use stored tax_amount from service (per unit); line tax = tax_amount * quantity
                    $stored_tax = (float) ($service['tax_amount'] ?? 0);
                    $line_tax = $stored_tax * $qty;

                    // Unit price (discounted or original); line net = unit price * qty
                    $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                    $line_net = $unitPrice * $qty;

                    $sum_net_amount += $service['tax_type'] == 'included' ? $line_net - $line_tax : $service['sub_total'] - $line_tax;
                    $sum_tax_amount += $line_tax;

                    $rows[$i] = [
                        'service_title' => ucwords($service['service_title']),
                        'price' => $currency_symbol . number_format($original_price, 2, '.', ''),
                        'discount' => ($discount_price == 0) ? $currency_symbol . "0.00" : $currency_symbol . number_format(($original_price - $discount_price), 2, '.', ''),
                        'net_amount' => $currency_symbol . number_format($line_net, 2, '.', ''),
                        'tax' => ($service['tax_percentage'] ?? '') . '%',
                        'tax_amount' => $currency_symbol . number_format($line_tax, 2, '.', ''),
                        'quantity' => (string) $qty,
                        'subtotal' => $currency_symbol . number_format($service['sub_total'], 2, '.', '')
                    ];
                    $i++;
                }

                $array['total'] = $total;
                $array['rows'] = $rows;
                // Order totals aligned with customer invoice_download (api/V1): total = taxable base, sub_total = total + tax
                $this->data['order']['total'] = number_format($sum_net_amount, 2, '.', '');
                $this->data['order']['tax'] = number_format($sum_tax_amount, 2, '.', '');
                // sub_total (incl. tax) and overall_amount for consistency with fetch_cart
                $sub_total_incl_tax = $sum_net_amount + $sum_tax_amount;
                $visiting_charges = (float) ($orderDetails['visiting_charges'] ?? 0);
                $this->data['order']['sub_total'] = number_format($sub_total_incl_tax, 2, '.', '');
                $this->data['order']['overall_amount'] = number_format($sub_total_incl_tax + $visiting_charges, 2, '.', '');
                $this->data['rows'] = $rows;
                $this->data['currency'] = $currency;
                try {
                    $html =  view('backend/admin/pages/invoice_from_api', $this->data);
                    $path = "public/uploads/";
                    $mpdf = new \Mpdf\Mpdf([
                        'tempDir' => $path,
                        'defaultFont' => 'dejavusans',
                        'mode' => 'utf-8',
                    ]);
                    $stylesheet = file_get_contents('public/backend/assets/css/vendor/bootstrap-table.css');
                    $mpdf->WriteHTML($stylesheet, 1); // CSS Script goes here.
                    $mpdf->WriteHTML($html);
                    $this->response->setHeader("Content-Type", "application/pdf");
                    $mpdf->Output('order-ID-' . $orderDetails['id'] . "-invoice.pdf", 'I');
                } catch (\Mpdf\MpdfException $e) {
                    print "Creating an mPDF object failed";
                    log_message('error', 'Creating an mPDF object failed with: ' . $e->getMessage());
                }
            } else {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(NO_ORDER_FOUND, 'No Order Found'),
                    'data' => []
                ]);
            }
        } catch (\Exception $th) {
            // throw $th;
            log_message('error', date('Y-m-d H:i:s') . 'Error in app/Controllers/partner/api/V1.php - invoice_download(): Authorization: ' . $this->request->header('Authorization') . ' Params Passed: ' . json_encode($_POST) . ' Issue: ' . $th->getTraceAsString());

            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            return $this->response->setJSON($response);
        }
    }

    public function get_available_slots()
    {
        try {
            $validation =  \Config\Services::validation();
            $validation->setRules(
                [
                    'date' => 'required|valid_date[Y-m-d]',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
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
                'Sun' => 'sunday'
            ];
            $partner_id = $this->user_details['id'];
            $date = $this->request->getPost('date');
            $time = $this->request->getPost('date');
            $date = new DateTime($date);
            $date = $date->format('Y-m-d');
            $day =  date('D', strtotime($date));
            $whole_day = $days[$day];
            $partner_data = fetch_details('partner_details', ['partner_id' => $partner_id], ['advance_booking_days']);
            $time_slots = get_available_slots($partner_id, $date);
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
            $partner_timing = fetch_details('partner_timings', ['partner_id' => $partner_id, "day" => $whole_day]);
            if (!empty($partner_data) && $partner_data[0]['advance_booking_days'] > 0) {
                $allowed_advanced_booking_days = $partner_data[0]['advance_booking_days'];
                $current_date = new DateTime();
                $max_available_date =  $current_date->modify("+ $allowed_advanced_booking_days day")->format('Y-m-d');
                if ($date > $max_available_date) {
                    $response = [
                        'error' => true,
                        'message' => labels(YOU_CAN_NOT_CHOOSE_DATE_BEYOND_AVAILABLE_BOOKING_DAYS, "You'can not choose date beyond available booking days which is") . ' ' . $allowed_advanced_booking_days . ' ' . labels(DAYS, "days"),
                        'data' => []
                    ];
                    return $this->response->setJSON(remove_null_values($response));
                }
            } else if (!empty($partner_data) && $partner_data[0]['advance_booking_days'] == 0) {
                $current_date = new DateTime();
                if ($date > $current_date->format('Y-m-d')) {
                    $response = [
                        'error' => true,
                        'message' => labels(ADVANCED_BOOKING_FOR_THIS_PARTNER_IS_NOT_AVAILABLE, "Advanced Booking for this partner is not available"),
                        'data' => []
                    ];
                    return $this->response->setJSON(remove_null_values($response));
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_PARTNER_FOUND, "No Partner Found"),
                    'data' => []
                ];
                return $this->response->setJSON(remove_null_values($response));
            }
            if (!empty($time_slots)) {
                $response = [
                    'error' => $time_slots['error'],
                    'message' => ($time_slots['error'] == false) ? labels(FOUND_TIME_SLOTS, "Found Time slots") : labels(NO_SLOT_AVAILABLE_FOR_THIS_DATE, "No slot available for this date"),
                    'data' => [
                        'all_slots' => (!empty($time_slots) && $time_slots['error'] == false) ? $time_slots['all_slots'] : [],
                    ]
                ];
                return $this->response->setJSON(remove_null_values($response));
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_SLOT_AVAILABLE_ON_THIS_DATE, "No slot is available on this date!"),
                    'data' => []
                ];
                return $this->response->setJSON(remove_null_values($response));
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_available_slots()');
            return $this->response->setJSON($response);
        }
    }
}
