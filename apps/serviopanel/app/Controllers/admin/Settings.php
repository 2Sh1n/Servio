<?php

namespace App\Controllers\admin;

use App\Models\Country_code_model;
use App\Models\Email_template_model;
use Exception;

use CodeIgniter\Queue\Queue;

class Settings extends Admin
{
    private $db, $builder;
    protected $superadmin;
    protected $validation;

    public function __construct()
    {
        parent::__construct();
        $this->db = \Config\Database::connect();
        $this->validation = \Config\Services::validation();
        $this->builder = $this->db->table('settings');
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
        helper('events');
        helper('function'); // Load function_helper to access html_is_effectively_empty()
    }

    public function __destruct()
    {
        $this->db->close();
        $this->data = [];
    }

    public function main_system_setting_page()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        setPageInfo($this->data, labels('System Settings', 'System Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'main_system_settings');
        return view('backend/admin/template', $this->data);
    }
    public function general_settings()
    {
        try {
            helper('form');
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {


                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();
                $data = get_settings('general_settings', true);

                $disk = fetch_current_file_manager();

                $files = [
                    'favicon' => ['file' => $this->request->getFile('favicon'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload favicon', 'Failed to upload favicon'), 'folder' => 'site', 'old_file' => $data['favicon'] ?? null, 'disk' => $disk],
                    'half_logo' => ['file' => $this->request->getFile('half_logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload half_logo', 'Failed to upload half_logo'), 'folder' => 'site', 'old_file' => $data['half_logo'] ?? null, 'disk' => $disk],
                    'logo' => ['file' => $this->request->getFile('logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload logo', 'Failed to upload logo'), 'folder' => 'site', 'old_file' => $data['logo'] ?? null, 'disk' => $disk],
                    'partner_favicon' => ['file' => $this->request->getFile('partner_favicon'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload partner_favicon', 'Failed to upload partner_favicon'), 'folder' => 'site', 'old_file' => $data['partner_favicon'] ?? null, 'disk' => $disk],
                    'partner_half_logo' => ['file' => $this->request->getFile('partner_half_logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload partner_half_logo', 'Failed to upload partner_half_logo'), 'folder' => 'site', 'old_file' => $data['partner_half_logo'] ?? null, 'disk' => $disk],
                    'partner_logo' => ['file' => $this->request->getFile('partner_logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload partner_logo', 'Failed to upload partner_logo'), 'folder' => 'site', 'old_file' => $data['partner_logo'] ?? null, 'disk' => $disk],
                    'login_image' => ['file' => $this->request->getFile('login_image'), 'path' => 'public/frontend/retro/', 'error' => labels('Failed to upload login_image', 'Failed to upload login_image'), 'folder' => 'site', 'old_file' => $data['login_image'] ?? null, 'disk' => $disk],
                ];

                $uploadedFiles = [];
                foreach ($files as $key => $config) {

                    if (!empty($_FILES[$key]) && isset($_FILES[$key])) {
                        $file = $config['file'];
                        if ($file && $file->isValid()) {
                            if (!empty($config['old_file'])) {
                                delete_file_based_on_server($config['folder'], $config['old_file'], $config['disk']);
                            }
                            $result = upload_file($config['file'], $config['path'], $config['error'], $config['folder'], 'yes');

                            if ($result['error'] == false) {

                                if ($key == "login_image") {


                                    $uploadedFiles[$key] = [
                                        'url' => "Login_BG.jpg",
                                        'disk' => $result['disk']
                                    ];
                                } else {

                                    $uploadedFiles[$key] = [
                                        'url' => $result['file_name'],
                                        'disk' => $result['disk']
                                    ];
                                }
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

                // die;
                foreach ($uploadedFiles as $key => $value) {
                    $updatedData[$key] = isset($value['url']) ? $value['url'] : (isset($data[$key]) ? $data[$key] : '');
                }

                unset($updatedData['update']);
                unset($updatedData[csrf_token()]);
                $updatedData['currency'] = (!empty($this->request->getPost('currency'))) ? $this->request->getPost('currency') : (isset($data['currency']) ? $data['currency'] : "");
                $updatedData['country_currency_code'] = (!empty($this->request->getPost('country_currency_code'))) ? $this->request->getPost('country_currency_code') : (isset($data['country_currency_code']) ? $data['country_currency_code'] : "");
                if ($this->request->getPost('decimal_point') == 0) {
                    $updatedData['decimal_point'] = "0";
                } elseif (!empty($this->request->getPost('decimal_point'))) {
                    $updatedData['decimal_point'] = $this->request->getPost('decimal_point');
                } else {
                    $updatedData['decimal_point'] = $data['decimal_point'];
                }
                if ($updatedData['distance_unit'] == 'miles') {
                    $distanceInMiles = $this->request->getPost('max_serviceable_distance');
                    $updatedData['distance_unit'] = $this->request->getPost('distance_unit');
                    $distanceInKm = $distanceInMiles * 1.60934;
                    $updatedData['max_serviceable_distance'] = round($distanceInKm);
                }
                if (!empty($this->request->getPost('otp_system'))) {
                    $updatedData['otp_system'] = (!empty($this->request->getPost('otp_system'))) ? $this->request->getPost('otp_system') : (isset($data['otp_system']) ? ($data['otp_system']) : "");
                }
                // Handle multi-language company fields
                $company_title_translations = $_POST['company_title'] ?? [];
                $updatedData['company_title'] = $company_title_translations;

                $copyright_details_translations = $_POST['copyright_details'] ?? [];
                $updatedData['copyright_details'] = $copyright_details_translations;

                $address_translations = $_POST['address'] ?? [];
                $updatedData['address'] = $address_translations;

                $short_description_translations = $_POST['short_description'] ?? [];
                $updatedData['short_description'] = $short_description_translations;

                // NOTE:
                // We must allow admin to CLEAR a setting by submitting an empty string.
                // Previous logic used !empty($this->request->getPost($key)), which kept the old
                // value whenever the new value was an empty string. That made it impossible
                // to remove / clear a value from the UI – the old value was always restored.
                //
                // To fix this, we now check whether the key exists in $_POST. If it exists,
                // we always use the submitted value (even if it is an empty string). If it
                // does not exist at all, we fall back to the existing stored value.
                $keys = ['customer_current_version_ios_app', 'customer_compulsary_update_force_update', 'provider_current_version_android_app', 'provider_current_version_ios_app', 'provider_compulsary_update_force_update', 'customer_app_maintenance_schedule_date', 'message_for_customer_application', 'customer_app_maintenance_mode', 'provider_app_maintenance_schedule_date', 'message_for_provider_application', 'provider_app_maintenance_mode', 'provider_location_in_provider_details', 'support_name', 'support_email', 'phone', 'system_timezone_gmt', 'system_timezone', 'primary_color', 'secondary_color', 'primary_shadow', 'booking_auto_cancle_duration', 'customer_playstore_url', 'customer_appstore_url', 'provider_playstore_url', 'provider_appstore_url', 'maxFilesOrImagesInOneMessage', 'maxFileSizeInMBCanBeSent', 'maxCharactersInATextMessage', 'android_google_interstitial_id', 'android_google_banner_id', 'ios_google_interstitial_id', 'ios_google_banner_id', "android_google_ads_status", "ios_google_ads_status", 'authentication_mode', 'company_map_location', 'support_hours', 'file_manager', 'aws_access_key_id', 'aws_secret_access_key', 'aws_secret_access_key', 'aws_default_region', 'aws_bucket', 'aws_url', 'schema_for_deeplink', 'enable_chat_image_upload', 'enable_chat_file_upload'];
                foreach ($keys as $key) {
                    // If the key is present in POST, always use its value (even if empty string).
                    // This lets the admin actually clear a field from the general settings form.
                    if (array_key_exists($key, $_POST)) {
                        $updatedData[$key] = $this->request->getPost($key);
                    } else {
                        // If key was not submitted at all, keep the existing stored value (if any).
                        $updatedData[$key] = isset($data[$key]) ? $data[$key] : "";
                    }
                }
                $updatedData['customer_current_version_android_app'] = (!empty($this->request->getPost('customer_current_version_android_app'))) ? $this->request->getPost('customer_current_version_android_app') : (isset($data['customer_current_version_android_app']) ? $data['customer_current_version_android_app'] : "");
                $updatedData['customer_current_version_ios_app'] = (!empty($this->request->getPost('customer_current_version_ios_app'))) ? $this->request->getPost('customer_current_version_ios_app') : (isset($data['customer_current_version_ios_app']) ? $data['customer_current_version_ios_app'] : "");
                $updatedData['provider_current_version_android_app'] = (!empty($this->request->getPost('provider_current_version_android_app'))) ? $this->request->getPost('provider_current_version_android_app') : (isset($data['provider_current_version_android_app']) ? $data['provider_current_version_android_app'] : "");
                $updatedData['provider_current_version_ios_app'] = (!empty($this->request->getPost('provider_current_version_ios_app'))) ? $this->request->getPost('provider_current_version_ios_app') : (isset($data['provider_current_version_ios_app']) ? $data['provider_current_version_ios_app'] : "");
                $updatedData['customer_app_maintenance_schedule_date'] = (!empty($this->request->getPost('customer_app_maintenance_schedule_date'))) ? $this->request->getPost('customer_app_maintenance_schedule_date') : (isset($data['customer_app_maintenance_schedule_date']) ? $data['customer_app_maintenance_schedule_date'] : "");
                $updatedData['message_for_customer_application'] = (!empty($this->request->getPost('message_for_customer_application'))) ? $this->request->getPost('message_for_customer_application') : (isset($data['message_for_customer_application']) ? $data['message_for_customer_application'] : "");
                $updatedData['provider_app_maintenance_schedule_date'] = (!empty($this->request->getPost('provider_app_maintenance_schedule_date'))) ? ($this->request->getPost('provider_app_maintenance_schedule_date')) : (isset($data['provider_app_maintenance_schedule_date']) ? $data['provider_app_maintenance_schedule_date'] : "");
                $updatedData['message_for_provider_application'] = (!empty($this->request->getPost('message_for_provider_application'))) ? $this->request->getPost('message_for_provider_application') : (isset($data['message_for_provider_application']) ? $data['message_for_provider_application'] : "");

                // Persistence layer: convert maintenance schedule from admin's local time to UTC before saving.
                // Use browser timezone when sent (so 12:30 IST is stored as 07:00 UTC). Fallback to PHP default.
                $maintenanceInputTz = $this->request->getPost('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                if ($this->request->getPost('maintenance_schedule_entry_timezone')) {
                    $this->session->set('maintenance_schedule_entry_timezone', $this->request->getPost('maintenance_schedule_entry_timezone'));
                }
                if (array_key_exists('customer_app_maintenance_schedule_date', $_POST) && $updatedData['customer_app_maintenance_schedule_date'] !== '') {
                    $updatedData['customer_app_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['customer_app_maintenance_schedule_date'], $maintenanceInputTz);
                }
                if (array_key_exists('provider_app_maintenance_schedule_date', $_POST) && $updatedData['provider_app_maintenance_schedule_date'] !== '') {
                    $updatedData['provider_app_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['provider_app_maintenance_schedule_date'], $maintenanceInputTz);
                }

                $updatedData['customer_compulsary_update_force_update'] = $data['customer_compulsary_update_force_update'] ?? '0';
                $updatedData['provider_compulsary_update_force_update'] = $data['provider_compulsary_update_force_update'] ?? '0';
                $updatedData['provider_location_in_provider_details'] = $data['provider_location_in_provider_details'] ?? '0';
                $updatedData['provider_app_maintenance_mode'] = $data['provider_app_maintenance_mode'] ?? '0';
                $updatedData['customer_app_maintenance_mode'] = $data['customer_app_maintenance_mode'] ?? '0';
                $updatedData['android_google_ads_status'] = $data['android_google_ads_status'] ?? '0';
                $updatedData['ios_google_ads_status'] = $data['ios_google_ads_status'] ?? '0';
                $updatedData['decimal_point'] = $data['decimal_point'] ?? '0';




                $updatedData['currency'] = (!empty($this->request->getPost('currency'))) ? $this->request->getPost('currency') : (isset($data['currency']) ? $data['currency'] : "");
                $updatedData['country_currency_code'] = (!empty($this->request->getPost('country_currency_code'))) ? $this->request->getPost('country_currency_code') : (isset($data['country_currency_code']) ? $data['country_currency_code'] : "");


                if ($this->request->getPost('image_compression_preference') == 0) {
                    $updatedData['image_compression_preference'] = "0";
                    $updatedData['image_compression_quality'] = "0";
                } elseif (!empty($this->request->getPost('image_compression_preference'))) {
                    $updatedData['image_compression_preference'] = $this->request->getPost('image_compression_preference');
                } else {
                    $updatedData['image_compression_preference'] = $data['image_compression_preference'];
                }
                if (!empty($updatedData['system_timezone_gmt'])) {
                    if ($updatedData['system_timezone_gmt'] == " 00:00") {
                        $updatedData['system_timezone_gmt'] = '+' . trim($updatedData['system_timezone_gmt']);
                    }
                }

                if (isset($updatedData['aws_url'])) {
                    $updatedData['aws_url'] = rtrim($updatedData['aws_url'], '/');
                }


                if (($this->request->getPost('company_map_location'))) {
                    $iframe = $this->request->getPost('company_map_location');

                    preg_match('/src=["\']([^"\']+)["\']/', $iframe, $matches);

                    if (!empty($matches[1])) {
                        $updatedData['company_map_location'] = $matches[1];
                    } else {
                        $updatedData['company_map_location'] = $iframe;
                    }
                }

                // Save file_transfer_process to database
                $file_transfer_process = $this->request->getPost('file_transfer_process') ?? 0;
                $updatedData['file_transfer_process'] = $file_transfer_process;

                $json_string = json_encode($updatedData);

                $file_manager = $_POST['file_manager'];

                if ($file_transfer_process == 1) {
                    $queue = service('queue');
                    $jobId = $queue->push('filemanagerchanges', 'fileManagerChangesJob', ['file_manager' => $file_manager]);
                }
                update_details(['value' => $file_manager], ['variable' => 'storage_disk'], 'settings');

                // Merge so finalData has all keys; maintenance dates are already in UTC in updatedData (converted above).
                // Do not convert finalData again or UTC would be misinterpreted as local and double-converted.
                $finalData = array_merge($data, $updatedData);
                $json_string = json_encode($finalData);

                if ($this->update_setting('general_settings', $json_string)) {
                    $_SESSION['toastMessage'] = labels('Settings has been successfuly updated', 'Settings has been successfuly updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the settings', 'Unable to update the settings');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/general-settings')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'general_settings');
            $query = $this->builder->get()->getResultArray();

            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                $imageSettings = ['half_logo', 'partner_favicon', 'partner_half_logo', 'partner_logo', 'login_image', 'favicon', 'logo'];

                $disk = fetch_current_file_manager();

                foreach ($imageSettings as $key) {
                    if (isset($settings[$key])) {
                        if (isset($disk)) {
                            switch ($disk) {
                                case 'local_server':
                                    if ($key == 'login_image') {

                                        $settings[$key] = !empty($settings[$key]) ? base_url('public/frontend/retro/') . $settings[$key] : base_url('public/frontend/retro/Login_BG.jpg');
                                    } else {

                                        $settings[$key] = !empty($settings[$key]) ? base_url('public/uploads/site/') . $settings[$key] : base_url('public/backend/assets/default.jpg');
                                    }
                                    break;
                                case 'aws_s3':
                                    $settings[$key] = !empty($settings[$key]) ? fetch_cloud_front_url('site', $settings[$key]) : base_url('public/backend/assets/default.jpg');
                                    break;
                                default:
                                    $settings[$key] = base_url('public/backend/assets/default.jpg');
                            }
                        } else {
                            $settings[$key] = base_url('public/backend/assets/default.jpg');
                        }
                    }
                }
                if (!empty($settings)) {
                    $this->data = array_merge($this->data, $settings);
                }

                // Handle multi-language company fields with backward compatibility
                $multi_lang_company_fields = ['company_title', 'copyright_details', 'address', 'short_description'];

                foreach ($multi_lang_company_fields as $field) {
                    if (isset($this->data[$field]) && is_array($this->data[$field])) {
                        // Keep the multi-language array structure
                        continue;
                    } else if (isset($settings[$field]) && is_string($settings[$field])) {
                        // Convert old string data to new structure
                        $default_lang = 'en'; // fallback
                        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                        if (!empty($languages)) {
                            $default_lang = $languages[0]['code'];
                        }
                        $this->data[$field] = [$default_lang => $settings[$field]];
                    }
                }

                // die;

                // public/frontend/retro
            }
            $settings['distance_unit'] = isset($settings['distance_unit']) ? $settings['distance_unit'] : 'km';
            if ($settings['distance_unit'] == "miles") {
                $this->data['max_serviceable_distance'] = round($settings['max_serviceable_distance'] * 0.621371);
            };

            $this->data['timezones'] = get_timezone_array();

            // fetch languages for multilingual support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            // Ensure multi-language company fields always show a value for the selected default language.
            if (!empty($languages)) {
                $multiLangCompanyFields = ['company_title', 'copyright_details', 'address', 'short_description'];
                $this->data = $this->applyFallbacksToFields($this->data, $multiLangCompanyFields, $languages);
            }

            setPageInfo($this->data, labels('General Settings', 'General Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'general_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - general_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function startQueueWorker()
    {
        $output = null;
        $retval = null;

        // Run the queue worker command and capture the output
        exec('/opt/lampp/bin/php /opt/lampp/htdocs/edemand/index.php queue:work 2>&1', $output, $retval);

        // Log output and return code
        log_message('error', 'Queue Worker Output: ' . implode("\n", $output));
        log_message('error', 'Queue Worker Return Code: ' . $retval);
    }

    public function email_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getGet('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $this->validation->setRules(
                    [
                        'smtpHost' => ["rules" => 'required', "errors" => ["required" => "Please enter SMTP Host"]],
                        'smtpUsername' => ["rules" => 'required', "errors" => ["required" => "Please enter SMTP Username"]],
                        'smtpPassword' => ["rules" => 'required', "errors" => ["required" => "Please enter SMTP Password"]],
                        'smtpPort' => ["rules" => 'required|numeric', "errors" => ["required" => "Please enter SMTP Port Number",    "numeric" => "Please enter numeric value for SMTP Port Number"]],
                    ],
                );
                if (!$this->validation->withRequest($this->request)->run()) {
                    $errors  = $this->validation->getErrors();
                    $response['error'] = true;
                    $response['message'] = labels($errors, $errors);
                    $response['csrfName'] = csrf_token();
                    $response['csrfHash'] = csrf_hash();
                    $response['data'] = [];
                    return $this->response->setJSON($response);
                }
                $updatedData = $this->request->getGet();
                $json_string = json_encode($updatedData);
                if ($this->update_setting('email_settings', $json_string)) {
                    $_SESSION['toastMessage'] = labels('Email settings has been successfuly updated', 'Email settings has been successfuly updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the email settings', 'Unable to update the email settings');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/email-settings')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'email_settings');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);
                $this->data = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('Email Settings', 'Email Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'email_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - email_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function pg_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();
                $updatedData['cod_setting'] = isset($updatedData['cod_setting']) ? 1 : 0;
                $updatedData['payment_gateway_setting'] = isset($updatedData['payment_gateway_setting']) ? 1 : 0;
                // Save provider online payment setting - allows providers to accept online payments
                $updatedData['provider_online_payment_setting'] = isset($updatedData['provider_online_payment_setting']) ? 1 : 0;
                $paypal_status = isset($updatedData['paypal_status']) ? 1 : 0;
                $razorpayApiStatus = isset($updatedData['razorpayApiStatus']) ? 1 : 0;
                $paystack_status = isset($updatedData['paystack_status']) ? 1 : 0;
                $stripe_status = isset($updatedData['stripe_status']) ? 1 : 0;
                $flutterwave_status = isset($updatedData['flutterwave_status']) ? 1 : 0;
                $xendit_status = isset($updatedData['xendit_status']) ? 1 : 0;
                $cashfree_status = isset($updatedData['cashfree_status']) ? 1 : 0;
                if ($updatedData['payment_gateway_setting'] == 1 && $paypal_status == 0 && $razorpayApiStatus == 0 && $paystack_status == 0 && $stripe_status == 0 && $flutterwave_status == 0 && $xendit_status == 0 && $cashfree_status == 0) {
                    $_SESSION['toastMessage'] = labels('At least one payment method must be enabled', 'At least one payment method must be enabled');
                    $_SESSION['toastMessageType']  = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/pg-settings')->withCookies();
                }
                unset($updatedData['update']);
                unset($updatedData[csrf_token()]);
                if (isset($updatedData['paypal_website_url'])) {
                    $updatedData['paypal_website_url'] = rtrim($updatedData['paypal_website_url'], '/');
                }
                if (isset($updatedData['flutterwave_website_url'])) {
                    $updatedData['flutterwave_website_url'] = rtrim($updatedData['flutterwave_website_url'], '/');
                }
                if (isset($updatedData['cashfree_website_url'])) {
                    $updatedData['cashfree_website_url'] = rtrim($updatedData['cashfree_website_url'], '/');
                }
                if (isset($updatedData['flutterwave_webhook_secret_key'])) {
                    updateEnv('FLUTTERWAVE_SECRET_KEY', $updatedData['flutterwave_webhook_secret_key']);
                }
                $json_string = json_encode($updatedData);
                if ($this->update_setting('payment_gateways_settings', $json_string)) {
                    $_SESSION['toastMessage'] = labels('Payment gateway settings has been successfully updated', 'Payment gateway settings has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the payment gateways settings', 'Unable to update the payment gateways settings');
                    $_SESSION['toastMessageType']  = 'error';

                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/pg-settings')->withCookies();
            } else {
                $this->builder->select('value');
                $this->builder->where('variable', 'payment_gateways_settings');
                $query = $this->builder->get()->getResultArray();
                if (count($query) == 1) {
                    $settings = $query[0]['value'];
                    $settings = json_decode($settings, true);
                    $this->data = array_merge($this->data, $settings);
                }
                setPageInfo($this->data, labels('Payment Gateways Settings', 'Payment Gateways Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'payment_gateways');
                return view('backend/admin/template', $this->data);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - pg_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function privacy_policy()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }

                // Extract privacy_policy translations from POST data
                $privacy_policy_translations = $_POST['privacy_policy'] ?? [];

                // Filter out effectively empty HTML content for optional language fields
                // If content is effectively empty (only spaces, &nbsp;, empty tags), set to null
                foreach ($privacy_policy_translations as $langCode => $content) {
                    if (html_is_effectively_empty($content)) {
                        // Set to null instead of saving empty HTML
                        $privacy_policy_translations[$langCode] = null;
                    }
                }

                // Create the new JSON structure with translations keyed by language code
                $updatedData = [
                    'privacy_policy' => $privacy_policy_translations
                ];

                $json_string = json_encode($updatedData);
                if ($this->update_setting('privacy_policy', $json_string)) {
                    // Send notifications when privacy policy is updated
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // This unified approach sends notifications to all users except admin (providers and customers)
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[company_name]], [[site_url]], etc.
                        $notificationContext = [];

                        // Queue all notifications (FCM, Email, SMS) to all users except admin
                        // user_groups [2, 3] = customers and providers (excluding admin group 1)
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'privacy_policy_changed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'user_groups' => [2, 3], // Send to customers (2) and providers (3), excluding admin (1)
                                'platforms' => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'] // All platforms
                            ]
                        );

                        // log_message('info', '[PRIVACY_POLICY_CHANGED] Notification result (provider): ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the privacy policy update
                        log_message('error', '[PRIVACY_POLICY_CHANGED] Notification error trace (provider): ' . $notificationError->getTraceAsString());
                    }

                    $_SESSION['toastMessage'] = labels('privacy Policy has been successfuly updated', 'privacy Policy has been successfuly updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the privacy policy', 'Unable to update the privacy policy');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/privacy-policy')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'privacy_policy');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle both old and new JSON structures for backward compatibility
                if (isset($settings['privacy_policy']) && is_array($settings['privacy_policy'])) {
                    // New structure: {"privacy_policy": {"en": "content", "fr": "content"}}
                    $this->data['privacy_policy'] = $settings['privacy_policy'];
                } else if (isset($settings['privacy_policy']) && is_string($settings['privacy_policy'])) {
                    // Old structure: {"privacy_policy": "content"} - convert to new structure
                    // Get default language for backward compatibility
                    $default_lang = 'en'; // fallback
                    $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                    if (!empty($languages)) {
                        $default_lang = $languages[0]['code'];
                    }
                    $this->data['privacy_policy'] = [$default_lang => $settings['privacy_policy']];
                } else {
                    // Initialize empty array if no privacy_policy data exists
                    $this->data['privacy_policy'] = [];
                }
            } else {
                // Initialize empty array if no settings found
                $this->data['privacy_policy'] = [];
            }

            // Fetch languages for multi-language support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            if (!empty($languages) && isset($this->data['terms_conditions']) && is_array($this->data['terms_conditions'])) {
                $this->data['terms_conditions'] = $this->ensureMultiLangFallbacks($this->data['terms_conditions'], $languages);
            }

            if (!empty($languages) && isset($this->data['privacy_policy']) && is_array($this->data['privacy_policy'])) {
                $this->data['privacy_policy'] = $this->ensureMultiLangFallbacks($this->data['privacy_policy'], $languages);
            }

            setPageInfo($this->data, labels('Privacy Policy Settings', 'Privacy Policy Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'privacy_policy');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - privacy_policy()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function customer_privacy_policy_page()
    {
        $settings = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title'] = labels('Privacy Policy | ' . $settings['company_title'], 'Privacy Policy | ' . $settings['company_title']);
        $this->data['meta_description'] = 'Privacy Policy | ' . $settings['company_title'];
        $this->data['privacy_policy'] = $this->getTranslatedSetting('customer_privacy_policy', 'customer_privacy_policy');
        $this->data['settings'] =  $settings;
        return view('backend/admin/pages/customer_app_privacy_policy', $this->data);
    }

    public function customer_tearms_and_condition()
    {
        $settings = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title'] = labels('Customer Terms & Condition  | ' . $settings['company_title'], 'Customer Terms & Condition  | ' . $settings['company_title']);
        $this->data['meta_description'] = 'Customer Terms & Condition  | ' . $settings['company_title'];
        $this->data['customer_terms_conditions'] = $this->getTranslatedSetting('customer_terms_conditions', 'customer_terms_conditions');
        $this->data['settings'] =  $settings;
        return view('backend/admin/pages/customer_terms_and_condition_page', $this->data);
    }

    public function provider_terms_and_condition()
    {
        $settings = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $companyTitle = $settings['company_title'];

        $this->data = [
            'title'             => labels("Provider Privacy Policy  | $companyTitle", "Provider Privacy Policy  | $companyTitle"),
            'meta_description'  => "Provider Privacy Policy  | $companyTitle",
            'terms_conditions'  => $this->getTranslatedSetting('terms_conditions', 'terms_conditions'),
            'settings'          => $settings
        ];

        return view('backend/admin/pages/provider_terms_and_condition_page', $this->data);
    }

    public function partner_privacy_policy_page()
    {
        $settings = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title'] = labels('Privacy Policy | ' . $settings['company_title'], 'Privacy Policy | ' . $settings['company_title']);
        $this->data['meta_description'] = 'Privacy Policy | ' . $settings['company_title'];
        $this->data['privacy_policy'] = $this->getTranslatedSetting('privacy_policy', 'privacy_policy');
        $this->data['settings'] =  $settings;
        return view('backend/admin/pages/partner_app_privacy_policy', $this->data);
    }

    public function customer_privacy_policy()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }

                // Extract customer_privacy_policy translations from POST data
                $customer_privacy_translations = $_POST['customer_privacy_policy'] ?? [];

                // Filter out effectively empty HTML content for optional language fields
                // If content is effectively empty (only spaces, &nbsp;, empty tags), set to null
                foreach ($customer_privacy_translations as $langCode => $content) {
                    if (html_is_effectively_empty($content)) {
                        // Set to null instead of saving empty HTML
                        $customer_privacy_translations[$langCode] = null;
                    }
                }

                // Create the new JSON structure with translations keyed by language code
                $updatedData = [
                    'customer_privacy_policy' => $customer_privacy_translations
                ];

                $json_string = json_encode($updatedData);
                if ($this->update_setting('customer_privacy_policy', $json_string)) {
                    // Send notifications when privacy policy is updated
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // This unified approach sends notifications to all users except admin (providers and customers)
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[company_name]], [[site_url]], etc.
                        $notificationContext = [];

                        // Queue all notifications (FCM, Email, SMS) to all users except admin
                        // user_groups [2, 3] = customers and providers (excluding admin group 1)
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'privacy_policy_changed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'user_groups' => [2, 3], // Send to customers (2) and providers (3), excluding admin (1)
                                'platforms' => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'] // All platforms
                            ]
                        );

                        // log_message('info', '[PRIVACY_POLICY_CHANGED] Notification result (customer): ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the privacy policy update
                        log_message('error', '[PRIVACY_POLICY_CHANGED] Notification error trace (customer): ' . $notificationError->getTraceAsString());
                    }

                    $_SESSION['toastMessage'] = labels('privacy Policy has been successfully updated', 'privacy Policy has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the privacy policy', 'Unable to update the privacy policy');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/customer-privacy-policy')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'customer_privacy_policy');
            $query = $this->builder->get()->getResultArray();

            // Fetch languages first (needed for fallback logic)
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');

            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle both old and new JSON structures for backward compatibility
                if (isset($settings['customer_privacy_policy']) && is_array($settings['customer_privacy_policy'])) {
                    // New structure: {"customer_privacy_policy": {"en": "content", "fr": "content"}}
                    // Apply fallback logic to ensure default language and all languages have values
                    $this->data['customer_privacy_policy'] = $this->ensureMultiLangFallbacks($settings['customer_privacy_policy'], $languages);
                } else if (isset($settings['customer_privacy_policy']) && is_string($settings['customer_privacy_policy'])) {
                    // Old structure: {"customer_privacy_policy": "content"} - convert to new structure
                    // Get default language for backward compatibility
                    $default_lang = 'en'; // fallback
                    $default_languages = fetch_details('languages', ['is_default' => 1], ['code']);
                    if (!empty($default_languages)) {
                        $default_lang = $default_languages[0]['code'];
                    }
                    $translations = [$default_lang => $settings['customer_privacy_policy']];
                    // Apply fallback logic to ensure all languages have values
                    $this->data['customer_privacy_policy'] = $this->ensureMultiLangFallbacks($translations, $languages);
                } else {
                    // Initialize empty array if no customer_privacy_policy data exists
                    $this->data['customer_privacy_policy'] = [];
                }
            } else {
                // Initialize empty array if no settings found
                $this->data['customer_privacy_policy'] = [];
            }

            // Store languages for view
            $this->data['languages'] = $languages;

            $this->data['title'] = 'Privacy Policy Settings | Admin Panel';
            $this->data['main_page'] = 'customer_privacy_policy';
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - customer_privacy_policy()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function refund_policy_page()
    {
        $settings = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title'] = 'Refund Policy | ' . $settings['company_title'];
        $this->data['meta_description'] = 'Refund Policy | ' . $settings['company_title'];
        $this->data['refund_policy'] = get_settings('refund_policy', true);
        $this->data['settings'] =  $settings;
        return view('backend/admin/pages/refund_policy_page', $this->data);
    }

    public function refund_policy()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();
                unset($updatedData['update']);
                unset($updatedData['files']);
                unset($updatedData[csrf_token()]);
                $json_string = json_encode($updatedData);
                if ($this->update_setting('refund_policy', $json_string)) {
                    $_SESSION['toastMessage'] = labels('refund policy has been successfully updated', 'refund policy has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the refund policy', 'Unable to update the refund policy');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/refund-policy')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'refund_policy');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);
                $this->data = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('Refund Policy Settings', 'Refund Policy Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'refund_policy');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - refund_policy()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function updater()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('Updater', 'Updater') . ' | ' . labels('admin_panel', 'Admin Panel'), 'updater');
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }

    public function terms_and_conditions()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }

                // Extract terms_conditions translations from POST data
                $terms_conditions_translations = $_POST['terms_conditions'] ?? [];

                // Filter out effectively empty HTML content for optional language fields
                // If content is effectively empty (only spaces, &nbsp;, empty tags), set to null
                foreach ($terms_conditions_translations as $langCode => $content) {
                    if (html_is_effectively_empty($content)) {
                        // Set to null instead of saving empty HTML
                        $terms_conditions_translations[$langCode] = null;
                    }
                }

                // Create the new JSON structure with translations keyed by language code
                $updatedData = [
                    'terms_conditions' => $terms_conditions_translations
                ];

                $json_string = json_encode($updatedData);
                if ($this->update_setting('terms_conditions', $json_string)) {
                    // Send notifications when terms and conditions are updated
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // This unified approach sends notifications to all users except admin (providers and customers)
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[company_name]], [[site_url]], etc.
                        $notificationContext = [];

                        // Queue all notifications (FCM, Email, SMS) to all users except admin
                        // user_groups [2, 3] = customers and providers (excluding admin group 1)
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'terms_and_conditions_changed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'user_groups' => [2, 3], // Send to customers (2) and providers (3), excluding admin (1)
                                'platforms' => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'] // All platforms
                            ]
                        );

                        // log_message('info', '[TERMS_AND_CONDITIONS_CHANGED] Notification result (provider): ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the terms and conditions update
                        log_message('error', '[TERMS_AND_CONDITIONS_CHANGED] Notification error trace (provider): ' . $notificationError->getTraceAsString());
                    }

                    $_SESSION['toastMessage'] = labels('Terms & Conditions has been successfully updated', 'Terms & Conditions has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the terms & conditions', 'Unable to update the terms & conditions');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/terms-and-conditions')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'terms_conditions');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle both old and new JSON structures for backward compatibility
                if (isset($settings['terms_conditions']) && is_array($settings['terms_conditions'])) {
                    // New structure: {"terms_conditions": {"en": "content", "fr": "content"}}
                    $this->data['terms_conditions'] = $settings['terms_conditions'];
                } else if (isset($settings['terms_conditions']) && is_string($settings['terms_conditions'])) {
                    // Old structure: {"terms_conditions": "content"} - convert to new structure
                    // Get default language for backward compatibility
                    $default_lang = 'en'; // fallback
                    $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                    if (!empty($languages)) {
                        $default_lang = $languages[0]['code'];
                    }
                    $this->data['terms_conditions'] = [$default_lang => $settings['terms_conditions']];
                } else {
                    // Initialize empty array if no terms_conditions data exists
                    $this->data['terms_conditions'] = [];
                }
            } else {
                // Initialize empty array if no settings found
                $this->data['terms_conditions'] = [];
            }

            // Fetch languages for multi-language support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            setPageInfo($this->data, labels('Terms & Conditions Settings', 'Terms & Conditions Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'terms_and_conditions');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - terms_and_conditions()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function customer_terms_and_conditions()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/customer-terms-and-conditions')->withCookies();
                    }
                }

                // Extract customer_terms_conditions translations from POST data
                $customer_terms_translations = $_POST['customer_terms_conditions'] ?? [];

                // Filter out effectively empty HTML content for optional language fields
                // If content is effectively empty (only spaces, &nbsp;, empty tags), set to null
                foreach ($customer_terms_translations as $langCode => $content) {
                    if (html_is_effectively_empty($content)) {
                        // Set to null instead of saving empty HTML
                        $customer_terms_translations[$langCode] = null;
                    }
                }

                // Create the new JSON structure with translations keyed by language code
                $updatedData = [
                    'customer_terms_conditions' => $customer_terms_translations
                ];

                $json_string = json_encode($updatedData);
                if ($this->update_setting('customer_terms_conditions', $json_string)) {
                    // Send notifications when terms and conditions are updated
                    // NotificationService handles FCM, Email, and SMS notifications using templates
                    // This unified approach sends notifications to all users except admin (providers and customers)
                    try {
                        // Prepare context data for notification templates
                        // This context will be used to populate template variables like [[company_name]], [[site_url]], etc.
                        $notificationContext = [];

                        // Queue all notifications (FCM, Email, SMS) to all users except admin
                        // user_groups [2, 3] = customers and providers (excluding admin group 1)
                        // NotificationService automatically handles:
                        // - Translation of templates based on user language
                        // - Variable replacement in templates
                        // - Notification settings checking for each channel
                        // - Fetching user email/phone/FCM tokens
                        // - Unsubscribe status checking for email
                        queue_notification_service(
                            eventType: 'terms_and_conditions_changed',
                            recipients: [],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                'user_groups' => [2, 3], // Send to customers (2) and providers (3), excluding admin (1)
                                'platforms' => ['android', 'ios', 'admin_panel', 'provider_panel', 'web'] // All platforms
                            ]
                        );

                        // log_message('info', '[TERMS_AND_CONDITIONS_CHANGED] Notification result (customer): ' . json_encode($result));
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the terms and conditions update
                        log_message('error', '[TERMS_AND_CONDITIONS_CHANGED] Notification error trace (customer): ' . $notificationError->getTraceAsString());
                    }

                    $_SESSION['toastMessage'] = labels('Terms & Conditions has been successfully updated', 'Terms & Conditions has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the terms & conditions', 'Unable to update the terms & conditions');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/customer-terms-and-conditions')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'customer_terms_conditions');
            $query = $this->builder->get()->getResultArray();

            // Fetch languages first (needed for fallback logic)
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');

            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle both old and new JSON structures for backward compatibility
                if (isset($settings['customer_terms_conditions']) && is_array($settings['customer_terms_conditions'])) {
                    // New structure: {"customer_terms_conditions": {"en": "content", "fr": "content"}}
                    // Apply fallback logic to ensure default language and all languages have values
                    $this->data['customer_terms_conditions'] = $this->ensureMultiLangFallbacks($settings['customer_terms_conditions'], $languages);
                } else if (isset($settings['customer_terms_conditions']) && is_string($settings['customer_terms_conditions'])) {
                    // Old structure: {"customer_terms_conditions": "content"} - convert to new structure
                    // Get default language for backward compatibility
                    $default_lang = 'en'; // fallback
                    $default_languages = fetch_details('languages', ['is_default' => 1], ['code']);
                    if (!empty($default_languages)) {
                        $default_lang = $default_languages[0]['code'];
                    }
                    $translations = [$default_lang => $settings['customer_terms_conditions']];
                    // Apply fallback logic to ensure all languages have values
                    $this->data['customer_terms_conditions'] = $this->ensureMultiLangFallbacks($translations, $languages);
                } else {
                    // Initialize empty array if no customer_terms_conditions data exists
                    $this->data['customer_terms_conditions'] = [];
                }
            } else {
                // Initialize empty array if no settings found
                $this->data['customer_terms_conditions'] = [];
            }

            // Store languages for view
            $this->data['languages'] = $languages;

            setPageInfo($this->data, labels('Terms & Conditions Settings', 'Terms & Conditions Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'customer_terms_and_conditions');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - customer_terms_and_conditions()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function about_us()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {


                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }


                // Extract about_us translations from POST data
                $about_us_translations = $_POST['about_us'] ?? [];

                // Filter out effectively empty HTML content for optional language fields
                // If content is effectively empty (only spaces, &nbsp;, empty tags), set to null
                foreach ($about_us_translations as $langCode => $content) {
                    if (html_is_effectively_empty($content)) {
                        // Set to null instead of saving empty HTML
                        $about_us_translations[$langCode] = null;
                    }
                }

                // Create the new JSON structure with translations keyed by language code
                $updatedData = [
                    'about_us' => $about_us_translations
                ];

                $json_string = json_encode($updatedData);

                if ($this->update_setting('about_us', $json_string)) {
                    $_SESSION['toastMessage'] = labels('About-us section has been successfully updated', 'About-us section has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update about-us section', 'Unable to update about-us section');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/about-us')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'about_us');
            $query = $this->builder->get()->getResultArray();

            // Fetch languages first (needed for fallback logic)
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');

            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle both old and new JSON structures for backward compatibility
                if (isset($settings['about_us']) && is_array($settings['about_us'])) {
                    // New structure: {"about_us": {"en": "content", "fr": "content"}}
                    // Apply fallback logic to ensure default language and all languages have values
                    $this->data['about_us'] = $this->ensureMultiLangFallbacks($settings['about_us'], $languages);
                } else if (isset($settings['about_us']) && is_string($settings['about_us'])) {
                    // Old structure: {"about_us": "content"} - convert to new structure
                    // Get default language for backward compatibility
                    $default_lang = 'en'; // fallback
                    $default_languages = fetch_details('languages', ['is_default' => 1], ['code']);
                    if (!empty($default_languages)) {
                        $default_lang = $default_languages[0]['code'];
                    }
                    $translations = [$default_lang => $settings['about_us']];
                    // Apply fallback logic to ensure all languages have values
                    $this->data['about_us'] = $this->ensureMultiLangFallbacks($translations, $languages);
                } else {
                    // Initialize empty array if no about_us data exists
                    $this->data['about_us'] = [];
                }
            } else {
                // Initialize empty array if no settings found
                $this->data['about_us'] = [];
            }
            setPageInfo($this->data, labels('About us Settings', 'About us Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'about_us');
            // Store languages for view
            $this->data['languages'] = $languages;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - customer_terms_and_conditions()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function contact_us()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }

                // Extract contact_us translations from POST data
                $contact_us_translations = $_POST['contact_us'] ?? [];

                // Create the new JSON structure with translations keyed by language code
                $updatedData = [
                    'contact_us' => $contact_us_translations
                ];

                $json_string = json_encode($updatedData);

                if ($this->update_setting('contact_us', $json_string)) {
                    $_SESSION['toastMessage'] = labels('Contact-us section has been successfully updated', 'Contact-us section has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update contact-us section', 'Unable to update contact-us section');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/contact-us')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'contact_us');
            $query = $this->builder->get()->getResultArray();

            // Fetch languages first (needed for fallback logic)
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');

            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle both old and new JSON structures for backward compatibility
                if (isset($settings['contact_us']) && is_array($settings['contact_us'])) {
                    // New structure: {"contact_us": {"en": "content", "fr": "content"}}
                    // Apply fallback logic to ensure default language and all languages have values
                    $this->data['contact_us'] = $this->ensureMultiLangFallbacks($settings['contact_us'], $languages);
                } else if (isset($settings['contact_us']) && is_string($settings['contact_us'])) {
                    // Old structure: {"contact_us": "content"} - convert to new structure
                    // Get default language for backward compatibility
                    $default_lang = 'en'; // fallback
                    $default_languages = fetch_details('languages', ['is_default' => 1], ['code']);
                    if (!empty($default_languages)) {
                        $default_lang = $default_languages[0]['code'];
                    }
                    $translations = [$default_lang => $settings['contact_us']];
                    // Apply fallback logic to ensure all languages have values
                    $this->data['contact_us'] = $this->ensureMultiLangFallbacks($translations, $languages);
                } else {
                    // Initialize empty array if no contact_us data exists
                    $this->data['contact_us'] = [];
                }
            } else {
                // Initialize empty array if no settings found
                $this->data['contact_us'] = [];
            }
            setPageInfo($this->data, labels('Contact us Settings', 'Contact us Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'contact_us');
            // Store languages for view
            $this->data['languages'] = $languages;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - contact_us()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function api_key_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                // Only check ALLOW_MODIFICATION for superadmin (demo mode)
                // Other admin users can always edit
                if ($this->superadmin == "superadmin@gmail.com") {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/api_key_settings')->withCookies();
                    }
                }
                // For non-superadmin admins, allow modification without checking ALLOW_MODIFICATION
                $updatedData = $this->request->getPost();
                unset($updatedData['files']);
                unset($updatedData[csrf_token()]);
                unset($updatedData['update']);

                // Handle Microsoft Clarity enabled checkbox
                if (isset($updatedData['microsoft_clarity_enabled'])) {
                    // Normalize any truthy value to string "1" and anything else to "0"
                    $updatedData['microsoft_clarity_enabled'] = ($updatedData['microsoft_clarity_enabled'] == '1') ? '1' : '0';
                } else {
                    // If field is completely missing, treat as disabled
                    $updatedData['microsoft_clarity_enabled'] = '0';
                }

                $json_string = json_encode($updatedData);
                if ($this->update_setting('api_key_settings', $json_string)) {
                    $_SESSION['toastMessage'] = labels('API key section has been successfully updated', 'API key section has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update API key section', 'Unable to update API key section');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/api_key_settings')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'api_key_settings');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);
                $this->data = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('API key Settings', 'API key Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'api_key_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - api_key_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    private function update_setting($variable, $value)
    {
        try {
            $this->builder->where('variable', $variable);
            if (exists(['variable' => $variable], 'settings')) {
                $this->db->transStart();
                $this->builder->update(['value' => $value]);
                $this->db->transComplete();
            } else {
                $this->db->transStart();
                $this->builder->insert(['variable' => $variable, 'value' => $value]);
                $this->db->transComplete();
            }
            return $this->db->transStatus();
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - update_setting()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    // public function themes()
    // {
    //     if (!$this->isLoggedIn || !$this->userIsAdmin) {
    //         return redirect('admin/login');
    //     }
    //     if ($this->request->getPost('update')) {
    //     }
    //     $this->data["themes"] = fetch_details('themes', [], [], null, '0', 'id', "ASC");
    //     setPageInfo($this->data, labels('About us Settings', 'About us Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'themes');
    //     return view('backend/admin/template', $this->data);
    // }

    public function system_tax_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();
                unset($updatedData['files']);
                unset($updatedData[csrf_token()]);
                unset($updatedData['update']);
                $json_string = json_encode($updatedData);
                if ($this->update_setting('system_tax_settings', $json_string)) {
                    $_SESSION['toastMessage'] = labels('System Tax settings successfully updated', 'System Tax settings successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update system tax settings', 'Unable to update system tax settings');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/system_tax_settings')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'system_tax_settings');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);
                $this->data = array_merge($this->data, $settings);
            }

            // Fetch languages for translation support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
            $this->data['languages'] = $languages;

            $this->data['show_on_checkout'] = get_settings('system_tax_settings', true)['show_on_checkout'];

            setPageInfo($this->data, labels('System Tax Settings', 'System Tax Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'system_tax_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - system_tax_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update_tax_settings()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = is_permitted($this->userId, 'update', 'tax');
            if ($permission) {
                if ($this->isLoggedIn && $this->userIsAdmin) {
                    $show_on_checkout = $this->request->getPost('show_on_checkout');
                    if ($this->update_setting('system_tax_settings', json_encode(['show_on_checkout' => $show_on_checkout]))) {
                        return successResponse(labels('tax_settings_updated_successfully', "Tax settings updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    return redirect('unauthorised');
                }
            } else {
                return ErrorResponse(labels('not_permitted', "Sorry! You are not permitted to take this action"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Tax.php - update_tax_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function app_settings()
    {

        try {
            if ($this->request->getPost('update')) {

                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();

                $data = get_settings('general_settings', true);

                // Backward compatibility: Migrate old Google AdMob settings to new customer/provider format during save
                // This ensures old format values are copied to new format if new format doesn't exist in database
                // Android Google AdMob settings migration
                if (isset($data['android_google_interstitial_id']) && !empty($data['android_google_interstitial_id'])) {
                    // If old format exists and new customer format doesn't exist in database, use old value
                    if (empty($data['customer_android_google_interstitial_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('customer_android_google_interstitial_id'))) {
                            $updatedData['customer_android_google_interstitial_id'] = $data['android_google_interstitial_id'];
                        }
                    }
                    // If old format exists and new provider format doesn't exist in database, use old value
                    if (empty($data['provider_android_google_interstitial_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('provider_android_google_interstitial_id'))) {
                            $updatedData['provider_android_google_interstitial_id'] = $data['android_google_interstitial_id'];
                        }
                    }
                }

                if (isset($data['android_google_banner_id']) && !empty($data['android_google_banner_id'])) {
                    // If old format exists and new customer format doesn't exist in database, use old value
                    if (empty($data['customer_android_google_banner_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('customer_android_google_banner_id'))) {
                            $updatedData['customer_android_google_banner_id'] = $data['android_google_banner_id'];
                        }
                    }
                    // If old format exists and new provider format doesn't exist in database, use old value
                    if (empty($data['provider_android_google_banner_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('provider_android_google_banner_id'))) {
                            $updatedData['provider_android_google_banner_id'] = $data['android_google_banner_id'];
                        }
                    }
                }

                // iOS Google AdMob settings migration
                if (isset($data['ios_google_interstitial_id']) && !empty($data['ios_google_interstitial_id'])) {
                    // If old format exists and new customer format doesn't exist in database, use old value
                    if (empty($data['customer_ios_google_interstitial_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('customer_ios_google_interstitial_id'))) {
                            $updatedData['customer_ios_google_interstitial_id'] = $data['ios_google_interstitial_id'];
                        }
                    }
                    // If old format exists and new provider format doesn't exist in database, use old value
                    if (empty($data['provider_ios_google_interstitial_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('provider_ios_google_interstitial_id'))) {
                            $updatedData['provider_ios_google_interstitial_id'] = $data['ios_google_interstitial_id'];
                        }
                    }
                }

                if (isset($data['ios_google_banner_id']) && !empty($data['ios_google_banner_id'])) {
                    // If old format exists and new customer format doesn't exist in database, use old value
                    if (empty($data['customer_ios_google_banner_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('customer_ios_google_banner_id'))) {
                            $updatedData['customer_ios_google_banner_id'] = $data['ios_google_banner_id'];
                        }
                    }
                    // If old format exists and new provider format doesn't exist in database, use old value
                    if (empty($data['provider_ios_google_banner_id'])) {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        if (empty($this->request->getPost('provider_ios_google_banner_id'))) {
                            $updatedData['provider_ios_google_banner_id'] = $data['ios_google_banner_id'];
                        }
                    }
                }

                // Android Google Ads Status migration
                // Check if old format exists (including "0" which is a valid status value)
                if (isset($data['android_google_ads_status']) && $data['android_google_ads_status'] !== '') {
                    // If old format exists and new customer format doesn't exist in database, use old value
                    if (!isset($data['customer_android_google_ads_status']) || $data['customer_android_google_ads_status'] === '') {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        $post_customer_android_status = $this->request->getPost('customer_android_google_ads_status');
                        if ($post_customer_android_status === null || $post_customer_android_status === '') {
                            $updatedData['customer_android_google_ads_status'] = $data['android_google_ads_status'];
                        }
                    }
                    // If old format exists and new provider format doesn't exist in database, use old value
                    if (!isset($data['provider_android_google_ads_status']) || $data['provider_android_google_ads_status'] === '') {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        $post_provider_android_status = $this->request->getPost('provider_android_google_ads_status');
                        if ($post_provider_android_status === null || $post_provider_android_status === '') {
                            $updatedData['provider_android_google_ads_status'] = $data['android_google_ads_status'];
                        }
                    }
                }

                // iOS Google Ads Status migration
                // Check if old format exists (including "0" which is a valid status value)
                if (isset($data['ios_google_ads_status']) && $data['ios_google_ads_status'] !== '') {
                    // If old format exists and new customer format doesn't exist in database, use old value
                    if (!isset($data['customer_ios_google_ads_status']) || $data['customer_ios_google_ads_status'] === '') {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        $post_customer_ios_status = $this->request->getPost('customer_ios_google_ads_status');
                        if ($post_customer_ios_status === null || $post_customer_ios_status === '') {
                            $updatedData['customer_ios_google_ads_status'] = $data['ios_google_ads_status'];
                        }
                    }
                    // If old format exists and new provider format doesn't exist in database, use old value
                    if (!isset($data['provider_ios_google_ads_status']) || $data['provider_ios_google_ads_status'] === '') {
                        // Only migrate if POST field is also empty (user hasn't entered a new value)
                        $post_provider_ios_status = $this->request->getPost('provider_ios_google_ads_status');
                        if ($post_provider_ios_status === null || $post_provider_ios_status === '') {
                            $updatedData['provider_ios_google_ads_status'] = $data['ios_google_ads_status'];
                        }
                    }
                }

                $disk = fetch_current_file_manager();

                $files = [
                    'favicon' => ['file' => $this->request->getFile('favicon'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload favicon', 'Failed to upload favicon'), 'folder' => 'site', 'old_file' => $data['favicon'] ?? null, 'disk' => $disk],
                    'half_logo' => ['file' => $this->request->getFile('half_logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload half_logo', 'Failed to upload half_logo'), 'folder' => 'site', 'old_file' => $data['half_logo'] ?? null, 'disk' => $disk],
                    'logo' => ['file' => $this->request->getFile('logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload logo', 'Failed to upload logo'), 'folder' => 'site', 'old_file' => $data['logo'] ?? null, 'disk' => $disk],
                    'partner_favicon' => ['file' => $this->request->getFile('partner_favicon'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload partner_favicon', 'Failed to upload partner_favicon'), 'folder' => 'site', 'old_file' => $data['partner_favicon'] ?? null, 'disk' => $disk],
                    'partner_half_logo' => ['file' => $this->request->getFile('partner_half_logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload partner_half_logo', 'Failed to upload partner_half_logo'), 'folder' => 'site', 'old_file' => $data['partner_half_logo'] ?? null, 'disk' => $disk],
                    'partner_logo' => ['file' => $this->request->getFile('partner_logo'), 'path' => 'public/uploads/site/', 'error' => labels('Failed to upload partner_logo', 'Failed to upload partner_logo'), 'folder' => 'site', 'old_file' => $data['partner_logo'] ?? null, 'disk' => $disk],
                    'login_image' => ['file' => $this->request->getFile('login_image'), 'path' => 'public/frontend/retro/', 'error' => labels('Failed to upload login_image', 'Failed to upload login_image'), 'folder' => 'site', 'old_file' => $data['login_image'] ?? null, 'disk' => $disk],
                ];
                $uploadedFiles = [];
                foreach ($files as $key => $config) {
                    if (!empty($_FILES[$key]) && isset($_FILES[$key])) {
                        $file = $config['file'];
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
                foreach ($uploadedFiles as $key => $value) {
                    $updatedData[$key] = isset($value['url']) ? $value['url'] : (isset($data[$key]) ? $data[$key] : '');
                }

                // Cleanup
                unset($updatedData['halfLogo']);
                unset($updatedData['partner_halfLogo']);
                unset($updatedData['update']);
                unset($updatedData[csrf_token()]);
                $updatedData['currency'] = (!empty($this->request->getPost('currency'))) ? $this->request->getPost('currency') : (isset($data['currency']) ? $data['currency'] : "");
                $updatedData['country_currency_code'] = (!empty($this->request->getPost('country_currency_code'))) ? $this->request->getPost('country_currency_code') : (isset($data['country_currency_code']) ? $data['country_currency_code'] : "");
                if ($this->request->getPost('decimal_point') == 0) {
                    $updatedData['decimal_point'] = "0";
                } elseif (!empty($this->request->getPost('decimal_point'))) {
                    $updatedData['decimal_point'] = $this->request->getPost('decimal_point');
                } else {
                    $updatedData['decimal_point'] = $data['decimal_point'];
                }
                $updatedData['customer_current_version_android_app'] = (!empty($this->request->getPost('customer_current_version_android_app'))) ? $this->request->getPost('customer_current_version_android_app') : (isset($data['customer_current_version_android_app']) ? $data['customer_current_version_android_app'] : "");
                $updatedData['customer_current_version_ios_app'] = (!empty($this->request->getPost('customer_current_version_ios_app'))) ? $this->request->getPost('customer_current_version_ios_app') : (isset($data['customer_current_version_ios_app']) ? $data['customer_current_version_ios_app'] : "");
                $updatedData['provider_current_version_android_app'] = (!empty($this->request->getPost('provider_current_version_android_app'))) ? $this->request->getPost('provider_current_version_android_app') : (isset($data['provider_current_version_android_app']) ? $data['provider_current_version_android_app'] : "");
                $updatedData['provider_current_version_ios_app'] = (!empty($this->request->getPost('provider_current_version_ios_app'))) ? $this->request->getPost('provider_current_version_ios_app') : (isset($data['provider_current_version_ios_app']) ? $data['provider_current_version_ios_app'] : "");
                $updatedData['customer_app_maintenance_schedule_date'] = (!empty($this->request->getPost('customer_app_maintenance_schedule_date'))) ? $this->request->getPost('customer_app_maintenance_schedule_date') : (isset($data['customer_app_maintenance_schedule_date']) ? $data['customer_app_maintenance_schedule_date'] : "");

                // Handle multi-language message_for_customer_application field
                $message_for_customer_application_translations = $_POST['message_for_customer_application'] ?? [];
                $updatedData['message_for_customer_application'] = $message_for_customer_application_translations;

                $updatedData['provider_app_maintenance_schedule_date'] = (!empty($this->request->getPost('provider_app_maintenance_schedule_date'))) ? ($this->request->getPost('provider_app_maintenance_schedule_date')) : (isset($data['provider_app_maintenance_schedule_date']) ? $data['provider_app_maintenance_schedule_date'] : "");

                // Handle multi-language message_for_provider_application field
                $message_for_provider_application_translations = $_POST['message_for_provider_application'] ?? [];
                $updatedData['message_for_provider_application'] = $message_for_provider_application_translations;

                $keys = ['customer_current_version_android_app', 'customer_current_version_ios_app', 'provider_current_version_android_app', 'provider_current_version_ios_app', 'customer_app_maintenance_schedule_date', 'message_for_customer_application', 'customer_app_maintenance_mode', 'provider_app_maintenance_schedule_date', 'message_for_provider_application', 'provider_app_maintenance_mode', 'company_title', 'support_name', 'support_email', 'phone', 'system_timezone_gmt', 'system_timezone', 'primary_color', 'secondary_color', 'primary_shadow', 'max_serviceable_distance', 'distance_unit', 'address', 'short_description', 'copyright_details', 'booking_auto_cancle_duration', 'customer_playstore_url', 'customer_appstore_url', 'provider_playstore_url', 'provider_appstore_url', 'maxFilesOrImagesInOneMessage', 'maxFileSizeInMBCanBeSent', 'maxCharactersInATextMessage', 'customer_android_google_interstitial_id', 'customer_android_google_banner_id', 'provider_android_google_interstitial_id', 'provider_android_google_banner_id', 'customer_ios_google_interstitial_id', 'customer_ios_google_banner_id', 'provider_ios_google_interstitial_id', 'provider_ios_google_banner_id', 'otp_system', 'authentication_mode', 'company_map_location', 'support_hours', 'allow_pre_booking_chat', 'allow_post_booking_chat', 'file_manager', 'aws_access_key_id', 'aws_secret_access_key', 'aws_secret_access_key', 'aws_default_region', 'aws_bucket', 'aws_url', 'storage_disk',];
                foreach ($keys as $key) {
                    $updatedData[$key] = (!empty($this->request->getPost($key))) ? $this->request->getPost($key) : (isset($data[$key]) ? ($data[$key]) : "");
                }
                // Persistence layer: store app maintenance schedule in UTC (same pattern as customer_web_maintenance_schedule_date in web_setting_update).
                // Use browser timezone when sent (e.g. 12:30 IST → 07:00 UTC). Fallback to PHP default. Store timezone in session for display (UTC→local) when loading the form.
                $maintenanceInputTzApp = $this->request->getPost('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                if ($this->request->getPost('maintenance_schedule_entry_timezone')) {
                    $this->session->set('maintenance_schedule_entry_timezone', $this->request->getPost('maintenance_schedule_entry_timezone'));
                }
                if (array_key_exists('customer_app_maintenance_schedule_date', $_POST) && !empty(trim($updatedData['customer_app_maintenance_schedule_date'] ?? ''))) {
                    $updatedData['customer_app_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['customer_app_maintenance_schedule_date'], $maintenanceInputTzApp);
                }
                if (array_key_exists('provider_app_maintenance_schedule_date', $_POST) && !empty(trim($updatedData['provider_app_maintenance_schedule_date'] ?? ''))) {
                    $updatedData['provider_app_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['provider_app_maintenance_schedule_date'], $maintenanceInputTzApp);
                }

                if ($this->request->getPost('customer_compulsary_update_force_update') == 0) {
                    $updatedData['customer_compulsary_update_force_update'] = "0";
                } elseif (!empty($this->request->getPost('customer_compulsary_update_force_update'))) {
                    $updatedData['customer_compulsary_update_force_update'] = $this->request->getPost('customer_compulsary_update_force_update');
                } else {
                    $updatedData['customer_compulsary_update_force_update'] = $data['customer_compulsary_update_force_update'];
                }
                if ($this->request->getPost('provider_compulsary_update_force_update') == 0) {
                    $updatedData['provider_compulsary_update_force_update'] = "0";
                } elseif (!empty($this->request->getPost('provider_compulsary_update_force_update'))) {
                    $updatedData['provider_compulsary_update_force_update'] = $this->request->getPost('provider_compulsary_update_force_update');
                } else {
                    $updatedData['provider_compulsary_update_force_update'] = $data['provider_compulsary_update_force_update'];
                }
                if ($this->request->getPost('provider_location_in_provider_details') == 0) {
                    $updatedData['provider_location_in_provider_details'] = "0";
                } elseif (!empty($this->request->getPost('provider_location_in_provider_details'))) {
                    $updatedData['provider_location_in_provider_details'] = $this->request->getPost('provider_location_in_provider_details');
                } else {
                    $updatedData['provider_location_in_provider_details'] = $data['provider_location_in_provider_details'];
                }
                if ($this->request->getPost('provider_app_maintenance_mode') == 0) {
                    $updatedData['provider_app_maintenance_mode'] = "0";
                } elseif (!empty($this->request->getPost('provider_app_maintenance_mode'))) {
                    $updatedData['provider_app_maintenance_mode'] = $this->request->getPost('provider_app_maintenance_mode');
                } else {
                    $updatedData['provider_app_maintenance_mode'] = 0;
                }
                if ($this->request->getPost('customer_app_maintenance_mode') == 0) {
                    $updatedData['customer_app_maintenance_mode'] = "0";
                } elseif (!empty($this->request->getPost('customer_app_maintenance_mode'))) {
                    $updatedData['customer_app_maintenance_mode'] = $this->request->getPost('customer_app_maintenance_mode');
                } else {
                    $updatedData['customer_app_maintenance_mode'] = "0";
                }

                // Handle customer Android Google Ads Status
                if ($this->request->getPost('customer_android_google_ads_status') == 0) {
                    $updatedData['customer_android_google_ads_status'] = "0";
                } elseif (!empty($this->request->getPost('customer_android_google_ads_status'))) {
                    $updatedData['customer_android_google_ads_status'] = $this->request->getPost('customer_android_google_ads_status');
                } else {
                    $updatedData['customer_android_google_ads_status'] = $data['customer_android_google_ads_status'] ?? "0";
                }
                // Handle provider Android Google Ads Status
                if ($this->request->getPost('provider_android_google_ads_status') == 0) {
                    $updatedData['provider_android_google_ads_status'] = "0";
                } elseif (!empty($this->request->getPost('provider_android_google_ads_status'))) {
                    $updatedData['provider_android_google_ads_status'] = $this->request->getPost('provider_android_google_ads_status');
                } else {
                    $updatedData['provider_android_google_ads_status'] = $data['provider_android_google_ads_status'] ?? "0";
                }
                // Handle customer iOS Google Ads Status
                if ($this->request->getPost('customer_ios_google_ads_status') == 0) {
                    $updatedData['customer_ios_google_ads_status'] = "0";
                } elseif (!empty($this->request->getPost('customer_ios_google_ads_status'))) {
                    $updatedData['customer_ios_google_ads_status'] = $this->request->getPost('customer_ios_google_ads_status');
                } else {
                    $updatedData['customer_ios_google_ads_status'] = $data['customer_ios_google_ads_status'] ?? "0";
                }
                // Handle provider iOS Google Ads Status
                if ($this->request->getPost('provider_ios_google_ads_status') == 0) {
                    $updatedData['provider_ios_google_ads_status'] = "0";
                } elseif (!empty($this->request->getPost('provider_ios_google_ads_status'))) {
                    $updatedData['provider_ios_google_ads_status'] = $this->request->getPost('provider_ios_google_ads_status');
                } else {
                    $updatedData['provider_ios_google_ads_status'] = $data['provider_ios_google_ads_status'] ?? "0";
                }

                // Validate Google AdMob settings: Interstitial and Banner IDs are required only when ads status is on
                // Collect all validation errors first, then display them all at once
                $admob_validation_errors = [];

                // Customer Android Google AdMob validation
                $customer_android_ads_status = $updatedData['customer_android_google_ads_status'] ?? "0";
                if ($customer_android_ads_status == "1" || $customer_android_ads_status == 1) {
                    $customer_android_interstitial_id = trim($this->request->getPost('customer_android_google_interstitial_id') ?? '');
                    $customer_android_banner_id = trim($this->request->getPost('customer_android_google_banner_id') ?? '');

                    if (empty($customer_android_interstitial_id)) {
                        $admob_validation_errors[] = labels('google_interstitial_id_is_required_for_android_customer_application', 'Google Interstitial ID is required for Android Customer Application');
                    }

                    if (empty($customer_android_banner_id)) {
                        $admob_validation_errors[] = labels('google_banner_id_is_required_for_android_customer_application', 'Google Banner ID is required for Android Customer Application');
                    }
                }

                // Provider Android Google AdMob validation
                $provider_android_ads_status = $updatedData['provider_android_google_ads_status'] ?? "0";
                if ($provider_android_ads_status == "1" || $provider_android_ads_status == 1) {
                    $provider_android_interstitial_id = trim($this->request->getPost('provider_android_google_interstitial_id') ?? '');
                    $provider_android_banner_id = trim($this->request->getPost('provider_android_google_banner_id') ?? '');

                    if (empty($provider_android_interstitial_id)) {
                        $admob_validation_errors[] = labels('google_interstitial_id_is_required_for_android_provider_application', 'Google Interstitial ID is required for Android Provider Application');
                    }

                    if (empty($provider_android_banner_id)) {
                        $admob_validation_errors[] = labels('google_banner_id_is_required_for_android_provider_application', 'Google Banner ID is required for Android Provider Application');
                    }
                }

                // Customer iOS Google AdMob validation
                $customer_ios_ads_status = $updatedData['customer_ios_google_ads_status'] ?? "0";
                if ($customer_ios_ads_status == "1" || $customer_ios_ads_status == 1) {
                    $customer_ios_interstitial_id = trim($this->request->getPost('customer_ios_google_interstitial_id') ?? '');
                    $customer_ios_banner_id = trim($this->request->getPost('customer_ios_google_banner_id') ?? '');

                    if (empty($customer_ios_interstitial_id)) {
                        $admob_validation_errors[] = labels('google_interstitial_id_is_required_for_ios_customer_application', 'Google Interstitial ID is required for iOS Customer Application');
                    }

                    if (empty($customer_ios_banner_id)) {
                        $admob_validation_errors[] = labels('google_banner_id_is_required_for_ios_customer_application', 'Google Banner ID is required for iOS Customer Application');
                    }
                }

                // Provider iOS Google AdMob validation
                $provider_ios_ads_status = $updatedData['provider_ios_google_ads_status'] ?? "0";
                if ($provider_ios_ads_status == "1" || $provider_ios_ads_status == 1) {
                    $provider_ios_interstitial_id = trim($this->request->getPost('provider_ios_google_interstitial_id') ?? '');
                    $provider_ios_banner_id = trim($this->request->getPost('provider_ios_google_banner_id') ?? '');

                    if (empty($provider_ios_interstitial_id)) {
                        $admob_validation_errors[] = labels('google_interstitial_id_is_required_for_ios_provider_application', 'Google Interstitial ID is required for iOS Provider Application');
                    }

                    if (empty($provider_ios_banner_id)) {
                        $admob_validation_errors[] = labels('google_banner_id_is_required_for_ios_provider_application', 'Google Banner ID is required for iOS Provider Application');
                    }
                }

                // Display all validation errors at once if any exist
                if (!empty($admob_validation_errors)) {
                    // Combine all error messages into a single message
                    $combined_error_message = implode('<br>', $admob_validation_errors);
                    $_SESSION['toastMessage'] = $combined_error_message;
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/app')->withCookies();
                }

                if ($this->request->getPost('image_compression_preference') == 0) {
                    $updatedData['image_compression_preference'] = "0";
                    $updatedData['image_compression_quality'] = "0";
                } elseif (!empty($this->request->getPost('image_compression_preference'))) {
                    $updatedData['image_compression_preference'] = $this->request->getPost('image_compression_preference');
                } else {
                    $updatedData['image_compression_preference'] = $data['image_compression_preference'];
                }
                if (!empty($updatedData['system_timezone_gmt'])) {
                    if ($updatedData['system_timezone_gmt'] == " 00:00") {
                        $updatedData['system_timezone_gmt'] = '+' . trim($updatedData['system_timezone_gmt']);
                    }
                }

                // Merge so finalData has all keys; maintenance dates are already in UTC in updatedData (converted above).
                // Do not convert finalData again or UTC would be misinterpreted as local and double-converted.
                $finalData = array_merge($data, $updatedData);

                // Cleanup
                unset($finalData['update']);
                unset($finalData[csrf_token()]);

                // Check if maintenance mode was enabled (changed from 0 to 1)
                // Get old values before saving
                // Normalize values to strings for consistent comparison (handles both string "0"/"1" and integer 0/1)
                $oldProviderMaintenanceMode = isset($data['provider_app_maintenance_mode']) ? (string)$data['provider_app_maintenance_mode'] : '0';
                $oldCustomerMaintenanceMode = isset($data['customer_app_maintenance_mode']) ? (string)$data['customer_app_maintenance_mode'] : '0';
                $newProviderMaintenanceMode = isset($updatedData['provider_app_maintenance_mode']) ? (string)$updatedData['provider_app_maintenance_mode'] : '0';
                $newCustomerMaintenanceMode = isset($updatedData['customer_app_maintenance_mode']) ? (string)$updatedData['customer_app_maintenance_mode'] : '0';

                // Save merged result
                $json_string = json_encode($finalData);
                if ($this->update_setting('general_settings', $json_string)) {
                    // Send notifications when maintenance mode is enabled
                    // Check if provider maintenance mode was just enabled (changed from 0 to 1)
                    // Use strict comparison to ensure we only send when actually enabling (not when already enabled)
                    if ($oldProviderMaintenanceMode !== '1' && $newProviderMaintenanceMode === '1') {
                        // NotificationService handles FCM, Email, and SMS notifications using templates
                        // Send notifications to providers only (group 3)
                        try {
                            // Prepare context data for notification templates
                            $notificationContext = [];

                            // Queue all notifications (FCM, Email, SMS) to providers only
                            queue_notification_service(
                                eventType: 'maintenance_mode',
                                recipients: [],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                    'user_groups' => [3], // Send only to providers (group_id 3)
                                    'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms only
                                ]
                            );

                            // log_message('info', '[MAINTENANCE_MODE] Notification result (provider): ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            // Log error but don't fail the settings update
                            log_message('error', '[MAINTENANCE_MODE] Notification error trace (provider): ' . $notificationError->getTraceAsString());
                        }
                    }

                    // Check if customer maintenance mode was just enabled (changed from 0 to 1)
                    // Use strict comparison to ensure we only send when actually enabling (not when already enabled)
                    if ($oldCustomerMaintenanceMode !== '1' && $newCustomerMaintenanceMode === '1') {
                        // NotificationService handles FCM, Email, and SMS notifications using templates
                        // Send notifications to customers only (group 2)
                        try {
                            // Prepare context data for notification templates
                            $notificationContext = [];

                            // Queue all notifications (FCM, Email, SMS) to customers only
                            queue_notification_service(
                                eventType: 'maintenance_mode',
                                recipients: [],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                    'user_groups' => [2], // Send only to customers (group_id 2)
                                    'platforms' => ['android', 'ios', 'web'] // Customer platforms only
                                ]
                            );

                            // log_message('info', '[MAINTENANCE_MODE] Notification result (customer): ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            // Log error but don't fail the settings update
                            log_message('error', '[MAINTENANCE_MODE] Notification error trace (customer): ' . $notificationError->getTraceAsString());
                        }
                    }

                    $_SESSION['toastMessage'] = labels('App settings has been successfully updated', 'App settings has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update the App settings', 'Unable to update the App settings');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/app')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'general_settings');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);
                if (!empty($settings)) {
                    // Backward compatibility: Migrate old Google AdMob settings to new customer/provider format
                    // Old format: android_google_interstitial_id, android_google_banner_id, ios_google_interstitial_id, ios_google_banner_id
                    // New format: customer_android_google_interstitial_id, provider_android_google_interstitial_id, etc.

                    // Android Google AdMob settings migration
                    if (isset($settings['android_google_interstitial_id']) && !empty($settings['android_google_interstitial_id'])) {
                        // If old format exists and new customer format doesn't exist, copy to customer
                        if (empty($settings['customer_android_google_interstitial_id'])) {
                            $settings['customer_android_google_interstitial_id'] = $settings['android_google_interstitial_id'];
                        }
                        // If old format exists and new provider format doesn't exist, copy to provider
                        if (empty($settings['provider_android_google_interstitial_id'])) {
                            $settings['provider_android_google_interstitial_id'] = $settings['android_google_interstitial_id'];
                        }
                    }

                    if (isset($settings['android_google_banner_id']) && !empty($settings['android_google_banner_id'])) {
                        // If old format exists and new customer format doesn't exist, copy to customer
                        if (empty($settings['customer_android_google_banner_id'])) {
                            $settings['customer_android_google_banner_id'] = $settings['android_google_banner_id'];
                        }
                        // If old format exists and new provider format doesn't exist, copy to provider
                        if (empty($settings['provider_android_google_banner_id'])) {
                            $settings['provider_android_google_banner_id'] = $settings['android_google_banner_id'];
                        }
                    }

                    // iOS Google AdMob settings migration
                    if (isset($settings['ios_google_interstitial_id']) && !empty($settings['ios_google_interstitial_id'])) {
                        // If old format exists and new customer format doesn't exist, copy to customer
                        if (empty($settings['customer_ios_google_interstitial_id'])) {
                            $settings['customer_ios_google_interstitial_id'] = $settings['ios_google_interstitial_id'];
                        }
                        // If old format exists and new provider format doesn't exist, copy to provider
                        if (empty($settings['provider_ios_google_interstitial_id'])) {
                            $settings['provider_ios_google_interstitial_id'] = $settings['ios_google_interstitial_id'];
                        }
                    }

                    if (isset($settings['ios_google_banner_id']) && !empty($settings['ios_google_banner_id'])) {
                        // If old format exists and new customer format doesn't exist, copy to customer
                        if (empty($settings['customer_ios_google_banner_id'])) {
                            $settings['customer_ios_google_banner_id'] = $settings['ios_google_banner_id'];
                        }
                        // If old format exists and new provider format doesn't exist, copy to provider
                        if (empty($settings['provider_ios_google_banner_id'])) {
                            $settings['provider_ios_google_banner_id'] = $settings['ios_google_banner_id'];
                        }
                    }

                    // Android Google Ads Status migration
                    // Check if old format exists (including "0" which is a valid status value)
                    if (isset($settings['android_google_ads_status']) && $settings['android_google_ads_status'] !== '') {
                        // If old format exists and new customer format doesn't exist, copy to customer
                        if (!isset($settings['customer_android_google_ads_status']) || $settings['customer_android_google_ads_status'] === '') {
                            $settings['customer_android_google_ads_status'] = $settings['android_google_ads_status'];
                        }
                        // If old format exists and new provider format doesn't exist, copy to provider
                        if (!isset($settings['provider_android_google_ads_status']) || $settings['provider_android_google_ads_status'] === '') {
                            $settings['provider_android_google_ads_status'] = $settings['android_google_ads_status'];
                        }
                    }

                    // iOS Google Ads Status migration
                    // Check if old format exists (including "0" which is a valid status value)
                    if (isset($settings['ios_google_ads_status']) && $settings['ios_google_ads_status'] !== '') {
                        // If old format exists and new customer format doesn't exist, copy to customer
                        if (!isset($settings['customer_ios_google_ads_status']) || $settings['customer_ios_google_ads_status'] === '') {
                            $settings['customer_ios_google_ads_status'] = $settings['ios_google_ads_status'];
                        }
                        // If old format exists and new provider format doesn't exist, copy to provider
                        if (!isset($settings['provider_ios_google_ads_status']) || $settings['provider_ios_google_ads_status'] === '') {
                            $settings['provider_ios_google_ads_status'] = $settings['ios_google_ads_status'];
                        }
                    }

                    // Handle multi-language fields with backward compatibility
                    $multi_lang_fields = ['message_for_customer_application', 'message_for_provider_application'];

                    foreach ($multi_lang_fields as $field) {
                        if (isset($settings[$field])) {
                            if (is_array($settings[$field])) {
                                // New structure: already an array of translations
                                $this->data[$field] = $settings[$field];
                            } else if (is_string($settings[$field])) {
                                // Old structure: single string - convert to new structure
                                $default_lang = 'en'; // fallback
                                $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                                if (!empty($languages)) {
                                    $default_lang = $languages[0]['code'];
                                }
                                $this->data[$field] = [$default_lang => $settings[$field]];
                            } else {
                                // Initialize empty array if no data exists
                                $this->data[$field] = [];
                            }
                        } else {
                            // Initialize empty array if field doesn't exist
                            $this->data[$field] = [];
                        }
                    }

                    // Merge other settings (but preserve multi-language data)
                    $this->data = array_merge($this->data, $settings);
                    // Display: convert UTC to timezone admin last used (from session) or PHP default, so they see the same time they entered.
                    $maintenanceDisplayTz = $this->session->get('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                    if (!empty($this->data['customer_app_maintenance_schedule_date'])) {
                        $this->data['customer_app_maintenance_schedule_date'] = maintenance_schedule_utc_to_local($this->data['customer_app_maintenance_schedule_date'], $maintenanceDisplayTz);
                    }
                    if (!empty($this->data['provider_app_maintenance_schedule_date'])) {
                        $this->data['provider_app_maintenance_schedule_date'] = maintenance_schedule_utc_to_local($this->data['provider_app_maintenance_schedule_date'], $maintenanceDisplayTz);
                    }
                }
            }

            // Fetch languages and sort them so default language appears first
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
            // Sort languages: default language first, then others
            usort($languages, function ($a, $b) {
                if ($a['is_default'] == $b['is_default']) {
                    return 0;
                }
                return ($a['is_default'] > $b['is_default']) ? -1 : 1;
            });
            $this->data['languages'] = $languages;

            if (!empty($languages)) {
                $multiLangAppFields = [
                    'message_for_customer_application',
                    'message_for_provider_application',
                    'company_title',
                    'copyright_details',
                    'address',
                    'short_description'
                ];
                $this->data = $this->applyFallbacksToFields($this->data, $multiLangAppFields, $languages);
            }

            $this->data['timezones'] = get_timezone_array();
            setPageInfo($this->data, labels('App Settings', 'App Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'app');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - app_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
    }

    public function firebase_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if ($this->request->getPost('update')) {
                if ($this->superadmin == "superadmin@gmail.com") {
                    defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                } else {
                    if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                        $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                        $_SESSION['toastMessageType']  = 'error';
                        $this->session->markAsFlashdata('toastMessage');
                        $this->session->markAsFlashdata('toastMessageType');
                        return redirect()->to('admin/settings/general-settings')->withCookies();
                    }
                }
                $updatedData = $this->request->getPost();
                unset($updatedData[csrf_token()]);
                unset($updatedData['update']);
                $json_file = false;
                $flag = 0;
                if (!empty($_FILES['json_file'])) {
                    if ($_FILES['json_file']['name'] != "") {
                        if (!valid_image('json_file')) {
                            $flag = 1;
                        } else {
                            $json_file = true;
                        }
                    }
                }
                if ($json_file) {
                    $file = $this->request->getFile('json_file');
                    $path = FCPATH . 'public/';
                    $newName = "firebase_config.json";
                    if (file_exists($path . $newName)) {
                        unlink($path . $newName);
                    }
                    $file->move($path, $newName);
                    $updatedData['json_file'] = $newName;
                } else {
                    $updatedData['json_file'] = isset($data['json_file']) ? $data['json_file'] : "";
                }
                $json_string = json_encode($updatedData);
                if ($this->update_setting('firebase_settings', $json_string)) {
                    $_SESSION['toastMessage'] = labels('Firebase has been successfully updated', 'Firebase has been successfully updated');
                    $_SESSION['toastMessageType']  = 'success';
                } else {
                    $_SESSION['toastMessage']  = labels('Unable to update Firebase section', 'Unable to update Firebase section');
                    $_SESSION['toastMessageType']  = 'error';
                }
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/firebase_settings')->withCookies();
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'firebase_settings');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);
                $this->data = array_merge($this->data, $settings);
            }
            setPageInfo($this->data, labels('Firebase Settings', 'Firebase Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'firebase_settings');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - firebase_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function web_setting_page()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            setPageInfo($this->data, labels('Web Settings', 'Web Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'web_settings');
            $this->builder->select('value');
            $this->builder->where('variable', 'web_settings');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle multi-language fields with backward compatibility
                $multi_lang_fields = ['web_title', 'message_for_customer_web', 'cookie_consent_title', 'cookie_consent_description'];

                foreach ($multi_lang_fields as $field) {
                    if (isset($settings[$field])) {
                        if (is_array($settings[$field])) {
                            // New structure: already an array of translations
                            $this->data[$field] = $settings[$field];
                        } else if (is_string($settings[$field])) {
                            // Old structure: single string - convert to new structure
                            $default_lang = 'en'; // fallback
                            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                            if (!empty($languages)) {
                                $default_lang = $languages[0]['code'];
                            }
                            $this->data[$field] = [$default_lang => $settings[$field]];
                        } else {
                            // Initialize empty array if no data exists
                            $this->data[$field] = [];
                        }
                    } else {
                        // Initialize empty array if field doesn't exist
                        $this->data[$field] = [];
                    }
                }

                // Merge other settings (but preserve multi-language data)
                $this->data = array_merge($this->data, $settings);

                // Display: convert UTC to timezone admin last used (from session) or PHP default.
                $maintenanceDisplayTz = $this->session->get('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                if (!empty($this->data['customer_web_maintenance_schedule_date'])) {
                    $this->data['customer_web_maintenance_schedule_date'] = maintenance_schedule_utc_to_local($this->data['customer_web_maintenance_schedule_date'], $maintenanceDisplayTz);
                }

                // Ensure multi-language fields are not overwritten by the merge
                foreach ($multi_lang_fields as $field) {
                    if (isset($this->data[$field]) && is_array($this->data[$field])) {
                        // Keep the multi-language array structure
                        continue;
                    } else if (isset($settings[$field]) && is_string($settings[$field])) {
                        // Convert old string data to new structure
                        $default_lang = 'en'; // fallback
                        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                        if (!empty($languages)) {
                            $default_lang = $languages[0]['code'];
                        }
                        $this->data[$field] = [$default_lang => $settings[$field]];
                    }
                }
            }
            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            if (!empty($languages)) {
                $multiLangWebFields = ['web_title', 'message_for_customer_web', 'cookie_consent_title', 'cookie_consent_description'];
                $this->data = $this->applyFallbacksToFields($this->data, $multiLangWebFields, $languages);
            }

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - web_setting_page()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function reset_theme_colors()
    {
        $response = service('response');

        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $response->setJSON(['error' => true, 'message' => labels('unauthorized_access', 'Unauthorized Access')]);
        }

        if ($this->superadmin !== "superadmin@gmail.com") {
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == "0") {
                return $response->setJSON(['error' => true, 'message' => labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR)]);
            }
        }

        $theme = $this->request->getPost('theme');
        if (!in_array($theme, ['light', 'dark'])) {
            return $response->setJSON(['error' => true, 'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong')]);
        }

        $seeder = new \App\Database\Seeds\WebThemeColorSeeder(new \Config\Database());

        if ($theme === 'light') {
            $seeder->resetLight();
            $defaults = \App\Database\Seeds\WebThemeColorSeeder::LIGHT_DEFAULTS;
        } else {
            $seeder->resetDark();
            $defaults = \App\Database\Seeds\WebThemeColorSeeder::DARK_DEFAULTS;
        }

        return $response->setJSON([
            'error'   => false,
            'message' => labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'),
            'data'    => $defaults,
        ]);
    }

    public function web_setting_update()
    {
        $path = FCPATH . "/public/uploads/web_settings/";
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        try {
            $social_media = [];
            $rules = [];
            $old_settings = get_settings('web_settings', true);

            if (isset($_POST['cookie_consent_status']) && $_POST['cookie_consent_status'] == 1) {
                $cookieConsentTitle = $this->request->getPost('cookie_consent_title');
                $cookieConsentDescription = $this->request->getPost('cookie_consent_description');

                $validationErrors = [];
                if (!$this->hasAnyNonEmptyTranslationValue($cookieConsentTitle)) {
                    $validationErrors[] = labels('cookie_consent_title_is_required', 'Cookie consent title is required');
                }
                if (!$this->hasAnyNonEmptyTranslationValue($cookieConsentDescription)) {
                    $validationErrors[] = labels('cookie_consent_description_is_required', 'Cookie consent description is required');
                }

                if (!empty($validationErrors)) {
                    $this->session->setFlashdata('toastMessage', implode('<br>', $validationErrors));
                    $this->session->setFlashdata('toastMessageType', 'error');
                    return redirect()->back()->withInput();
                }
            }

            $updatedData['social_media'] = ($social_media);

            // Handle multi-language web_title field
            $web_title_translations = $_POST['web_title'] ?? [];
            $updatedData['web_title'] = $web_title_translations;

            $updatedData['playstore_url'] = $_POST['playstore_url'];
            $updatedData['customer_web_maintenance_mode'] = isset($_POST['customer_web_maintenance_mode']) ? 1 : 0;

            // Validate maintenance mode date range if maintenance mode is enabled
            if (isset($_POST['customer_web_maintenance_mode']) && $_POST['customer_web_maintenance_mode'] == 1) {
                $maintenance_schedule_date = $this->request->getPost('customer_web_maintenance_schedule_date');

                // Check if date range is provided and valid
                if (empty($maintenance_schedule_date)) {
                    $this->session->setFlashdata('toastMessage', labels('maintenance_date_range_required', 'Please enter a valid start and end date for maintenance mode'));
                    $this->session->setFlashdata('toastMessageType', 'error');
                    return redirect()->back()->withInput();
                }

                // Parse the date range (format: "YYYY-MM-DD HH:MM to YYYY-MM-DD HH:MM")
                $dateParts = explode(' to ', $maintenance_schedule_date);
                if (count($dateParts) !== 2) {
                    $this->session->setFlashdata('toastMessage', labels('invalid_date_format', 'Please enter a valid date range'));
                    $this->session->setFlashdata('toastMessageType', 'error');
                    return redirect()->back()->withInput();
                }

                $startDate = \DateTime::createFromFormat('Y-m-d H:i', trim($dateParts[0]));
                $endDate = \DateTime::createFromFormat('Y-m-d H:i', trim($dateParts[1]));

                // Validate date parsing
                if (!$startDate || !$endDate) {
                    $this->session->setFlashdata('toastMessage', labels('invalid_date_format', 'Please enter a valid date range'));
                    $this->session->setFlashdata('toastMessageType', 'error');
                    return redirect()->back()->withInput();
                }

                // Check if start date is before end date
                if ($startDate >= $endDate) {
                    $this->session->setFlashdata('toastMessage', labels('start_date_must_be_before_end_date', 'Start date must be before end date'));
                    $this->session->setFlashdata('toastMessageType', 'error');
                    return redirect()->back()->withInput();
                }

                // Check if end date is not greater than or equal to today's date
                $today = new \DateTime();

                if ($endDate < $today) {
                    $this->session->setFlashdata('toastMessage', labels('maintenance_end_date_must_be_today_or_later', 'Please enter a valid start and end date. End date must be today or later'));
                    $this->session->setFlashdata('toastMessageType', 'error');
                    return redirect()->back()->withInput();
                }
            }

            if ($this->isLoggedIn && $this->userIsAdmin) {
                if ($this->request->getPost('update')) {

                    if ($this->superadmin == "superadmin@gmail.com") {
                        defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == "1";
                    } else {
                        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == "0") {
                            $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                            $_SESSION['toastMessageType']  = 'error';
                            $this->session->markAsFlashdata('toastMessage');
                            $this->session->markAsFlashdata('toastMessageType');
                            return redirect()->to('admin/settings/web_setting')->withCookies();
                        }
                    }
                    $old_settings = get_settings('web_settings', true);
                    // Exclude multi-language fields from old_data since they're handled separately above
                    $old_data = ['landing_page_logo', 'landing_page_backgroud_image', 'rating_section_status', 'faq_section_status', 'category_section_status', 'category_ids', 'rating_ids', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image', 'process_flow_status', 'cookie_consent_status', 'app_section_status', 'register_provider_from_web_setting_status', 'partner_login_url', 'partner_register_url', 'light_primary_color', 'light_secondary_color', 'light_bg_color', 'light_text_color', 'light_card_bg_color', 'light_description_text_color', 'dark_primary_color', 'dark_secondary_color', 'dark_bg_color', 'dark_text_color', 'dark_card_bg_color', 'dark_description_text_color'];
                    foreach ($old_data as $key) {
                        $updatedData[$key] = (!empty($this->request->getPost($key))) ? $this->request->getPost($key) : (isset($old_settings[$key]) ? ($old_settings[$key]) : "");
                    }

                    // Validate theme color hex values to prevent storing arbitrary strings
                    $colorKeys = ['light_primary_color', 'light_secondary_color', 'light_bg_color', 'light_text_color', 'light_card_bg_color', 'light_description_text_color', 'dark_primary_color', 'dark_secondary_color', 'dark_bg_color', 'dark_text_color', 'dark_card_bg_color', 'dark_description_text_color'];
                    foreach ($colorKeys as $ck) {
                        if (!empty($updatedData[$ck]) && !preg_match('/^#[0-9a-fA-F]{6,8}$/', $updatedData[$ck])) {
                            $updatedData[$ck] = isset($old_settings[$ck]) ? $old_settings[$ck] : '';
                        }
                    }

                    if ($this->superadmin == "superadmin@gmail.com") {
                        defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                    } else {
                        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                            $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                            $_SESSION['toastMessageType']  = 'error';
                            $this->session->markAsFlashdata('toastMessage');
                            $this->session->markAsFlashdata('toastMessageType');
                            return redirect()->to('admin/settings/general-settings')->withCookies();
                        }
                    }
                    $data = get_settings('web_settings', true);
                    $files_to_check = array('web_logo', 'web_favicon', 'web_half_logo', 'footer_logo');
                    $path = FCPATH . 'public/uploads/web_settings/';
                    foreach ($files_to_check as $row) {
                        $file = $this->request->getFile($row);

                        if ($file && $file->isValid()) {
                            if (!valid_image($row)) {
                            } else {
                                $result = upload_file(
                                    $file,
                                    "public/uploads/web_settings/",
                                    labels('error uploading web settings file', 'error uploading web settings file'),
                                    'web_settings'
                                );

                                if ($result['error'] == false) {
                                    $updatedData[$row] = $result['file_name'];
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            }
                        } else {
                            $updatedData[$row] = isset($data[$row]) ? $data[$row] : "";
                        }
                    }


                    $files_to_check = array('landing_page_logo', 'landing_page_backgroud_image', 'web_logo', 'web_favicon', 'web_half_logo', 'footer_logo', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image');

                    foreach ($files_to_check as $row) {
                        $file = $this->request->getFile($row);

                        if ($file && $file->isValid()) {
                            if (!valid_image($row)) {
                                $flag = 1;
                            } else {
                                $result = upload_file(
                                    $file,
                                    "public/uploads/web_settings/",
                                    labels('error uploading web settings file', 'error uploading web settings file'),
                                    'web_settings'
                                );

                                if ($result['error'] == false) {
                                    $updatedData[$row] = $result['file_name'];
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            }
                        } else {
                            $updatedData[$row] = isset($data[$row]) ? $data[$row] : "";
                        }
                    }
                    $updatedSocialMedia = [];
                    if (!empty($data['social_media'])) {
                        $updatedSocialMedia = [];
                    }
                    $updatedData1 = [];
                    $updatedSocialMedia = [];
                    $request = \Config\Services::request();
                    $updatedSocialMedia = [];
                    foreach ($_POST['social_media'] as $i => $item) {
                        $upload_path = 'public/uploads/web_settings/';

                        if ($item['exist_url'] == 'new') {
                            $file = $request->getFile("social_media.{$i}.file");
                            if ($file && $file->isValid()) {
                                $result = upload_file($file, $upload_path, labels('error uploading web settings file', 'error uploading web settings file'), 'web_settings');
                                if ($result['error'] == false) {
                                    $updatedSocialMedia[] = [
                                        'url' => $item['url'],
                                        'file' => $result['file_name']
                                    ];
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            } else {
                                $disk = fetch_current_file_manager();

                                if (!empty($item['url'])) {
                                    $updatedSocialMedia[] = [
                                        'url' => $item['url'],
                                        'file' => ''
                                    ];
                                }
                            }
                        } else {
                            $updatedData1 = [
                                'url' => ($item['exist_url'] != $item['url']) ? $item['url'] : $item['exist_url']
                            ];
                            $file = $request->getFile("social_media.{$i}.file");
                            if ($file && $file->isValid()) {
                                $result = upload_file($file, $upload_path, labels('error uploading web settings file', 'error uploading web settings file'), 'web_settings');
                                if ($result['error'] == false) {
                                    $updatedData1['file'] = $result['file_name'];
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            } else {
                                $disk = fetch_current_file_manager();

                                $updatedData1['file'] = $item['exist_file'];
                            }
                            $updatedSocialMedia[] = $updatedData1;
                        }
                    }
                    $updatedData['social_media'] = $updatedSocialMedia;
                    if ($this->request->getPost('customer_web_maintenance_mode') == 0) {
                        $updatedData['customer_web_maintenance_mode'] = "0";
                    } elseif (!empty($this->request->getPost('customer_web_maintenance_mode'))) {
                        $updatedData['customer_web_maintenance_mode'] = $this->request->getPost('customer_web_maintenance_mode');
                    } else {
                        $updatedData['customer_web_maintenance_mode'] = "0";
                    }
                    if ($this->request->getPost('app_section_status') == 0) {
                        $updatedData['app_section_status'] = "0";
                    } elseif (!empty($this->request->getPost('app_section_status'))) {
                        $updatedData['app_section_status'] = 1;
                    } else {
                        $updatedData['app_section_status'] = "0";
                    }

                    $updatedData['cookie_consent_status'] = isset($_POST['cookie_consent_status']) && $_POST['cookie_consent_status'] == '1' ? 1 : 0;
                    if ($updatedData['cookie_consent_status'] == 1) {
                        // Handle multi-language cookie_consent_title field
                        $cookie_consent_title_translations = $_POST['cookie_consent_title'] ?? [];
                        $updatedData['cookie_consent_title'] = $cookie_consent_title_translations;

                        // Handle multi-language cookie_consent_description field
                        $cookie_consent_description_translations = $_POST['cookie_consent_description'] ?? [];
                        $updatedData['cookie_consent_description'] = $cookie_consent_description_translations;
                    } else {
                        // Preserve existing values if toggle is off
                        $updatedData['cookie_consent_title'] = isset($data['cookie_consent_title']) ? $data['cookie_consent_title'] : [];
                        $updatedData['cookie_consent_description'] = isset($data['cookie_consent_description']) ? $data['cookie_consent_description'] : [];
                    }

                    // Handle register provider from web setting status toggle
                    if ($this->request->getPost('register_provider_from_web_setting_status') == 0) {
                        $updatedData['register_provider_from_web_setting_status'] = "0";
                    } elseif (!empty($this->request->getPost('register_provider_from_web_setting_status'))) {
                        $updatedData['register_provider_from_web_setting_status'] = 1;
                    } else {
                        $updatedData['register_provider_from_web_setting_status'] = "0";
                    }

                    // Handle multi-language message_for_customer_web field
                    $message_for_customer_web_translations = $_POST['message_for_customer_web'] ?? [];
                    $updatedData['message_for_customer_web'] = $message_for_customer_web_translations;
                    $updatedData['customer_web_maintenance_schedule_date'] = (!empty($this->request->getPost('customer_web_maintenance_schedule_date'))) ? $this->request->getPost('customer_web_maintenance_schedule_date') : (isset($data['customer_web_maintenance_schedule_date']) ? $data['customer_web_maintenance_schedule_date'] : "");

                    // Convert web maintenance schedule using browser timezone when sent, else PHP default.
                    $maintenanceInputTzWeb = $this->request->getPost('maintenance_schedule_entry_timezone') ?: date_default_timezone_get();
                    if ($this->request->getPost('maintenance_schedule_entry_timezone')) {
                        $this->session->set('maintenance_schedule_entry_timezone', $this->request->getPost('maintenance_schedule_entry_timezone'));
                    }
                    if (array_key_exists('customer_web_maintenance_schedule_date', $_POST) && !empty(trim($updatedData['customer_web_maintenance_schedule_date'] ?? ''))) {
                        $updatedData['customer_web_maintenance_schedule_date'] = maintenance_schedule_local_to_utc($updatedData['customer_web_maintenance_schedule_date'], $maintenanceInputTzWeb);
                    }

                    $updatedData['applestore_url'] = $_POST['applestore_url'];

                    // Check if maintenance mode was enabled (changed from 0 to 1)
                    // Get old values before saving - normalize to strings for consistent comparison
                    $oldCustomerWebMaintenanceMode = isset($old_settings['customer_web_maintenance_mode']) ? (string)$old_settings['customer_web_maintenance_mode'] : '0';
                    $newCustomerWebMaintenanceMode = isset($updatedData['customer_web_maintenance_mode']) ? (string)$updatedData['customer_web_maintenance_mode'] : '0';

                    foreach ($updatedData as $key => $val) {
                        $old_settings[$key] = $val;
                    }

                    unset($updatedData[csrf_token()]);
                    unset($updatedData['update']);
                    $json_string = json_encode($old_settings);
                    // echo "<pre>";
                    // print_r($json_string);
                    // die;
                    if ($this->update_setting('web_settings', $json_string)) {
                        // Send notifications when maintenance mode is enabled
                        // Check if web maintenance mode was just enabled (changed from 0 to 1)
                        // Use strict comparison to ensure we only send when actually enabling (not when already enabled)
                        if ($oldCustomerWebMaintenanceMode !== '1' && $newCustomerWebMaintenanceMode === '1') {
                            // NotificationService handles FCM, Email, and SMS notifications using templates
                            // Send notifications to all users (customers and providers) for web maintenance
                            try {
                                // Prepare context data for notification templates
                                $notificationContext = [];

                                // Queue all notifications (FCM, Email, SMS) to all user groups for web maintenance
                                queue_notification_service(
                                    eventType: 'maintenance_mode',
                                    recipients: [],
                                    context: $notificationContext,
                                    options: [
                                        'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                        'user_groups' => [2, 3], // Send to both customers (group_id 2) and providers (group_id 3)
                                        'platforms' => ['web'] // Web platform only
                                    ]
                                );

                                //  log_message('info', '[MAINTENANCE_MODE] Notification result (web): ' . json_encode($result));
                            } catch (\Throwable $notificationError) {
                                // Log error but don't fail the settings update
                                log_message('error', '[MAINTENANCE_MODE] Notification error trace (web): ' . $notificationError->getTraceAsString());
                            }
                        }

                        // Check if customer web maintenance mode was just enabled (changed from 0 to 1)
                        // Use strict comparison to ensure we only send when actually enabling (not when already enabled)
                        if ($oldCustomerWebMaintenanceMode !== '1' && $newCustomerWebMaintenanceMode === '1') {
                            // NotificationService handles FCM, Email, and SMS notifications using templates
                            // Send notifications to customers only (group 2) for customer web maintenance
                            try {
                                // Prepare context data for notification templates
                                $notificationContext = [];

                                // Queue all notifications (FCM, Email, SMS) to customers only
                                queue_notification_service(
                                    eventType: 'maintenance_mode',
                                    recipients: [],
                                    context: $notificationContext,
                                    options: [
                                        'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                        'user_groups' => [2], // Send only to customers (group_id 2)
                                        'platforms' => ['web'] // Web platform only
                                    ]
                                );

                                // log_message('info', '[MAINTENANCE_MODE] Notification result (customer web): ' . json_encode($result));
                            } catch (\Throwable $notificationError) {
                                // Log error but don't fail the settings update
                                log_message('error', '[MAINTENANCE_MODE] Notification error trace (customer web): ' . $notificationError->getTraceAsString());
                            }
                        }

                        $_SESSION['toastMessage'] = labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully');
                        $_SESSION['toastMessageType']  = 'success';
                    } else {
                        $_SESSION['toastMessage']  = labels(ERROR_OCCURED, 'An error occurred');
                        $_SESSION['toastMessageType']  = 'error';
                    }
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web_setting')->withCookies();
                }
                $this->builder->select('value');
                $this->builder->where('variable', 'web_settings');
                $query = $this->builder->get()->getResultArray();
                if (count($query) == 1) {
                    $settings = $query[0]['value'];
                    $settings = json_decode($settings, true);
                    $this->data = array_merge($this->data, $settings);
                }

                setPageInfo($this->data, labels('Web Settings', 'Web Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'web_settings');
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - web_setting_page()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function contry_codes()
    {
        try {
            // Load available countries for import dropdown
            $availableCountries = $this->getAvailableCountriesForImport();
            $this->data['available_countries'] = $availableCountries;

            setPageInfo($this->data, labels('Country Code Settings', 'Country Code Settings') . '  | ' . labels('admin_panel', 'Admin Panel'), 'country_code');

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - contry_codes()');
            $this->data['available_countries'] = [];
            setPageInfo($this->data, labels('Country Code Settings', 'Country Code Settings') . '  | ' . labels('admin_panel', 'Admin Panel'), 'country_code');
            return view('backend/admin/template', $this->data);
        }
    }

    /**
     * Import selected country codes from JSON file to database
     * This method handles bulk import of selected countries
     */
    public function import_country_codes()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }

            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            // Get country codes data directly from POST
            $countriesData = $this->request->getPost('countries_data');

            if (empty($countriesData)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('select_at_least_one_country_to_import', 'Please select at least one country to import'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                ]);
            }

            // Get existing country codes and calling codes to avoid duplicates
            $existingCountries = fetch_details('country_codes', [], ['country_code', 'calling_code']);
            $existingCountryCodes = array_column($existingCountries, 'country_code');
            $existingCallingCodes = array_column($existingCountries, 'calling_code');

            $validation = \Config\Services::validation();
            $countryCodeModel = new Country_code_model();
            $importedCount = 0;
            $skippedCount = 0;
            $errors = [];

            // Define validation rules
            $validationRules = [
                'country_name' => 'required|max_length[100]|trim',
                'country_code' => 'required|exact_length[2]|alpha|trim',
                'calling_code' => 'required|regex_match[/^\+\d{1,7}$/]|trim',
                'flag_image' => 'required|trim'
            ];

            $validationMessages = [
                'country_name' => [
                    'required' => 'Country name is required',
                    'max_length' => 'Country name must be less than 100 characters'
                ],
                'country_code' => [
                    'required' => 'Country code is required',
                    'exact_length' => 'Country code must be exactly 2 characters',
                    'alpha' => 'Country code must contain only letters'
                ],
                'calling_code' => [
                    'required' => 'Calling code is required',
                    'regex_match' => 'Calling code must start with + followed by 1-7 digits'
                ],
                'flag_image' => [
                    'required' => 'Flag image is required'
                ]
            ];

            $this->db->transStart();

            foreach ($countriesData as $index => $countryData) {
                // Set validation rules and messages
                $validation->setRules($validationRules, $validationMessages);

                // Validate current country data
                if (!$validation->run($countryData)) {
                    $validationErrors = $validation->getErrors();
                    $errors[] = "Row " . ($index + 1) . ": " . implode(', ', $validationErrors);
                    continue;
                }

                // Get validated data
                $countryName = trim($countryData['country_name']);
                $countryCode = strtoupper(trim($countryData['country_code']));
                $callingCode = trim($countryData['calling_code']);
                $flagImage = trim($countryData['flag_image']);

                // Additional validation for flag image extension
                $flagFileName = basename(parse_url($flagImage, PHP_URL_PATH));
                if (!preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $flagFileName)) {
                    $errors[] = "Row " . ($index + 1) . ": " . labels('invalid_flag_image_format', 'Invalid flag image format');
                    continue;
                }

                // Check for duplicates
                if (in_array($countryCode, $existingCountryCodes) || in_array($callingCode, $existingCallingCodes)) {
                    $skippedCount++;
                    continue;
                }

                // Insert country code
                $insertData = [
                    'country_name' => $countryName,
                    'country_code' => $countryCode,
                    'calling_code' => $callingCode,
                    'flag_image' => $flagFileName,
                    'is_default' => 0
                ];

                if ($countryCodeModel->save($insertData)) {
                    $importedCount++;
                    // Prevent duplicates in same batch
                    $existingCountryCodes[] = $countryCode;
                    $existingCallingCodes[] = $callingCode;
                } else {
                    $errors[] = labels('failed_to_save', 'Failed to save') . ": " . $countryName;
                }
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception(labels('database_transaction_failed', 'Database transaction failed'));
            }

            if (!empty($errors)) {
                $message = implode(', ', $errors);
            } else {
                $message = labels('country_codes_imported_successfully', 'Country code(s) imported successfully');
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => $message,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        } catch (\Throwable $th) {
            $this->db->transRollback();
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - import_country_codes()');

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, "Something Went Wrong"),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
    }

    public function add_contry_code()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $this->validation->setRules(
                [
                    'name' => ["rules" => 'required', "errors" => ["required" => "Please enter name"]],
                    'code' => ["rules" => 'required', "errors" => ["required" => "Please enter code"]],
                ],
            );
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors  = $this->validation->getErrors();
                $response['error'] = true;
                $response['message'] = labels($errors, $errors);
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['data'] = [];
                return $this->response->setJSON($response);
            }
            $data['code'] = ($_POST['code']);
            $data['name'] = ($_POST['name']);
            $contry_code = new Country_code_model();
            if ($contry_code->save($data)) {
                $response = [
                    'error' => false,
                    'message' => labels('Country code added successfully', 'Country code added successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return json_encode($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels('Please try again....', 'Please try again....'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return json_encode($response);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - add_contry_code()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function fetch_contry_code()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where = [];
        $from_app = false;
        $contry_code = new Country_code_model();
        $data = $contry_code->list($from_app, $search, $limit, $offset, $sort, $order, $where);
        return $data;
    }
    public function delete_contry_code()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                $response['error'] = true;
                $response['message'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                return $this->response->setJSON($response);
            }
            $db = \Config\Database::connect();
            $id = $this->request->getVar('id');
            $builder = $db->table('country_codes');
            $builder->where('id', $id);
            $data = fetch_details("country_codes", ['id' => $id]);
            $settings = fetch_details('country_codes', ['is_default' => 1]);
            if ($settings[0]['id'] ==  $id) {
                $response = [
                    'error' => true,
                    'message' => labels('default_country_code_cannot_be_removed', 'Default country code cannot be removed'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            if ($builder->delete()) {
                $response = [
                    'error' => false,
                    'message' => labels(DATA_DELETED_SUCCESSFULLY, 'Data deleted successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - delete_contry_code()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function store_default_country_code()
    {
        try {
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                return ErrorResponse(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR), true);
            }
            $settings = fetch_details('country_codes', ['is_default' => 1]);
            if (!empty($settings)) {
                $country_codes = fetch_details('country_codes', ['is_default' => 1]);
                $Country_code_model = new Country_code_model();
                $data['is_default'] = 0;
                $Country_code_model->update($country_codes[0]['id'], $data);
                $data2['is_default'] = 1;
                $Country_code_model2 = new Country_code_model();
                $Country_code_model2->update($_POST['id'], $data2);
            }
            $response = [
                'error' => false,
                'message' => labels('default_setted', 'Default setted'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash(),
                'data' => []
            ];
            return $this->response->setJSON($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - store_default_country_code()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update_country_codes()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $this->validation->setRules(
                [
                    'name' => ["rules" => 'required', "errors" => ["required" => "Please enter name"]],
                    'code' => ["rules" => 'required', "errors" => ["required" => "Please enter code"]],
                ],
            );
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors  = $this->validation->getErrors();
                $response['error'] = true;
                $response['message'] = $errors;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['data'] = [];
                return $this->response->setJSON($response);
            }
            $data['code'] = ($_POST['code']);
            $data['name'] = ($_POST['name']);
            $contry_code = new Country_code_model();
            if ($contry_code->update($_POST['id'], $data)) {
                $response = [
                    'error' => false,
                    'message' => labels('Country code updated successfully', 'Country code updated successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return json_encode($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels('Please try again....', 'Please try again....'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return json_encode($response);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - update_country_codes()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get available countries for import (AJAX endpoint)
     * Returns available countries that are not already in the database
     */
    public function get_available_countries()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('unauthorized', 'Unauthorized access'),
                    'available_countries' => []
                ]);
            }

            // Get available countries using the existing helper method
            $availableCountries = $this->getAvailableCountriesForImport();

            return $this->response->setJSON([
                'error' => false,
                'message' => labels('countries_fetched_successfully', 'Countries fetched successfully'),
                'available_countries' => $availableCountries
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - get_available_countries()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
                'available_countries' => []
            ]);
        }
    }

    public function about_us_page_preview()
    {
        $settings = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title'] = 'About Us | ' . $settings['company_title'];
        $this->data['meta_description'] = 'About Us | ' . $settings['company_title'];

        // Pass the translated content to the view
        $this->data['about_us'] = $this->getTranslatedSetting('about_us', 'about_us');
        $this->data['settings'] = $settings;

        return view('backend/admin/pages/about_us_preview', $this->data);
    }

    public function contact_us_page_preview()
    {
        $settings = get_settings('general_settings', true);
        $settings['company_title'] = $this->getTranslatedSetting('general_settings', 'company_title');
        $this->data['title'] = 'Contact Us | ' . $settings['company_title'];
        $this->data['meta_description'] = 'Contact Us | ' . $settings['company_title'];

        // Pass the translated content to the view
        $this->data['contact_us'] = $this->getTranslatedSetting('contact_us', 'contact_us');
        $this->data['settings'] = $settings;
        return view('backend/admin/pages/contact_us_preview', $this->data);
    }

    public function email_template_configuration()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('unauthorised');
        }
        setPageInfo($this->data, labels('Email Configuration', 'Email Configuration') . '  | ' . labels('admin_panel', 'Admin Panel'), 'email_template_configuration');
        return view('backend/admin/template', $this->data);
    }

    public function email_template_configuration_update()
    {
        try {
            if ($this->superadmin == "superadmin@gmail.com") {
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            } else {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType']  = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/sms-gateways')->withCookies();
                }
            }
            $validationRules = [
                'subject' => ["rules" => 'required', "errors" => ["required" => "Please enter Subject"]],
                'email_type' => ["rules" => 'required', "errors" => ["required" => "Please select type"]],
                'template' => ["rules" => 'required', "errors" => ["required" => "Please select Template"]],
            ];
            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }
            $updatedData = $this->request->getPost('template');
            $email_type = $this->request->getPost('email_type');
            $subject = $this->request->getPost('subject');
            $email_to = $this->request->getPost('email_to');
            $bcc = $this->request->getPost('bcc');
            $cc = $this->request->getPost('cc');
            $template = htmlspecialchars($updatedData);
            $parameters = extractVariables($updatedData);
            $data['type'] = $email_type;
            $data['subject'] = $subject;
            $data['to'] = json_encode($email_to);
            $data['template'] = $template;
            $data['bcc'] = $bcc;
            $data['cc'] = $cc;
            $data['parameters'] = json_encode($parameters);
            $insert = insert_details($data, 'email_templates');
            if ($insert) {
                $response = [
                    'error' => false,
                    'message' => labels('Template Saved successfully!', 'Template Saved successfully!'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(SOMETHING_WENT_WRONG, "Something Went Wrong"),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
            }
            return json_encode($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - email_template_configuration_update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function email_template_list()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('unauthorised');
        }
        setPageInfo($this->data, labels('Email Templates', 'Email Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'email_template_list');
        return view('backend/admin/template', $this->data);
    }

    public function email_template_list_fetch()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $where = [];
        $from_app = false;
        $email_templates = new Email_template_model();
        $data = $email_templates->list($from_app, $search, $limit, $offset, $sort, $order, $where);
        return $data;
    }

    public function edit_email_template()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('unauthorised');
        }
        helper('function');

        // Get template ID from URL
        $uri = service('uri');
        $template_id = $uri->getSegments()[3];

        // Fetch the email template
        $templates = fetch_details('email_templates', ['id' => $template_id])[0];

        // Load required models for multi-language support
        $languageModel = new \App\Models\Language_model();
        $translatedEmailTemplateModel = new \App\Models\Translated_email_template_model();

        // Fetch all active languages (default language will be first)
        $languages = $languageModel->orderBy('is_default', 'DESC')->findAll();

        // Fetch existing translations for this template
        $translations = [];
        if (!empty($template_id)) {
            $translationResults = $translatedEmailTemplateModel->getTemplateTranslations($template_id);
            // Organize translations by language code for easy access in view
            foreach ($translationResults as $translation) {
                $translations[$translation['language_code']] = $translation;
            }
        }

        // Parse parameters from database (stored as JSON)
        // Parameters field may be stored in different formats:
        // - Normal JSON: ["user_name","provider_name"]
        // - Escaped JSON string: [\"company_name\",\"provider_id\"]
        // Normalize to a clean array format
        $parameters = $this->normalizeEmailTemplateParameters($templates['parameters'] ?? '');

        // Create parameter mapping for user-friendly labels
        // Maps parameter keys (like "user_name") to human-readable labels
        $parameterLabels = $this->getParameterLabels();

        // Get translated label for the email type
        // This will be displayed in the readonly input field
        $typeLabel = $this->getEmailTypeLabel($templates['type'] ?? '');

        // Pass data to view
        $this->data['template'] = $templates;
        $this->data['languages'] = $languages;
        $this->data['translations'] = $translations;
        $this->data['parameters'] = $parameters;
        $this->data['parameterLabels'] = $parameterLabels;
        $this->data['typeLabel'] = $typeLabel;

        setPageInfo($this->data, labels('Email Templates', 'Email Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'email_template_edit');
        return view('backend/admin/template', $this->data);
    }

    public function edit_email_template_operation()
    {
        try {
            // Check for demo mode restrictions
            if ($this->superadmin == "superadmin@gmail.com") {
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            } else {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType']  = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/sms-gateways')->withCookies();
                }
            }

            // Load models for email template handling
            $emailTemplateModel = new \App\Models\Email_template_model();

            // Validate basic required fields
            $validationRules = [
                'email_type' => ["rules" => 'required', "errors" => ["required" => labels('please_select_type', 'Please select type')]],
                'template_id' => ["rules" => 'required', "errors" => ["required" => 'Template ID is required']],
            ];

            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get translations data from form submission
            $translations = $this->request->getPost('translations', FILTER_UNSAFE_RAW);

            // Get default language from database
            $db = \Config\Database::connect();
            $default_language = $db->table('languages')->where('is_default', 1)->get()->getRow();
            $db->close();
            $default_lang_code = $default_language ? $default_language->code : 'en';

            // Validate that translations are provided
            if (empty($translations)) {
                $errors = [
                    'translations' => labels('translations_required', 'Translations are required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if default language translations are provided
            if (!isset($translations[$default_lang_code])) {
                $errors = [
                    'default_language' => labels('default_language_translations_missing', 'Default language translations are required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get default language translation data
            $default_translation = $translations[$default_lang_code];

            // Validate default language subject
            if (empty($default_translation['subject'])) {
                $errors = [
                    'default_language_subject' => labels('default_language_subject_required', 'Default language subject is required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Validate default language template
            if (empty($default_translation['template'])) {
                $errors = [
                    'default_language_template' => labels('default_language_template_required', 'Default language template is required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get form data
            $id = $this->request->getPost('template_id');
            $email_type = $this->request->getPost('email_type');
            $email_to = $this->request->getPost('email_to');
            $bcc = $this->request->getPost('bcc');
            $cc = $this->request->getPost('cc');

            // Use default language data for main template
            $default_subject = $default_translation['subject'];
            $default_template = $default_translation['template'];

            // Extract parameters from template for variable replacement
            $parameters = extractVariables($default_template);

            // Prepare main template data using default language
            $data['type'] = $email_type;
            $data['subject'] = $default_subject;
            $data['to'] = json_encode($email_to);
            $data['template'] = $default_template;
            $data['parameters'] = json_encode($parameters);

            // Process BCC field if provided
            if (isset($_POST['bcc'][0]) && !empty($_POST['bcc'][0])) {
                $base_tags = $this->request->getPost('bcc');
                $s_t = $base_tags;
                $val = explode(',', str_replace(']', '', str_replace('[', '', $s_t[0])));
                $bcc_array = [];
                foreach ($val as $s) {
                    $bcc_array[] = json_decode($s, true)['value'];
                }
                $data['bcc'] = implode(',', $bcc_array);
            }

            // Process CC field if provided
            if (isset($_POST['cc'][0]) && !empty($_POST['cc'][0])) {
                $base_tags = $this->request->getPost('cc');
                $s_t = $base_tags;
                $val = explode(',', str_replace(']', '', str_replace('[', '', $s_t[0])));
                $cc_array = [];
                foreach ($val as $s) {
                    $cc_array[] = json_decode($s, true)['value'];
                }
                $data['cc'] = implode(',', $cc_array);
            }

            // Update main email template with default language data
            $update = update_details($data, ['id' => $id], 'email_templates', false);

            // Handle translations for all languages
            if ($update && !empty($translations)) {
                $translatedEmailTemplateModel = new \App\Models\Translated_email_template_model();

                foreach ($translations as $lang_code => $translation_data) {
                    // Skip if both subject and template are empty
                    if (empty($translation_data['subject']) && empty($translation_data['template'])) {
                        continue;
                    }

                    // Prepare translation data for saving
                    $translation_data_to_save = [
                        'subject' => $translation_data['subject'] ?? '',
                        'template' => $translation_data['template'] ?? ''
                    ];

                    // Save or update translation using model
                    $translatedEmailTemplateModel->saveTranslation($id, $lang_code, $translation_data_to_save);
                }
            }

            // Prepare response based on update status
            if ($update) {
                $response = [
                    'error' => false,
                    'message' => labels('template_updated_successfully', 'Template updated successfully!'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'reload' => true, // Trigger page reload to show updated data
                    'data' => []
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
            }
            return json_encode($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - edit_email_template_operation()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function delete_email_template()
    {
        try {
            if ($this->superadmin == "superadmin@gmail.com") {
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            } else {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType']  = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/general-settings')->withCookies();
                }
            }
            $creator_id = $this->userId;
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $id = $this->request->getPost('id');
            $db = \Config\Database::connect();
            $builder = $db->table('email_templates');
            if ($builder->delete(['id' => $id])) {
                return successResponse(labels('email_template_deleted_successfully', 'Email template deleted successfully'), false, [], [], 200, csrf_token(), csrf_hash());
            }
            return ErrorResponse(labels(ERROR_OCCURED, 'An error occured'), true, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - delete_email_template()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function sms_gateway_setting_index()
    {
        if (!$this->isLoggedIn) {
            return redirect('unauthorised');
        }
        $this->builder->select('value');
        $this->builder->where('variable', 'sms_gateway_setting');
        $query = $this->builder->get()->getResultArray();
        if (count($query) == 1) {
            $settings = $query[0]['value'];
            $settings = json_decode($settings, true);
            if (!empty($settings)) {
                $this->data = array_merge($this->data, $settings);
            }
        }
        // Alias for view: key '2factor' cannot be used as PHP variable name
        $this->data['twofactor'] = $this->data['2factor'] ?? [];
        setPageInfo($this->data, labels('SMS Gateway settings', 'SMS Gateway settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'sms_gateways');
        return view('backend/admin/template', $this->data);
    }

    public function sms_gateway_setting_update()
    {
        if ($this->superadmin == "superadmin@gmail.com") {
            defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
        } else {
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                $_SESSION['toastMessageType']  = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/sms-gateways')->withCookies();
            }
        }
        $smsgateway_data = array();
        // Read requested statuses from POST (unchecked checkboxes are not submitted)
        $twilio_requested = isset($_POST['twilio_status']) ? '1' : '0';
        $fast2sms_requested = isset($_POST['fast2sms_status']) ? '1' : '0';
        $msg91_requested = isset($_POST['msg91_status']) ? '1' : '0';
        $twofactor_requested = isset($_POST['2factor_status']) ? '1' : '0';

        // Enforce single active gateway: only one can be active at a time.
        // Priority: twilio > fast2sms > msg91 > 2factor (first requested wins)
        $active_gateway = '';
        if ($twilio_requested === '1') {
            $active_gateway = 'twilio';
        } elseif ($fast2sms_requested === '1') {
            $active_gateway = 'fast2sms';
        } elseif ($msg91_requested === '1') {
            $active_gateway = 'msg91';
        } elseif ($twofactor_requested === '1') {
            $active_gateway = '2factor';
        }

        // Twilio: account SID + auth token + from numbers
        $smsgateway_data['twilio']['twilio_status'] = ($active_gateway === 'twilio') ? '1' : '0';
        $smsgateway_data['twilio']['twilio_account_sid'] = isset($_POST['twilio_account_sid']) ? $_POST['twilio_account_sid'] : '';
        $smsgateway_data['twilio']['twilio_auth_token'] = isset($_POST['twilio_auth_token']) ? $_POST['twilio_auth_token'] : '';
        $smsgateway_data['twilio']['twilio_from'] = isset($_POST['twilio_from']) ? $_POST['twilio_from'] : '';
        // Fast2SMS: authorization key + sender ID
        $smsgateway_data['fast2sms']['fast2sms_status'] = ($active_gateway === 'fast2sms') ? '1' : '0';
        $smsgateway_data['fast2sms']['fast2sms_api_key'] = isset($_POST['fast2sms_api_key']) ? $_POST['fast2sms_api_key'] : '';
        $smsgateway_data['fast2sms']['fast2sms_sender_id'] = isset($_POST['fast2sms_sender_id']) ? $_POST['fast2sms_sender_id'] : '';
        // MSG91: authkey only
        $smsgateway_data['msg91']['msg91_status'] = ($active_gateway === 'msg91') ? '1' : '0';
        $smsgateway_data['msg91']['msg91_authkey'] = isset($_POST['msg91_authkey']) ? $_POST['msg91_authkey'] : '';
        // 2Factor: API key + Sender ID (raw message, no template ID)
        $smsgateway_data['2factor']['2factor_status'] = ($active_gateway === '2factor') ? '1' : '0';
        $smsgateway_data['2factor']['2factor_api_key'] = isset($_POST['2factor_api_key']) ? $_POST['2factor_api_key'] : '';
        $smsgateway_data['2factor']['2factor_sender_id'] = isset($_POST['2factor_sender_id']) ? $_POST['2factor_sender_id'] : '';
        $smsgateway_data['current_sms_gateway'] = $active_gateway;
        $smsgateway_data = json_encode($smsgateway_data);
        // $this->update_setting('sms_gateway_setting', $smsgateway_data);
        if ($this->update_setting('sms_gateway_setting', $smsgateway_data)) {
            $_SESSION['toastMessage'] = labels('SMS Gateway settings has been successfully updated', 'SMS Gateway settings has been successfully updated');
            $_SESSION['toastMessageType']  = 'success';
        } else {
            $_SESSION['toastMessage']  = labels('Unable to update the SMS Gateway settings', 'Unable to update the SMS Gateway settings');
            $_SESSION['toastMessageType']  = 'error';
        }
        $this->session->markAsFlashdata('toastMessage');
        $this->session->markAsFlashdata('toastMessageType');
        return redirect()->to('admin/settings/sms-gateways')->withCookies();
    }

    public function sms_templates()
    {
        try {
            $validationRules = [
                'title' => ["rules" => 'required', "errors" => ["required" => "Please enter Title"]],
                'type' => ["rules" => 'required', "errors" => ["required" => "Please select type"]],
                'template' => ["rules" => 'required', "errors" => ["required" => "Please select Template"]],
            ];
            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }
            $updatedData = $this->request->getPost('template');
            $type = $this->request->getPost('type');
            $title = $this->request->getPost('title');
            $template = htmlspecialchars($updatedData);
            $parameters = extractVariables($updatedData);
            $data['type'] = $type;
            $data['title'] = $title;
            $data['template'] = $template;
            $data['parameters'] = json_encode($parameters);
            $insert = insert_details($data, 'sms_templates');
            if ($insert) {
                $response = [
                    'error' => false,
                    'message' => labels('Template Saved successfully!', 'Template Saved successfully!'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(SOMETHING_WENT_WRONG, "Something Went Wrong"),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
            }
            return json_encode($response);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - sms_templates()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function sms_template_list()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('sms_templates');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $sort = ($_GET['sort'] ?? '') == 'id' ? 'id' : ($_GET['sort'] ?? 'id');
        $order = $_GET['order'] ?? 'DESC';
        $offset = $_GET['offset'] ?? '0';
        if (!empty($search)) {
            $multipleWhere = [
                'id' => $search,
                'type' => $search,
            ];
        }
        if (!empty($where)) {
            $builder->where($where);
        }
        if (!empty($multipleWhere)) {
            $builder->groupStart()->orLike($multipleWhere)->groupEnd();
        }
        $total = $builder->countAllResults(false);
        $template_record = $builder->select('*')
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
        foreach ($template_record as $row) {
            $operations = '';
            $operations = '<div class="dropdown">
                    <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <button class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
            $operations .= '<a class="dropdown-item" href="' . base_url('/admin/settings/edit_sms_template/' . $row['id']) . '"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit_sms_template', 'Edit SMS Template') . '</a>';
            $operations .= '</div></div>';
            $tempRow['id'] = $row['id'];
            $tempRow['type'] = $row['type'];
            $tempRow['title'] = $row['title'];
            $tempRow['template'] = $row['template'];
            $tempRow['parameters'] =  substr($row['parameters'], 0, 30) . '...';
            $truncatedtemplate = substr($row['template'], 0, 30) . '...';
            $tempRow['truncatedtemplate'] = $truncatedtemplate;
            $tempRow['operations'] = $operations;
            $rows[] = $tempRow;
        }
        $bulkData['total'] = $total;
        $bulkData['rows'] = $rows;
        return json_encode($bulkData);
    }

    public function edit_sms_template()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('unauthorised');
        }
        helper('function');
        $uri = service('uri');
        $template_id = $uri->getSegments()[3];
        // Load models
        $smsTemplateModel = new \App\Models\Sms_template_model();
        $languageModel = new \App\Models\Language_model();
        $translatedSmsTemplateModel = new \App\Models\Translated_sms_template_model();

        // Get template data
        $template = $smsTemplateModel->getTemplateById($template_id);
        if (empty($template)) {
            $template = $smsTemplateModel->first();
        }

        // Fetch all active languages
        $languages = $languageModel->orderBy('is_default', 'DESC')->findAll();

        // Fetch existing translations for this template
        $translations = [];
        if (!empty($template['id'])) {
            $translationResults = $translatedSmsTemplateModel->getTemplateTranslations($template['id']);
            foreach ($translationResults as $translation) {
                $translations[$translation['language_code']] = $translation;
            }
        }

        // Parse parameters from database (stored as JSON)
        // Parameters field may be stored in different formats:
        // - Normal JSON: ["user_name","provider_name"]
        // - Escaped JSON string: [\"company_name\",\"provider_id\"]
        // Normalize to a clean array format
        $parameters = $this->normalizeEmailTemplateParameters($template['parameters'] ?? '');

        // Create parameter mapping for user-friendly labels
        // Maps parameter keys (like "user_name") to human-readable labels
        $parameterLabels = $this->getParameterLabels();

        // Get translated label for the SMS type
        // This will be displayed in the readonly input field
        $typeLabel = $this->getEmailTypeLabel($template['type'] ?? '');

        // Get gateway_template_ids from default language translation, else base table fallback
        $gatewayTemplateIds = [];
        $db = \Config\Database::connect();
        $defaultLangRow = $db->table('languages')->where('is_default', 1)->get()->getRow();
        $defaultLangCode = $defaultLangRow ? $defaultLangRow->code : 'en';
        $defaultTranslation = !empty($template['id']) ? $translatedSmsTemplateModel->getTranslatedTemplate($template['id'], $defaultLangCode) : null;
        $raw = $defaultTranslation['gateway_template_ids'] ?? $template['gateway_template_ids'] ?? null;
        if (!empty($raw)) {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $gatewayTemplateIds = is_array($decoded) ? $decoded : [];
        }

        // Pass data to view
        $this->data['template'] = $template;
        $this->data['gateway_template_ids'] = $gatewayTemplateIds;
        $this->data['template_based_gateways'] = config('Sms')->templateBasedGateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91'];
        $this->data['languages'] = $languages;
        $this->data['translations'] = $translations;
        $this->data['parameters'] = $parameters;
        $this->data['parameterLabels'] = $parameterLabels;
        $this->data['typeLabel'] = $typeLabel;
        setPageInfo($this->data, labels('SMS Templates', 'SMS Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'edit_sms_template');
        return view('backend/admin/template', $this->data);
    }

    public function edit_sms_template_update()
    {
        try {

            if ($this->superadmin == "superadmin@gmail.com") {
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            } else {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType']  = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/sms-gateways')->withCookies();
                }
            }
            // Load models for validation
            $smsTemplateModel = new \App\Models\Sms_template_model();

            // Get translated validation messages
            $validationMessages = $smsTemplateModel->getTranslatedValidationMessages();

            // Validate basic required fields
            $validationRules = [
                'type' => ["rules" => 'required', "errors" => ["required" => $validationMessages['type']['required']]],
                'template_id' => ["rules" => 'required', "errors" => ["required" => 'Template ID is required']],
            ];

            if (!$this->validate($validationRules)) {
                $errors = $this->validator->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get translations and validate them
            $translations = $this->request->getPost('translations');
            $db = \Config\Database::connect();
            $default_language = $db->table('languages')->where('is_default', 1)->get()->getRow();
            $db->close();
            $default_lang_code = $default_language ? $default_language->code : 'en';

            // Validate that translations are provided
            if (empty($translations)) {
                $errors = [
                    'translations' => labels('translations_required', 'Translations are required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if default language translations are provided
            if (!isset($translations[$default_lang_code])) {
                $errors = [
                    'default_language' => labels('default_language_translations_missing', 'Default language translations are required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            $default_translation = $translations[$default_lang_code];
            if (empty($default_translation['title'])) {
                $errors = [
                    'default_language_title' => labels('default_language_title_required', 'Default language title is required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            if (empty($default_translation['template'])) {
                $errors = [
                    'default_language_template' => labels('default_language_template_required', 'Default language template is required')
                ];
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->request->getPost('template_id');
            $type = $this->request->getPost('type');

            // Load models
            $smsTemplateModel = new \App\Models\Sms_template_model();
            $translatedSmsTemplateModel = new \App\Models\Translated_sms_template_model();

            // Use default language data for main template
            $default_title = $default_translation['title'];
            $default_template = $default_translation['template'];

            // Build gateway_template_ids from POST (stored in sms_templates base table)
            $gatewayTemplateIds = [];
            $allowedGateways = array_keys(config('Sms')->templateBasedGateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91']);
            $postedIds = $this->request->getPost('gateway_template_ids');
            if (is_array($postedIds)) {
                foreach ($postedIds as $key => $value) {
                    $key = trim((string) $key);
                    $value = trim((string) $value);
                    if ($key !== '' && $value !== '' && in_array($key, $allowedGateways, true)) {
                        $gatewayTemplateIds[$key] = $value;
                    }
                }
            }

            // Update main template with default language data and gateway_template_ids (base table)
            $template_escaped = htmlspecialchars($default_template);
            $parameters = extractVariables($default_template);
            $data = [
                'type' => $type,
                'title' => $default_title,
                'template' => $template_escaped,
                'parameters' => json_encode($parameters),
                'gateway_template_ids' => empty($gatewayTemplateIds) ? null : json_encode($gatewayTemplateIds)
            ];
            $update = $smsTemplateModel->updateTemplate($id, $data);

            // Handle translations for all languages
            if ($update && !empty($translations)) {
                foreach ($translations as $lang_code => $translation_data) {
                    // Skip if both title and template are empty
                    if (empty($translation_data['title']) && empty($translation_data['template'])) {
                        continue;
                    }

                    $translation_data_to_save = [
                        'title' => $translation_data['title'] ?? '',
                        'template' => htmlspecialchars($translation_data['template'] ?? '')
                    ];

                    $translatedSmsTemplateModel->saveTranslation($id, $lang_code, $translation_data_to_save);
                }
            }
            if ($update) {
                $response = [
                    'error' => false,
                    'message' => labels('template_updated_successfully', 'Template updated successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'reload' => true, // Add reload flag to trigger page reload
                    'data' => []
                ];
                return json_encode($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return json_encode($response);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - sms_templates()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function notification_settings()
    {
        if (!$this->isLoggedIn) {
            return redirect('unauthorised');
        }
        $current_settings = get_settings('notification_settings', true);
        // Complete list of all notification event types used in the system
        // This list includes all notifications that can be sent via email, SMS, or push notifications
        // Organized by category for better maintainability
        $notification_settings = [
            // Provider management notifications
            'provider_approved',
            'provider_disapproved',
            'new_provider_registerd',
            'provider_update_information',
            'provider_edits_service_details',

            // Withdrawal and payment notifications
            'withdraw_request_approved',
            'withdraw_request_disapproved',
            'withdraw_request_received',
            'payment_settlement',
            'cash_collection_by_provider',
            'payment_refund_executed',
            'payment_refund_successful',

            // Service management notifications
            'service_approved',
            'service_disapproved',

            // User account notifications
            'user_account_active',
            'user_account_deactive',
            'new_user_registered',

            // Booking status notifications
            'booking_confirmed',
            'booking_rescheduled',
            'booking_cancelled',
            'booking_completed',
            'booking_started',
            'booking_ended',
            'new_booking_confirmation_to_customer',
            'new_booking_received_for_provider',

            // Rating and review notifications
            'new_rating_given_by_customer',
            'rating_request_to_customer',

            // Communication notifications
            'user_query_submitted',

            // User moderation notifications
            // 'user_reported',
            'user_blocked',

            // Promo code notifications
            'promo_code_added',

            // Category notifications
            'new_category_available',
            'category_removed',

            // Subscription notifications
            'subscription_changed',
            'subscription_removed',
            'subscription_purchased',
            'subscription_expired',
            'subscription_payment_successful',
            'subscription_payment_failed',
            'subscription_payment_pending',

            // Payment gateway notifications
            'online_payment_success',
            'online_payment_failed',
            'online_payment_pending',

            // Custom job request notifications
            'bid_on_custom_job_request',
            'new_custom_job_request',

            // Additional charges notifications
            'added_additional_charges',

            // System and policy notifications
            'privacy_policy_changed',
            'terms_and_conditions_changed',
            'maintenance_mode',

            // Content notifications
            'new_blog'
        ];
        $this->data['notification_settings'] = $notification_settings;
        $this->data['current_settings'] = $current_settings; // Include current settings
        setPageInfo($this->data, labels('Notification settings', 'Notification settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'notification_settings');
        return view('backend/admin/template', $this->data);
    }

    public function notification_setting_update()
    {
        try {
            if ($this->superadmin == "superadmin@gmail.com") {
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            } else {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType']  = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/notification-settings')->withCookies();
                }
            }
            $updatedData = $this->request->getPost();
            unset($updatedData['update']);
            unset($updatedData[csrf_token()]);
            $json_string = json_encode($updatedData);
            if ($this->update_setting('notification_settings', $json_string)) {
                $_SESSION['toastMessage'] = labels('Notification settings has been successfully updated', 'Notification settings has been successfully updated');
                $_SESSION['toastMessageType']  = 'success';
            } else {
                $_SESSION['toastMessage']  = labels('Unable to update the Notification settings', 'Unable to update the Notification settings');
                $_SESSION['toastMessageType']  = 'error';
            }
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/settings/notification-settings')->withCookies();
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - notification_setting_update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function sms_email_preview()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            helper('function');
            $uri = service('uri');
            $type = $uri->getSegments()[3];

            // Get email and SMS templates
            $email_template = fetch_details('email_templates', ['type' => $type]);
            $sms_template = fetch_details('sms_templates', ['type' => $type]);

            // Get notification template by event_key (event_key should match the type)
            // Fetch notification template using event_key which matches the type parameter
            $notification_template = fetch_details('notification_templates', ['event_key' => $type]);
            // If no notification template found, create empty array structure to avoid errors
            if (empty($notification_template)) {
                $notification_template = [['id' => null, 'event_key' => $type, 'title' => '', 'body' => '', 'parameters' => '']];
            }

            // Load required models for multi-language support
            $languageModel = new \App\Models\Language_model();
            $translatedEmailTemplateModel = new \App\Models\Translated_email_template_model();
            $translatedSmsTemplateModel = new \App\Models\Translated_sms_template_model();
            $translatedNotificationTemplateModel = new \App\Models\TranslatedNotificationTemplateModel();

            // Fetch all active languages (default language will be first)
            $languages = $languageModel->orderBy('is_default', 'DESC')->findAll();

            // Fetch existing translations for email template
            $email_translations = [];
            if (!empty($email_template[0]['id'])) {
                $emailTranslationResults = $translatedEmailTemplateModel->getTemplateTranslations($email_template[0]['id']);
                // Organize translations by language code for easy access in view
                foreach ($emailTranslationResults as $translation) {
                    $email_translations[$translation['language_code']] = $translation;
                }
            }

            // Fetch existing translations for SMS template
            $sms_translations = [];
            if (!empty($sms_template[0]['id'])) {
                $smsTranslationResults = $translatedSmsTemplateModel->getTemplateTranslations($sms_template[0]['id']);
                // Organize translations by language code for easy access in view
                foreach ($smsTranslationResults as $translation) {
                    $sms_translations[$translation['language_code']] = $translation;
                }
            }

            // Fetch existing translations for notification template
            $notification_translations = [];
            if (!empty($notification_template[0]['id'])) {
                $notificationTranslationResults = $translatedNotificationTemplateModel->getTemplateTranslations($notification_template[0]['id']);
                // Organize translations by language code for easy access in view
                foreach ($notificationTranslationResults as $translation) {
                    $notification_translations[$translation['language_code']] = $translation;
                }
            }

            // Parse parameters from database for all three template types
            // Parameters are stored as JSON in the database and need to be normalized
            $email_parameters = $this->normalizeEmailTemplateParameters($email_template[0]['parameters'] ?? '');
            $sms_parameters = $this->normalizeEmailTemplateParameters($sms_template[0]['parameters'] ?? '');
            $notification_parameters = $this->normalizeEmailTemplateParameters($notification_template[0]['parameters'] ?? '');

            // Create parameter mapping for user-friendly labels
            // Maps parameter keys (like "user_name") to human-readable labels
            $parameterLabels = $this->getParameterLabels();

            // Get translated labels for template types (all three use the same type values)
            // These will be displayed in readonly input fields
            $email_typeLabel = $this->getEmailTypeLabel($email_template[0]['type'] ?? '');
            $sms_typeLabel = $this->getEmailTypeLabel($sms_template[0]['type'] ?? '');
            $notification_typeLabel = $this->getEmailTypeLabel($notification_template[0]['event_key'] ?? '');

            // Pass data to view
            $this->data['email_template'] =  $email_template[0] ?? [];
            $this->data['sms_template'] =  $sms_template[0] ?? [];
            $this->data['notification_template'] =  $notification_template[0];
            $this->data['languages'] = $languages;
            $this->data['email_translations'] = $email_translations;
            $this->data['sms_translations'] = $sms_translations;
            $this->data['notification_translations'] = $notification_translations;
            $this->data['email_parameters'] = $email_parameters;
            $this->data['sms_parameters'] = $sms_parameters;
            $this->data['notification_parameters'] = $notification_parameters;
            $this->data['parameterLabels'] = $parameterLabels;
            $this->data['email_typeLabel'] = $email_typeLabel;
            $this->data['sms_typeLabel'] = $sms_typeLabel;
            $this->data['notification_typeLabel'] = $notification_typeLabel;

            // Gateway template IDs for SMS modal (from default translation or base table)
            $sms_gateway_template_ids = [];
            $default_lang_code_sms = '';
            foreach ($languages as $lang) {
                if ($lang['is_default'] == 1) {
                    $default_lang_code_sms = $lang['code'];
                    break;
                }
            }
            $default_sms_translation = !empty($sms_template[0]['id']) && !empty($default_lang_code_sms) && isset($sms_translations[$default_lang_code_sms])
                ? $sms_translations[$default_lang_code_sms]
                : null;
            $raw_ids = $default_sms_translation['gateway_template_ids'] ?? $sms_template[0]['gateway_template_ids'] ?? null;
            if (!empty($raw_ids)) {
                $decoded = is_string($raw_ids) ? json_decode($raw_ids, true) : $raw_ids;
                $sms_gateway_template_ids = is_array($decoded) ? $decoded : [];
            }
            $this->data['sms_gateway_template_ids'] = $sms_gateway_template_ids;
            $this->data['sms_template_based_gateways'] = config('Sms')->templateBasedGateways ?? ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91'];

            setPageInfo($this->data, labels('preview_of_templates', 'Preview Of Templates') . ' | ' . labels('admin_panel', 'Admin Panel'), 'sms_email_preview');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - sms_email_preview()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function web_landing_page_settings()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $this->builder->select('value');
            $this->builder->where('variable', 'web_settings');
            $query = $this->builder->get()->getResultArray();
            if (count($query) == 1) {
                $settings = $query[0]['value'];
                $settings = json_decode($settings, true);

                // Handle multi-language fields with backward compatibility for landing page
                $multi_lang_fields = [
                    'landing_page_title',
                    'category_section_title',
                    'category_section_description',
                    'rating_section_title',
                    'rating_section_description',
                    'faq_section_title',
                    'faq_section_description',
                    'process_flow_title',
                    'process_flow_description',
                    'footer_description',
                    'step_1_title',
                    'step_2_title',
                    'step_3_title',
                    'step_4_title',
                    'step_1_description',
                    'step_2_description',
                    'step_3_description',
                    'step_4_description'
                ];

                foreach ($multi_lang_fields as $field) {
                    if (isset($settings[$field])) {
                        if (is_array($settings[$field])) {
                            // New structure: already an array of translations
                            $this->data[$field] = $settings[$field];
                        } else if (is_string($settings[$field])) {
                            // Old structure: single string - convert to new structure
                            $default_lang = 'en'; // fallback
                            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                            if (!empty($languages)) {
                                $default_lang = $languages[0]['code'];
                            }
                            $this->data[$field] = [$default_lang => $settings[$field]];
                        }
                    } else {
                        // Field doesn't exist - set empty array
                        $this->data[$field] = [];
                    }
                }

                // Merge other settings (but preserve multi-language data)
                $this->data = array_merge($this->data, $settings);

                // Ensure multi-language fields are not overwritten by the merge
                foreach ($multi_lang_fields as $field) {
                    if (isset($this->data[$field]) && is_array($this->data[$field])) {
                        // Keep the multi-language array structure
                        continue;
                    } else if (isset($settings[$field]) && is_string($settings[$field])) {
                        // Convert old string data to new structure
                        $default_lang = 'en'; // fallback
                        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
                        if (!empty($languages)) {
                            $default_lang = $languages[0]['code'];
                        }
                        $this->data[$field] = [$default_lang => $settings[$field]];
                    }
                }
            }
            $db = \Config\Database::connect();
            $builder = $db->table('services_ratings sr');
            $builder->select('sr.*,u.image as profile_image,u.username')
                ->join('users u', "(sr.user_id = u.id)")
                ->orderBy('id', 'DESC');
            $services_ratings = $builder->get()->getResultArray();
            foreach ($services_ratings as $key => $row) {
                $services_ratings[$key]['profile_image'] = base_url('public/backend/assets/profiles/' . $row['profile_image']);
            }
            $this->data['services_ratings'] = $services_ratings;

            $this->data['categories_name'] = get_categories_with_translated_names();
            setPageInfo($this->data, labels('Web Landing Page Settings', 'Web Landing Page Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'web_landing_page');

            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            if (!empty($languages)) {
                $this->data = $this->applyFallbacksToFields($this->data, $multi_lang_fields, $languages);

                if (isset($this->data['process_flow_data']) && is_array($this->data['process_flow_data'])) {
                    $this->data['process_flow_data'] = $this->applyFallbacksToNestedItems($this->data['process_flow_data'], $languages, ['title', 'description']);
                }
            }
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - web_landing_page_settings()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function web_setting_landing_page_update_old()
    {
        try {

            if ($this->superadmin == "superadmin@gmail.com") {
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
            } else {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                    $_SESSION['toastMessageType']  = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/sms-gateways')->withCookies();
                }
            }

            // Validate latitude and longitude if disable landing page settings is enabled
            if (isset($_POST['disable_landing_page_settings_status']) && $_POST['disable_landing_page_settings_status'] == 1) {
                $default_latitude = $this->request->getPost('default_latitude');
                $default_longitude = $this->request->getPost('default_longitude');

                // Check if latitude and longitude are provided
                if (empty($default_latitude) || empty($default_longitude)) {
                    $_SESSION['toastMessage'] = labels('latitude_and_longitude_are_required', 'Latitude and longitude are required when disable landing page settings is enabled');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                // Validate latitude range (-90 to 90)
                $lat = (float)$default_latitude;
                if ($lat < -90 || $lat > 90) {
                    $_SESSION['toastMessage'] = labels('please_enter_valid_latitude', 'Please enter a valid latitude (between -90 and 90)');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                // Validate longitude range (-180 to 180)
                $lng = (float)$default_longitude;
                if ($lng < -180 || $lng > 180) {
                    $_SESSION['toastMessage'] = labels('please_enter_valid_longitude', 'Please enter a valid longitude (between -180 and 180)');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                // Validate that there are active, approved partners within the serviceable distance
                if (!$this->validatePartnersInLocation($default_latitude, $default_longitude)) {
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }
            }
            // Handle multi-language landing page fields
            $multi_lang_landing_fields = [
                'landing_page_title',
                'category_section_title',
                'category_section_description',
                'rating_section_title',
                'rating_section_description',
                'faq_section_title',
                'faq_section_description',
                'process_flow_title',
                'process_flow_description',
                'footer_description',
                'step_1_title',
                'step_2_title',
                'step_3_title',
                'step_4_title',
                'step_1_description',
                'step_2_description',
                'step_3_description',
                'step_4_description'
            ];

            // Process each multi-language field from POST data
            foreach ($multi_lang_landing_fields as $field) {
                $field_translations = $_POST[$field] ?? [];
                $updatedData[$field] = $field_translations;
            }
            $updatedData['disable_landing_page_settings_status'] = isset($_POST['disable_landing_page_settings_status']) ? 1 : 0;

            // If disable landing page settings is enabled, automatically disable all other toggles
            if ($updatedData['disable_landing_page_settings_status'] == 1) {
                $updatedData['rating_section_status'] = 0;
                $updatedData['faq_section_status'] = 0;
                $updatedData['category_section_status'] = 0;
                $updatedData['process_flow_status'] = 0;
            } else {
                // If disable landing page settings is disabled, use the submitted values
                $updatedData['rating_section_status'] = isset($_POST['rating_section_status']) ? 1 : 0;
                $updatedData['faq_section_status'] = isset($_POST['faq_section_status']) ? 1 : 0;
                $updatedData['category_section_status'] = isset($_POST['category_section_status']) ? 1 : 0;
                $updatedData['process_flow_status'] = isset($_POST['process_flow_status']) ? 1 : 0;
            }

            // Handle latitude and longitude with proper decimal formatting (max 6 decimal places)
            $default_latitude = $this->request->getPost('default_latitude');
            $default_longitude = $this->request->getPost('default_longitude');

            if (!empty($default_latitude)) {
                $updatedData['default_latitude'] = number_format((float)$default_latitude, 6, '.', '');
            } else {
                $updatedData['default_latitude'] = '';
            }

            if (!empty($default_longitude)) {
                $updatedData['default_longitude'] = number_format((float)$default_longitude, 6, '.', '');
            } else {
                $updatedData['default_longitude'] = '';
            }

            // Multi-language fields are now handled above - no need for simple assignments
            $categories = $this->request->getPost('categories');
            if (!empty($categories)) {
                $category_ids =  !empty($categories) ? ($categories) : "";
            }
            $updatedData['category_ids'] = isset($category_ids) ? $category_ids : '';
            $ratings = $this->request->getPost('new_rating_ids');
            if (!empty($ratings)) {
                $rating_ids =  !empty($ratings) ? ($ratings) : "";
            }
            $updatedData['rating_ids'] = isset($rating_ids) ? $rating_ids : '';
            $old_settings = get_settings('web_settings', true);
            $old_data = ['social_media', 'playstore_url', 'app_section_status', 'applestore_url', 'web_logo', 'web_favicon', 'web_half_logo', 'footer_logo'];
            foreach ($old_data as $key) {
                $updatedData[$key] = (!empty($this->request->getPost($key))) ? $this->request->getPost($key) : (isset($old_settings[$key]) ? ($old_settings[$key]) : "");
            }
            if ($this->isLoggedIn && $this->userIsAdmin) {
                if ($this->request->getPost('update')) {
                    if ($this->superadmin == "superadmin@gmail.com") {
                        defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
                    } else {
                        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                            $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                            $_SESSION['toastMessageType']  = 'error';
                            $this->session->markAsFlashdata('toastMessage');
                            $this->session->markAsFlashdata('toastMessageType');
                            return redirect()->to('admin/settings/general-settings')->withCookies();
                        }
                    }
                    $data = get_settings('web_settings', true);


                    $data = get_settings('web_settings', true);
                    $files_to_check = array('landing_page_logo', 'landing_page_backgroud_image', 'web_logo', 'web_favicon', 'web_half_logo', 'footer_logo', 'step_1_image', 'step_2_image', 'step_3_image', 'step_4_image');

                    foreach ($files_to_check as $row) {
                        $file = $this->request->getFile($row);

                        if ($file && $file->isValid()) {
                            if (!valid_image($row)) {
                                $flag = 1;
                            } else {
                                $result = upload_file(
                                    $file,
                                    "public/uploads/web_settings/",
                                    labels('error uploading web settings file', 'error uploading web settings file'),
                                    'web_settings'
                                );

                                if ($result['error'] == false) {
                                    $updatedData[$row] = $result['file_name'];
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            }
                        } else {
                            $updatedData[$row] = isset($data[$row]) ? $data[$row] : "";
                        }
                    }
                    unset($updatedData[csrf_token()]);
                    unset($updatedData['update']);
                    $json_string = json_encode($updatedData);
                    if ($this->update_setting('web_settings', $json_string)) {
                        $_SESSION['toastMessage'] = labels('Landing Page Settings has been successfully updated', 'Landing Page Settings has been successfully updated');
                        $_SESSION['toastMessageType']  = 'success';
                    } else {
                        $_SESSION['toastMessage']  = labels('Unable to update Landing Page', 'Unable to update Landing Page');
                        $_SESSION['toastMessageType']  = 'error';
                    }
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - web_setting_page()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function web_setting_landing_page_update()
    {
        try {
            // --- Demo mode restrictions ---
            if (
                $this->superadmin != "superadmin@gmail.com" &&
                defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0
            ) {
                $_SESSION['toastMessage']     = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/sms-gateways')->withCookies();
            }

            // --- Baseline: old settings so nothing else gets wiped ---
            $old_settings = get_settings('web_settings', true);
            $landing_page_updates = [];

            // --- Allowed landing page keys ---
            // $landing_page_keys = [
            //     // Multi-language fields
            //     'landing_page_title',
            //     'category_section_title',
            //     'category_section_description',
            //     'rating_section_title',
            //     'rating_section_description',
            //     'faq_section_title',
            //     'faq_section_description',
            //     'process_flow_title',
            //     'process_flow_description',
            //     'footer_description',
            //     'step_1_title',
            //     'step_2_title',
            //     'step_3_title',
            //     'step_4_title',
            //     'step_1_description',
            //     'step_2_description',
            //     'step_3_description',
            //     'step_4_description',

            //     // Section toggles
            //     'disable_landing_page_settings_status',
            //     'rating_section_status',
            //     'faq_section_status',
            //     'category_section_status',
            //     'process_flow_status',

            //     // Lat/long
            //     'default_latitude',
            //     'default_longitude',

            //     // Relations
            //     'category_ids',
            //     'rating_ids',

            //     // Images
            //     'landing_page_logo',
            //     'landing_page_backgroud_image',
            //     'step_1_image',
            //     'step_2_image',
            //     'step_3_image',
            //     'step_4_image'
            // ];

            // --- Validation: lat/long when disable_landing_page_settings_status = 1 ---
            $default_latitude  = $this->request->getPost('default_latitude');
            $default_longitude = $this->request->getPost('default_longitude');

            if (isset($_POST['disable_landing_page_settings_status']) && $_POST['disable_landing_page_settings_status'] == 'on') {

                if (empty($default_latitude) || empty($default_longitude)) {
                    $_SESSION['toastMessage'] = labels(
                        'latitude_and_longitude_are_required',
                        'Latitude and longitude are required'
                    );
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                $lat = (float)$default_latitude;
                if ($lat < -90 || $lat > 90) {
                    $_SESSION['toastMessage'] = labels('please_enter_valid_latitude', 'Please enter a valid latitude (between -90 and 90)');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                $lng = (float)$default_longitude;
                if ($lng < -180 || $lng > 180) {
                    $_SESSION['toastMessage'] = labels('please_enter_valid_longitude', 'Please enter a valid longitude (between -180 and 180)');
                    $_SESSION['toastMessageType'] = 'error';
                    $this->session->markAsFlashdata('toastMessage');
                    $this->session->markAsFlashdata('toastMessageType');
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }

                if (!$this->validatePartnersInLocation($default_latitude, $default_longitude)) {
                    return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
                }
            }
            // --- Multi-language fields ---
            $multi_lang_fields = [
                'landing_page_title',
                'category_section_title',
                'category_section_description',
                'rating_section_title',
                'rating_section_description',
                'faq_section_title',
                'faq_section_description',
                'process_flow_title',
                'process_flow_description',
                'footer_description',
                'step_1_title',
                'step_2_title',
                'step_3_title',
                'step_4_title',
                'step_1_description',
                'step_2_description',
                'step_3_description',
                'step_4_description'
            ];
            foreach ($multi_lang_fields as $field) {
                $landing_page_updates[$field] = $_POST[$field] ?? ($old_settings[$field] ?? []);
            }

            // --- Section toggles ---
            $landing_page_updates['disable_landing_page_settings_status'] = isset($_POST['disable_landing_page_settings_status']) ? 1 : 0;

            if ($landing_page_updates['disable_landing_page_settings_status'] == 1) {
                $landing_page_updates['rating_section_status']   = 0;
                $landing_page_updates['faq_section_status']      = 0;
                $landing_page_updates['category_section_status'] = 0;
                $landing_page_updates['process_flow_status']     = 0;
            } else {
                $landing_page_updates['rating_section_status']   = isset($_POST['rating_section_status']) ? 1 : 0;
                $landing_page_updates['faq_section_status']      = isset($_POST['faq_section_status']) ? 1 : 0;
                $landing_page_updates['category_section_status'] = isset($_POST['category_section_status']) ? 1 : 0;
                $landing_page_updates['process_flow_status']     = isset($_POST['process_flow_status']) ? 1 : 0;
            }

            // --- Lat/long ---
            $lat = $this->request->getPost('default_latitude');
            $lng = $this->request->getPost('default_longitude');
            $landing_page_updates['default_latitude']  = !empty($lat) ? number_format((float)$lat, 6, '.', '') : '';
            $landing_page_updates['default_longitude'] = !empty($lng) ? number_format((float)$lng, 6, '.', '') : '';

            // --- Categories & ratings ---
            $landing_page_updates['category_ids'] = $this->request->getPost('categories') ?? ($old_settings['category_ids'] ?? '');
            $landing_page_updates['rating_ids']   = $this->request->getPost('new_rating_ids') ?? ($old_settings['rating_ids'] ?? '');

            // --- Image uploads ---
            $files_to_check = [
                'landing_page_logo',
                'landing_page_backgroud_image',
                'step_1_image',
                'step_2_image',
                'step_3_image',
                'step_4_image'
            ];
            foreach ($files_to_check as $row) {
                $file = $this->request->getFile($row);

                if ($file && $file->isValid()) {
                    if (!valid_image($row)) {
                        continue; // TODO: handle invalid image properly
                    }
                    $result = upload_file(
                        $file,
                        "public/uploads/web_settings/",
                        labels('error uploading web settings file', 'error uploading web settings file'),
                        'web_settings'
                    );
                    if ($result['error'] == false) {
                        $landing_page_updates[$row] = $result['file_name'];
                    } else {
                        return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    $landing_page_updates[$row] = $old_settings[$row] ?? "";
                }
            }

            // --- Merge landing page updates into old settings ---
            foreach ($landing_page_updates as $key => $val) {
                $old_settings[$key] = $val;
            }

            // --- Save result ---
            unset($old_settings[csrf_token()]);
            unset($old_settings['update']);
            $json_string = json_encode($old_settings, JSON_UNESCAPED_UNICODE);

            if ($this->update_setting('web_settings', $json_string)) {
                $_SESSION['toastMessage']     = labels('Landing Page Settings has been successfully updated', 'Landing Page Settings has been successfully updated');
                $_SESSION['toastMessageType'] = 'success';
            } else {
                $_SESSION['toastMessage']     = labels('Unable to update Landing Page', 'Unable to update Landing Page');
                $_SESSION['toastMessageType'] = 'error';
            }

            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/settings/web-landing-page-settings')->withCookies();
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Settings.php - web_setting_landing_page_update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }



    public function review_list()
    {
        $db = \Config\Database::connect();
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $search = $_GET['search'] ?? '';
        $sort = $_GET['sort'] ?? 'sr.id';
        $order = $_GET['order'] ?? 'DESC';

        // Get current and default language codes for translations
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Builder for total count
        $totalBuilder = $db->table('services_ratings sr');
        $totalBuilder->join('users u', 'u.id = sr.user_id');
        $totalBuilder->join('services s', 's.id = sr.service_id');
        $totalBuilder->select('COUNT(sr.id) as total');

        if (!empty($search)) {
            $totalBuilder->groupStart()
                ->like('sr.comment', $search)
                ->orLike('u.username', $search)
                ->orLike('s.title', $search)
                ->groupEnd();
        }

        if (!empty($_GET['rating_star_filter'])) {
            $totalBuilder->where('sr.rating', $_GET['rating_star_filter']);
        }

        $total = $totalBuilder->get()->getRowArray()['total'] ?? 0;

        // Builder for data
        $builder = $db->table('services_ratings sr');
        $builder->join('users u', 'u.id = sr.user_id');
        $builder->join('services s', 's.id = sr.service_id');
        $builder->select('sr.id, sr.rating, sr.comment, sr.created_at as rated_on, 
            u.image as profile_image, u.username as user_name, 
            s.title as service_name, s.id as service_id, s.user_id as partner_id');

        if (!empty($search)) {
            $builder->groupStart()
                ->like('sr.comment', $search)
                ->orLike('u.username', $search)
                ->orLike('s.title', $search)
                ->groupEnd();
        }

        if (!empty($_GET['rating_star_filter'])) {
            $builder->where('sr.rating', $_GET['rating_star_filter']);
        }

        $builder->orderBy($sort, $order);
        $builder->limit((int)$limit, (int)$offset);
        $rating_records = $builder->get()->getResultArray();

        // Extract unique service IDs and partner IDs for batch translation - OPTIMIZED
        $serviceIds = array_unique(array_column($rating_records, 'service_id'));
        $partnerIds = array_unique(array_column($rating_records, 'partner_id'));

        // Get all translated names in batch (OPTIMIZED - only 2 queries total)
        // This prevents N+1 query problems and dramatically improves performance
        $translations = get_batch_translated_names($serviceIds, $partnerIds, $currentLang, $defaultLang);

        // log_message('debug', 'Settings controller (review_list) - translations: ' . json_encode($translations));
        $rows = [];
        foreach ($rating_records as $row) {
            // Use translated service name with fallback to original
            // Fallback logic: Current language -> Default language -> Original data
            $serviceName = $row['service_name']; // Default to original
            if (
                isset($translations['services'][$row['service_id']]) &&
                !empty($translations['services'][$row['service_id']])
            ) {
                $serviceName = $translations['services'][$row['service_id']];
            }

            // Use translated partner name with fallback to original
            // Fallback logic: Current language -> Default language -> Original data
            $partnerName = '';
            if (
                isset($translations['partners'][$row['partner_id']]) &&
                !empty($translations['partners'][$row['partner_id']])
            ) {
                $partnerName = $translations['partners'][$row['partner_id']];
            } else {
                // Fallback to original partner name from users table
                $partnerName = fetch_details('users', ['id' => $row['partner_id']], ['username'])[0]['username'] ?? '';
            }

            $tempRow = [
                'id' => $row['id'],
                'comment' => $row['comment'],
                'user_name' => $row['user_name'],
                'service_name' => $serviceName, // Now translated with proper fallback!
                'rated_on' => $row['rated_on'],
                'stars' => '<i class="fa-solid fa-star text-warning"></i> ' . $row['rating'],
                'partner_name' => $partnerName, // Now translated with proper fallback!
            ];
            $rows[] = $tempRow;
        }

        return $this->response->setJSON([
            'total' => $total,
            'rows' => $rows
        ]);
    }

    public function become_provider_setting_page()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        $this->builder->select('value');
        $this->builder->where('variable', 'become_provider_page_settings');
        $query = $this->builder->get()->getResultArray();
        if (count($query) == 1) {
            $settings1 = $query[0]['value'];
            $settings1 = json_decode($settings1, true);

            $this->data = array_merge($this->data, $settings1);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('services_ratings sr');
        $builder->select('sr.*,u.image as profile_image,u.username')
            ->join('users u', "(sr.user_id = u.id)")
            ->orderBy('id', 'DESC');
        $services_ratings = $builder->get()->getResultArray();

        foreach ($services_ratings as $key => $row) {
            $services_ratings[$key]['profile_image'] = isset($row['profile_image']) ? base_url($row['profile_image']) : 'public/uploads/users/default.png';
        }
        $this->data['services_ratings'] = $services_ratings;
        $this->data['categories_name'] = get_categories_with_translated_names();

        // fetch languages
        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
        $this->data['languages'] = $languages;

        if (!empty($languages)) {
            // Apply fallback to top-level section fields (short_headline/title/description)
            $sectionKeys = [
                'hero_section',
                'how_it_work_section',
                'category_section',
                'subscription_section',
                'top_providers_section',
                'review_section',
                'faq_section',
                'feature_section'
            ];

            foreach ($sectionKeys as $sectionKey) {
                if (isset($this->data[$sectionKey]) && is_array($this->data[$sectionKey])) {
                    foreach (['short_headline', 'title', 'description'] as $field) {
                        if (isset($this->data[$sectionKey][$field]) && is_array($this->data[$sectionKey][$field])) {
                            $this->data[$sectionKey][$field] = $this->ensureMultiLangFallbacks(
                                $this->data[$sectionKey][$field],
                                $languages
                            );
                        }
                    }
                }
            }

            // Apply fallback to nested collections
            if (isset($this->data['how_it_work_section']['steps']) && is_array($this->data['how_it_work_section']['steps'])) {
                $this->data['how_it_work_section']['steps'] = $this->applyFallbacksToNestedItems(
                    $this->data['how_it_work_section']['steps'],
                    $languages,
                    ['title', 'description']
                );
            }

            if (isset($this->data['feature_section']['features']) && is_array($this->data['feature_section']['features'])) {
                $this->data['feature_section']['features'] = $this->applyFallbacksToNestedItems(
                    $this->data['feature_section']['features'],
                    $languages,
                    ['short_headline', 'title', 'description']
                );
            }

            if (isset($this->data['faq_section']['faqs']) && is_array($this->data['faq_section']['faqs'])) {
                $this->data['faq_section']['faqs'] = $this->applyFallbacksToNestedItems(
                    $this->data['faq_section']['faqs'],
                    $languages,
                    ['question', 'answer']
                );
            }
        }

        setPageInfo($this->data, labels('Become Provider Settings', 'Become Provider Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'become_provider_page_settings');
        return view('backend/admin/template', $this->data);
    }

    public function become_provider_setting_page_update()
    {

        if ($this->superadmin == "superadmin@gmail.com") {
            defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
        } else {
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                $_SESSION['toastMessage'] = labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR);
                $_SESSION['toastMessageType']  = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/sms-gateways')->withCookies();
            }
        }
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $request = $this->request->getPost();
            $uploadedFiles = $this->request->getFiles();

            $rules = [];
            $sections = ['hero_section', 'how_it_work_section', 'category_section', 'subscription_section', 'top_providers_section', 'review_section', 'faq_section',];

            $errors = [];

            // Custom validation for multilanguage fields
            // Get default language for validation
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $default_language = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $default_language = $language['code'];
                    break;
                }
            }

            // Validate each enabled section's multilanguage fields
            foreach ($sections as $section) {
                if (isset($request["{$section}_status"]) && (($request["{$section}_status"] == "on") || $request["{$section}_status"] == "1")) {

                    // Check if at least the default language has required fields filled
                    $short_headline_array = $request["{$section}_short_headline"] ?? [];
                    $title_array = $request["{$section}_title"] ?? [];
                    $description_array = $request["{$section}_description"] ?? [];

                    if (empty($short_headline_array[$default_language])) {
                        $errors["{$section}_short_headline"] = labels("Please enter {$section} short headline for the default language", "Please enter {$section} short headline for the default language");
                    }
                    if (empty($title_array[$default_language])) {
                        $errors["{$section}_title"] = labels("Please enter {$section} title for the default language", "Please enter {$section} title for the default language");
                    }
                    if (empty($description_array[$default_language])) {
                        $errors["{$section}_description"] = labels("Please enter {$section} description for the default language", "Please enter {$section} description for the default language");
                    }

                    // Additional validation for category section
                    if ($section == 'category_section' && empty($request['category_section_category_ids'])) {
                        $errors['category_section_category_ids'] = labels("Please select categories for category section", "Please select categories for category section");
                    }
                }
            }

            // Remove old validation approach - now using custom validation above

            // Additional Validation for Steps (multilanguage)
            if (
                isset($request['how_it_work_section_status']) &&
                (($request['how_it_work_section_status'] == "on") || $request['how_it_work_section_status'] == "1")
            ) {
                $steps_data = $request['how_it_work_section_steps'] ?? [];
                // Check if default language steps are filled
                if (isset($steps_data[$default_language])) {
                    foreach ($steps_data[$default_language] as $index => $step) {
                        if (empty($step['title']) || empty($step['description'])) {
                            $errors["how_it_work_section_steps.$index"] = labels("Please enter how it works section steps title and description for the default language", "Please enter how it works section steps title and description for the default language");
                        }
                    }
                }
            }

            // Additional Validation for FAQs (multilingual - new structure)
            if (
                isset($request['faq_section_status']) &&
                (($request['faq_section_status'] == "on") || $request['faq_section_status'] == "1")
            ) {
                $faqs_data = $request['faqs'] ?? [];

                if (!empty($faqs_data)) {
                    foreach ($faqs_data as $index => $faq) {

                        // Check if it's the new multilingual structure
                        if (isset($faq['question']) && is_array($faq['question'])) {
                            // New structure: multilingual format - check only default language
                            $default_question = $faq['question'][$default_language] ?? '';
                            $default_answer = $faq['answer'][$default_language] ?? '';
                        } else {
                            // Old structure: single language format (assume default language)
                            $default_question = $faq['question'] ?? '';
                            $default_answer = $faq['answer'] ?? '';
                        }
                    }
                } else {
                    $errors["faq_section_faqs"] = labels("Please add at least one FAQ", "Please add at least one FAQ");
                }
            }

            // Additional Validation for Feature Section (multilanguage)
            if (
                isset($request['feature_section_status']) &&
                (($request['feature_section_status'] == "on") || $request['feature_section_status'] == "1")
            ) {
                $features_data = $request['feature_section_feature'] ?? [];
                // Check if default language features are filled
                if (isset($features_data[$default_language]) && is_array($features_data[$default_language])) {
                    foreach ($features_data[$default_language] as $index => $feature) {
                        // Skip non-numeric indices (like position, exist_image, etc.)
                        if (!is_numeric($index)) {
                            continue;
                        }

                        if (empty($feature['title']) || empty($feature['description'])) {
                            $errors["feature_section_feature.$index"] = labels("Please enter feature title and description for the default language", "Please enter feature title and description for the default language");
                        }
                    }
                }
            }



            // Check if there are errors
            if (!empty($errors)) {
                $errorMessages = implode('<br>', array_values($errors)); // Join errors with a line break
                $this->session->setFlashdata('toastMessage', $errorMessages);
                $this->session->setFlashdata('toastMessageType', 'error');
                return redirect()->back()->withInput();
            }

            // Process each section
            $settings = [];
            $disk = fetch_current_file_manager();

            foreach ($sections as $section) {
                $section_data = [
                    'status' => ((isset($request["{$section}_status"])) && ($request["{$section}_status"] == "on")) ? 1 : 0,
                    // Handle multilanguage fields - store as arrays with language codes as keys
                    'short_headline' => $request["{$section}_short_headline"] ?? [],
                    'title' => $request["{$section}_title"] ?? [],
                    'description' => $request["{$section}_description"] ?? []
                ];
                if ($section == 'how_it_work_section') {
                    // Handle multilanguage steps data - store as clean PHP array (no JSON encoding)
                    $steps_data = $request['how_it_work_section_steps'] ?? [];
                    $section_data['steps'] = $steps_data;
                } elseif ($section == 'hero_section') {
                    $hero_section_images_selector = [];
                    // Handle existing images first
                    $existing_images = $request['hero_section_images_existing'] ?? [];
                    if (!empty($existing_images)) {
                        foreach ($existing_images as $existing_image) {
                            // Skip images marked for removal
                            if (isset($existing_image['remove']) && $existing_image['remove'] == '1') {
                                // Delete the image file
                                if (!empty($existing_image['image'])) {
                                    delete_file_based_on_server('become_provider', $existing_image['image'], $existing_image['disk']);
                                }
                                continue;
                            }
                            $hero_section_images_selector[] = [
                                'image' => $existing_image['image'],
                            ];
                        }
                    }
                    // Handle new uploaded images
                    if (isset($uploadedFiles['hero_section_images'])) {
                        foreach ($uploadedFiles['hero_section_images'] as $img) {
                            if ($img->isValid()) {
                                $result = upload_file($img, "public/uploads/become_provider/", labels("error creating become_provider", "error creating become_provider"), 'become_provider');
                                if ($result['error'] == false) {
                                    $hero_section_images_selector[] = [
                                        'image' => $result['file_name'],
                                    ];
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            }
                        }
                    }
                    $section_data['images'] = $hero_section_images_selector;
                } else if ($section == 'review_section') {
                    // Handle rating_ids properly - form sends comma-separated string
                    $rating_ids_raw = $request['new_rating_ids'] ?? '';

                    // Process rating_ids to handle different formats (backward compatibility)
                    if (is_string($rating_ids_raw) && !empty($rating_ids_raw)) {
                        // If it's a comma-separated string (from form), split it
                        $rating_ids = explode(',', $rating_ids_raw);
                        $rating_ids = array_filter(array_map('trim', $rating_ids)); // Clean up
                    } else if (is_array($rating_ids_raw) && isset($rating_ids_raw[0])) {
                        // If it's an array with first element being a string (legacy format)
                        $rating_ids = is_array($rating_ids_raw[0]) ? $rating_ids_raw[0] : explode(',', $rating_ids_raw[0]);
                        $rating_ids = array_filter(array_map('trim', $rating_ids)); // Clean up
                    } else if (is_array($rating_ids_raw)) {
                        // If it's already an array of IDs (newer format)
                        $rating_ids = array_filter(array_map('trim', $rating_ids_raw)); // Clean up
                    } else {
                        // Empty or invalid data
                        $rating_ids = [];
                    }

                    // Store as comma-separated string for consistency and backward compatibility
                    $final_rating_ids = !empty($rating_ids) ? implode(',', $rating_ids) : '';
                    $section_data['rating_ids'] = $final_rating_ids;
                } else if ($section == 'category_section') {
                    // Handle category_ids properly - form sends array, we need string
                    $category_ids_raw = $request['category_section_category_ids'] ?? [];

                    // Process category_ids to handle different formats (backward compatibility)
                    if (is_array($category_ids_raw) && !empty($category_ids_raw)) {
                        // If it's an array (from form), clean and convert to string
                        $category_ids = array_filter(array_map('trim', $category_ids_raw));
                        $section_data['category_ids'] = implode(',', $category_ids);
                    } else if (is_string($category_ids_raw) && !empty($category_ids_raw)) {
                        // If it's already a comma-separated string (backward compatibility)
                        $category_ids = explode(',', $category_ids_raw);
                        $category_ids = array_filter(array_map('trim', $category_ids));
                        $section_data['category_ids'] = implode(',', $category_ids);
                    } else {
                        // Empty or invalid data
                        $section_data['category_ids'] = '';
                    }
                } else if ($section == 'faq_section') {
                    // Handle multilanguage FAQ data - store as clean PHP array (no JSON encoding)
                    // New structure: faqs[index][question/answer][language_code]
                    $faqs = $request['faqs'] ?? [];
                    $section_data['faqs'] = $faqs;
                }

                // Store section data as clean PHP array (no JSON encoding at section level)
                $settings[$section] = $section_data;
            }

            if (isset($request['feature_section_status']) && (($request['feature_section_status'] == "on") || $request['feature_section_status'] == "1")) {
                if (isset($request['feature_section_feature']) && $request['feature_section_feature']) {

                    // ============================================================
                    // NEW SIMPLE STRUCTURE (Index-First Approach)
                    // ============================================================
                    // Form sends data in a clean, predictable format:
                    // - feature_section_feature[0][en][title]         = "Title in English"
                    // - feature_section_feature[0][hi][title]         = "Title in Hindi"
                    // - feature_section_feature[0][position]          = "left"
                    // - feature_section_feature[0][image]             = (file upload)
                    // - feature_section_feature[0][exist_image]       = "filename.jpg" or "new"
                    // - feature_section_feature[0][exist_disk]        = "local_server" or "aws_s3"
                    //
                    // This structure makes it simple to:
                    // 1. Process each feature independently
                    // 2. Handle multiple languages cleanly
                    // 3. Track image state per feature
                    // 4. Add/remove features dynamically
                    // ============================================================

                    $updatedFeatures = [];
                    $request_obj = \Config\Services::request();
                    $uploadPath = 'public/uploads/become_provider/';

                    // Get existing feature section data from database to preserve images
                    $existing_settings = get_settings('become_provider_page_settings', true);
                    $existing_features = isset($existing_settings['feature_section']['features']) ? $existing_settings['feature_section']['features'] : [];

                    // Process each feature section by index
                    // The new structure is already organized by index, so no reorganization needed
                    $features_by_index = $request['feature_section_feature'];

                    // Sort by index to maintain order (0, 1, 2, ...)
                    ksort($features_by_index);

                    foreach ($features_by_index as $i => $feature_data) {
                        // Skip non-numeric indices (safety check)
                        if (!is_numeric($i)) {
                            continue;
                        }

                        // ============================================================
                        // Step 1: Extract image metadata for this feature
                        // ============================================================
                        // exist_image: Contains the current image filename or "new" for new features
                        // exist_disk: Contains the storage location (local_server, aws_s3, etc.)
                        // remove_image: Flag to delete the image (not currently used)
                        $exist_image = $feature_data['exist_image'] ??
                            ($request_obj->getPost("feature_section_feature.{$i}.exist_image") ?? 'new');
                        $exist_disk = $feature_data['exist_disk'] ??
                            ($request_obj->getPost("feature_section_feature.{$i}.exist_disk") ?? '');
                        $remove_image = $feature_data['remove'] ??
                            ($request_obj->getPost("feature_section_feature.{$i}.remove") ?? '0');

                        // Get existing image from database if available (for comparison)
                        $existing_image = isset($existing_features[$i]['image']) ? $existing_features[$i]['image'] : '';

                        // ============================================================
                        // Step 2: Extract multilanguage text data
                        // ============================================================
                        // Loop through feature_data and extract language-specific content
                        // Language codes are keys like 'en', 'hi', etc.
                        // We skip metadata keys like 'position', 'image', 'exist_image', etc.
                        $multilang_short_headline = [];
                        $multilang_title = [];
                        $multilang_description = [];

                        foreach ($feature_data as $key => $value) {
                            // Check if this is a language code (array with translation fields)
                            if (is_array($value) && !in_array($key, ['position', 'image', 'exist_image', 'exist_disk', 'remove'])) {
                                $lang_code = $key;
                                $multilang_short_headline[$lang_code] = trim($value['short_headline'] ?? '');
                                $multilang_title[$lang_code] = trim($value['title'] ?? '');
                                $multilang_description[$lang_code] = trim($value['description'] ?? '');
                            }
                        }

                        // Get position from feature data (left or right alignment)
                        $position = $feature_data['position'] ??
                            ($request_obj->getPost("feature_section_feature.{$i}.position") ?? 'right');

                        // ============================================================
                        // Step 3: Validate content exists
                        // ============================================================
                        // Check if feature has content in at least one language
                        // Skip empty features to avoid saving blank entries
                        $has_content = false;
                        foreach ($multilang_title as $lang_code => $title) {
                            if (!empty(trim($title))) {
                                $has_content = true;
                                break;
                            }
                        }

                        // Skip if no content
                        if (!$has_content) {
                            continue;
                        }

                        // ============================================================
                        // Step 4: Determine if this is a new or existing feature
                        // ============================================================
                        // A feature is "new" if:
                        // - exist_image is "new" AND
                        // - No existing image in database for this index
                        $is_new_feature = ($exist_image == 'new' && empty($existing_image));

                        // ============================================================
                        // Step 5: Handle image upload based on feature type
                        // ============================================================

                        // CASE A: New Feature (Creating for the first time)
                        if ($is_new_feature) {
                            // Try to get the uploaded file
                            $file = $request_obj->getFile("feature_section_feature[{$i}][image]") ??
                                $request_obj->getFile("feature_section_feature.{$i}.image");

                            if ($file && $file->isValid()) {
                                // Upload the new image
                                $result = upload_file($file, $uploadPath, labels("error creating feature section", "error creating feature section"), 'become_provider');
                                if ($result['error'] == false) {
                                    // Create feature entry with uploaded image
                                    $feature_entry = [
                                        'short_headline' => $multilang_short_headline,
                                        'title' => $multilang_title,
                                        'description' => $multilang_description,
                                        'position' => $position,
                                        'image' => $result['file_name']
                                    ];

                                    $updatedFeatures[] = $feature_entry;
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            } else {
                                // If no file is uploaded for new entry, save feature without image
                                // Image is optional, so we allow features without images
                                $feature_entry = [
                                    'short_headline' => $multilang_short_headline,
                                    'title' => $multilang_title,
                                    'description' => $multilang_description,
                                    'position' => $position,
                                    'image' => '' // Empty image since no file was uploaded
                                ];

                                $updatedFeatures[] = $feature_entry;
                            }
                        }
                        // CASE B: Existing Feature (Updating)
                        else {
                            // Check if the image is marked for removal
                            if ($remove_image == '1') {
                                // Delete the image file
                                if (!empty($exist_image)) {
                                    delete_file_based_on_server('become_provider', $exist_image, $exist_disk);
                                }
                                // Set image to empty
                                $exist_image = '';
                            }

                            // Prepare updated data with multilanguage content
                            $updatedData = [
                                'short_headline' => $multilang_short_headline,
                                'title' => $multilang_title,
                                'description' => $multilang_description,
                                'position' => $position
                            ];

                            // Check if a new file is being uploaded for existing entry
                            $file = $request_obj->getFile("feature_section_feature[{$i}][image]") ??
                                $request_obj->getFile("feature_section_feature.{$i}.image");

                            if ($file && $file->isValid()) {
                                // CASE B1: New image uploaded - replace the old one
                                $result = upload_file($file, $uploadPath, labels("error updating feature section", "error updating feature section"), 'become_provider');
                                if ($result['error'] == false) {
                                    $updatedData['image'] = $result['file_name'];
                                    // Delete old image using central helper (supports all disks)
                                    if (!empty($exist_image) && $exist_image != 'new') {
                                        delete_file_based_on_server('become_provider', $exist_image, $exist_disk);
                                    }
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            } else {
                                // CASE B2: No new file uploaded - keep existing image
                                // Use exist_image if available, otherwise fall back to existing_image from database
                                $final_image = !empty($exist_image) && $exist_image != 'new' ? $exist_image : $existing_image;
                                $updatedData['image'] = $final_image;
                            }

                            $updatedFeatures[] = $updatedData;
                        }
                    }
                    // Prepare final settings array
                    $feature_section = [
                        'status' => ($request_obj->getPost("feature_section_status") !== null && $request_obj->getPost("feature_section_status") === "on") ? 1 : 0,
                        'features' => ($updatedFeatures),
                    ];

                    // Store feature section as clean PHP array (no JSON encoding at section level)
                    $settings['feature_section'] = $feature_section;
                }
            }

            // Update settings with new data - encode once with Unicode support for proper Hindi/multilingual text
            $json_string = json_encode($settings, JSON_UNESCAPED_UNICODE);

            if ($this->update_setting('become_provider_page_settings', $json_string)) {
                $_SESSION['toastMessage'] = labels('Become Provider Page settings has been successfuly updated', 'Become Provider Page settings has been successfuly updated');
                $_SESSION['toastMessageType']  = 'success';
            } else {
                $_SESSION['toastMessage']  = labels('Unable to update the Become Provider Page settings', 'Unable to update the Become Provider Page settings');
                $_SESSION['toastMessageType']  = 'error';
            }
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/settings/become-provider-setting')->withCookies();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Get available countries for import (not already in database)
     * This is a helper method to get countries from JSON that aren't in the database
     */
    private function getAvailableCountriesForImport()
    {
        // Read the JSON file containing country codes
        $jsonPath = FCPATH . 'public/country_codes.json';

        if (!file_exists($jsonPath)) {
            return [];
        }

        $jsonContent = file_get_contents($jsonPath);
        $countryCodes = json_decode($jsonContent, true);

        if (!$countryCodes) {
            return [];
        }

        // Get existing country codes from database to avoid duplicates
        $existingCodes = fetch_details('country_codes', [], ['calling_code', 'country_name']);
        $existingCodesArray = array_column($existingCodes, 'calling_code');

        // Prepare available countries (not already in database)
        $availableCountries = [];
        foreach ($countryCodes as $countryName => $details) {
            $callingCode = $details['calling_code'];

            // Skip if this calling code already exists in database
            if (!in_array($callingCode, $existingCodesArray)) {
                $availableCountries[] = [
                    'country_name' => $countryName,
                    'calling_code' => $callingCode,
                    'country_code' => $details['country_code'] ?? '',
                    'flag_image' => $details['flag_image'] ? base_url('public/backend/assets/country_flags/' . $details['flag_image']) : ''
                ];
            }
        }

        // Sort countries alphabetically by name
        usort($availableCountries, function ($a, $b) {
            return strcmp($a['country_name'], $b['country_name']);
        });

        return $availableCountries;
    }

    private function getTranslatedSetting(string $key, ?string $field = null): string
    {
        $session     = session();
        $currentLang = $session->get('lang') ?? 'en';

        // Pull the setting (with optional nested field if needed)
        $settings = get_settings($key, true);
        $value    = $field ? ($settings[$field] ?? '') : ($settings[$key] ?? '');

        // Case 1: Old clients → plain HTML (last fallback for old single language data)
        if (is_string($value)) {
            return $value;
        }

        // Case 2: New clients → translations array with 4-tier fallback logic
        if (is_array($value)) {
            $defaultLang = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';

            // Tier 1: Current language translation (if available)
            if (!empty($value[$currentLang])) {
                return $value[$currentLang];
            }

            // Tier 2: Default language translation (if current language fails)
            if (!empty($value[$defaultLang])) {
                return $value[$defaultLang];
            }

            // Tier 3: First available translation (if default language fails)
            if (!empty($value)) {
                return reset($value);
            }
        }

        // Tier 4: If all above fail, try to get old single language data as last fallback
        // This handles cases where the new structure exists but is empty
        $oldValue = get_settings($key, true);
        if (is_string($oldValue)) {
            return $oldValue;
        }

        return '';
    }

    /**
     * Ensures multi-language fields have fallback values for all languages
     * This prevents blank fields when default language is changed to a language without translations
     * 
     * Fallback priority:
     * 1. Existing value for the language
     * 2. English translation (if available)
     * 3. First available translation
     * 
     * @param array $translations The translations array (e.g., ['en' => 'content', 'fr' => ''])
     * @param array $allLanguages All available languages from database
     * @return array Translations array with fallback values filled in
     */
    private function ensureMultiLangFallbacks(array $translations, array $allLanguages): array
    {
        // If translations is empty, return empty array
        if (empty($translations)) {
            return [];
        }

        // Get default language code
        $defaultLang = 'en'; // fallback
        foreach ($allLanguages as $lang) {
            if ($lang['is_default'] == 1) {
                $defaultLang = $lang['code'];
                break;
            }
        }

        // Resolve fallback once so every language reuses the same predictable value
        $fallbackValue = resolve_translation_fallback($translations, ['en']);

        // Ensure default language has a value (critical for form display)
        if (empty($translations[$defaultLang]) && !empty($fallbackValue)) {
            $translations[$defaultLang] = $fallbackValue;
        }

        // Ensure all languages in the system have at least a fallback value
        // This prevents blank fields when switching languages in forms
        foreach ($allLanguages as $lang) {
            $langCode = $lang['code'];
            // If language doesn't have a value and we have a fallback, use it
            if (empty($translations[$langCode]) && !empty($fallbackValue)) {
                $translations[$langCode] = $fallbackValue;
            }
        }

        return $translations;
    }

    /**
     * Apply fallback logic to a list of translation fields within a data array.
     *
     * @param array $data       Source array that contains translation fields.
     * @param array $fields     Field names that should receive fallback logic.
     * @param array $languages  Available languages for fallback resolution.
     *
     * @return array Updated array with fallback values applied.
     */
    private function applyFallbacksToFields(array $data, array $fields, array $languages): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = $this->ensureMultiLangFallbacks($data[$field], $languages);
            }
        }

        return $data;
    }

    /**
     * Apply fallback logic to every item inside a nested array (e.g., steps, FAQs, features).
     *
     * @param array $items      Nested items that contain translation fields.
     * @param array $languages  Available languages for fallback resolution.
     * @param array $fields     Field names inside each item that need fallback logic.
     *
     * @return array Updated nested array with fallback values applied to each item.
     */
    private function applyFallbacksToNestedItems(array $items, array $languages, array $fields): array
    {
        foreach ($items as $index => $item) {
            if (is_array($item)) {
                $items[$index] = $this->applyFallbacksToFields($item, $fields, $languages);
            }
        }

        return $items;
    }

    /**
     * Returns true when at least one translation value contains meaningful text.
     * Accepts either a single string or a translation array (e.g. ['en' => '...']).
     */
    private function hasAnyNonEmptyTranslationValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $translation) {
                if ($this->isMeaningfulText($translation)) {
                    return true;
                }
            }

            return false;
        }

        return $this->isMeaningfulText($value);
    }

    /**
     * Treats empty strings and editor-only markup (like "<p><br></p>") as empty.
     */
    private function isMeaningfulText($value): bool
    {
        if ($value === null) {
            return false;
        }

        $text = is_string($value) ? $value : (string) $value;

        if (function_exists('html_is_effectively_empty')) {
            return !html_is_effectively_empty($text);
        }

        return trim(strip_tags($text)) !== '';
    }

    /**
     * Validate that there are active, approved partners within the serviceable distance
     * for the given latitude and longitude coordinates
     * 
     * @param float $latitude The latitude coordinate to check
     * @param float $longitude The longitude coordinate to check
     * @return bool Returns true if partners are found, false otherwise
     */
    private function validatePartnersInLocation($latitude, $longitude)
    {
        // Get system settings to retrieve max serviceable distance
        $settings = get_settings('general_settings', true);

        // Check if max serviceable distance is configured
        if (empty($settings['max_serviceable_distance'])) {
            $_SESSION['toastMessage'] = labels('max_serviceable_distance_not_configured', 'Max serviceable distance is not configured in system settings');
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return false; // Return false to indicate validation failure
        }

        $max_distance = $settings['max_serviceable_distance'];

        // Connect to database
        $db = \Config\Database::connect();

        // Query to find partners within the serviceable distance
        // who are approved and have active subscriptions
        $builder = $db->table('partner_details pd');
        $builder->select("
            pd.partner_id,
            u.latitude,
            u.longitude,
            st_distance_sphere(POINT('$longitude', '$latitude'), POINT(u.longitude, u.latitude))/1000 as distance
        ")
            ->join('users u', 'pd.partner_id = u.id')
            ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
            ->where('pd.is_approved', 1) // Only approved partners
            ->where('ps.status', 'active') // Only partners with active subscriptions
            ->having('distance < ' . (float)$max_distance) // Within serviceable distance
            ->groupBy('pd.partner_id');

        $partners = $builder->get()->getResultArray();

        // Check if any partners were found
        if (empty($partners)) {
            $_SESSION['toastMessage'] = labels('no_providers_in_location', 'No providers are available in this location. Please use other latitude and longitude values.');
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return false; // Return false to indicate validation failure
        }

        return true; // Return true to indicate validation success
    }

    /**
     * Get parameter labels mapping for email templates
     * 
     * Maps parameter keys to their human-readable labels
     * Keys should match the variable names used in templates (e.g., [[user_name]])
     * 
     * @return array Array mapping parameter keys to labels
     */
    private function getParameterLabels(): array
    {
        // Map parameter keys to their labels
        // Keys should match the variable names used in templates (e.g., [[user_name]])
        return [
            // User-related parameters
            'user_id' => labels('user_id', 'User ID'),
            'user_name' => labels('user_name', 'User Name'),

            // Provider/Partner parameters
            'provider_name' => labels('provider_name', 'Provider Name'),
            'provider_id' => labels('provider_id', 'Provider ID'),
            'company_name' => labels('company_name', 'Company Name'),

            // Site/Company parameters
            'site_url' => labels('site_url', 'Site URL'),
            'company_contact_info' => labels('company_contact_info', 'Company Contact Info'),
            'company_logo' => labels('company_logo', 'Company Logo'),

            // Booking-related parameters
            'booking_id' => labels('booking_id', 'Booking ID'),
            'booking_date' => labels('booking_date', 'Booking Date'),
            'booking_time' => labels('booking_time', 'Booking Time'),
            'booking_service_names' => labels('booking_service_names', 'Booking Service Names'),
            'booking_address' => labels('booking_address', 'Booking Address'),
            'booking_status' => labels('status', 'Status'),

            // Payment parameters
            'amount' => labels('amount', 'Amount'),
            'currency' => labels('currency', 'Currency'),

            // Service parameters
            'service_id' => labels('service_id', 'Service ID'),
            'service_name' => labels('service_name', 'Service Name'),
        ];
    }

    /**
     * Get translated label for email template type
     * 
     * Maps email template type values to their human-readable translated labels
     * Used to display user-friendly type names in the readonly input field
     * 
     * @param string $type The email template type value (e.g., "provider_approved")
     * @return string Translated label for the type
     */
    private function getEmailTypeLabel(string $type): string
    {
        // Map type values to their translated labels
        // This matches the labels used in the email template configuration form
        $typeLabels = [
            'provider_approved' => labels('provider_approved', 'Provider Approved'),
            'provider_disapproved' => labels('provider_disapproved', 'Provider Disapproved'),
            'withdraw_request_approved' => labels('approved_withdraw_request', 'Approved Withdrawal Request'),
            'withdraw_request_disapproved' => labels('disapproved_withdraw_request', 'Disapproved Withdrawal Request'),
            'payment_settlement' => labels('payment_settled', 'Payment Settled'),
            'service_approved' => labels('service_approved', 'Service Approved'),
            'service_disapproved' => labels('service_disapproved', 'Service Disapproved'),
            'user_account_active' => labels('user_account_activated', 'User Account Activated'),
            'user_account_deactive' => labels('user_account_deactivated', 'User Account Deactivated'),
            'provider_update_information' => labels('provider_information_updated', 'Provider Information Updated'),
            'new_provider_registerd' => labels('new_provider_registered', 'New Provider Registered'),
            'withdraw_request_received' => labels('withdrawal_request_received', 'Withdrawal Request Received'),
            'cash_collection_by_provider' => labels('cash_collection_by_provider', 'Cash Collection by Provider'),
            'booking_status_updated' => labels('booking_status_updated', 'Booking Status Updated'),
            'new_booking_confirmation_to_customer' => labels('new_booking_confirmation_to_customer', 'New Booking Confirmation to Customer'),
            'new_booking_received_for_provider' => labels('new_booking_received_for_provider', 'New Booking Received for Provider'),
            'new_rating_given_by_customer' => labels('new_rating_given_by_customer', 'New Rating Given by Customer'),
            'rating_request_to_customer' => labels('rating_request_to_customer', 'Rating Request to Customer'),
            'user_query_submitted' => labels('user_query_submitted', 'User Query Submitted'),
            'new_message' => labels('new_message', 'New Message'),
            // 'user_reported' => labels('user_reported', 'User Reported'),
            'user_blocked' => labels('user_blocked', 'User Blocked'),
            'promo_code_added' => labels('promo_code_added', 'Promo Code Added'),
            'new_category_available' => labels('new_category_available', 'New Category Available'),
            'category_removed' => labels('category_removed', 'Category Removed'),
            'subscription_changed' => labels('subscription_changed', 'Subscription Changed'),
            'subscription_removed' => labels('subscription_removed', 'Subscription Removed'),
            'privacy_policy_changed' => labels('privacy_policy_changed', 'Privacy Policy Changed'),
            'terms_and_conditions_changed' => labels('terms_and_conditions_changed', 'Terms and Conditions Changed'),
            'maintenance_mode' => labels('maintenance_mode', 'Maintenance Mode'),
            'new_blog' => labels('new_blog', 'New Blog'),
            'subscription_payment_successful' => labels('subscription_payment_successful', 'Subscription Payment Successful'),
            'subscription_payment_failed' => labels('subscription_payment_failed', 'Subscription Payment Failed'),
            'subscription_payment_pending' => labels('subscription_payment_pending', 'Subscription Payment Pending'),
            'subscription_purchased' => labels('subscription_purchased', 'Subscription Purchased'),
            'payment_refund_executed' => labels('payment_refund_executed', 'Payment Refund Executed'),
            'booking_confirmed' => labels('booking_confirmed', 'Booking Confirmed'),
            'booking_rescheduled' => labels('booking_rescheduled', 'Booking Rescheduled'),
            'booking_cancelled' => labels('booking_cancelled', 'Booking Cancelled'),
            'booking_completed' => labels('booking_completed', 'Booking Completed'),
            'booking_started' => labels('booking_started', 'Booking Started'),
            'booking_ended' => labels('booking_ended', 'Booking Ended'),
            'online_payment_success' => labels('online_payment_success', 'Online Payment Success'),
            'online_payment_failed' => labels('online_payment_failed', 'Online Payment Failed'),
            'online_payment_pending' => labels('online_payment_pending', 'Online Payment Pending'),
            'bid_on_custom_job_request' => labels('bid_on_custom_job_request', 'Bid on Custom Job Request'),
            'added_additional_charges' => labels('added_additional_charges', 'Added Additional Charges'),
            'new_user_registered' => labels('new_user_registered', 'New User Registered'),
            'new_custom_job_request' => labels('new_custom_job_request', 'New Custom Job Request'),
            'payment_refund_successful' => labels('payment_refund_successful', 'Payment Refund Successful'),
            'subscription_expired' => labels('subscription_expired', 'Subscription Expired'),
            'provider_edits_service_details' => labels('provider_edits_service_details', 'Provider Edits Service Details'),

        ];

        // Return translated label if found, otherwise return the type value itself
        return $typeLabels[$type] ?? $type;
    }

    private function normalizeEmailTemplateParameters($parameters)
    {
        // Step 1: If it's already an array, just return it
        if (is_array($parameters)) {
            return $parameters;
        }

        // Step 2: Trim whitespace and decode HTML entities, just in case the DB stored funky stuff
        $parameters = trim(html_entity_decode($parameters));

        // Step 3: Handle empty or null values
        if (empty($parameters)) {
            return [];
        }

        // Step 4: Clean up escaped backslashes and quotes
        // Convert things like: [\"user_name\",\"provider_name\"] → ["user_name","provider_name"]
        $parameters = preg_replace('/\\\\+"/', '"', $parameters);

        // Step 5: Attempt to decode JSON normally
        $decoded = json_decode($parameters, true);

        // Step 6: If decoding failed, try one more time after cleaning further
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Sometimes the DB might store single quotes or weirdly escaped brackets
            $cleaned = str_replace(["'", '\\\\"'], ['"', '"'], $parameters);
            $decoded = json_decode($cleaned, true);
        }

        // Step 7: If it's still not valid JSON, fall back to manual parsing
        if (!is_array($decoded)) {
            // Attempt to extract values inside brackets manually
            if (preg_match('/\[(.*?)\]/', $parameters, $matches)) {
                $items = explode(',', $matches[1]);
                $decoded = array_map(function ($v) {
                    return trim(str_replace(['"', "'"], '', $v));
                }, $items);
            } else {
                $decoded = [];
            }
        }

        return $decoded;
    }
}
