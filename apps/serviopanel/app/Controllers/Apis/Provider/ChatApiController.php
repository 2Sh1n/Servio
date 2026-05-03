<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Services\NotificationService;
class ChatApiController extends BaseController
{
    protected $request;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
    ];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function send_chat_message()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'receiver_type' => 'required'
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
            // Try to grab multiple files; fallback to single
            $attachments = $this->request->getFileMultiple('attachment');
            if (empty($attachments)) {
                $file = $this->request->getFile('attachment');
                $attachments = $file ? [$file] : [];
            }

            // Check if there's at least one valid file
            $hasAttachment = !empty($attachments) && $attachments[0]->isValid();

            // Only require message if no valid attachment
            if (!$hasAttachment) {
                $validation = \Config\Services::validation();
                $validation->setRules(['message' => 'required']);

                if (!$validation->withRequest($this->request)->run()) {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => $validation->getErrors(),
                        'data'    => [],
                    ]);
                }
            }
            $message = $this->request->getPost('message') ?? "";
            $receiver_id = $this->request->getPost('receiver_id');
            if ($receiver_id == null) {
                $user_group = fetch_details('users_groups', ['group_id' => '1']);
                $receiver_id = end($user_group)['group_id'];
            }
            $receiver_type = $this->request->getPost('receiver_type');
            $sender_id =  $this->user_details['id'];
            $booking_id =  $this->request->getPost('booking_id');
            if (isset($booking_id)) {
                $e_id_data = fetch_details('enquiries', ['customer_id' => $receiver_id, 'userType' => 2, 'booking_id' => $booking_id]);
                $e_id = empty($e_id_data) ? add_enquiry_for_chat("customer", $_POST['receiver_id'], true, $_POST['booking_id']) : $e_id_data[0]['id'];
            } else {
                if ($booking_id == null) {
                    if ($receiver_type == "0") {
                        $enquiry = fetch_details('enquiries', ['customer_id' => null, 'userType' => 1, 'booking_id' => NULL, 'provider_id' => $sender_id]);
                        if (empty($enquiry[0])) {
                            $provider = fetch_details('users', ['id' => $sender_id], ['username'])[0];
                            $data['title'] =  $provider['username'] . '_query';
                            $data['status'] =  1;
                            $data['userType'] =  1;
                            $data['customer_id'] = null;
                            $data['provider_id'] = $sender_id;
                            $data['date'] =  now();
                            $store = insert_details($data, 'enquiries');
                            $e_id = $store['id'];
                        } else {
                            $e_id = $enquiry[0]['id'];
                        }
                    } else if ($receiver_type == "2") {
                        $enquiry = fetch_details('enquiries', ['customer_id' => $receiver_id, 'userType' => 2, 'booking_id' => NULL, 'provider_id' => $sender_id]);
                        if (empty($enquiry[0])) {
                            $customer = fetch_details('users', ['id' => $sender_id], ['username'])[0];
                            $data['title'] =  $customer['username'] . '_query';
                            $data['status'] =  1;
                            $data['userType'] =  2;
                            $data['customer_id'] = $receiver_id;
                            $data['provider_id'] = $sender_id;
                            $data['date'] =  now();
                            $store = insert_details($data, 'enquiries');
                            $e_id = $store['id'];
                        } else {
                            $e_id = $enquiry[0]['id'];
                        }
                    }
                }
            }
            $last_date = getLastMessageDateFromChat($e_id);
            // Attachment check
            $is_file = (!empty($attachments) && $attachments[0]->isValid());
            $attachment_image = $is_file ? $_FILES['attachment'] : null;

            $booking_id = $this->request->getPost('booking_id') ?? null;
            $data = insert_chat_message_for_chat($sender_id, $receiver_id, $message, $e_id, 1, $receiver_type, date('Y-m-d H:i:s'), $is_file, $attachment_image, $booking_id);

            // Determine notification type and get data
            $notifType = isset($booking_id) ? 'provider_booking' : ($receiver_type == 2 ? 'provider' : 'admin');
            $when_customer_is_receiver = isset($booking_id) ? 'yes' : ($receiver_type == 2 ? 'yes' : null);
            $new_data = getSenderReceiverDataForChatNotification($sender_id, $receiver_id, $data['id'], $last_date, $notifType, $when_customer_is_receiver);

            // Surface booking/provider metadata for client apps (mirrors customer chat response structure).
            $chatExtras = build_chat_message_details(
                (int) $sender_id,
                $booking_id ? (int) $booking_id : null,
                $receiver_type !== null ? (int) $receiver_type : null,
                (int) $sender_id
            );
            $data_with_extras = array_merge($data ?? [], $chatExtras);
            $new_data = array_merge($new_data ?? [], $chatExtras);

            // Build a single message payload with the same shape as get_chat_history() rows.
            $singleMessageForPush = fetch_details('chats', ['id' => (int)($data['id'] ?? 0)]);
            $singleMessageForPush = !empty($singleMessageForPush[0]) ? $singleMessageForPush[0] : ($data ?? []);
            $singleMessageForPush['sender_details'] = $new_data['sender_details'] ?? [];
            $singleMessageForPush['receiver_details'] = $new_data['receiver_details'] ?? [];

            $disk = fetch_current_file_manager();
            if (!empty($singleMessageForPush['file'])) {
                $decoded_files = json_decode($singleMessageForPush['file'], true);
                if (is_array($decoded_files)) {
                    $tempFiles = [];
                    foreach ($decoded_files as $f) {
                        if ($disk == 'local_server') {
                            $fileUrl = base_url($f['file']);
                        } elseif ($disk == 'aws_s3') {
                            $fileUrl = fetch_cloud_front_url('chat_attachment', $f['file']);
                        } else {
                            $fileUrl = base_url($f['file']);
                        }
                        $tempFiles[] = [
                            'file' => $fileUrl,
                            'file_type' => $f['file_type'] ?? '',
                            'file_name' => $f['file_name'] ?? '',
                            'file_size' => $f['file_size'] ?? '',
                        ];
                    }
                    $singleMessageForPush['file'] = $tempFiles;
                } else {
                    $singleMessageForPush['file'] = [];
                }
            } else {
                $singleMessageForPush['file'] = [];
            }

            $singleChatMessagePayload = json_encode($singleMessageForPush, JSON_UNESCAPED_SLASHES);
            if ($singleChatMessagePayload === false) {
                $singleChatMessagePayload = '{}';
            }

           
            // Build a single provider payload for receiver context.
            // Receiver must be provider; if receiver is admin then keep it NULL.
            $singleProviderPayload = null;
            if ($this->user_details['id']) {
                $providerUser = fetch_details('users', ['id' => (int) $this->user_details['id']], ['id', 'image']);
                $providerDetails = fetch_details('partner_details', ['partner_id' => (int) $this->user_details['id']], ['partner_id', 'company_name']);
                $providerObject = [
                    'id' => (int) $this->user_details['id'],
                    'last_chat_date' => (string)($new_data['created_at'] ?? $data['created_at'] ?? $last_date),
                    'partner_id' => (int) $this->user_details['id'],
                    'partner_name' => $providerDetails[0]['company_name'] ?? '',
                    'translated_partner_name' => $providerDetails[0]['company_name'] ?? '',
                    'image' => '',
                    'booking_id' => $booking_id ? (string)$booking_id : null,
                    'order_status' => null,
                    'translated_order_status' => null,
                    'un_read_chats' => 0,
                    'is_blocked' => 0,
                    'is_block_by_user' => 0,
                    'is_block_by_provider' => 0,
                ];

                // Keep translated provider naming aligned with get_chat_providers_list.
                if (!empty($providerObject['partner_id']) && !empty($providerObject['partner_name'])) {
                    $translatedData = get_translated_partner_field((int) $providerObject['partner_id'], 'company_name', $providerObject['partner_name']);
                    $providerObject['partner_name'] = $translatedData ?? $providerObject['partner_name'];
                    $providerObject['translated_partner_name'] = $translatedData ?? $providerObject['partner_name'];
                }

                if ($booking_id) {
                    $orderRow = fetch_details('orders', ['id' => (int) $booking_id], ['status']);
                    $providerObject['order_status'] = $orderRow[0]['status'] ?? null;
                    if (!empty($providerObject['order_status'])) {
                        $providerObject['translated_order_status'] = getTranslatedValue($providerObject['order_status'], 'panel');
                    }
                }

                // Match blocking flags from get_chat_providers_list.
                $userReport = fetch_details('user_reports', [
                    'reporter_id' => (int) $sender_id,
                    'reported_user_id' => (int) $receiver_id
                ], ['id']);
                $providerReport = fetch_details('user_reports', [
                    'reporter_id' => (int) $receiver_id,
                    'reported_user_id' => (int) $sender_id
                ], ['id']);
                $providerObject['is_block_by_user'] = !empty($userReport) ? 1 : 0;
                $providerObject['is_block_by_provider'] = !empty($providerReport) ? 1 : 0;
                $providerObject['is_blocked'] = (!empty($userReport) || !empty($providerReport)) ? 1 : 0;

                $providerImage = $providerUser[0]['image'] ?? '';
                if (!empty($providerImage)) {
                    // Keep image resolution logic aligned with get_chat_providers_list output.
                    $providerObject['image'] = (file_exists(FCPATH . 'public/backend/assets/profiles/' . $providerImage))
                        ? base_url('public/backend/assets/profiles/' . $providerImage)
                        : ((file_exists(FCPATH . $providerImage))
                            ? base_url($providerImage)
                            : ((!file_exists(FCPATH . "public/uploads/users/partners/" . $providerImage))
                                ? base_url("public/backend/assets/profiles/default.png")
                                : base_url("public/uploads/users/partners/" . $providerImage)
                            )
                        );
                }
                if (empty($providerObject['image'])) {
                    $providerObject['image'] = base_url("public/backend/assets/profiles/default.png");
                }

                $singleProviderPayload = json_encode($providerObject, JSON_UNESCAPED_SLASHES);
                if ($singleProviderPayload === false) {
                    $singleProviderPayload = null;
                }
            }

            // Send FCM notification using NotificationService
            // This works for all scenarios: provider to admin, provider to customer, etc.
            try {
                // Detect demo mode using ALLOW_MODIFICATION flag.
                // In demo mode we still send FCM, but only to a small,
                // recent subset of tokens to avoid slow responses.
                $isDemoMode = (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION != 1);
                $language   = get_current_language_from_request();

                // Prepare context data for notification templates.
                // NOTE:
                // - Keep keys simple and camelCase so that apps and panels can read them easily.
                // - Core identity fields (names / types) stay here.
                $notificationContext = [
                    // Serialized rich context payloads for chat notification consumers.
                    'chat_message' => $singleChatMessagePayload,
                    'chat_user'    => $singleProviderPayload,
                ];

                // Add booking_id if present (legacy key used by some templates).
                if ($booking_id) {
                    $notificationContext['booking_id'] = $booking_id;
                }

                // Determine platforms based on receiver type
                $platforms = ['android', 'ios', 'web'];
                if ($receiver_type == 0) {
                    // Admin
                    $platforms = ['admin_panel'];
                } elseif ($receiver_type == 1) {
                    // Provider
                    $platforms = ['android', 'ios', 'web', 'provider_panel'];
                }

                // Build base options for NotificationService.
                $sendOptions = [
                    'channels'  => ['fcm'],
                    'language'  => $language,
                    'platforms' => $platforms,
                    'data'      => $notificationContext,
                ];

                // In demo mode, instruct FCM provider to only use the last
                // N tokens per language (here 20) so pushes are still sent
                // but the request stays fast even with many stored tokens.
                if ($isDemoMode) {
                    $sendOptions['fcm_demo_token_limit'] = 20;
                }

                $notificationService = new NotificationService();
                $notificationService->send(
                    'new_message',
                    ['user_id' => (int) $receiver_id],
                    $notificationContext,
                    $sendOptions
                );
              
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the message sending
                log_message('error', '[NEW_MESSAGE] FCM notification error trace (partner): ' . $notificationError->getTraceAsString());
            }

            return response_helper(labels(SENT_MESSAGE_SUCCESSFULLY, 'Sent message successfully '), false, $data_with_extras, 200);
        } catch (\Throwable $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - send_chat_message()');
            return $this->response->setJSON($response);
        }
    }

    public function get_chat_history()
    {
        try {
            $validation = service('validation');
            $validation->setRules([
                'type' => 'required',
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
            $type = $this->request->getPost('type');
            $e_id = $this->request->getPost('e_id');


            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $db = \Config\Database::connect();
            $current_user_id = $this->user_details['id'];

            $provider_report = fetch_details('user_reports', [
                'reporter_id' => $current_user_id,
                'reported_user_id' => $this->request->getPost('customer_id')
            ]);


            $is_block_by_provider = !empty($provider_report) ? "1" : "0";

            // Check if provider blocked user
            $user_report = fetch_details('user_reports', [
                'reporter_id' => $this->request->getPost('customer_id'),
                'reported_user_id' => $current_user_id
            ]);
            $is_block_by_user = !empty($user_report) ? "1" : "0";

            // Set overall blocked status
            $is_blocked = $is_block_by_provider == "1" ? "1" : "0";


            if ($type == "0") {
                // Chat messages sent by provider to admin
                $e_id_data = fetch_details('enquiries', ['customer_id' => NULL, 'userType' => 1, 'provider_id' => $current_user_id, 'booking_id' => null]);
                if (!empty($e_id_data)) {
                    $e_id = $e_id_data[0]['id'];
                    $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null);
                    $countBuilder = $db->table('chats c');
                    $countBuilder->select('COUNT(*) as total')
                        ->where('c.booking_id', null)
                        ->where('c.e_id', $e_id);
                    $totalRecords = $countBuilder->get()->getRow()->total;
                    $mainBuilder = $db->table('chats c');
                    $mainBuilder->select('c.*')
                        ->where('c.e_id', $e_id)
                        ->where('c.booking_id', null)
                        ->limit($limit, $offset);
                    $chat_record = $mainBuilder->orderBy('c.created_at', 'DESC')->get()->getResultArray();
                    $disk = fetch_current_file_manager();
                    foreach ($chat_record as $key => $row) {
                        $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'admin');

                        $provider_report = fetch_details('user_reports', [
                            'reporter_id' => $row['sender_id'],
                            'reported_user_id' => $row['receiver_id']
                        ]);
                        $is_block_by_provider = !empty($provider_report) ? "1" : "0";

                        // Check if provider blocked user
                        $user_report = fetch_details('user_reports', [
                            'reporter_id' => $row['receiver_id'],
                            'reported_user_id' =>  $row['sender_id']
                        ]);
                        $is_block_by_user = !empty($user_report) ? "1" : "0";

                        // Set overall blocked status
                        $is_blocked = $is_block_by_provider == "1" ? "1" : "0";

                        $chat_record[$key]['sender_details'] = $new_data['sender_details'];
                        $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
                        if (!empty($chat_record[$key]['file'])) {
                            $decoded_files = json_decode($chat_record[$key]['file'], true);
                            if (is_array($decoded_files)) {
                                $tempFiles = [];
                                foreach ($decoded_files as $data) {
                                    if ($disk == 'local_server') {
                                        $file = base_url($data['file']);
                                    } elseif ($disk == 'aws_s3') {
                                        $file = fetch_cloud_front_url('chat_attachment', $data['file']);
                                    } else {
                                        $file = base_url($data['file']);
                                    }
                                    $tempFiles[] = [
                                        'file' => $file,
                                        'file_type' => $data['file_type'],
                                        'file_name' => $data['file_name'],
                                        'file_size' => $data['file_size'],
                                    ];
                                }
                                $chat_record[$key]['file'] = $tempFiles;
                            } else {
                                $chat_record[$key]['file'] = [];
                            }
                        } else {
                            $chat_record[$key]['file'] = [];
                        }
                    }
                    return response_helper(labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '), false, $chat_record, 200, ['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                } else {
                    return response_helper(labels(NO_DATA_FOUND, 'No data Found '), false, [], 200, ['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                }
            } else if ($type = "2") {
                // Chat messages sent by provider to customer
                if ($this->request->getPost('booking_id') != null) {
                    $booking = fetch_details('orders', ['id' => $this->request->getPost('booking_id')], ['user_id']);
                }
                if (!empty($booking)) {
                    $e_id_data = fetch_details('enquiries', ['booking_id' => $this->request->getPost('booking_id'), 'customer_id' => $booking[0]['user_id']]);
                    if (!empty($e_id_data)) {
                        $e_id = $e_id_data[0]['id'];
                        $booking_id = $e_id_data[0]['booking_id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, (int) $booking_id);
                        $countBuilder = $db->table('chats c');
                        $countBuilder->select('COUNT(*) as total')
                            ->where('c.e_id', $e_id)
                            ->where('c.booking_id', $booking_id);
                        $totalRecords = $countBuilder->get()->getRow()->total;
                        $mainBuilder = $db->table('chats c');
                        $mainBuilder->select('c.*')
                            ->where('c.e_id', $e_id)
                            ->where('c.booking_id', $booking_id)
                            ->limit($limit, $offset);
                        $chat_record = $mainBuilder->orderBy('c.created_at', 'DESC')->get()->getResultArray();
                        $disk = fetch_current_file_manager();
                        foreach ($chat_record as $key => $row) {
                            $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'admin');
                            $chat_record[$key]['sender_details'] = $new_data['sender_details'];
                            $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
                            if (!empty($chat_record[$key]['file'])) {
                                $decoded_files = json_decode($chat_record[$key]['file'], true);
                                if (is_array($decoded_files)) {
                                    $tempFiles = [];
                                    foreach ($decoded_files as $data) {
                                        if ($disk == 'local_server') {
                                            $file = base_url($data['file']);
                                        } elseif ($disk == 'aws_s3') {
                                            $file = fetch_cloud_front_url('chat_attachment', $data['file']);
                                        } else {
                                            $file = base_url($data['file']);
                                        }
                                        $tempFiles[] = [
                                            'file' => $file,
                                            'file_type' => $data['file_type'],
                                            'file_name' => $data['file_name'],
                                            'file_size' => $data['file_size'],
                                        ];
                                    }
                                    $chat_record[$key]['file'] = $tempFiles;
                                } else {
                                    $chat_record[$key]['file'] = [];
                                }
                            } else {
                                $chat_record[$key]['file'] = [];
                            }
                        }
                        return response_helper(labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '), false, $chat_record, 200, ['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                    } else {
                        return response_helper(labels(NO_DATA_FOUND, 'No data found '), false, [], 200, ['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                    }
                } else {
                    if ($this->request->getPost('booking_id') == null) {
                        $customer_id = $this->request->getPost('customer_id');
                        $e_id_data = fetch_details('enquiries', ['booking_id' => NULL, 'customer_id' => $customer_id, 'provider_id' => $current_user_id]);

                        $e_id = $e_id_data[0]['id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null);
                        $countBuilder = $db->table('chats c');
                        $countBuilder->select('COUNT(*) as total')
                            ->where('c.e_id', $e_id);
                        $totalRecords = $countBuilder->get()->getRow()->total;
                        $mainBuilder = $db->table('chats c');
                        $mainBuilder->select('c.*')
                            ->where('c.e_id', $e_id)
                            ->limit($limit, $offset);
                        $chat_record = $mainBuilder->orderBy('c.created_at', 'DESC')->get()->getResultArray();
                        $disk = fetch_current_file_manager();
                        foreach ($chat_record as $key => $row) {
                            $new_data = getSenderReceiverDataForChatNotification($row['sender_id'], $row['receiver_id'], $row['id'], $row['created_at'], 'provider_booking', 'yes');

                            // // Check if user blocked provider
                            // $user_report = fetch_details('user_reports', [
                            //     'reporter_id' => $row['sender_id'],
                            //     'reported_user_id' => $row['receiver_id']
                            // ]);
                            // $is_block_by_user = !empty($user_report) ? "1" : "0";

                            // // Check if provider blocked user
                            // $provider_report = fetch_details('user_reports', [
                            //     'reporter_id' => $row['receiver_id'],
                            //     'reported_user_id' => $row['sender_id']
                            // ]);
                            // $is_block_by_provider = !empty($provider_report) ? "1" : "0";

                            // // Set overall blocked status
                            // $is_blocked = $is_block_by_user == "1" ? "1" : "0";

                            $chat_record[$key]['sender_details'] = $new_data['sender_details'];
                            $chat_record[$key]['receiver_details'] = $new_data['receiver_details'];
                            if (!empty($chat_record[$key]['file'])) {
                                $decoded_files = json_decode($chat_record[$key]['file'], true);
                                if (is_array($decoded_files)) {
                                    $tempFiles = [];
                                    foreach ($decoded_files as $data) {
                                        if ($disk == 'local_server') {
                                            $file = base_url($data['file']);
                                        } elseif ($disk == 'aws_s3') {
                                            $file = fetch_cloud_front_url('chat_attachment', $data['file']);
                                        } else {
                                            $file = base_url($data['file']);
                                        }
                                        $tempFiles[] = [
                                            'file' => $file,
                                            'file_type' => $data['file_type'],
                                            'file_name' => $data['file_name'],
                                            'file_size' => $data['file_size'],
                                        ];
                                    }
                                    $chat_record[$key]['file'] = $tempFiles;
                                } else {
                                    $chat_record[$key]['file'] = [];
                                }
                            } else {
                                $chat_record[$key]['file'] = [];
                            }
                        }
                        return response_helper(labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '), false, $chat_record, 200, ['total' => $totalRecords, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                    }
                    return response_helper(labels(NO_BOOKING_FOUND, 'No Booking found'), false, [], 200, ['total' => 0, 'is_blocked' => $is_blocked, 'is_block_by_user' => $is_block_by_user, 'is_block_by_provider' => $is_block_by_provider]);
                }
            }
        } catch (\Throwable $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_chat_history()');
            return $this->response->setJSON($response);
        }
    }

    public function mark_message_as_read()
    {
        try {
            $validation = service('validation');
            $validation->setRules([
                'type' => 'required',
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
            $type = $this->request->getPost('type');
            $current_user_id = $this->user_details['id'];
            $db = \Config\Database::connect();

            if ($type == "0") {
                // Admin chat: mark messages as read where receiver is current provider
                $e_id_data = fetch_details('enquiries', ['customer_id' => NULL, 'userType' => 1, 'provider_id' => $current_user_id, 'booking_id' => null]);
                if (!empty($e_id_data)) {
                    $e_id = $e_id_data[0]['id'];
                    $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null);
                }
            } else if ($type == "2") {
                $booking_id = $this->request->getPost('booking_id');
                if ($booking_id != null) {
                    $booking = fetch_details('orders', ['id' => $booking_id], ['user_id']);
                    if (!empty($booking)) {
                        $e_id_data = fetch_details('enquiries', ['booking_id' => $booking_id, 'customer_id' => $booking[0]['user_id']]);
                        if (!empty($e_id_data)) {
                            $e_id = $e_id_data[0]['id'];
                            $b_id = $e_id_data[0]['booking_id'];
                            $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, (int) $b_id);
                        }
                    }
                } else {
                    $customer_id = $this->request->getPost('customer_id');
                    $e_id_data = fetch_details('enquiries', ['booking_id' => NULL, 'customer_id' => $customer_id, 'provider_id' => $current_user_id]);
                    if (!empty($e_id_data)) {
                        $e_id = $e_id_data[0]['id'];
                        $this->markChatsAsReadForReceiver($db, (int) $current_user_id, (int) $e_id, null);
                    }
                }
            }

            return response_helper(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), false, [], 200);
        } catch (\Throwable $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - mark_message_as_read()');
            return $this->response->setJSON($response);
        }
    }

    public function get_chat_customers_list()
    {
        try {
            $currentUserId = (int) $this->user_details['id'];
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            // 'enquiry' = pre-booking chats, 'booking' = booking-related chats, null = both
            $type = $this->request->getPost('type') ?? null;
            $db = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select(' us.id as customer_id,
                                us.username as customer_name,
                                us.image as image,
                                MAX(c.created_at) AS last_chat_date,
                                c.booking_id,
                                o.status as booking_status,
                                (SELECT COUNT(*)
                                   FROM chats uc
                                  WHERE uc.booking_id = c.booking_id
                                    AND uc.receiver_id = ' . $currentUserId . '
                                    AND uc.is_read = 0
                                ) AS un_read_chats')
                ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 1) OR (c.receiver_id = u.id AND c.receiver_type = 1)")
                ->join('orders o', "o.id = c.booking_id")
                ->join('users us', "us.id = o.user_id")
                ->where('o.partner_id', $this->user_details['id'])
                ->groupStart()
                ->where('c.sender_id', $currentUserId)
                ->orWhere('c.receiver_id', $currentUserId)
                ->groupEnd()
                ->groupBy('c.booking_id')
                ->orderBy('last_chat_date', 'DESC');
            $totalCustomersQuery1 = $builder->countAllResults(false);
            $customers_with_chats = $builder->get()->getResultArray();
            // print_r($customers_with_chats);
            // exit;
            $disk = fetch_current_file_manager();
            foreach ($customers_with_chats as $key => $row) {
                $orderStatus = isset($row['order_status']) && !empty($row['order_status']) ? $row['order_status'] : '';
                $bookingStatus = isset($row['booking_status']) && !empty($row['booking_status']) ? $row['booking_status'] : '';
                if (!empty($orderStatus)) {
                    $customers_with_chats[$key]['translated_order_status'] = getTranslatedValue($orderStatus, 'panel');
                }
                $customers_with_chats[$key]['translated_booking_status'] = getTranslatedValue($bookingStatus, 'panel');
                if (isset($row['image'])) {
                    if ($disk == "local_server") {
                        $imagePath = $row['image'];
                        $customers_with_chats[$key]['image'] = fix_provider_path($imagePath);
                    } else if ($disk == "aws_s3") {
                        $customers_with_chats[$key]['image'] = fetch_cloud_front_url('profile', $row['image']);
                    } else {
                        $imagePath = $row['image'];
                        $customers_with_chats[$key]['image'] = fix_provider_path($imagePath);
                    }
                }
            }
            $builder1 = $db->table('users u');
            $builder1->select(' us.id as customer_id,
                                us.username as customer_name,
                                us.image as image,
                                MAX(c.created_at) AS last_chat_date,
                                c.booking_id,
                                (SELECT COUNT(*)
                                   FROM chats uc
                                   JOIN enquiries ue ON ue.id = uc.e_id
                                  WHERE ue.provider_id = e.provider_id
                                    AND ue.customer_id = e.customer_id
                                    AND uc.booking_id IS NULL
                                    AND uc.receiver_id = ' . $currentUserId . '
                                    AND uc.is_read = 0
                                ) AS un_read_chats')
                ->join('chats c', "(c.sender_id = u.id AND c.sender_type = 1) OR (c.receiver_id = u.id AND c.receiver_type = 1)")
                ->join('enquiries e', "e.id = c.e_id")
                ->join('users us', "us.id = e.customer_id")
                ->where('e.provider_id', $this->user_details['id'])
                ->groupStart()
                ->where('c.sender_id', $currentUserId)
                ->orWhere('c.receiver_id', $currentUserId)
                ->groupEnd()
                ->groupBy('e.customer_id')
                ->orderBy('last_chat_date', 'DESC');
            $totalCustomersQuery2 = $builder1->countAllResults(false);
            $customer_pre_booking_queries = $builder1->get()->getResultArray();
            // print_r($customer_pre_booking_queries);
            // exit;
            foreach ($customer_pre_booking_queries as $key => $row) {

                if (isset($row['image'])) {
                    if ($disk == "local_server") {
                        $imagePath = $row['image'];
                        $customer_pre_booking_queries[$key]['image'] = fix_provider_path($imagePath);
                    } else if ($disk == "aws_s3") {
                        $customer_pre_booking_queries[$key]['image'] = fetch_cloud_front_url('profile', $row['image']);
                    } else {
                        $imagePath = $row['image'];
                        $customer_pre_booking_queries[$key]['image'] = fix_provider_path($imagePath);
                    }
                    $customer_pre_booking_queries[$key]['order_id'] = "";
                    $customer_pre_booking_queries[$key]['order_status'] = "";
                }
            }

            //note: If limit and offset are greater than total records, then array slice empty array is returned.
            $merged_array = array_merge($customers_with_chats, $customer_pre_booking_queries);
            $totalRecords = $totalCustomersQuery1 + $totalCustomersQuery2;

            // Filter by type if specified (similar to filter_type in get_chat_providers_list)
            if ($type === 'booking') {
                // Keep only chats that have a booking_id (booking-related chats)
                $merged_array = array_values(array_filter($merged_array, function ($chat) {
                    return (!empty($chat['booking_id']) && $chat['booking_id'] !== null);
                }));
                $totalRecords = count($merged_array);
            } elseif ($type === 'enquiry') {
                // Keep only chats without a booking_id (pre-booking / enquiry chats)
                $merged_array = array_values(array_filter($merged_array, function ($chat) {
                    return empty($chat['booking_id']);
                }));
                $totalRecords = count($merged_array);
            }

            // Aggregated count of customers (users) who have at least one unread chat
            // within the current filter (booking / enquiry / both).
            $usersWithUnreadChats = count(array_filter(
                $merged_array,
                static function ($chat) {
                    return (int) ($chat['un_read_chats'] ?? 0) > 0;
                }
            ));

            usort($merged_array, function ($a, $b) {
                return ($b['last_chat_date'] <=> $a['last_chat_date']);
            });

            $merged_array = array_slice($merged_array, $offset, $limit);

            return response_helper(
                labels(RETRIVED_SUCCESSFULLY, 'Retrived successfully '),
                false,
                $merged_array,
                200,
                [
                    'total' => $totalRecords,
                    'total_unread_users' => $usersWithUnreadChats,
                ]
            );
        } catch (\Throwable $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_chat_customers_list()');
            return $this->response->setJSON($response);
        }
    }

    public function delete_chat_user()
    {
        try {
            $sender_id = $this->user_details['id'];
            $receiver_id = $this->request->getPost('user_id');
            $booking_id = $this->request->getPost('booking_id');

            $user_details = fetch_details('users', ['id' => $receiver_id]);

            $chats = fetch_details('chats', ['sender_id' => $sender_id, 'receiver_id' => $receiver_id]);
            $chats_reverse = fetch_details('chats', ['sender_id' => $receiver_id, 'receiver_id' => $sender_id]);

            if (empty($chats) && empty($chats_reverse)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(CHAT_NOT_FOUND, 'Chat not found'),
                ]);
            }

            if (isset($booking_id) && !empty($booking_id)) {
                $delete_chat = delete_details(['booking_id' => $booking_id], 'chats');
            } else {
                $delete_chat = delete_details(['sender_id' => $sender_id, 'receiver_id' => $receiver_id, 'booking_id' => null], 'chats');
                $delete_chat_reverse = delete_details(['sender_id' => $receiver_id, 'receiver_id' => $sender_id, 'booking_id' => null], 'chats');
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(CHAT_DELETED_SUCCESSFULLY, 'Chat deleted successfully'),
            ]);
        } catch (\Throwable $th) {
            throw $th;
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    // Private Helper methods
    // Marks all unread chats for a given enquiry + (optional) booking
    // where the given user is the receiver.
    private function markChatsAsReadForReceiver($db, int $currentUserId, int $enquiryId, ?int $bookingId = null): void
    {
        $builder = $db->table('chats');
        $builder->where('e_id', $enquiryId)
            ->where('receiver_id', $currentUserId)
            ->where('is_read', 0);

        if ($bookingId === null) {
            $builder->where('booking_id', null);
        } else {
            $builder->where('booking_id', $bookingId);
        }

        $builder->update([
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
