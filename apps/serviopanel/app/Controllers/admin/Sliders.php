<?php

namespace App\Controllers\admin;

use App\Models\Slider_model;

class Sliders extends Admin
{
    public $sliders, $creator_id;
    protected $superadmin;
    protected $db;
    protected $validation;

    public function __construct()
    {
        parent::__construct();
        $this->sliders = new Slider_model();
        $this->creator_id = $this->userId;
        $this->db = \Config\Database::connect();
        $this->validation = \Config\Services::validation();
        $this->superadmin = $this->session->get('email');
        helper('ResponceServices');
    }
    public function index()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        // Load function helper to ensure get_translated_partner_field is available
        helper('function');
        setPageInfo($this->data, labels('Sliders', 'Sliders') . '  | ' . labels('admin_panel', 'Admin Panel'), 'sliders');
        $this->data['categories_name'] = get_categories_with_translated_names();
        
        // Fetch provider data with translations based on current language
        // This ensures provider names are displayed in the correct language
        $providerData = fetch_details('partner_details', ['is_approved' => 1], ['id', 'partner_id', 'company_name']);
        foreach ($providerData as &$prov) {
            // Get translated company name with proper fallback logic
            // This function handles empty values by falling back to default language
            $translatedCompanyName = $this->getTranslatedProviderName(
                $prov['partner_id'], 
                !empty($prov['company_name']) ? $prov['company_name'] : null
            );
            $prov['company_name'] = $translatedCompanyName;
        }

        $this->data['provider_title'] = $providerData;
        $this->data['services_title'] = $this->db->table('services s')
            ->select('s.id,s.title')
            ->join('users u', 's.user_id = u.id')
            ->where('status', '1')
            ->get()->getResultArray();
        return view('backend/admin/template', $this->data);
    }
    public function add_slider()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!is_permitted($this->creator_id, 'create', 'sliders')) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $type = $this->request->getPost('type');
            $common_rules = [
                'app_image' => ["rules" => 'uploaded[app_image]', "errors" => ["uploaded" => labels(THE_APP_IMAGE_FIELD_IS_REQUIRED, "The app_image field is required"),]],
                'web_image' => ["rules" => 'uploaded[web_image]', "errors" => ["uploaded" => labels(THE_WEB_IMAGE_FIELD_IS_REQUIRED, "The web_image field is required"),]]
            ];
            if ($type == "Category" || $type == "provider" || $type == "url" || $type == "typeurl") {
                $specific_rule = '';
                $specific_error = '';
                $string = "";
                if ($type == "Category") {
                    $specific_rule = 'Category_item';
                    $specific_error = 'category';
                    $string = 'select';
                } elseif ($type == "provider") {
                    $specific_rule = 'service_item';
                    $specific_error = 'provider';
                    $string = 'select';
                } elseif ($type == "url") {
                    $specific_rule = 'url';
                    $specific_error = 'url';
                    $string = 'add';
                }
                $specific_rules = [
                    $specific_rule => ["rules" => 'required', "errors" => ["required" => labels("please", "Please") . " " . $string . " " . $specific_error]]
                ];
            } else {
                $specific_rules = [
                    'type' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_TYPE_OF_SLIDER, "Please select type of slider")]]
                ];
            }
            $validation_rules = array_merge($common_rules, $specific_rules);
            $this->validation->setRules($validation_rules);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors  = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }
            $name = $this->request->getPost('type');
            $url = "";
            if ($name == "Category") {
                $id = $this->request->getPost('Category_item');
            } else if ($name == "provider") {
                $id = $this->request->getPost('service_item');
            } else if ($name == "url") {
                $url = $this->request->getPost('url');
                $id = "000";
            } else {
                $id = "000";
            }
            $paths = [
                'app_image' => ['file' => $this->request->getFile('app_image'), 'path' => 'public/uploads/sliders/', 'error' => labels(FAILED_TO_CREATE_SLIDERS_FOLDERS, "Failed to create sliders folders"), 'folder' => 'sliders'],
                'web_image' => ['file' => $this->request->getFile('web_image'), 'path' => 'public/uploads/sliders/', 'error' => labels(FAILED_TO_CREATE_SLIDERS_FOLDERS, "Failed to create sliders folders"), 'folder' => 'sliders'],
            ];
            $uploadedFiles = [];
            foreach ($paths as $key => $upload) {
                if ($upload['file'] && $upload['file']->isValid()) {
                    $result = upload_file($upload['file'], $upload['path'], $upload['error'], $upload['folder']);
                    if ($result['error'] == false) {
                        $uploadedFiles[$key] = [
                            'url' => $result['file_name'],
                            'disk' => $result['disk']
                        ];
                    } else {
                        return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
            }
            $data['type'] = $name;
            $data['type_id'] = $id;
            $data['app_image'] = $uploadedFiles['app_image']['url'] ??  $this->request->getFile('app_image')->getName();
            $data['web_image'] = $uploadedFiles['web_image']['url'] ??  $this->request->getFile('web_image')->getName();
            $data['status'] = (isset($_POST['slider_switch'])) ? 1 : 0;
            $data['url'] = $url;
            if (!is_dir(FCPATH . 'public/uploads/sliders/')) {
                if (!mkdir(FCPATH . 'public/uploads/sliders/', 0775, true)) {
                    return ErrorResponse(labels(FAILED_TO_CREATE_FOLDERS, "Failed to create folders"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }
            if ($this->sliders->save($data)) {
                return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Sliders.php - add_slider()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

        $where = [];
        if (isset($_GET['slider_filter']) && !empty($_GET['slider_filter'])) {
            $where['status'] = $_GET['slider_filter'];
        }
        print_r($this->sliders->list(false, $search, $limit, $offset, $sort, $order, $where));
    }
    public function update_slider()
    {
        try {
            $disk = fetch_current_file_manager();

            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = is_permitted($this->creator_id, 'update', 'sliders');
            if ($permission) {
                if ($this->isLoggedIn && $this->userIsAdmin) {
                    $type = $this->request->getPost('type_1');
                    $common_rules = [];
                    // we mirror add form validation (without forcing image uploads) so edit behaves consistently
                    if ($type == "Category" || $type == "provider" || $type == "url" || $type == "typeurl") {
                        $specific_rule = '';
                        $specific_error = '';
                        $string = "";
                        if ($type == "Category") {
                            $specific_rule = 'Category_item_1';
                            $specific_error = 'category';
                            $string = 'select';
                        } elseif ($type == "provider") {
                            $specific_rule = 'service_item_1';
                            $specific_error = 'provider';
                            $string = 'select';
                        } elseif ($type == "url") {
                            $specific_rule = 'url';
                            $specific_error = 'url';
                            $string = 'add';
                        }
                        $specific_rules = [
                            $specific_rule => ["rules" => 'required', "errors" => ["required" => labels("please", "Please") . " " . $string . " " . $specific_error]]
                        ];
                    } else {
                        $specific_rules = [
                            'type_1' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_TYPE_OF_SLIDER, "Please select type of slider")]]
                        ];
                    }
                    $validation_rules = array_merge($common_rules, $specific_rules);
                    $this->validation->setRules($validation_rules);
                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors  = $this->validation->getErrors();
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $id = $this->request->getPost('id');
                    $name = $this->request->getPost('type_1');
                    $old_data = fetch_details('sliders', ['id' => $id]);
                    $old_app_image = $old_data[0]['app_image'];
                    $old_web_image = $old_data[0]['web_image'];
                    $url = "";
                    if ($name == "Category") {
                        $type_id = $this->request->getPost('Category_item_1');
                    } else if ($name == "provider") {
                        $type_id = $this->request->getPost('service_item_1');
                    } else if ($name == "url") {
                        $url = $this->request->getPost('url');
                        $type_id = "000";
                    } else {
                        $type_id = "000";
                    }
                    $paths = [
                        'app_image' => ['file' => $this->request->getFile('app_image'), 'path' => 'public/uploads/sliders/', 'old_image' => $old_app_image, 'error' => labels(FAILED_TO_UPLOAD_APP_IMAGE, "Failed to upload app image"), 'folder' => 'sliders', 'disk' => $disk,],
                        'web_image' => ['file' =>  $this->request->getFile('web_image'), 'path' => 'public/uploads/sliders/', 'old_image' => $old_web_image, 'error' => labels(FAILED_TO_UPLOAD_WEB_IMAGE, "Failed to upload web image"), 'folder' => 'sliders', 'disk' => $disk,],
                    ];
                    $uploadedFiles = [];
                    foreach ($paths as $key => $upload) {
                        if ($upload['file']->getName() != "") {
                            delete_file_based_on_server('sliders', $upload['old_image'], $upload['disk']);
                            $result = upload_file($upload['file'], $upload['path'], $upload['error'], $upload['folder']);
                            if ($result['error'] === false) {
                                if ($upload['disk'] == "local_server") {
                                    $upload['old_image'] = "public/uploads/sliders/" . $upload['old_image'];
                                }
                                $uploadedFiles[$key] = [
                                    'url' => $result['file_name'],
                                    'disk' => $result['disk']
                                ];
                            } else {
                                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        } else {
                            $uploadedFiles[$key] = [
                                'url' => $upload['old_image'],
                                'disk' => $upload['disk']
                            ];
                        }
                    }
                    $data['type'] = $name;
                    $data['type_id'] = $type_id;
                    $data['app_image'] =  $uploadedFiles['app_image']['url'] ?? $this->request->getFile('app_image')->getName();
                    $data['web_image'] = $uploadedFiles['web_image']['url'] ?? $this->request->getFile('web_image')->getName();
                    $data['status'] = (isset($_POST['edit_slider_switch'])) ? 1 : 0;
                    $data['url'] = $url;
                    $upd =  $this->sliders->update($id, $data);
                    if ($upd) {
                        return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    return redirect('admin/login');
                }
            } else {
                return NoPermission();
            }
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Sliders.php - update_slider()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function delete_sliders()
    {
        try {
            $disk = fetch_current_file_manager();

            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!is_permitted($this->creator_id, 'delete', 'sliders')) {
                return NoPermission();
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $db = \Config\Database::connect();
            $id = $this->request->getPost('user_id');
            $old_data = fetch_details('sliders', ['id' => $id]);
            if (empty($old_data)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $app_image = "";
            $web_image = "";
            if ($disk === "local_server") {
                $app_image = "public/uploads/sliders/" . $old_data[0]['app_image'];
            } elseif ($disk === "aws_s3") {
                $app_image = $old_data[0]['app_image'];
            }
            if ($disk === "local_server") {
                $web_image = "public/uploads/sliders/" . $old_data[0]['web_image'];
            } elseif ($disk === "aws_s3") {
                $web_image = $old_data[0]['web_image'];
            }
            $builder = $db->table('sliders');
            if ($builder->delete(['id' => $id])) {
                if (!empty($app_image)) {
                    delete_file_based_on_server('sliders', $app_image, $disk);
                }
                if (!empty($web_image)) {
                    delete_file_based_on_server('sliders', $web_image, $disk);
                }
                return successResponse(labels(SUCCESSFULLY_DELETED, "Successfully deleted"), false, [], [], 200, csrf_token(), csrf_hash());
            }
            return ErrorResponse(labels(ERROR_OCCURED, "An error occured"), true, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Sliders.php - delete_sliders()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get providers list with translations based on current language
     * This endpoint is used to dynamically update provider dropdowns when language changes
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response with provider data
     */
    public function get_providers()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 401, csrf_token(), csrf_hash());
            }
            
            // Load function helper to ensure get_translated_partner_field is available
            helper('function');
            
            // Fetch all provider details
            $providerData = fetch_details('partner_details', ['is_approved' => 1], ['id', 'partner_id', 'company_name']);
            
            // Apply translations to each provider based on current language
            // This ensures provider names are displayed correctly in the selected language
            $providers = [];
            foreach ($providerData as $prov) {
                // Get translated company name with proper fallback logic
                // This function handles empty values by falling back to default language
                $translatedCompanyName = $this->getTranslatedProviderName(
                    $prov['partner_id'], 
                    !empty($prov['company_name']) ? $prov['company_name'] : null
                );
                
                $providers[] = [
                    'id' => $prov['id'],
                    'partner_id' => $prov['partner_id'],
                    'company_name' => $translatedCompanyName
                ];
            }
            
            return successResponse(
                labels('providers_fetched_successfully', 'Providers fetched successfully'), 
                false, 
                ['providers' => $providers], 
                [], 
                200, 
                csrf_token(), 
                csrf_hash()
            );
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Sliders.php - get_providers()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Get translated provider company name with proper fallback logic
     * 
     * This method improves upon get_translated_partner_field by checking if values are empty
     * and falling back to default language if the current language translation is empty.
     * 
     * Fallback order:
     * 1. Current language translation (if not empty)
     * 2. Default language translation (if not empty)
     * 3. Original value from partner_details table
     * 
     * @param int $partnerId The partner ID
     * @param string|null $defaultValue The original company name from partner_details table
     * @return string The translated company name or fallback value
     */
    private function getTranslatedProviderName($partnerId, $defaultValue = null)
    {
        // Get current and default language codes
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Validate partner ID
        if (empty($partnerId)) {
            return $defaultValue ?? '';
        }

        // Load translation model
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
        $translations = $translationModel->getAllTranslationsForPartner($partnerId);

        // If no translations exist, return default value
        if (empty($translations)) {
            return $defaultValue ?? '';
        }

        $currentLangValue = null;
        $defaultLangValue = null;

        // Extract values for current and default languages
        foreach ($translations as $item) {
            if ($item['language_code'] === $currentLang && isset($item['company_name'])) {
                $currentLangValue = $item['company_name'];
            }
            if ($item['language_code'] === $defaultLang && isset($item['company_name'])) {
                $defaultLangValue = $item['company_name'];
            }
        }

        // Return current language value if it's not empty
        if (!empty($currentLangValue)) {
            return $currentLangValue;
        }

        // Fall back to default language value if it's not empty
        if (!empty($defaultLangValue)) {
            return $defaultLangValue;
        }

        // Final fallback to original value from partner_details table
        return $defaultValue ?? '';
    }
}
