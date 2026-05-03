<?php

namespace App\Controllers\partner;

use App\Models\Orders_model;

class Orders extends Partner
{
    protected $validation;

    public $orders;
    public $data;

    public function __construct()
    {
        parent::__construct();
        $this->orders = new Orders_model();
        helper('ResponceServices');
    }

    public function index()
    {
        if ($this->isLoggedIn && !$this->userIsAdmin) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }

            setPageInfo($this->data, labels('bookings', 'Bookings') . ' | ' . labels('provider_panel', 'Provider Panel'), 'orders');
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }

            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    private function extractServiceNameFromRow(array $row): string
    {
        if (!empty($row['service_name'])) {
            return (string) $row['service_name'];
        }

        if (!empty($row['service_title'])) {
            return (string) $row['service_title'];
        }

        if (!empty($row['services']) && is_array($row['services'])) {
            $firstService = $row['services'][0] ?? [];

            if (!empty($firstService['service_title'])) {
                return (string) $firstService['service_title'];
            }

            if (!empty($firstService['title'])) {
                return (string) $firstService['title'];
            }

            if (!empty($firstService['name'])) {
                return (string) $firstService['name'];
            }
        }

        if (!empty($row['order_services']) && is_array($row['order_services'])) {
            $firstService = $row['order_services'][0] ?? [];

            if (!empty($firstService['service_title'])) {
                return (string) $firstService['service_title'];
            }

            if (!empty($firstService['title'])) {
                return (string) $firstService['title'];
            }

            if (!empty($firstService['name'])) {
                return (string) $firstService['name'];
            }
        }

        return '';
    }

    public function list()
    {
        try {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }

            $orders_model = new Orders_model();
            $where = ['o.partner_id' => $this->userId];

            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 20;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            $order_status_filter = (isset($_GET['order_status_filter']) && !empty($_GET['order_status_filter'])) ? $_GET['order_status_filter'] : '';
            $final_total_filter = (isset($_GET['final_total_filter']) && !empty($_GET['final_total_filter'])) ? $_GET['final_total_filter'] : '';

            $orders = $orders_model->list(false, $search, $limit, $offset, $sort, $order, $where, '', '', '', '', '', '');

            if (is_string($orders)) {
                $decoded = json_decode($orders, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $orders = $decoded;
                }
            }

            if (isset($orders['rows']) && is_array($orders['rows'])) {
                foreach ($orders['rows'] as &$row) {
                    $row['service_name'] = $this->extractServiceNameFromRow($row);
                }
                unset($row);
            }

            return $this->response->setJSON($orders);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function view_orders()
    {
        try {
            $uri = service('uri');

            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('partner/login');
            } else {
                $this->orders = new Orders_model();
                setPageInfo($this->data, labels('bookings', 'Bookings') . ' | ' . labels('provider_panel', 'Provider Panel'), 'order_details');

                $order_id = $uri->getSegments()[3];
                $order_data = fetch_details('orders', ['id' => $order_id]);

                if (empty($order_data)) {
                    return redirect('partner/orders');
                }

                $where['o.id'] = $order_id;
                $order_details = $this->orders->list(true, '', 10, 0, '', '', $where);

                if ((empty($order_details['data']))) {
                    return redirect('partner/orders');
                }

                $this->data['order_details'] = $order_details['data'][0];

                $od = $order_details['data'][0];
                $order_tax_amount = 0;

                if (!empty($od['services']) && is_array($od['services'])) {
                    foreach ($od['services'] as $s) {
                        $order_tax_amount += floatval($s['tax_amount'] ?? 0) * floatval($s['quantity'] ?? 0);
                    }
                }

                $order_total = floatval(str_replace(',', '', $od['total'] ?? 0));
                $order_subtotal = $order_total - $order_tax_amount;

                $this->data['order_subtotal'] = format_amount($order_subtotal);
                $this->data['order_tax_amount'] = format_amount($order_tax_amount);
                $this->data['order_final_total'] = format_amount(floatval(str_replace(',', '', $od['final_total'] ?? 0)));

                $data = get_settings('general_settings', true);
                $this->data['currency'] = $data['currency'];
                $this->data['promocode_discount'] = $od['promo_discount'] ?? 0;

                $advance_booking_days = isset($order_details['data'][0]['advance_booking_days']) ? intval($order_details['data'][0]['advance_booking_days']) : null;
                $timezone = $data['system_timezone'] ?? date_default_timezone_get();
                $today = new \DateTime('now', new \DateTimeZone($timezone));
                $min_reschedule_date = $today->format('Y-m-d');
                $max_reschedule_date = null;

                if ($advance_booking_days !== null) {
                    if ($advance_booking_days > 0) {
                        $max_reschedule_date = (clone $today)->modify('+' . $advance_booking_days . ' days')->format('Y-m-d');
                    } else {
                        $max_reschedule_date = $min_reschedule_date;
                    }
                }

                $this->data['advance_booking_days'] = $advance_booking_days;
                $this->data['reschedule_min_date'] = $min_reschedule_date;
                $this->data['reschedule_max_date'] = $max_reschedule_date;

                $sub_orders = fetch_details('orders', ['parent_id' => $order_id]);
                $this->data['sub_order'] = $sub_orders;

                $partner_personal_data = fetch_details('users', ['id' => $this->userId], ['email'])[0];
                $this->data['personal_data'] = $partner_personal_data;

                return view('backend/partner/template', $this->data);
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - view_orders()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function order_summary_table($order_id = "")
    {
        try {
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $orders_model = new Orders_model();
            $data = get_settings('general_settings', true);
            $currency = $data['currency'];
            $orders = $orders_model->invoice($order_id);
            $services = $orders['order']['services'];
            $total = count($services);
            $subtotal = 0.00;
            $tax_amount = 0;

            if (!empty($orders)) {
                $i = 0;
                $rows = [];

                foreach ($services as $service) {
                    $subtotal += floatval($service['sub_total']);
                    $tax_amount = ($service['tax_type'] == "excluded") ? $tax_amount + floatval($service['tax_amount'] * $service['quantity']) : $tax_amount;
                    $operations = '<button class="btn btn-danger btn-sm cancel_service" data-id="' . $service['id'] . '"> <i class="fas fa-trash"></i> </button>';

                    if (empty($service['service_image'])) {
                        $profile = '
                        <a href="#" id="pop">
                            <img id="profile_picture" onerror="this.onerror=null;this.src=\'' . base_url('public/backend/assets/default.png') . '\'" 
                                 src="' . base_url('public/backend/assets/default.png') . '" 
                                 height="50px" width="50px" class="rounded-circle mr-4" style="border-radius:5px !important">
                        </a>';
                    } else {
                        $profile = '
                        <a href="#" id="pop">
                            <img id="profile_picture" onerror="this.onerror=null;this.src=\'' . base_url('public/backend/assets/default.png') . '\'" src="' . base_url($service['service_image']) . '" height="50px" width="50px" class="rounded-circle mr-4" style="border-radius:5px !important">
                        </a>';
                    }

                    $profile = '<li class="media p-2">' . $profile . ' <div class="media-body"><div class="media-title mt-3">' . $service['service_title'] . '</div></div></li>';

                    $rows[$i] = [
                        'service_title' => $profile,
                        'price' => $currency . number_format($service['price']),
                        'discount' => ($service['discount_price'] == 0) ? "0" : $currency . (($service['price'] - $service['discount_price'])),
                        'net_amount' => ($service['discount_price'] != 0) ? $currency . number_format($service['discount_price']) : $currency . ($service['price']),
                        'tax' => ($service['tax_type'] == "excluded") ? $service['tax_percentage'] . '%' : '0%',
                        'tax_amount' => ($service['tax_type'] == "excluded") ? $service['tax_amount'] : 0,
                        'quantity' => ucwords($service['quantity']),
                        'duration' => ucwords($service['duration'] * $service['quantity']),
                        'subtotal' => $currency . $service['sub_total'],
                        'operations' => $operations,
                    ];
                    $i++;
                }

                $array['total'] = $total;
                $array['rows'] = $rows;
                echo json_encode($array);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - order_summary_table()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function invoice_table($order_id = "")
    {
        try {
            $uri = service('uri');
            $order_id = $uri->getSegments()[3];
            $orders_model = new Orders_model();
            $data = get_settings('general_settings', true);
            $currency = $data['currency'];
            $orders = $orders_model->invoice($order_id);
            $services = $orders['order']['services'];
            $total = count($services);

            if (!empty($orders)) {
                $i = 0;
                $rows = [];
                $sum_net_amount = 0;
                $sum_tax_amount = 0;

                foreach ($services as $service) {
                    $original_price = (float) ($service['price'] ?? 0);
                    $discount_price = (float) ($service['discount_price'] ?? 0);
                    $qty = (int) ($service['quantity'] ?? 1);

                    $stored_tax = (float) ($service['tax_amount'] ?? 0);
                    $line_tax = $stored_tax * $qty;

                    $unitPrice = ($discount_price > 0) ? $discount_price : $original_price;
                    $line_net = $unitPrice * $qty;

                    $sum_net_amount += $service['tax_type'] == 'included' ? $line_net - $line_tax : $service['sub_total'] - $line_tax;
                    $sum_tax_amount += $line_tax;

                    $operations = '<button class="btn btn-danger btn-sm cancel_service" data-id="' . $service['id'] . '"> <i class="fas fa-trash"></i> </button>';

                    $rows[$i] = [
                        'service_title' => ucwords($service['service_title']),
                        'price' => $currency . number_format($original_price, 2, '.', ''),
                        'discount' => ($discount_price == 0) ? $currency . "0.00" : $currency . number_format(($original_price - $discount_price), 2, '.', ''),
                        'net_amount' => $currency . number_format($unitPrice, 2, '.', ''),
                        'tax' => ($service['tax_percentage'] ?? '') . '%',
                        'tax_amount' => $currency . number_format($line_tax, 2, '.', ''),
                        'quantity' => ucwords((string) $qty),
                        'subtotal' => $currency . number_format($service['sub_total'], 2, '.', ''),
                        'operations' => $operations,
                    ];
                    $i++;
                }

                $recalc_total = number_format($sum_net_amount, 2, '.', '');

                $row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark'>" . labels('total', 'Total') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $recalc_total . "</strong>",
                    'operations' => "",
                ];

                $tax_row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark'>" . labels('tax', 'Tax') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . number_format($sum_tax_amount, 2, '.', '') . "</strong>",
                    'operations' => "",
                ];

                if ($orders['order']['visiting_charges'] != "0") {
                    $visiting_charges = [
                        'service_title' => "",
                        'price' => "",
                        'discount' => "",
                        'net_amount' => "",
                        'tax' => "",
                        'tax_amount' => "",
                        'quantity' => "<strong class='text-dark '>" . labels('visiting_charges', 'Visiting Charges') . "</strong>",
                        'subtotal' => "<strong class='text-dark '>" . $currency . $orders['order']['visiting_charges'] . "</strong>",
                        'operations' => "",
                    ];
                }

                $promo_code_discount = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark '>" . labels('promocode_discount', 'Promo Code Discount') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $orders['order']['promo_discount'] . "</strong>",
                    'operations' => "",
                ];

                $order_row = $orders['order'];
                $payble_amount = isset($order_row['final_total']) ? ($order_row['final_total'] - $order_row['promo_discount']) : ($order_row['total'] - $order_row['promo_discount']);

                $final_total_row = [
                    'service_title' => "",
                    'price' => "",
                    'discount' => "",
                    'net_amount' => "",
                    'tax' => "",
                    'tax_amount' => "",
                    'quantity' => "<strong class='text-dark '>" . labels('final_total', 'Final Total') . "</strong>",
                    'subtotal' => "<strong class='text-dark '>" . $currency . $payble_amount . "</strong>",
                    'operations' => "",
                ];

                if (!empty($order_row['additional_charges'])) {
                    $additional_charges = json_decode($order_row['additional_charges'], true);
                    if (is_array($additional_charges)) {
                        foreach ($additional_charges as $charge) {
                            $rows[] = [
                                'service_title' => "",
                                'price' => "",
                                'discount' => "",
                                'net_amount' => "",
                                'tax' => "",
                                'tax_amount' => "",
                                'quantity' => "<strong class='text-dark'>" . ($charge['name'] ?? '') . "</strong>",
                                'subtotal' => "<strong class='text-dark'>" . $currency . ($charge['charge'] ?? 0) . "</strong>",
                                'operations' => "",
                            ];
                        }
                    }
                }

                if (!empty($rows)) {
                    array_push($rows, $row);
                    array_push($rows, $tax_row);
                    if ($orders['order']['visiting_charges'] != "0") {
                        array_push($rows, $visiting_charges);
                    }
                    array_push($rows, $promo_code_discount);
                    array_push($rows, $final_total_row);
                }

                $array['total'] = $total;
                $array['rows'] = $rows;
                echo json_encode($array);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - invoice_table()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function invoice()
    {
        try {
            if (!$this->isLoggedIn && !$this->userIsPartner) {
                return redirect('partner/login');
            } else {
                $uri = service('uri');
                $order_id = $uri->getSegments()[3];
                $order_data = fetch_details('orders', ['id' => $order_id]);

                if (empty($order_data)) {
                    return redirect('partner/orders');
                }

                $this->orders = new Orders_model();
                setPageInfo($this->data, labels('bookings', 'Bookings') . ' | ' . labels('provider_panel', 'Provider Panel'), 'invoice');

                $order_details = $this->orders->invoice($order_id);
                $subtotal = 0.00;

                foreach ($order_details['order']['services'] as $service) {
                    $subtotal += floatval($service['sub_total']);
                }

                $promocode_discount = 0.00;
                if (isset($order_details) && !empty($order_details['order']['promo_discount'])) {
                    $promocode_discount = intval($order_details['order']['total'] + $order_details['order']['visiting_charges']) * intval($order_details['order']['promo_discount']) / 100;
                }

                $this->data['promocode_discount'] = $promocode_discount;
                $this->data['subtotal'] = $subtotal;

                $data = get_settings('general_settings', true);
                $this->data['currency'] = $data['currency'];
                $this->data['logo'] = $data['partner_logo'];
                $this->data['order'] = $order_details['order'];

                return view('backend/partner/template', $this->data);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - invoice()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update_order_status()
    {
        try {
            if ($this->isLoggedIn && $this->userIsPartner) {
                $order_id = $this->request->getPost('order_id');
                $status = $this->request->getPost('status');
                $date = $this->request->getPost('rescheduled_date');
                $selected_time = $this->request->getPost('reschedule');
                $otp = $this->request->getPost('otp');
                $uploadedFiles = $this->request->getFiles('filepond');
                $partner_id = fetch_details('orders', ['id' => $order_id], ['partner_id'])[0]['partner_id'];

                if ($status == "rescheduled" && $selected_time == "") {
                    $response = [
                        'error' => true,
                        'message' => labels('please_select_reschedule_timing', "Please select reschedule timing"),
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }

                $is_provider_available = check_availability($partner_id, $date, $selected_time);

                if ($status == "rescheduled" && $is_provider_available) {
                    $response = validate_status($order_id, $status, $date, $selected_time, null, null, null, $this->userId, get_current_language());

                    if (is_array($response) && isset($response['error']) && $response['error'] == false) {
                        $orderData = fetch_details('orders', ['id' => $order_id], ['user_id']);
                        $customerId = !empty($orderData) ? $orderData[0]['user_id'] : '';

                        if (!isset($response['data'])) {
                            $response['data'] = [];
                        }

                        $response['data']['clarity_event'] = 'booking_status_updated';
                        $response['data']['booking_id'] = $order_id;
                        $response['data']['status'] = $status;
                        $response['data']['customer_id'] = $customerId;
                    }

                    return json_encode($response);
                } else {
                    if ($status == "completed") {
                        $response = validate_status($order_id, $status, '', '', $otp, "", null, $this->userId, get_current_language());
                    } elseif ($status == "started") {
                        $response = validate_status($order_id, $status, '', '', '', isset($uploadedFiles) ? $uploadedFiles : "", null, $this->userId, get_current_language());
                    } elseif ($status == "booking_ended") {
                        $additionalCharge = $this->request->getPost('booking_ended_additional_charges') ?? '';
                        $response = validate_status($order_id, $status, '', '', '', isset($uploadedFiles) ? $uploadedFiles : "", $additionalCharge, $this->userId, get_current_language());
                    } elseif ($status == "cancelled") {
                        $response = validate_status($order_id, $status, '', '', '', '', '', $this->userId, get_current_language());
                    } else {
                        $response = validate_status($order_id, $status, null, null, null, null, null, $this->userId, get_current_language());
                    }

                    if (is_array($response) && isset($response['error']) && $response['error'] == false) {
                        $orderData = fetch_details('orders', ['id' => $order_id], ['user_id']);
                        $customerId = !empty($orderData) ? $orderData[0]['user_id'] : '';

                        $eventType = 'booking_status_updated';
                        if ($status == 'accepted' || $status == 'confirmed') {
                            $eventType = 'booking_accepted';
                        } elseif ($status == 'rejected') {
                            $eventType = 'booking_rejected';
                        } elseif ($status == 'cancelled') {
                            $eventType = 'booking_cancelled';
                        } elseif ($status == 'completed') {
                            $eventType = 'booking_completed';
                        }

                        if (!isset($response['data'])) {
                            $response['data'] = [];
                        }

                        $response['data']['clarity_event'] = $eventType;
                        $response['data']['booking_id'] = $order_id;
                        $response['data']['status'] = $status;
                        $response['data']['customer_id'] = $customerId;
                    }

                    return json_encode($response);
                }
            } else {
                return redirect('admin/login');
            }
        } catch (\Exception $e) {
            log_the_responce($e, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Orders.php - update_order_status()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function get_slots()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        $order_id = $this->request->getPost('id');
        $date = $this->request->getPost('date');

        if (empty($order_id) || empty($date)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(INVALID_BOOKING_OR_STATUS_DATA, "Invalid booking or status data"),
                'data' => []
            ]);
        }

        $order = fetch_details('orders', ['id' => $order_id], ['partner_id']);
        if (empty($order)) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(NO_PARTNER_FOUND, "No Partner Found"),
                'data' => []
            ]);
        }

        $partner_id = $order[0]['partner_id'];
        $settings = get_settings('general_settings', true);
        $timezone = $settings['system_timezone'] ?? date_default_timezone_get();

        $selected_date = \DateTime::createFromFormat('Y-m-d', $date, new \DateTimeZone($timezone));
        if (!$selected_date) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('invalid_date_format', "Invalid date format"),
                'data' => []
            ]);
        }

        $selected_date->setTime(0, 0);
        $today = new \DateTime('now', new \DateTimeZone($timezone));
        $today->setTime(0, 0);

        if ($selected_date < $today) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(PLEASE_SELECT_UPCOMING_DATE, "Please select upcoming date"),
                'data' => []
            ]);
        }

        $partner_data = fetch_details('partner_details', ['partner_id' => $partner_id], ['advance_booking_days']);
        if (!empty($partner_data)) {
            $allowed_advanced_booking_days = intval($partner_data[0]['advance_booking_days']);

            if ($allowed_advanced_booking_days === 0 && $selected_date > $today) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(ADVANCED_BOOKING_FOR_THIS_PARTNER_IS_NOT_AVAILABLE, "Advanced booking for this partner is not available"),
                    'data' => []
                ]);
            }

            if ($allowed_advanced_booking_days > 0) {
                $max_available_date = (clone $today)->modify('+' . $allowed_advanced_booking_days . ' days');
                if ($selected_date > $max_available_date) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels(YOU_CAN_NOT_CHOOSE_DATE_BEYOND_AVAILABLE_BOOKING_DAYS, "You can not choose date beyond available booking days") . ' ' . $allowed_advanced_booking_days . ' ' . labels(DAYS, "days"),
                        'data' => []
                    ]);
                }
            }
        } else {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(NO_PARTNER_FOUND, "No Partner Found"),
                'data' => []
            ]);
        }

        $slots = get_available_slots($partner_id, $selected_date->format('Y-m-d'));
        return $this->response->setJSON($slots);
    }

    public function newlist()
    {
        $orders_model = new Orders_model();

        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }

        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 5;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where['o.partner_id'] = $this->userId;

        $result = $orders_model->dashboardRecentBookingList($limit, $offset, $sort, $order, $where);

        $data = json_decode($result, true);

        if (isset($data['rows']) && is_array($data['rows'])) {
            foreach ($data['rows'] as &$row) {
                $row['service_name'] = $this->extractServiceNameFromRow($row);
                $row['operations'] = '<a href="' . base_url('partner/orders/veiw_orders/' . $row['id']) . '" class="btn btn-primary btn-sm" title="' . labels('view_the_order', 'View the Order') . '"><i class="fa fa-eye" aria-hidden="true"></i></a>';
            }
            unset($row);
        }

        return json_encode($data);
    }
}