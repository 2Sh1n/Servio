<?php

namespace App\Controllers\admin;

use App\Models\Service_model;
use App\Models\Language_model;
use App\Models\TranslatedServiceDetails_model;
use Config\ApiResponseAndNotificationStrings;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Seo_model;
use App\Services\ServicesService;
use App\Services\utility\SlugService;
use Exception;

class Services extends Admin
{
    public $validation, $db, $ionAuth, $creator_id, $service, $seoModel;
    protected $superadmin;
    protected ApiResponseAndNotificationStrings $trans;
    protected ServicesService $serviceService;
    protected $defaultLanguage;
    protected $slugService;

    public function __construct()
    {
        parent::__construct();
        $this->service = new Service_model();
        $this->seoModel = new Seo_model();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        $this->trans = new ApiResponseAndNotificationStrings();
        $this->serviceService = new ServicesService();
        $this->defaultLanguage = get_default_language();
        helper('ResponceServices');
        $this->slugService = new SlugService();
    }

    public function index()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'services');
            // $this->data['categories_name'] = fetch_details('categories', [], ['id', 'name', 'parent_id']);
            $this->data['categories_name'] = get_categories_with_translated_names();
            $this->data['categories_tree'] = $this->getCategoriesTree();

            // Get current language for translation
            $currentLanguage = get_current_language();
            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            // Fetch partner data with translated company names based on current language
            $partner_data = $this->db->table('users u')
                ->select('u.id,u.username,pd.company_name,pd.number_of_members,tpd.company_name as translated_company_name')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id AND tpd.language_code = "' . $currentLanguage . '"', 'left')
                ->where('pd.is_approved', '1')
                ->get()->getResultArray();

            // Process partner data to use translated company names when available
            foreach ($partner_data as &$partner) {
                // Use translated company name if available and not empty, otherwise use original
                $partner['display_company_name'] = (!empty($partner['translated_company_name'])) ? $partner['translated_company_name'] : $partner['company_name'];
            }

            $this->data['partner_name'] = $partner_data;
            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    function getCategoriesTree()
    {
        try {
            $categories = $this->db->table('categories')->get()->getResultArray();
            $tree = [];
            foreach ($categories as $category) {
                if (!$category['parent_id']) {
                    $tree[] = $this->buildTree($categories, $category);
                }
            }
            return $tree;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - getCategoriesTree()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    function buildTree(&$categories, $currentCategory)
    {
        try {
            $tree = [
                'id' => $currentCategory['id'],
                'text' => $currentCategory['name'],
            ];
            $children = [];
            foreach ($categories as $category) {
                if ($category['parent_id'] == $currentCategory['id']) {
                    $children[] = $this->buildTree($categories, $category);
                }
            }
            if (!empty($children)) {
                $tree['children'] = $children;
            }
            return $tree;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - buildTree()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $additional_data = [], $column_name = '', $whereIn = [])
    {
        try {
            $Service_model = new Service_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $data = $Service_model->list(false, $search, $limit, $offset, $sort, $order);
            return $data;
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function add_service()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            // Block service creation for users that only have read permission.
            if (!is_permitted($this->creator_id, 'create', 'services')) {
                return NoPermission();
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (isset($_POST) && !empty($_POST)) {
                $price = $this->request->getPost('price');
                // Get the default language from database
                $defaultLanguage = 'en'; // fallback
                $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
                foreach ($languages as $language) {
                    if ($language['is_default'] == 1) {
                        $defaultLanguage = $language['code'];
                        break;
                    }
                }

                // Validate default language fields
                $postData = $this->request->getPost();
                $defaultLanguageErrors = [];

                // Check if data is in new format (as objects)
                if (isset($postData['title']) && is_array($postData['title'])) {
                    // Check title
                    $titleValue = $postData['title'][$defaultLanguage] ?? null;
                    if (empty($titleValue)) {
                        $defaultLanguageErrors[] = labels("service_title_in_default_language_is_required", "Service title is required for default language");
                    }

                    // Check description
                    $descriptionValue = $postData['description'][$defaultLanguage] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels("service_description_in_default_language_is_required", "Service description is required for default language");
                    }

                    // Check long description
                    $longDescriptionValue = $postData['long_description'][$defaultLanguage] ?? null;
                    if (empty($longDescriptionValue)) {
                        $defaultLanguageErrors[] = labels("service_long_description_in_default_language_is_required", "Service long description is required for default language");
                    }

                    // Check tags
                    $tagsValue = $this->processTags($postData['tags'][$defaultLanguage] ?? null);
                    if (empty($tagsValue)) {
                        $defaultLanguageErrors[] = labels("service_tags_in_default_language_are_required", "Service tags are required for default language");
                    }
                } else {
                    // Fallback: Check old format (field[language])
                    $titleField = 'title[' . $defaultLanguage . ']';
                    $descriptionField = 'description[' . $defaultLanguage . ']';
                    $longDescriptionField = 'long_description[' . $defaultLanguage . ']';
                    $tagsField = 'tags[' . $defaultLanguage . ']';

                    // Check title
                    $titleValue = $postData[$titleField] ?? null;
                    if (empty($titleValue)) {
                        $defaultLanguageErrors[] = labels("service_title_in_default_language_is_required", "Service title is required for default language");
                    }

                    // Check description
                    $descriptionValue = $postData[$descriptionField] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels("service_description_in_default_language_is_required", "Service description is required for default language");
                    }

                    // Check long description
                    $longDescriptionValue = $postData[$longDescriptionField] ?? null;
                    if (empty($longDescriptionValue)) {
                        $defaultLanguageErrors[] = labels("service_long_description_in_default_language_is_required", "Service long description is required for default language");
                    }

                    // Check tags
                    $tagsValue = $this->processTags($postData[$tagsField] ?? null);
                    if (empty($tagsValue)) {
                        $defaultLanguageErrors[] = labels("service_tags_in_default_language_are_required", "Service tags are required for default language");
                    }
                }

                if (!empty($defaultLanguageErrors)) {
                    return ErrorResponse($defaultLanguageErrors, true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Check if this is a cloning operation (service_id exists) - needed for validation
                $cloning = false;
                $source_service_id = $this->request->getVar('service_id');
                $original_service = null;

                if ($source_service_id) {
                    $original_service = fetch_details('services', ['id' => $source_service_id], ['image', 'other_images', 'files']);
                    if (!empty($original_service)) {
                        $cloning = true;
                    }
                }

                // Build validation rules
                $validationRules = [
                    'partner' => ["rules" => 'required', "errors" => ["required" => labels("Please select provider", "Please select provider")]],
                    'categories' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_CATEGORY, "Please select category")]],
                    'price' => ["rules" => 'required|numeric', "errors" => ["required" => labels(PLEASE_ENTER_PRICE, "Please enter price"),     "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_PRICE, "Please enter numeric value for price")]],
                    'discounted_price' => ["rules" => 'required|numeric|less_than[' . $price . ']', "errors" => ["required" => labels(PLEASE_ENTER_DISCOUNTED_PRICE, "Please enter discounted price"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DISCOUNTED_PRICE, "Please enter numeric value for discounted price"),    "less_than" => labels(DISCOUNTED_PRICE_SHOULD_BE_LESS_THAN_PRICE, "Discounted price should be less than price")]],
                    'members' => ["rules" => 'required|numeric', "errors" => ["required" => labels(PLEASE_ENTER_REQUIRED_MEMBER_FOR_SERVICE, "Please enter required member for service"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_REQUIRED_MEMBER, "Please enter numeric value for required member")]],
                    'duration' => ["rules" => 'required|numeric', "errors" => ["required" => labels(PLEASE_ENTER_DURATION_TO_PERFORM_TASK, "Please enter duration to perform task"),    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DURATION_OF_TASK, "Please enter numeric value for duration of task")]],
                    'max_qty' => ["rules" => 'required|numeric', "errors" => ["required" => labels(PLEASE_ENTER_MAX_QUANTITY_ALLOWED_FOR_SERVICES, "Please enter max quantity allowed for services"),     "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MAX_QUANTITY_ALLOWED_FOR_SERVICES, "Please enter numeric value for max quantity allowed for services")]],
                    'meta_title' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(META_TITLE_IS_OPTIONAL, "Meta title is optional")]],
                    'meta_description' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(META_DESCRIPTION_IS_OPTIONAL, "Meta description is optional")]],
                    'meta_keywords' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(META_KEYWORDS_ARE_OPTIONAL, "Meta keywords are optional")]],
                    'meta_image' => ["rules" => 'permit_empty|uploaded[meta_image]|is_image[meta_image]', "errors" => ["permit_empty" => labels(META_IMAGE_IS_OPTIONAL, "Meta image is optional"), "uploaded" => labels(INVALID_META_IMAGE, "Invalid meta image"), "is_image" => labels(META_IMAGE_MUST_BE_A_VALID_IMAGE, "Meta image must be a valid image")]],
                    'schema_markup' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(SCHEMA_MARKUP_IS_OPTIONAL, "Schema markup is optional")]],
                    'service_slug' => ["rules" => 'required', "errors" => ["required" => labels(PLEASE_ENTER_SERVICE_SLUG, "Please enter service slug"),]],
                ];

                // Image is required unless cloning (where original image can be used)
                if (!$cloning) {
                    $validationRules['service_image_selector'] = [
                        "rules" => 'uploaded[service_image_selector]|ext_in[service_image_selector,png,jpg,gif,jpeg,webp]|max_size[service_image_selector,8496]|is_image[service_image_selector]',
                        "errors" => [
                            "uploaded" => labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file"),
                            "ext_in" => labels(ONLY_JPEG_JPG_AND_PNG_FILES_ARE_ALLOWED, "Only JPEG, JPG, and PNG files are allowed"),
                            "is_image" => labels(FILE_MUST_BE_A_VALID_IMAGE, "File must be a valid image")
                        ]
                    ];
                } else {
                    // When cloning, image is optional but if provided, must be valid
                    $validationRules['service_image_selector'] = [
                        "rules" => 'permit_empty|uploaded[service_image_selector]|ext_in[service_image_selector,png,jpg,gif,jpeg,webp]|max_size[service_image_selector,8496]|is_image[service_image_selector]',
                        "errors" => [
                            "uploaded" => labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file"),
                            "ext_in" => labels(ONLY_JPEG_JPG_AND_PNG_FILES_ARE_ALLOWED, "Only JPEG, JPG, and PNG files are allowed"),
                            "is_image" => labels(FILE_MUST_BE_A_VALID_IMAGE, "File must be a valid image")
                        ]
                    ];
                }

                $this->validation->setRules($validationRules);
                if (!$this->validation->withRequest($this->request)->run()) {
                    $errors  = $this->validation->getErrors();
                    return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Get default language fields for main table (for slug generation and fallback)
                $defaultTitle = '';
                if (isset($postData['title']) && is_array($postData['title'])) {
                    $defaultTitle = $postData['title'][$defaultLanguage] ?? '';
                } else {
                    $defaultTitle = $postData['title[' . $defaultLanguage . ']'] ?? '';
                }

                $path = "public/uploads/services/";

                if (!is_dir(FCPATH . 'public/uploads/services/')) {
                    if (!mkdir(FCPATH . 'public/uploads/services/', 0775, true)) {
                        return ErrorResponse(labels(FAILED_TO_CREATE_SERVICES_FOLDERS, "Failed to create folders"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                $paths = [
                    'image' => [
                        'file' => $this->request->getFile('service_image_selector'),
                        'path' => 'public/uploads/services/',
                        'error' => labels(FAILED_TO_CREATE_SERVICES_FOLDERS, 'Failed to create services folders'),
                        'folder' => 'services'
                    ],
                ];
                $uploadedFiles = [];

                // Additional check: Ensure image is uploaded when not cloning
                $imageFile = $this->request->getFile('service_image_selector');
                if (!$cloning && (!$imageFile || !$imageFile->isValid() || $imageFile->getSize() == 0)) {
                    return ErrorResponse(labels(PLEASE_UPLOAD_AN_IMAGE_FILE, "Please upload an image file"), true, [], [], 200, csrf_token(), csrf_hash());
                }

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
                $multipleFiles = $this->request->getFiles('filepond');
                $otherImagesConfig = [
                    'path' => 'public/uploads/services/',
                    'error' => labels(FAILED_TO_UPLOAD_OTHER_IMAGES, 'Failed to upload other images'),
                    'folder' => 'services'
                ];
                $uploadedOtherImages = [];
                if (isset($multipleFiles['other_service_image_selector'])) {
                    $files = $multipleFiles['other_service_image_selector'];
                    foreach ($files as $file) {
                        if (!empty($files[0]) && $files[0]->getSize() > 0) {

                            if ($file->isValid()) {
                                $result = upload_file($file, $otherImagesConfig['path'], $otherImagesConfig['error'], $otherImagesConfig['folder']);
                                if ($result['error'] == false) {
                                    if ($result['disk'] == "local_server") {
                                        $uploadedOtherImages[] = 'public/uploads/services/' . $result['file_name'];
                                    } elseif ($result['disk'] == "aws_s3") {
                                        $uploadedOtherImages[] = $result['file_name'];
                                    } else {
                                        $uploadedOtherImages[] = $result['file_name'];
                                    }
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            }
                        } else if ($cloning && empty($uploadedOtherImages) && !empty($original_service[0]['other_images'])) {
                            // If cloning and no new images uploaded, use original images
                            $other_images_data = json_decode($original_service[0]['other_images'], true);
                            if (is_array($other_images_data)) {
                                $uploadedOtherImages = $other_images_data;
                            }
                            break;
                        }
                    }
                } else if ($cloning && !empty($original_service[0]['other_images'])) {
                    // If cloning and no file input, use original images
                    $other_images_data = json_decode($original_service[0]['other_images'], true);
                    if (is_array($other_images_data)) {
                        $uploadedOtherImages = $other_images_data;
                    }
                }

                // Process existing other images that the user wants to keep or remove
                $existing_other_images = $this->request->getPost('existing_other_images');
                $remove_other_images_flags = $this->request->getPost('remove_other_images');

                // If the user has marked images for removal
                if ($cloning && !empty($existing_other_images) && !empty($remove_other_images_flags)) {
                    // Create a clean array for images to keep
                    $filtered_other_images = [];

                    foreach ($existing_other_images as $index => $image_path) {
                        // Skip images marked for removal
                        if (isset($remove_other_images_flags[$index]) && $remove_other_images_flags[$index] === "1") {
                            continue;
                        }

                        // Keep only the path part without base_url
                        $base_url = base_url();
                        if (strpos($image_path, $base_url) === 0) {
                            $image_path = substr($image_path, strlen($base_url));
                        }

                        $filtered_other_images[] = $image_path;
                    }

                    // Update the uploaded other images with only the ones to keep
                    $uploadedOtherImages = $filtered_other_images;
                }

                $other_images = [
                    'other_images' => !empty($uploadedOtherImages) ? json_encode($uploadedOtherImages) : "[]",
                ];
                $uploadedFilesDocuments = [];
                $FilesDocumentsConfig = [
                    'path' => 'public/uploads/services/',
                    'error' => labels('Failed to upload files', 'Failed to upload files'),
                    'folder' => 'services'
                ];
                if (isset($multipleFiles['files'])) {
                    $files = $multipleFiles['files'];

                    if (!empty($files[0]) && $files[0]->getSize() > 0) {
                        foreach ($files as $file) {
                            if ($file->isValid()) {
                                $result = upload_file($file, $FilesDocumentsConfig['path'], $FilesDocumentsConfig['error'], $FilesDocumentsConfig['folder']);
                                if ($result['error'] == false) {
                                    if ($result['disk'] == "local_server") {
                                        $uploadedFilesDocuments[] = 'public/uploads/services/' . $result['file_name'];
                                    } elseif ($result['disk'] == "aws_s3") {
                                        $uploadedFilesDocuments[] = $result['file_name'];
                                    } else {
                                        $uploadedFilesDocuments[] = $result['file_name'];
                                    }
                                } else {
                                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                                }
                            }
                        }
                    } else if ($cloning && empty($uploadedFilesDocuments) && !empty($original_service[0]['files'])) {
                        // If cloning and no new files uploaded, use original files
                        $files_data = json_decode($original_service[0]['files'], true);
                        if (is_array($files_data)) {
                            $uploadedFilesDocuments = $files_data;
                        }
                    }
                } else if ($cloning && !empty($original_service[0]['files'])) {
                    // If cloning and no file input, use original files
                    $files_data = json_decode($original_service[0]['files'], true);
                    if (is_array($files_data)) {
                        $uploadedFilesDocuments = $files_data;
                    }
                }

                // Process existing files that the user wants to keep or remove
                $existing_files = $this->request->getPost('existing_files');
                $remove_files_flags = $this->request->getPost('remove_files');

                // If the user has marked files for removal
                if ($cloning && !empty($existing_files) && !empty($remove_files_flags)) {
                    // Create a clean array for files to keep
                    $filtered_files = [];

                    foreach ($existing_files as $index => $file_path) {
                        // Skip files marked for removal
                        if (isset($remove_files_flags[$index]) && $remove_files_flags[$index] === "1") {
                            continue;
                        }

                        // Keep only the path part without base_url
                        $base_url = base_url();
                        if (strpos($file_path, $base_url) === 0) {
                            $file_path = substr($file_path, strlen($base_url));
                        }

                        $filtered_files[] = $file_path;
                    }

                    // Update the uploaded files with only the ones to keep
                    $uploadedFilesDocuments = $filtered_files;
                }

                $files = !empty($uploadedFilesDocuments) ? json_encode($uploadedFilesDocuments) : "[]";

                $category_id = $this->request->getPost('categories');
                $discounted_price = $this->request->getPost('discounted_price');
                if ($discounted_price >= $price && $discounted_price == $price) {
                    return ErrorResponse("discounted price can not be higher than or equal to the price!", true, [], [], 200, csrf_token(), csrf_hash());
                }
                $user_id = $this->request->getPost('partner');
                $partner_data = fetch_details('partner_details', ['partner_id' => $this->request->getPost('partner')]);
                if ($this->request->getVar('members') > $partner_data[0]['number_of_members']) {
                    return ErrorResponse("Number Of member could not greater than " . $partner_data[0]['number_of_members'], true, [], [], 200, csrf_token(), csrf_hash());
                }
                // FAQs are now handled as translatable fields, so we don't need to process them here
                // The ServiceService will handle FAQ storage in the translated_service_details table
                $check_payment_gateway = get_settings('payment_gateways_settings', true);
                $cod_setting =  $check_payment_gateway['cod_setting'];
                if ($cod_setting == 1) {
                    $is_pay_later_allowed = ($this->request->getPost('pay_later') == "on") ? 1 : 0;
                } else {
                    $is_pay_later_allowed = 0;
                }
                $is_cancelable = (isset($_POST['is_cancelable'])) ? 1 : 0;
                if (isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server') {
                    $image = 'public/uploads/services/' . $uploadedFiles['image']['url'];
                } else {
                    if ($cloning && !empty($original_service[0]['image'])) {
                        $image = $original_service[0]['image'];
                    } else {
                        $image = $uploadedFiles['image']['url'] ?? "";
                    }
                }

                // Get default language field values for main table storage
                $defaultTitle = $titleValue ?? '';
                $defaultDescription = $descriptionValue ?? '';
                $defaultLongDescription = $longDescriptionValue ?? '';
                // Process tags to handle JSON format like [{"value":"Test"}] and convert to comma-separated string
                $defaultTags = $this->processTags($tagsValue) ?? '';

                // Process default language FAQs for main table
                // Use the same decoding logic as partner Services controller to avoid escaped-character issues.
                $defaultFaqs = '';
                if (isset($postData['faqs'])) {
                    if (is_string($postData['faqs'])) {
                        // Form sends FAQs as an escaped JSON string; first unescape HTML entities, then decode JSON.
                        $defaultFaqsData = json_decode(htmlspecialchars_decode($postData['faqs'] ?? ''), true);
                        if (is_array($defaultFaqsData) && !empty($defaultFaqsData)) {
                            $languageFaqs = $defaultFaqsData[$defaultLanguage] ?? [];
                            if (!empty($languageFaqs)) {
                                $defaultFaqs = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                            }
                        }
                    } elseif (is_array($postData['faqs'])) {
                        $defaultFaqsData = $postData['faqs'][$defaultLanguage] ?? [];
                        if (!empty($defaultFaqsData)) {
                            $defaultFaqs = json_encode($defaultFaqsData, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }

                $postedSlug = $this->request->getPost('service_slug');
                $resolvedSlug = $this->slugService->resolve(
                    currentSlug: null,
                    inputSlug: $postedSlug,
                    fallbackName: $defaultTitle,
                    table: 'services'
                );

                // Prepare service data WITH default language translatable fields in main table
                $service = [
                    'user_id' => $user_id,
                    'category_id' => $category_id,
                    'tax_type' => $this->request->getVar('tax_type'),
                    'tax_id' => $this->request->getVar('tax_id'),
                    'price' => $price,
                    'discounted_price' => $discounted_price,
                    'image' => $image,
                    'other_images' => $other_images['other_images'] ?? '',
                    'number_of_members_required' => $this->request->getVar('members'),
                    'duration' => $this->request->getVar('duration'),
                    'rating' => 0,
                    'number_of_ratings' => 0,
                    'on_site_allowed' => ($this->request->getPost('on_site') == "on") ? 1 : 0,
                    'is_pay_later_allowed' => $is_pay_later_allowed,
                    'is_cancelable' => $is_cancelable,
                    'cancelable_till' => $this->request->getVar('cancelable_till'),
                    'max_quantity_allowed' => $this->request->getPost('max_qty'),
                    'status' => (isset($_POST['status'])) ? 1 : 0,
                    'files' => $files,
                    'at_store' => (isset($_POST['at_store'])) ? 1 : 0,
                    'at_doorstep' => (isset($_POST['at_doorstep'])) ? 1 : 0,
                    'approved_by_admin' => isset($_POST['approve_service_value']) ? $_POST['approve_service_value'] : "1",
                    // Use default language title for slug generation
                    'slug' => $resolvedSlug,
                    // Store default language translations in main table as well
                    'title' => $defaultTitle,
                    'description' => $defaultDescription,
                    'long_description' => $defaultLongDescription,
                    'tags' => $defaultTags,
                    'faqs' => $defaultFaqs,
                ];

                if ($this->service->save($service)) {
                    $serviceId = $this->service->insertID(); // Get the inserted service ID

                    // Handle translated fields using ServiceService
                    $postData = $this->request->getPost();

                    // Transform form data to translated_fields structure
                    $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage, null);

                    // Add translated_fields to postData for ServiceService
                    $postData['translated_fields'] = $translatedFields;

                    $translationResult = $this->serviceService->handleServiceCreationWithTranslations($postData, $service, $serviceId, $defaultLanguage);

                    if (!$translationResult['success']) {
                        // If translation saving fails, we should handle this appropriately
                        // For now, we'll log the error but continue with the process
                        log_message('error', 'Failed to save service translations: ' . implode(', ', $translationResult['errors']));
                    }

                    try {
                        $this->saveServiceSeoSettings($serviceId); // Save SEO settings
                    } catch (\Throwable $th) {
                        log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - add_service() - SEO settings');
                        return ErrorResponse(labels(ERROR_OCCURED, "Failed to save SEO settings: " . $th->getMessage()), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $message = $cloning ? labels("service_cloned_successfully", "Service cloned successfully") : labels(DATA_SAVED_SUCCESSFULLY, "Service saved successfully");
                    // Redirect to service list page after successful save with a slight delay
                    return successResponse($message, false, [], ['redirect_url' => base_url('admin/services')], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(ERROR_OCCURED, "Service can not be saved!"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect()->to('partner/services');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - add_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function delete_service()
    {
        try {
            $disk = fetch_current_file_manager();

            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $id = $this->request->getPost('id');
            $old_data = fetch_details('services', ['id' => $id]);
            if ($old_data[0]['image'] != NULL &&  !empty($old_data[0]['image'])) {
                delete_file_based_on_server('services', $old_data[0]['image'], $disk);
            }
            if ($old_data[0]['other_images'] != NULL &&  !empty($old_data[0]['other_images'])) {
                $other_images = json_decode($old_data[0]['other_images'], true);
                foreach ($other_images as $oi) {
                    delete_file_based_on_server('services', $oi, $disk);
                }
            }
            if ($old_data[0]['files'] != NULL &&  !empty($old_data[0]['files'])) {
                $files = json_decode($old_data[0]['files'], true);
                foreach ($files as $oi) {
                    delete_file_based_on_server('services', $oi, $disk);
                }
            }

            // Clean up SEO settings and images before deleting service
            $this->seoModel->cleanupSeoData($id, 'services');

            // Clean up SEO translations before deleting service
            $this->cleanupSeoTranslations($id);

            // Clean up service translations before deleting service
            $this->serviceService->deleteServiceTranslations($id);

            $builder = $this->db->table('services')->delete(['id' => $id]);
            $builder2 = $this->db->table('cart')->delete(['service_id' => $id]);
            $builder3 = $this->db->table('services_ratings')->delete(['service_id' => $id]);
            if ($builder) {
                return successResponse("success in deleting the service", false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse("Unsuccessful in deleting services", true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - delete_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update_service()
    {
        try {

            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            // Stop edit attempts from users without update permission.
            if (!is_permitted($this->creator_id, 'update', 'services')) {
                return NoPermission();
            }
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }

            if ($_POST && !empty($_POST)) {
                $disk = fetch_current_file_manager();

                $price = $this->request->getPost('price');

                // Get the default language from database
                $defaultLanguage = 'en'; // fallback
                $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
                foreach ($languages as $language) {
                    if ($language['is_default'] == 1) {
                        $defaultLanguage = $language['code'];
                        break;
                    }
                }

                // Validate default language fields
                $postData = $this->request->getPost();
                $defaultLanguageErrors = [];

                // Check if data is in new format (as objects)
                if (isset($postData['title']) && is_array($postData['title'])) {
                    // Check title
                    $titleValue = $postData['title'][$defaultLanguage] ?? null;
                    if (empty($titleValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_TITLE_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service title in default language is required!");
                    }

                    // Check description
                    $descriptionValue = $postData['description'][$defaultLanguage] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service description in default language is required!");
                    }

                    // Check long description
                    $longDescriptionValue = $postData['long_description'][$defaultLanguage] ?? null;
                    if (empty($longDescriptionValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_LONG_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service long description in default language is required!");
                    }

                    // Check tags
                    $tagsValue = $postData['tags'][$defaultLanguage] ?? null;
                    if (empty($tagsValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, "Service tags in default language are required!");
                    }
                } else {
                    // Fallback: Check old format (field[language])
                    $titleField = 'title[' . $defaultLanguage . ']';
                    $descriptionField = 'description[' . $defaultLanguage . ']';
                    $longDescriptionField = 'long_description[' . $defaultLanguage . ']';
                    $tagsField = 'tags[' . $defaultLanguage . ']';

                    // Check title
                    $titleValue = $postData[$titleField] ?? null;
                    if (empty($titleValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_TITLE_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service title in default language is required!");
                    }

                    // Check description
                    $descriptionValue = $postData[$descriptionField] ?? null;
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service description in default language is required!");
                    }

                    // Check long description
                    $longDescriptionValue = $postData[$longDescriptionField] ?? null;
                    if (empty($longDescriptionValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_LONG_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service long description in default language is required!");
                    }

                    // Check tags
                    $tagsValue = $postData[$tagsField] ?? null;
                    if (empty($tagsValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, "Service tags in default language are required!");
                    }
                }

                if (!empty($defaultLanguageErrors)) {
                    return ErrorResponse($defaultLanguageErrors, true, [], [], 200, csrf_token(), csrf_hash());
                }

                $rules = [
                    'partner' => ["rules" => 'required', "errors" => ["required" => "Please select provider"]],
                    'categories' => ["rules" => 'required', "errors" => ["required" => "Please select category"]],
                    'price' => ["rules" => 'required|numeric', "errors" => ["required" => "Please enter price",     "numeric" => "Please enter numeric value for price"]],
                    'discounted_price' => ["rules" => 'required|numeric|less_than[' . $price . ']', "errors" => ["required" => "Please enter discounted price",    "numeric" => "Please enter numeric value for discounted price",    "less_than" => "Discounted price should be less than price"]],
                    'members' => ["rules" => 'required|numeric', "errors" => ["required" => "Please enter required member for service",    "numeric" => "Please enter numeric value for required member"]],
                    'duration' => ["rules" => 'required|numeric', "errors" => ["required" => "Please enter duration to perform task",    "numeric" => "Please enter numeric value for duration of task"]],
                    'max_qty' => ["rules" => 'required|numeric', "errors" => ["required" => "Please enter max quantity allowed for services",     "numeric" => "Please enter numeric value for max quantity allowed for services"]],

                ];
                if (isset($_FILES['service_image_selector']) && $_FILES['service_image_selector']['size'] > 0) {
                    $rules['service_image_selector'] = [
                        "rules" => 'uploaded[service_image_selector]|ext_in[service_image_selector,png,jpg,gif,jpeg,webp]|max_size[service_image_selector,8496]|is_image[service_image_selector]'
                    ];
                }
                $this->validation->setRules($rules);
                if (!$this->validation->withRequest($this->request)->run()) {
                    $errors = $this->validation->getErrors();
                    return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                }
                $Service_id = $this->request->getPost('service_id');
                $old_images_and_documents = fetch_details('services', ['id' => $Service_id]);

                // Process default language tags
                $defaultTags = $this->request->getPost('tags[' . $defaultLanguage . ']') ?: $this->request->getPost('tags');
                if (is_array($defaultTags)) {
                    $tags = [];
                    foreach ($defaultTags as $tag) {
                        if (is_string($tag)) {
                            $tags[] = trim($tag);
                        } elseif (is_array($tag) && isset($tag['value'])) {
                            $tags[] = trim($tag['value']);
                        }
                    }
                } else {
                    // Handle string format tags
                    $tags = explode(',', $defaultTags);
                    $tags = array_map('trim', $tags);
                    $tags = array_filter($tags);
                }

                if (empty($tags)) {
                    return ErrorResponse("Service tags in default language are required!", true, [], [], 200, csrf_token(), csrf_hash());
                }
                // FAQs are now handled as translatable fields, so we don't need to process them here
                // The ServiceService will handle FAQ storage in the translated_service_details table
                $paths = [
                    'image' => [
                        'file' => $this->request->getFile('service_image_selector_edit'),
                        'path' => 'public/uploads/services/',
                        'error' => labels('Failed to create services folders', 'Failed to create services folders'),
                        'folder' => 'services',
                        'old_file' => $old_images_and_documents[0]['image'],
                        'disk' => $disk,
                    ],
                ];
                $uploadedFiles = [];
                foreach ($paths as $key => $upload) {
                    if ($upload['file'] && $upload['file']->isValid()) {
                        if (!empty($upload['old_file'])) {
                            delete_file_based_on_server($upload['folder'], $upload['old_file'], $upload['disk']);
                        }
                        $result = upload_file($upload['file'], $upload['path'], $upload['error'], $upload['folder']);
                        if ($result['error'] === false) {
                            $uploadedFiles[$key] = [
                                'url' => $result['file_name'],
                                'disk' => $result['disk']
                            ];
                        } else {
                            return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                }

                if (isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server') {
                    $image_name = isset($uploadedFiles['image']['url']) ? ('public/uploads/services/' . $uploadedFiles['image']['url']) : $old_images_and_documents[0]['image'];
                } else {
                    $image_name = isset($uploadedFiles['image']['url']) ? $uploadedFiles['image']['url'] : $old_images_and_documents[0]['image'];
                }

                // Initialize arrays for images
                $updated_images = [];
                $uploadedOtherImages = [];
                $uploadedFilesDocuments = [];

                // Process existing images and check for removals
                $existing_images = $this->request->getPost('existing_other_images');
                $remove_flags = $this->request->getPost('remove_other_images');

                // Strip base URL from existing image paths to get correct relative paths
                if (!empty($existing_images)) {
                    foreach ($existing_images as $key => $image_path) {
                        // Remove base URL if present
                        $base_url = base_url();
                        if (strpos($image_path, $base_url) === 0) {
                            $existing_images[$key] = substr($image_path, strlen($base_url));
                        }
                    }
                }

                // First handle existing images (if any) and process removals
                if (!empty($existing_images)) {
                    foreach ($existing_images as $index => $image) {
                        // Check if this image is marked for removal
                        if (isset($remove_flags[$index]) && $remove_flags[$index] === "1") {
                            // Delete the image marked for removal
                            delete_file_based_on_server('services', $image, $disk);
                        } else {
                            // Keep images not marked for removal
                            $updated_images[] = $image;
                        }
                    }
                } else if (!empty($old_images_and_documents[0]['other_images']) && !$this->request->getPost('remove_other_images')) {
                    // If no form interaction but old images exist in DB
                    $old_other_images = json_decode($old_images_and_documents[0]['other_images'], true);
                    if (!empty($old_other_images) && is_array($old_other_images)) {
                        $updated_images = $old_other_images;
                    }
                }

                // Now handle new image uploads and add them to the updated_images array
                $multipleFiles = $this->request->getFiles('filepond');
                if (isset($multipleFiles['other_service_image_selector_edit'])) {
                    foreach ($multipleFiles['other_service_image_selector_edit'] as $file) {
                        if ($file->isValid()) {
                            // Upload new image
                            $result = upload_file($file, 'public/uploads/services/', labels('Failed to upload other images', 'Failed to upload other images'), 'services');
                            if ($result['error'] == false) {
                                $new_image = $result['disk'] === "local_server"
                                    ? 'public/uploads/services/' . $result['file_name']
                                    : $result['file_name'];

                                // Add new image to our list
                                $updated_images[] = $new_image;
                            } else {
                                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        }
                    }
                }

                // Set the other_images field with all preserved existing images and new uploads
                $other_images = !empty($updated_images) ? json_encode($updated_images) : "[]";

                // Process existing files and check for removals
                $updated_files = [];
                $existing_files = $this->request->getPost('existing_files');
                $remove_files_flags = $this->request->getPost('remove_files');

                // Strip base URL from existing file paths to get correct relative paths
                if (!empty($existing_files)) {
                    foreach ($existing_files as $key => $file_path) {
                        // Remove base URL if present
                        $base_url = base_url();
                        if (strpos($file_path, $base_url) === 0) {
                            $existing_files[$key] = substr($file_path, strlen($base_url));
                        }
                    }
                }

                // First handle existing files (if any) and process removals
                if (!empty($existing_files)) {
                    foreach ($existing_files as $index => $file) {
                        // Check if this file is marked for removal
                        if (isset($remove_files_flags[$index]) && $remove_files_flags[$index] === "1") {
                            // Delete the file marked for removal
                            delete_file_based_on_server('services', $file, $disk);
                        } else {
                            // Keep files not marked for removal
                            $updated_files[] = $file;
                        }
                    }
                } else if (!empty($old_images_and_documents[0]['files']) && !$this->request->getPost('remove_files')) {
                    // If no form interaction but old files exist in DB
                    $old_files = json_decode($old_images_and_documents[0]['files'], true);
                    if (!empty($old_files) && is_array($old_files)) {
                        $updated_files = $old_files;
                    }
                }

                // Handle Files Upload
                if (isset($multipleFiles['files_edit'])) {
                    foreach ($multipleFiles['files_edit'] as $file) {
                        if ($file->isValid()) {
                            // Upload new file
                            $result = upload_file($file, 'public/uploads/services/', labels('Failed to upload files', 'Failed to upload files'), 'services');
                            if ($result['error'] == false) {
                                $new_file = $result['disk'] === "local_server"
                                    ? 'public/uploads/services/' . $result['file_name']
                                    : $result['file_name'];

                                // Add new file to our list
                                $updated_files[] = $new_file;
                            } else {
                                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        }
                    }
                }

                // Set the files field with all preserved existing files and new uploads
                $files = !empty($updated_files) ? json_encode($updated_files) : "[]";

                $discounted_price = $this->request->getPost('discounted_price');
                if ($discounted_price >= $price) {
                    return ErrorResponse("Discounted price cannot be higher than or equal to the price", true, [], [], 200, csrf_token(), csrf_hash());
                }
                if (isset($_POST['is_cancelable']) && $_POST['is_cancelable'] == 'on') {
                    $is_cancelable = "1";
                } else {
                    $is_cancelable = "0";
                }
                if ($is_cancelable == "1" && $this->request->getVar('cancelable_till') == "") {
                    return ErrorResponse("Please Add Minutes", true, [], [], 200, csrf_token(), csrf_hash());
                }


                $check_payment_gateway = get_settings('payment_gateways_settings', true);
                $cod_setting =  $check_payment_gateway['cod_setting'];
                if ($cod_setting == 1) {
                    $is_pay_later_allowed = ($this->request->getPost('pay_later') == "on") ? 1 : 0;
                } else {
                    $is_pay_later_allowed = 0;
                }

                // Store default language data in main table
                $defaultTitle = $titleValue ?? '';
                $defaultDescription = $descriptionValue ?? '';
                $defaultLongDescription = $longDescriptionValue ?? '';
                $defaultTags = $this->processTags($tagsValue) ?? '';

                // Process default language FAQs for main table
                // Use the same decoding logic as partner Services controller to avoid escaped-character issues.
                $defaultFaqs = '';
                if (isset($postData['faqs'])) {
                    if (is_string($postData['faqs'])) {
                        // Form sends FAQs as an escaped JSON string; first unescape HTML entities, then decode JSON.
                        $defaultFaqsData = json_decode(htmlspecialchars_decode($postData['faqs'] ?? ''), true);
                        if (is_array($defaultFaqsData) && !empty($defaultFaqsData)) {
                            $languageFaqs = $defaultFaqsData[$defaultLanguage] ?? [];
                            if (!empty($languageFaqs)) {
                                $defaultFaqs = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                            }
                        }
                    } elseif (is_array($postData['faqs'])) {
                        $defaultFaqsData = $postData['faqs'][$defaultLanguage] ?? [];
                        if (!empty($defaultFaqsData)) {
                            $defaultFaqs = json_encode($defaultFaqsData, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }

                // Process slug generation/update
                // Only generate/update slug if title changed or slug field is manually entered
                // This prevents auto-generation when editing other fields
                $existingSlug = $existingService['slug'] ?? '';
                $resolvedSlug = $this->slugService->resolve(
                    currentSlug: $existingSlug,
                    inputSlug: trim($this->request->getPost('service_slug') ?? ''),
                    fallbackName: $defaultTitle,
                    table: 'services',
                    excludeId: $Service_id
                );

                if ($this->slugService->isLegacySlug($existingSlug)) {
                    $resolvedSlug = $this->slugService->generate(
                        $defaultTitle,
                        $defaultTitle,
                        'services',
                        $Service_id
                    );
                }

                $service = [
                    'user_id' => $this->request->getPost('partner'),
                    'category_id' => $_POST['categories'],
                    'tax_type' => $this->request->getPost('tax_type'),
                    'tax_id' => $this->request->getPost('tax_id'),
                    'tax' => $this->request->getPost('tax'),
                    'price' => $price,
                    'discounted_price' => $discounted_price,
                    'image' => $image_name,
                    'number_of_members_required' => $this->request->getPost('members'),
                    'duration' => $this->request->getPost('duration'),
                    'max_quantity_allowed' => $this->request->getPost('max_qty'),
                    'status' => ($this->request->getPost('status') === "on") ? 1 : 0,
                    'other_images' => $other_images,
                    'files' => $files,
                    'is_pay_later_allowed' => $is_pay_later_allowed,
                    'status' => ($this->request->getPost('status') == "on") ? 1 : 0,
                    'is_cancelable' => $is_cancelable,
                    'cancelable_till' => ($is_cancelable == "1") ? $this->request->getVar('cancelable_till') : '',
                    'at_store' => ($this->request->getPost('at_store') == "on") ? 1 : 0,
                    'at_doorstep' => ($this->request->getPost('at_doorstep') == "on") ? 1 : 0,
                    'approved_by_admin' => $this->request->getPost('approve_service_value'),
                    // Store default language data in main table
                    'title' => $defaultTitle,
                    'description' => $defaultDescription,
                    'long_description' => $defaultLongDescription,
                    'tags' => $defaultTags,
                    'faqs' => $defaultFaqs,
                ];

                // Only add slug to service array if it was generated/updated
                // This ensures we don't overwrite existing slug when editing other fields
                if ($resolvedSlug !== null) {
                    $service['slug'] = $resolvedSlug;
                }

                // log_message('error', 'FINAL TAGS: ' . print_r($postData['tags'], true));

                if ($this->service->update($Service_id, $service)) {
                    // Handle translated fields using ServiceService
                    $postData = $this->request->getPost();

                    // LOG: Start of translation processing
                    // log_message('debug', '[TRANSLATION_DEBUG] Starting translation processing for service ID: ' . $Service_id);
                    // log_message('debug', '[TRANSLATION_DEBUG] POST data received: ' . json_encode($postData, JSON_UNESCAPED_UNICODE));

                    // Get existing translations for the service
                    $existingTranslationsResult = $this->serviceService->getServiceWithTranslations($Service_id);
                    $existingTranslations = $existingTranslationsResult['translated_data'] ?? [];

                    // LOG: Existing translations
                    // log_message('debug', '[TRANSLATION_DEBUG] Existing translations for service ' . $Service_id . ': ' . json_encode($existingTranslations, JSON_UNESCAPED_UNICODE));

                    // Transform form data to translated_fields structure
                    $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage, $Service_id, $existingTranslations);

                    // LOG: Transformed fields
                    // log_message('debug', '[TRANSLATION_DEBUG] Transformed fields: ' . json_encode($translatedFields, JSON_UNESCAPED_UNICODE));

                    // Add translated_fields to postData for ServiceService
                    $postData['translated_fields'] = $translatedFields;

                    $translationResult = $this->serviceService->handleServiceUpdateWithTranslations($postData, $service, $Service_id, $defaultLanguage);

                    // LOG: Translation result
                    // log_message('debug', '[TRANSLATION_DEBUG] Translation result: ' . json_encode($translationResult, JSON_UNESCAPED_UNICODE));

                    // if (!$translationResult['success']) {
                    //     // If translation saving fails, we should handle this appropriately
                    //     // For now, we'll log the error but continue with the process
                    //     log_message('error', '[TRANSLATION_ERROR] Failed to save service translations: ' . implode(', ', $translationResult['errors']));
                    // } else {
                    //     log_message('debug', '[TRANSLATION_DEBUG] Successfully processed translations for service ' . $Service_id);
                    // }

                    try {
                        $this->saveServiceSeoSettings($Service_id); // Save SEO settings
                    } catch (\Throwable $th) {
                        log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - update_service() - SEO settings');
                        return ErrorResponse("Failed to save SEO settings: " . $th->getMessage(), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    // Redirect to service list page after successful update with a slight delay
                    return successResponse("Service updated successfully", false, [], ['redirect_url' => base_url('admin/services')], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse("Service cannot be saved!", true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect()->to('partner/services');
            }
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - update_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function edit_service()
    {
        try {
            helper('function');
            $uri = service('uri');

            if ($this->isLoggedIn && $this->userIsAdmin) {
                // Editing needs update privilege to stop read-only clones.
                // Use redirect-based permission check for view-rendering methods
                if (!is_permitted($this->creator_id, 'update', 'services')) {
                    $session = \Config\Services::session();
                    if ($session) {
                        $_SESSION['toastMessage'] = labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to take this action');
                        $_SESSION['toastMessageType'] = 'error';
                        $session->markAsFlashdata('toastMessage');
                        $session->markAsFlashdata('toastMessageType');
                    }
                    return redirect()->to(base_url('admin/services'));
                }
                $disk = fetch_current_file_manager();

                $service_id = $uri->getSegments()[3];

                setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'services');
                // $this->data['categories_name'] = fetch_details('categories', [], ['id', 'name', 'parent_id']);
                $this->data['categories_name'] = get_categories_with_translated_names();

                $service = fetch_details('services', ['id' => $service_id])[0];

                // Debug: Log the raw service data from database (uncomment for debugging)
                // log_message('debug', 'Raw service FAQs from database: ' . ($service['faqs'] ?? 'null'));
                if ($disk == 'local_server') {
                    $localPath = base_url($service['image']);
                    if (check_exists($localPath)) {
                        $service['image'] = $localPath;
                    } else {
                        $service['image'] = '';
                    }
                } else if ($disk == "aws_s3") {
                    $service['image'] = fetch_cloud_front_url('services', $service['image']);
                } else {
                    $service['image'] = $service['image'];
                }
                if (!empty($service['other_images'])) {
                    $service['other_images'] = array_map(function ($data) use ($service, $disk) {
                        if ($disk === "local_server") {
                            return base_url($data);
                        } elseif ($disk === "aws_s3") {
                            return fetch_cloud_front_url('services', $data);
                        }
                    }, json_decode($service['other_images'], true));
                } else {
                    $service['other_images'] = [];
                }
                if (!empty($service['files'])) {
                    $service['files'] = array_map(function ($data) use ($service, $disk) {
                        if ($disk === "local_server") {
                            return base_url($data);
                        } elseif ($disk === "aws_s3") {
                            return fetch_cloud_front_url('services', $data);
                        }
                    }, json_decode($service['files'], true));
                } else {
                    $service['files'] = [];
                }

                // Process FAQs data - decode JSON string to array for proper handling in view
                if (!empty($service['faqs'])) {
                    $faqsData = json_decode($service['faqs'], true);
                    if (is_array($faqsData)) {
                        $faqs = [];
                        // Handle both old array format [["question","answer"]] and new object format [{"question":"q","answer":"a"}]
                        if (isset($faqsData[0])) {
                            if (is_array($faqsData[0]) && count($faqsData[0]) >= 2 && !isset($faqsData[0]['question'])) {
                                // Old array format - direct array of pairs [["question","answer"]]
                                foreach ($faqsData as $pair) {
                                    if (is_array($pair) && count($pair) >= 2) {
                                        $faq = [
                                            'question' => $pair[0],
                                            'answer' => $pair[1]
                                        ];
                                        $faqs[] = $faq;
                                    }
                                }
                            } elseif (is_array($faqsData[0]) && isset($faqsData[0]['question']) && isset($faqsData[0]['answer'])) {
                                // New object format - array of objects [{"question":"q","answer":"a"}]
                                $faqs = $faqsData;
                            } else {
                                // Object format - object with numeric keys {"1":["question","answer"]}
                                foreach ($faqsData as $key => $pair) {
                                    if (is_array($pair) && count($pair) >= 2) {
                                        $faq = [
                                            'question' => $pair[0],
                                            'answer' => $pair[1]
                                        ];
                                        $faqs[] = $faq;
                                    }
                                }
                            }
                        }
                        $service['faqs'] = $faqs;
                    } else {
                        $service['faqs'] = [];
                    }
                } else {
                    $service['faqs'] = [];
                }

                // Debug: Log the processed FAQ data (uncomment for debugging)
                // log_message('debug', 'Processed service FAQs: ' . json_encode($service['faqs']));

                $this->data['service'] = $service;

                // Get current language for translation
                $currentLanguage = get_current_language();

                // Fetch partner data with translated company names based on current language
                $partner_data = $this->db->table('users u')
                    ->select('u.id,u.username,pd.company_name,pd.at_store,pd.at_doorstep,pd.need_approval_for_the_service,tpd.company_name as translated_company_name')
                    ->join('partner_details pd', 'pd.partner_id = u.id')
                    ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id AND tpd.language_code = "' . $currentLanguage . '"', 'left')
                    ->where('pd.is_approved', '1')
                    ->get()->getResultArray();

                // Process partner data to use translated company names when available
                foreach ($partner_data as &$partner) {
                    // Use translated company name if available and not empty, otherwise use original
                    if (!empty($partner['translated_company_name'])) {
                        $partner['display_company_name'] = $partner['translated_company_name'];
                    } else {
                        $partner['display_company_name'] = $partner['company_name'];
                    }
                }

                $this->data['partner_name'] = $partner_data;
                // Fetch taxes with translated names based on current language
                $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
                $this->data['tax_data'] = $tax_data;
                $this->data['main_page'] = 'edit_service';

                $this->seoModel->setTableContext('services');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($service_id, 'full');
                $this->data['service_seo_settings'] = $seo_settings;

                // fetch languages
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                // Load translated service details using ServiceService
                $translatedData = $this->serviceService->getServiceWithTranslations($service_id);

                // Process FAQ data with proper fallback logic
                // For each language, try to get FAQs from translations table first, then fall back to main table
                $mergedServiceDetails = $service;

                // Load translated SEO settings for all languages (must happen AFTER $mergedServiceDetails is initialized)
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $translatedSeoSettings = $seoTranslationModel->getAllTranslationsForService($service_id);

                // Add translated SEO data to service data for easy access in view
                foreach ($translatedSeoSettings as $translation) {
                    $languageCode = $translation['language_code'];
                    if (!isset($mergedServiceDetails['translated_seo_' . $languageCode])) {
                        $mergedServiceDetails['translated_seo_' . $languageCode] = [];
                    }
                    $mergedServiceDetails['translated_seo_' . $languageCode] = [
                        'seo_title' => $translation['seo_title'],
                        'seo_description' => $translation['seo_description'],
                        'seo_keywords' => $translation['seo_keywords'],
                        'seo_schema_markup' => $translation['seo_schema_markup']
                    ];
                }

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefaultLanguage = $language['is_default'] == 1;

                    // Initialize FAQ data for this language
                    $languageFaqs = [];

                    // First, try to get FAQs from translations table
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        if (!empty($translation['faqs'])) {
                            // Process translated FAQs data - decode JSON string to array if needed
                            if (is_array($translation['faqs'])) {
                                $languageFaqs = $translation['faqs'];
                            } else {
                                $translatedFaqsData = json_decode($translation['faqs'], true);
                                if (is_array($translatedFaqsData)) {
                                    // Handle both old array format [["question","answer"]] and new object format [{"question":"q","answer":"a"}]
                                    if (isset($translatedFaqsData[0])) {
                                        if (is_array($translatedFaqsData[0]) && count($translatedFaqsData[0]) >= 2 && !isset($translatedFaqsData[0]['question'])) {
                                            // Old array format - direct array of pairs [["question","answer"]]
                                            foreach ($translatedFaqsData as $pair) {
                                                if (is_array($pair) && count($pair) >= 2) {
                                                    $faq = [
                                                        'question' => $pair[0],
                                                        'answer' => $pair[1]
                                                    ];
                                                    $languageFaqs[] = $faq;
                                                }
                                            }
                                        } elseif (is_array($translatedFaqsData[0]) && isset($translatedFaqsData[0]['question']) && isset($translatedFaqsData[0]['answer'])) {
                                            // New object format - array of objects [{"question":"q","answer":"a"}]
                                            $languageFaqs = $translatedFaqsData;
                                        } else {
                                            // Object format - object with numeric keys {"1":["question","answer"]}
                                            foreach ($translatedFaqsData as $key => $pair) {
                                                if (is_array($pair) && count($pair) >= 2) {
                                                    $faq = [
                                                        'question' => $pair[0],
                                                        'answer' => $pair[1]
                                                    ];
                                                    $languageFaqs[] = $faq;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // If no FAQs found in translations table, fall back to main table (for default language)
                    if (empty($languageFaqs) && $isDefaultLanguage) {
                        if (isset($service['faqs']) && is_array($service['faqs']) && !empty($service['faqs'])) {
                            $languageFaqs = $service['faqs'];
                            // log_message('debug', 'Using FAQs from main table for default language: ' . json_encode($languageFaqs));
                        }
                    }

                    // Set FAQs for this language
                    if ($isDefaultLanguage) {
                        // For default language, set directly in main service data
                        $mergedServiceDetails['faqs'] = $languageFaqs;
                        // log_message('debug', 'Set default language FAQs: ' . json_encode($languageFaqs));
                    } else {
                        // For other languages, set in translated data
                        if (!isset($mergedServiceDetails['translated_' . $languageCode])) {
                            $mergedServiceDetails['translated_' . $languageCode] = [];
                        }
                        $mergedServiceDetails['translated_' . $languageCode]['faqs'] = $languageFaqs;
                        // log_message('debug', 'Set translated FAQs for ' . $languageCode . ': ' . json_encode($languageFaqs));
                    }

                    // Set other translated fields with proper fallback logic
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Process other translatable fields with fallback logic
                        $translatedTitle = !empty($translation['title']) ? $translation['title'] : ($isDefaultLanguage ? $service['title'] : '');
                        $translatedDescription = !empty($translation['description']) ? $translation['description'] : ($isDefaultLanguage ? $service['description'] : '');
                        $translatedLongDescription = !empty($translation['long_description']) ? $translation['long_description'] : ($isDefaultLanguage ? $service['long_description'] : '');
                        $translatedTags = !empty($translation['tags']) ? $translation['tags'] : ($isDefaultLanguage ? $service['tags'] : '');

                        if (!$isDefaultLanguage) {
                            $mergedServiceDetails['translated_' . $languageCode] = [
                                'title' => $translatedTitle,
                                'description' => $translatedDescription,
                                'long_description' => $translatedLongDescription,
                                'tags' => $translatedTags,
                                'faqs' => $languageFaqs
                            ];
                        } else {
                            // For default language, update main service data with translated data if available
                            if (!empty($translation['title'])) {
                                $mergedServiceDetails['title'] = $translation['title'];
                            }
                            if (!empty($translation['description'])) {
                                $mergedServiceDetails['description'] = $translation['description'];
                            }
                            if (!empty($translation['long_description'])) {
                                $mergedServiceDetails['long_description'] = $translation['long_description'];
                            }
                            if (!empty($translation['tags'])) {
                                $mergedServiceDetails['tags'] = $translation['tags'];
                            }
                        }
                    }
                }

                $this->data['service'] = $mergedServiceDetails;

                // Final debug: Log the final service data being passed to view (uncomment for debugging)
                // log_message('debug', 'Final service FAQs being passed to view: ' . json_encode($this->data['service']['faqs'] ?? 'not set'));

                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - edit_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function add_service_view()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $permission = is_permitted($this->creator_id, 'create', 'services');
            if (!$permission) {
                return NoPermission();
            }
            setPageInfo($this->data, labels('add_service', 'Add Service') . ' | ' . labels('admin_panel', 'Admin Panel'), 'add_service');
            $partner_details = !empty(fetch_details('partner_details', ['partner_id' => $this->userId])) ? fetch_details('partner_details', ['partner_id' => $this->userId])[0] : [];
            $partner_timings = !empty(fetch_details('partner_timings', ['partner_id' => $this->userId])) ? fetch_details('partner_timings', ['partner_id' => $this->userId]) : [];
            $this->data['data'] = fetch_details('users', ['id' => $this->userId])[0];
            $currency = get_settings('general_settings', true);
            if (empty($currency)) {
                $_SESSION['toastMessage'] = labels('Please first add currency and basic details in general settings', 'Please first add currency and basic details in general settings');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/settings/general-settings')->withCookies();
            }
            $this->data['currency'] = $currency['currency'];
            $this->data['partner_details'] = $partner_details;
            $this->data['partner_timings'] = $partner_timings;
            $this->data['city_name'] = fetch_details('cities', [], ['id', 'name']);
            // $this->data['categories_name'] = fetch_details('categories', [], ['id', 'name', 'parent_id']);
            $this->data['categories_name'] = get_categories_with_translated_names();
            // $this->data['categories_tree'] = $this->getCategoriesTree();


            // Get current language for translation
            $currentLanguage = get_current_language();
            $defaultLanguage = get_default_language();

            // Fetch partner data with translated company names based on current language
            $partner_data = $this->db->table('users u')
                ->select('u.id,u.username,pd.company_name,pd.number_of_members,pd.at_store,pd.at_doorstep,pd.need_approval_for_the_service,tpd.company_name as translated_company_name')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id AND tpd.language_code = "' . $currentLanguage . '"', 'left')
                ->where('pd.is_approved', '1')
                ->get()->getResultArray();

            // Process partner data to use translated company names when available
            foreach ($partner_data as &$partner) {
                // Use translated company name if available and not empty, otherwise use original
                if (!empty($partner['translated_company_name'])) {
                    $partner['display_company_name'] = $partner['translated_company_name'];
                } else {
                    $partner['display_company_name'] = $partner['company_name'];
                }
            }

            $this->data['partner_name'] = $partner_data;
            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;

            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - add_service_view()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function service_detail()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $uri = service('uri');
            $service_id = $uri->getSegments()[3];
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'service_details');
            // $this->data['categories_name'] = fetch_details('categories', [], ['id', 'name']);
            $this->data['categories_name'] = get_categories_with_translated_names();
            $this->data['categories_tree'] = $this->getCategoriesTree();
            $partner_data = $this->db->table('users u')
                ->select('u.id,u.username,pd.company_name,pd.number_of_members')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->where('is_approved', '1')
                ->get()->getResultArray();
            $this->data['partner_name'] = $partner_data;
            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $service = fetch_details('services', ['id' => $service_id]);
            $disk = fetch_current_file_manager();

            if (!empty($service)) {
                if ($disk == 'local_server') {
                    $localPath = !empty($service[0]['image'] && file_exists(FCPATH . 'public/uploads/services/' . basename($service[0]['image']))) ? base_url('/public/uploads/services/' . basename($service[0]['image'])) : base_url('public/backend/assets/default.png');
                    if (check_exists($localPath)) {
                        $service[0]['image'] = $localPath;
                    } else {
                        $service[0]['image'] = '';
                    }
                } else if ($disk == "aws_s3") {
                    $service[0]['image'] = fetch_cloud_front_url('services', $service[0]['image']);
                } else {
                    $service[0]['image'] = $service[0]['image'];
                }
                if (!empty($service[0]['other_images'])) {
                    $decodedOtherImages = json_decode($service[0]['other_images'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $service[0]['other_images'] = array_map(function ($data) use ($service, $disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            }
                            return $data;
                        }, $decodedOtherImages);
                    } else {
                        $service[0]['other_images'] = [];
                    }
                } else {
                    $service[0]['other_images'] = [];
                }
                if (!empty($service[0]['files'])) {
                    $decodedFiles = json_decode($service[0]['files'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $service[0]['files'] = array_map(function ($data) use ($service, $disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            }
                            return $data;
                        }, $decodedFiles);
                    } else {
                        $service[0]['files'] = [];
                    }
                } else {
                    $service[0]['files'] = [];
                }
            } else {
                return redirect('admin/login');
            }
            // Get current language and default language for translations
            $currentLanguage = get_current_language();
            $defaultLanguage = get_default_language();

            // Load translation models
            $translatedPartnerModel = new \App\Models\TranslatedPartnerDetails_model();
            $translatedServiceModel = new \App\Models\TranslatedServiceDetails_model();
            $categoryModel = new \App\Models\Category_model();

            // Get provider's company name with translation fallback
            // Priority: current language → default language → base table
            $providerId = $service[0]['user_id'] ?? null;
            $providerCompanyName = '';
            if (!empty($providerId)) {
                // Try to get partner details from base table first
                $partnerDetails = fetch_details('partner_details', ['partner_id' => $providerId], ['company_name']);
                $baseCompanyName = !empty($partnerDetails) ? ($partnerDetails[0]['company_name'] ?? '') : '';

                // Try current language translation
                $currentTranslation = $translatedPartnerModel->getTranslatedDetails($providerId, $currentLanguage);
                if (!empty($currentTranslation) && !empty($currentTranslation['company_name'])) {
                    $providerCompanyName = $currentTranslation['company_name'];
                } else {
                    // Try default language translation
                    if ($currentLanguage !== $defaultLanguage) {
                        $defaultTranslation = $translatedPartnerModel->getTranslatedDetails($providerId, $defaultLanguage);
                        if (!empty($defaultTranslation) && !empty($defaultTranslation['company_name'])) {
                            $providerCompanyName = $defaultTranslation['company_name'];
                        } else {
                            // Fallback to base table
                            $providerCompanyName = $baseCompanyName;
                        }
                    } else {
                        // Current language is default, use base table
                        $providerCompanyName = $baseCompanyName;
                    }
                }
            }
            // Add translated provider company name to service data
            $service[0]['provider_company_name'] = $providerCompanyName;

            // Get category name with translation fallback
            // Priority: current language → default language → base table
            $categoryId = $service[0]['category_id'] ?? null;
            $categoryName = '';
            if (!empty($categoryId)) {
                // Get category name using the model's method which handles fallback
                $categoryName = $categoryModel->getTranslatedCategoryName($categoryId, $currentLanguage);

                // If empty, try default language
                if (empty($categoryName) && $currentLanguage !== $defaultLanguage) {
                    $categoryName = $categoryModel->getTranslatedCategoryName($categoryId, $defaultLanguage);
                }

                // If still empty, get from base table
                if (empty($categoryName)) {
                    $categoryData = fetch_details('categories', ['id' => $categoryId], ['name']);
                    $categoryName = !empty($categoryData) ? ($categoryData[0]['name'] ?? '') : '';
                }
            }
            // Add translated category name to service data
            $service[0]['category_name'] = $categoryName;

            // Get service translations for title, description, long_description, and FAQs
            // Priority: current language → default language → base table
            $allServiceTranslations = $translatedServiceModel->getAllTranslationsForService($service_id);

            // Organize translations by language code
            $serviceTranslationsByLang = [];
            foreach ($allServiceTranslations as $translation) {
                $serviceTranslationsByLang[$translation['language_code']] = $translation;
            }

            // Get current language translation
            $currentServiceTranslation = $serviceTranslationsByLang[$currentLanguage] ?? null;
            // Get default language translation
            $defaultServiceTranslation = $serviceTranslationsByLang[$defaultLanguage] ?? null;

            // Process title with fallback
            if (!empty($currentServiceTranslation) && !empty($currentServiceTranslation['title'])) {
                $service[0]['title'] = $currentServiceTranslation['title'];
            } elseif (!empty($defaultServiceTranslation) && !empty($defaultServiceTranslation['title'])) {
                $service[0]['title'] = $defaultServiceTranslation['title'];
            }
            // If both translations are empty, keep the base table title (already in $service[0]['title'])

            // Process description with fallback
            if (!empty($currentServiceTranslation) && !empty($currentServiceTranslation['description'])) {
                $service[0]['description'] = $currentServiceTranslation['description'];
            } elseif (!empty($defaultServiceTranslation) && !empty($defaultServiceTranslation['description'])) {
                $service[0]['description'] = $defaultServiceTranslation['description'];
            }
            // If both translations are empty, keep the base table description (already in $service[0]['description'])

            // Process long_description with fallback
            if (!empty($currentServiceTranslation) && !empty($currentServiceTranslation['long_description'])) {
                $service[0]['long_description'] = $currentServiceTranslation['long_description'];
            } elseif (!empty($defaultServiceTranslation) && !empty($defaultServiceTranslation['long_description'])) {
                $service[0]['long_description'] = $defaultServiceTranslation['long_description'];
            }
            // If both translations are empty, keep the base table long_description (already in $service[0]['long_description'])

            // Process FAQs with fallback
            if (!empty($currentServiceTranslation) && !empty($currentServiceTranslation['faqs'])) {
                $service[0]['faqs'] = $currentServiceTranslation['faqs'];
            } elseif (!empty($defaultServiceTranslation) && !empty($defaultServiceTranslation['faqs'])) {
                $service[0]['faqs'] = $defaultServiceTranslation['faqs'];
            }
            // If both translations are empty, keep the base table FAQs (already in $service[0]['faqs'])

            $this->data['service'] = $service;
            $this->data['tax_data'] = $tax_data;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - service_detail()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function disapprove_service()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = is_permitted($this->creator_id, 'update', 'services');
            if ($permission) {
                if ($this->isLoggedIn && $this->userIsAdmin) {
                    $partner_id = $this->request->getPost('partner_id');
                    $service_id = $this->request->getPost('service_id');
                    $builder = $this->db->table('services');
                    $service_approval = $builder->set('approved_by_admin', 0)->where('user_id', $partner_id)->where('id', $service_id)->update();

                    if ($service_approval) {
                        // Send notifications when service is disapproved
                        try {
                            // Fetch service details for template variables
                            $service_details = fetch_details('services', ['id' => $service_id], ['title']);
                            $service_title = !empty($service_details) ? $service_details[0]['title'] : '';

                            // Prepare context data for notification templates
                            // This context will be used to populate template variables like [[service_title]], [[service_id]], [[provider_name]], etc.
                            $notificationContext = [
                                'provider_id' => $partner_id,
                                'user_id' => $partner_id,
                                'service_id' => $service_id,
                                'service_title' => $service_title
                            ];

                            // Queue all notifications (FCM, Email, SMS) using NotificationService
                            // NotificationService automatically handles:
                            // - Translation of templates based on user language
                            // - Variable replacement in templates
                            // - Notification settings checking for each channel
                            // - Fetching user email/phone/FCM tokens
                            // - Unsubscribe status checking for email
                            queue_notification_service(
                                eventType: 'service_disapproved',
                                recipients: ['user_id' => $partner_id],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                    'language' => $this->defaultLanguage,
                                    'platforms' => ['android', 'ios', 'provider_panel'], // Provider platforms for FCM
                                    'type' => 'service_request_status', // Notification type for app routing
                                    'data' => [
                                        'status' => 'reject',
                                        'type_id' => (string)$partner_id,
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                    ]
                                ]
                            );

                            // log_message('info', '[SERVICE_DISAPPROVED] Notification result: ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            // Log error but don't fail the service disapproval
                            log_message('error', '[SERVICE_DISAPPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                        }
                        return successResponse("Service is disapproved", false, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        return successResponse("Could not disapprove service", false, [$service_approval], [], 200, csrf_token(), csrf_hash());
                    }
                } else {
                    return redirect('admin/login');
                }
            } else {
                return NoPermission();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - disapprove_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function approve_service()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            $permission = is_permitted($this->creator_id, 'update', 'services');
            if (!$permission) {
                return NoPermission();
            }
            $partner_id = $this->request->getPost('partner_id');
            $service_id = $this->request->getPost('service_id');
            $builder = $this->db->table('services');
            $service_approval = $builder->set('approved_by_admin', 1)->where('user_id', $partner_id)->where('id', $service_id)->update();

            if ($service_approval) {
                // Send notifications when service is approved
                try {
                    // Fetch service details for template variables
                    $service_details = fetch_details('services', ['id' => $service_id], ['title']);
                    $service_title = !empty($service_details) ? $service_details[0]['title'] : '';


                    // Prepare context data for notification templates
                    // This context will be used to populate template variables like [[service_title]], [[service_id]], [[provider_name]], etc.
                    $notificationContext = [
                        'provider_id' => $partner_id,
                        'user_id' => $partner_id,
                        'service_id' => $service_id,
                        'service_title' => $service_title
                    ];

                    // Send all notifications (FCM, Email, SMS) using NotificationService
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    queue_notification_service(
                        eventType: 'service_approved',
                        recipients: ['user_id' => $partner_id],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                            'language' => $this->defaultLanguage,
                            'platforms' => ['android', 'ios', 'provider_panel'], // Provider platforms for FCM
                            'type' => 'service_request_status', // Notification type for app routing
                            'data' => [
                                'status' => 'approve',
                                'type_id' => (string)$partner_id,
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ]
                        ]
                    );

                    // log_message('info', '[SERVICE_APPROVED] Notification result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the service approval
                    log_message('error', '[SERVICE_APPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                }
                return successResponse("Service is approved", false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return successResponse("Could not Approve service", false, [$service_approval], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - approve_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function duplicate()
    {
        try {
            helper('function');
            $uri = service('uri');
            if ($this->isLoggedIn && $this->userIsAdmin) {
                // Cloning a service requires create permission just like adding a new one.
                // Use redirect-based permission check for view-rendering methods
                if (!is_permitted($this->creator_id, 'create', 'services')) {
                    $session = \Config\Services::session();
                    if ($session) {
                        $_SESSION['toastMessage'] = labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to take this action');
                        $_SESSION['toastMessageType'] = 'error';
                        $session->markAsFlashdata('toastMessage');
                        $session->markAsFlashdata('toastMessageType');
                    }
                    return redirect()->to(base_url('admin/services'));
                }
                $service_id = $uri->getSegments()[3];
                setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'services');
                $this->data['categories_name'] = get_categories_with_translated_names();

                // Fetch the original service data
                $original_service = fetch_details('services', ['id' => $service_id])[0];
                $disk = fetch_current_file_manager();

                // Handle main image
                if (!empty($original_service['image'])) {
                    if ($disk == 'local_server') {
                        $original_service['image'] = base_url($original_service['image']);
                    } else if ($disk == "aws_s3") {
                        $original_service['image'] = fetch_cloud_front_url('services', $original_service['image']);
                    }
                }

                // Handle other images
                if (!empty($original_service['other_images'])) {
                    $other_images = json_decode($original_service['other_images'], true);
                    if (is_array($other_images)) {
                        $original_service['other_images'] = array_map(function ($data) use ($disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            } else {
                                return $data;
                            }
                        }, $other_images);
                    } else {
                        $original_service['other_images'] = [];
                    }
                } else {
                    $original_service['other_images'] = [];
                }

                // Handle files
                if (!empty($original_service['files'])) {
                    $files = json_decode($original_service['files'], true);
                    if (is_array($files)) {
                        $original_service['files'] = array_map(function ($data) use ($disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            } else {
                                return $data;
                            }
                        }, $files);
                    } else {
                        $original_service['files'] = [];
                    }
                } else {
                    $original_service['files'] = [];
                }

                // Process FAQs data - decode JSON string to array for proper handling in view
                if (!empty($original_service['faqs'])) {
                    $faqsData = json_decode($original_service['faqs'], true);
                    if (is_array($faqsData)) {
                        $faqs = [];
                        // Handle both old array format [["question","answer"]] and new object format [{"question":"q","answer":"a"}]
                        if (isset($faqsData[0])) {
                            if (is_array($faqsData[0]) && count($faqsData[0]) >= 2 && !isset($faqsData[0]['question'])) {
                                // Old array format - direct array of pairs [["question","answer"]]
                                foreach ($faqsData as $pair) {
                                    if (is_array($pair) && count($pair) >= 2) {
                                        $faq = [
                                            'question' => $pair[0],
                                            'answer' => $pair[1]
                                        ];
                                        $faqs[] = $faq;
                                    }
                                }
                            } elseif (is_array($faqsData[0]) && isset($faqsData[0]['question']) && isset($faqsData[0]['answer'])) {
                                // New object format - array of objects [{"question":"q","answer":"a"}]
                                $faqs = $faqsData;
                            } else {
                                // Object format - object with numeric keys {"1":["question","answer"]}
                                foreach ($faqsData as $key => $pair) {
                                    if (is_array($pair) && count($pair) >= 2) {
                                        $faq = [
                                            'question' => $pair[0],
                                            'answer' => $pair[1]
                                        ];
                                        $faqs[] = $faq;
                                    }
                                }
                            }
                        }
                        $original_service['faqs'] = $faqs;
                    } else {
                        $original_service['faqs'] = [];
                    }
                } else {
                    $original_service['faqs'] = [];
                }

                $this->data['service'] = $original_service;

                // Get current language for translation
                $currentLanguage = get_current_language();

                // Fetch partner data with translated company names based on current language
                $partner_data = $this->db->table('users u')
                    ->select('u.id,u.username,pd.company_name,pd.at_store,pd.at_doorstep,pd.need_approval_for_the_service,tpd.company_name as translated_company_name')
                    ->join('partner_details pd', 'pd.partner_id = u.id')
                    ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id AND tpd.language_code = "' . $currentLanguage . '"', 'left')
                    ->where('pd.is_approved', '1')
                    ->get()->getResultArray();

                // Process partner data to use translated company names when available
                foreach ($partner_data as &$partner) {
                    // Use translated company name if available and not empty, otherwise use original
                    if (!empty($partner['translated_company_name'])) {
                        $partner['display_company_name'] = $partner['translated_company_name'];
                    } else {
                        $partner['display_company_name'] = $partner['company_name'];
                    }
                }

                $this->data['partner_name'] = $partner_data;
                // Fetch taxes with translated names based on current language
                $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
                $this->data['tax_data'] = $tax_data;
                $this->seoModel->setTableContext('services');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($service_id, 'full');
                $this->data['service_seo_settings'] = $seo_settings;
                $this->data['main_page'] = 'service_clone';

                // fetch languages
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                // Load translated service details using ServiceService
                $translatedData = $this->serviceService->getServiceWithTranslations($service_id);

                // Process FAQ data with proper fallback logic
                // For each language, try to get FAQs from translations table first, then fall back to main table
                $mergedServiceDetails = $original_service;

                // Load translated SEO settings for all languages (must happen AFTER $mergedServiceDetails is initialized)
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $translatedSeoSettings = $seoTranslationModel->getAllTranslationsForService($service_id);

                foreach ($translatedSeoSettings as $translation) {
                    $languageCode = $translation['language_code'];
                    if (!isset($mergedServiceDetails['translated_seo_' . $languageCode])) {
                        $mergedServiceDetails['translated_seo_' . $languageCode] = [];
                    }
                    $mergedServiceDetails['translated_seo_' . $languageCode] = [
                        'seo_title' => $translation['seo_title'],
                        'seo_description' => $translation['seo_description'],
                        'seo_keywords' => $translation['seo_keywords'],
                        'seo_schema_markup' => $translation['seo_schema_markup']
                    ];
                }



                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefaultLanguage = $language['is_default'] == 1;

                    // Initialize FAQ data for this language
                    $languageFaqs = [];

                    // First, try to get FAQs from translations table
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        if (!empty($translation['faqs'])) {
                            // Process translated FAQs data - decode JSON string to array if needed
                            if (is_array($translation['faqs'])) {
                                $languageFaqs = $translation['faqs'];
                            } else {
                                $translatedFaqsData = json_decode($translation['faqs'], true);
                                if (is_array($translatedFaqsData)) {
                                    // Handle both old array format [["question","answer"]] and new object format [{"question":"q","answer":"a"}]
                                    if (isset($translatedFaqsData[0])) {
                                        if (is_array($translatedFaqsData[0]) && count($translatedFaqsData[0]) >= 2 && !isset($translatedFaqsData[0]['question'])) {
                                            // Old array format - direct array of pairs [["question","answer"]]
                                            foreach ($translatedFaqsData as $pair) {
                                                if (is_array($pair) && count($pair) >= 2) {
                                                    $faq = [
                                                        'question' => $pair[0],
                                                        'answer' => $pair[1]
                                                    ];
                                                    $languageFaqs[] = $faq;
                                                }
                                            }
                                        } elseif (is_array($translatedFaqsData[0]) && isset($translatedFaqsData[0]['question']) && isset($translatedFaqsData[0]['answer'])) {
                                            // New object format - array of objects [{"question":"q","answer":"a"}]
                                            $languageFaqs = $translatedFaqsData;
                                        } else {
                                            // Object format - object with numeric keys {"1":["question","answer"]}
                                            foreach ($translatedFaqsData as $key => $pair) {
                                                if (is_array($pair) && count($pair) >= 2) {
                                                    $faq = [
                                                        'question' => $pair[0],
                                                        'answer' => $pair[1]
                                                    ];
                                                    $languageFaqs[] = $faq;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // If no FAQs found in translations table, fall back to main table (for default language)
                    if (empty($languageFaqs) && $isDefaultLanguage) {
                        if (isset($original_service['faqs']) && is_array($original_service['faqs']) && !empty($original_service['faqs'])) {
                            $languageFaqs = $original_service['faqs'];
                        }
                    }

                    // Set FAQs for this language
                    if ($isDefaultLanguage) {
                        // For default language, set directly in main service data
                        $mergedServiceDetails['faqs'] = $languageFaqs;
                    } else {
                        // For other languages, set in translated data
                        if (!isset($mergedServiceDetails['translated_' . $languageCode])) {
                            $mergedServiceDetails['translated_' . $languageCode] = [];
                        }
                        $mergedServiceDetails['translated_' . $languageCode]['faqs'] = $languageFaqs;
                    }

                    // Set other translated fields with proper fallback logic
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Process other translatable fields with fallback logic
                        $translatedTitle = !empty($translation['title']) ? $translation['title'] : ($isDefaultLanguage ? $original_service['title'] : '');
                        $translatedDescription = !empty($translation['description']) ? $translation['description'] : ($isDefaultLanguage ? $original_service['description'] : '');
                        $translatedLongDescription = !empty($translation['long_description']) ? $translation['long_description'] : ($isDefaultLanguage ? $original_service['long_description'] : '');
                        $translatedTags = !empty($translation['tags']) ? $translation['tags'] : ($isDefaultLanguage ? $original_service['tags'] : '');

                        if (!$isDefaultLanguage) {
                            $mergedServiceDetails['translated_' . $languageCode] = [
                                'title' => $translatedTitle,
                                'description' => $translatedDescription,
                                'long_description' => $translatedLongDescription,
                                'tags' => $translatedTags,
                                'faqs' => $languageFaqs
                            ];
                        } else {
                            // For default language, update main service data with translated data if available
                            if (!empty($translation['title'])) {
                                $mergedServiceDetails['title'] = $translation['title'];
                            }
                            if (!empty($translation['description'])) {
                                $mergedServiceDetails['description'] = $translation['description'];
                            }
                            if (!empty($translation['long_description'])) {
                                $mergedServiceDetails['long_description'] = $translation['long_description'];
                            }
                            if (!empty($translation['tags'])) {
                                $mergedServiceDetails['tags'] = $translation['tags'];
                            }
                        }
                    }
                }

                $this->data['service'] = $mergedServiceDetails;

                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - duplicate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function bulk_import_services()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            // Bulk import requires at least create permission (for insert) or update permission (for update)
            // Check if user has either create or update permission
            $hasCreate = is_permitted($this->creator_id, 'create', 'services');
            $hasUpdate = is_permitted($this->creator_id, 'update', 'services');

            if (!$hasCreate && !$hasUpdate) {
                $session = \Config\Services::session();
                if ($session) {
                    $_SESSION['toastMessage'] = labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to use bulk import');
                    $_SESSION['toastMessageType'] = 'error';
                    $session->markAsFlashdata('toastMessage');
                    $session->markAsFlashdata('toastMessageType');
                }
                return redirect()->to(base_url('admin/services'));
            }

            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'bulk_import_services');
            $partner_data = $this->db->table('users u')
                ->select('u.id,u.username,pd.company_name,pd.number_of_members')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->where('is_approved', '1')
                ->get()->getResultArray();
            $this->data['partner_name'] = $partner_data;
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }

    public function bulk_import_service_upload()
    {
        // Check if user is logged in and is admin
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }
        $file = $this->request->getFile('file');
        $filePath = FCPATH . 'public/uploads/service_bulk_upload/';
        if (!is_dir($filePath)) {
            if (!mkdir($filePath, 0775, true)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => labels(FAILED_TO_CREATE_FOLDERS, "Failed to create folders"),
                ]);
            }
        }
        $newName = $file->getRandomName();
        $file->move($filePath, $newName);
        $fullPath = $filePath . $newName;
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = $sheet->getRowIterator(1)->current();
        $cellIterator = $headerRow->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        // Parse multilanguage headers for translatable fields
        // Translatable fields: title, description, long_description, tags, faqs
        $languageHeaders = [];
        $other_image_Headers = [];
        $FilesHeaders = [];
        $columnIndex = 0;
        $OtherImagecolumnIndex = 0;
        $FilescolumnIndex = 0;

        // Get headers correctly - each header is in a separate cell
        $headerRowData = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', NULL, TRUE, FALSE, FALSE);
        $headers = $headerRowData[0]; // First row contains headers

        // Clean up headers - trim whitespace and quotes
        $headers = array_map(function ($header) {
            return trim($header, ' "');
        }, $headers);

        // Ensure headers array has sequential numeric indices (0, 1, 2, ...) to match row array indices
        $headers = array_values($headers);

        // Get available languages from database for validation
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
        $defaultLanguage = 'en'; // fallback
        foreach ($languages as $language) {
            if ($language['is_default'] == 1) {
                $defaultLanguage = $language['code'];
                break;
            }
        }

        if (!in_array('ID', $headers)) {
            //insert - requires create permission
            if (!is_permitted($this->creator_id, 'create', 'services')) {
                return ErrorResponse(
                    labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to create services'),
                    true,
                    [],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            }
            // Parse headers for translatable fields with language codes
            // Format: Title[en], Description[es], faq[en][question][1], etc.
            foreach ($cellIterator as $cell) {
                $header = $cell->getValue();

                // Match multilanguage translatable fields: Title[en], Description[en], etc.
                // Accept headers like "Title[hi]" or "Title [HI]" so minor formatting differences don't drop translations.
                // Accept optional spacing / casing in headers so translations keep working even if CSV columns are renamed slightly.
                if (preg_match('/^(Title|Description|Long Description|Tags)\s*\[([a-z]{2,})\]\s*$/i', $header, $matches)) {
                    $fieldName = strtolower(str_replace(' ', '_', $matches[1]));
                    $langCode = strtolower(trim($matches[2]));
                    if (!isset($languageHeaders[$fieldName])) {
                        $languageHeaders[$fieldName] = [];
                    }
                    $languageHeaders[$fieldName][$langCode] = $columnIndex;
                }
                // Match multilanguage FAQs: faq[en][question][1], faq[es][answer][1]
                // Same relaxed matching for FAQ columns to keep multilingual FAQs intact during bulk uploads.
                // Relaxed FAQ header parsing to avoid silently skipping question / answer pairs for languages like Hindi.
                elseif (preg_match('/^faq\s*\[([a-z]{2,})\]\s*\[(question|answer)\]\s*\[(\d+)\]\s*$/i', $header, $matches)) {
                    $langCode = strtolower(trim($matches[1]));
                    $type = $matches[2];
                    $faqNumber = $matches[3];
                    if (!isset($languageHeaders['faqs'])) {
                        $languageHeaders['faqs'] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode])) {
                        $languageHeaders['faqs'][$langCode] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode][$faqNumber])) {
                        $languageHeaders['faqs'][$langCode][$faqNumber] = [];
                    }
                    $languageHeaders['faqs'][$langCode][$faqNumber][$type] = $columnIndex;
                }
                // Match other images
                elseif (preg_match('/^Other Image\[(\d+)\]$/', $header, $matches)) {
                    $other_image_number = $matches[1];
                    $other_image_Headers[$other_image_number] = $OtherImagecolumnIndex;
                }
                // Match files
                elseif (preg_match('/^Files\[(\d+)\]$/', $header, $matches)) {
                    $fileNumber = $matches[1];
                    $FilesHeaders[$fileNumber] = $FilescolumnIndex;
                }

                $columnIndex++;
                $OtherImagecolumnIndex++;
                $FilescolumnIndex++;
            }
            $data = $sheet->toArray();
            array_shift($data);
            $data = array_filter($data, function ($row) {
                return !empty(array_filter($row));
            });
            // Reindex data array to ensure sequential numeric indices match header indices
            $data = array_values($data);

            // Validate default language has required fields
            $requiredFields = ['title', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($languageHeaders[$field][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('MULTILANGUAGE_MISSING_REQUIRED_FIELD', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers"),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
            }

            $services = [];
            $serviceTranslations = [];
            $serviceSeoTranslations = []; // Store SEO translations for each service

            foreach ($data as $rowIndex => $row) {
                // Ensure row has sequential numeric indices to match header indices
                $row = array_values($row);

                // NEW CSV Structure (after multilanguage support):
                // Index 0: Provider ID
                // Index 1: Category ID
                // Index 2: Duration
                // Index 3: Members Required
                // Index 4: Max Quantity
                // Index 5: Price Type
                // Index 6: Tax ID
                // Index 7: Price
                // Index 8: Discounted Price
                // Index 9: Is Cancelable
                // Index 10: Cancelable before
                // Index 11: Pay Later Allowed
                // Index 12: At Store
                // Index 13: At Doorstep
                // Index 14: Status
                // Index 15: Approve Service
                // Index 16: Image
                // Then: Dynamic language columns...

                // Validate references
                $provider = fetch_details('partner_details', ['partner_id' => $row[0]]);
                if (empty($provider)) {
                    return ErrorResponse(labels('Provider ID', 'Provider ID') . " :: " . $row[0] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $category = fetch_details('categories', ['id' => $row[1]]);
                if (empty($category)) {
                    return ErrorResponse(labels('Category ID', 'Category ID') . " :: " . $row[1] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $tax = fetch_details('taxes', ['id' => $row[6]]); // FIXED: was $row[10], now $row[6]
                if (empty($tax)) {
                    return ErrorResponse(labels('Tax ID', 'Tax ID') . " :: " . $row[6] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Extract translated fields per language
                $translatedFields = $this->extractTranslatedFieldsFromBulkRow($row, $languageHeaders, $defaultLanguage);

                // Extract SEO translations from this row
                $seoTranslations = $this->extractSeoTranslationsFromRow($row, $headers, $languages);

                // Store SEO translations for later saving (after service is created)
                $serviceSeoTranslations[$rowIndex] = $seoTranslations;

                // Validate default language required fields have values
                if (empty($translatedFields['title'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('MULTILANGUAGE_MISSING_TITLE', "Title is required for default language ($defaultLanguage) at row " . ($rowIndex + 2)),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
                if (empty($translatedFields['description'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('MULTILANGUAGE_MISSING_DESCRIPTION', "Description is required for default language ($defaultLanguage) at row " . ($rowIndex + 2)),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }

                // Process other images
                $other_images = [];
                foreach ($other_image_Headers as $indexes) {
                    $other_image = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($other_image)) {
                        copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($other_image)) {
                            $other_images[] = $other_image;
                        }
                    }
                }

                // Process files
                $files = [];
                foreach ($FilesHeaders as $indexes) {
                    $file = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($file)) {
                        copy_image($row[$indexes], '/public/uploads/services/');
                        $files[] = $file;
                    }
                }

                // Process main service image (FIXED: was $row[20], now $row[16])
                $image = !empty($row[16]) ? copy_image($row[16], '/public/uploads/services/') : "";

                // Generate slug from default language title
                // This ensures every service has a unique slug for URL-friendly access
                $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';

                $resolvedSlug = $this->slugService->generate(
                    raw: $defaultTitle,
                    fallback: $defaultTitle,
                    table: 'services'
                );

                // Prepare service data INCLUDING default language values for fallback
                // Store default language translatable fields in main table as fallback
                // These same values will also be stored in translated_service_details table
                $serviceData = [
                    'user_id' => $row[0], // Index 0: Provider ID
                    'category_id' => $row[1], // Index 1: Category ID
                    'title' => $defaultTitle,
                    'description' => $translatedFields['description'][$defaultLanguage] ?? '',
                    'long_description' => $translatedFields['long_description'][$defaultLanguage] ?? '',
                    'tags' => $translatedFields['tags'][$defaultLanguage] ?? '',
                    'faqs' => $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE),
                    // Auto-generate slug from default language title
                    'slug' => $resolvedSlug,
                    // Non-translatable fields (FIXED: all indexes corrected)
                    'duration' => $row[2], // FIXED: was $row[5], now $row[2]
                    'number_of_members_required' => $row[3], // FIXED: was $row[6], now $row[3]
                    'max_quantity_allowed' => $row[4], // FIXED: was $row[7], now $row[4]
                    'tax_type' => $row[5], // FIXED: was $row[9], now $row[5]
                    'tax_id' => $row[6], // FIXED: was $row[10], now $row[6]
                    'price' => $row[7], // FIXED: was $row[11], now $row[7]
                    'discounted_price' => $row[8], // FIXED: was $row[12], now $row[8]
                    'is_cancelable' => $row[9], // FIXED: was $row[13], now $row[9]
                    'cancelable_till' => ($row[9] == 1) ? $row[10] : "", // FIXED: indexes corrected
                    'is_pay_later_allowed' => $row[11], // FIXED: was $row[15], now $row[11]
                    'at_store' => $row[12], // FIXED: was $row[16], now $row[12]
                    'at_doorstep' => $row[13], // FIXED: was $row[17], now $row[13]
                    'status' => $row[14], // FIXED: was $row[18], now $row[14]
                    'approved_by_admin' => ($provider[0]['need_approval_for_the_service'] == "1") ? "1" : "0",
                    'other_images' => json_encode($other_images),
                    'image' => $image,
                    'files' => json_encode($files),
                ];

                $services[] = $serviceData;
                $serviceTranslations[] = $translatedFields;
            }

            // Insert services and their translations
            $serviceModel = new Service_model();
            $db = \Config\Database::connect();
            $db->transStart();

            try {
                foreach ($services as $index => $service) {
                    // Insert service with default language values as fallback
                    if (!$serviceModel->insert($service)) {
                        throw new \Exception("Failed to add service at row " . ($index + 2));
                    }

                    $serviceId = $serviceModel->insertID();

                    // Save ALL language translations (including default) in translated_service_details table
                    // This provides:
                    // 1. Main table: Has default language values for quick access and fallback
                    // 2. Translations table: Has ALL languages including default for consistency

                    // Prepare translation data for ServicesService
                    $translationData = $serviceTranslations[$index];

                    // Convert FAQ format for ServicesService if needed
                    // ServicesService expects FAQs grouped by language: ['en' => [...], 'es' => [...]]
                    // The extractTranslatedFieldsFromBulkRow already creates 'faqs' in language-wise format
                    // If 'faqs' is missing, convert 'faqs_by_number' to language-wise format
                    if (empty($translationData['faqs']) && isset($translationData['faqs_by_number']) && !empty($translationData['faqs_by_number'])) {
                        // Convert faqs_by_number (grouped by FAQ number) to language-wise format
                        // Structure: [1 => ['en' => [...], 'es' => [...]], 2 => ['en' => [...], 'es' => [...]]]
                        // Convert to: ['en' => [...], 'es' => [...]]
                        $faqsByLanguage = [];
                        foreach ($translationData['faqs_by_number'] as $faqNumber => $faqByLanguage) {
                            foreach ($faqByLanguage as $langCode => $faqData) {
                                if (!isset($faqsByLanguage[$langCode])) {
                                    $faqsByLanguage[$langCode] = [];
                                }
                                $faqsByLanguage[$langCode][] = $faqData;
                            }
                        }
                        // Convert arrays to JSON strings to match the expected format
                        foreach ($faqsByLanguage as $langCode => $languageFaqs) {
                            $translationData['faqs'][$langCode] = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                        }
                        unset($translationData['faqs_by_number']);
                    } else if (isset($translationData['faqs_by_number'])) {
                        // Remove faqs_by_number if faqs already exists (faqs is the correct format)
                        unset($translationData['faqs_by_number']);
                    }

                    $translationResult = $this->serviceService->saveTranslatedFields(
                        $serviceId,
                        $translationData
                    );

                    if (!$translationResult['success']) {
                        $errors = implode(', ', $translationResult['errors']);
                        throw new \Exception("Failed to save translations for service ID $serviceId: " . $errors);
                    }

                    // Save SEO settings for this service (at the end, after translations)
                    if (isset($serviceSeoTranslations[$index])) {
                        $seoResult = $this->saveBulkServiceSeoSettings(
                            $serviceId,
                            $serviceSeoTranslations[$index],
                            $languages
                        );

                        if (!$seoResult) {
                            log_message('error', "Row {$index}: Failed to save SEO settings for service {$serviceId}");
                            // Continue with other services even if SEO save fails
                        }
                    }
                }

                $db->transComplete();

                if ($db->transStatus() === false) {
                    throw new \Exception('Transaction failed');
                }

                return successResponse("Services added successfully", false, [], [], 200, csrf_token(), csrf_hash());
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Bulk import service error: ' . $e->getMessage());
                return ErrorResponse($e->getMessage(), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } else {
            //update - requires update permission
            if (!is_permitted($this->creator_id, 'update', 'services')) {
                return ErrorResponse(
                    labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to update services'),
                    true,
                    [],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            }
            // Parse headers for translatable fields with language codes (same as INSERT)
            // Format: Title[en], Description[es], faq[en][question][1], etc.
            foreach ($cellIterator as $cell) {
                $header = $cell->getValue();

                // Match multilanguage translatable fields: Title[en], Description[en], etc.
                if (preg_match('/^(Title|Description|Long Description|Tags)\s*\[([a-z]{2,})\]\s*$/i', $header, $matches)) {
                    $fieldName = strtolower(str_replace(' ', '_', $matches[1]));
                    $langCode = strtolower(trim($matches[2]));
                    if (!isset($languageHeaders[$fieldName])) {
                        $languageHeaders[$fieldName] = [];
                    }
                    $languageHeaders[$fieldName][$langCode] = $columnIndex;
                }
                // Match multilanguage FAQs: faq[en][question][1], faq[es][answer][1]
                elseif (preg_match('/^faq\s*\[([a-z]{2,})\]\s*\[(question|answer)\]\s*\[(\d+)\]\s*$/i', $header, $matches)) {
                    $langCode = strtolower(trim($matches[1]));
                    $type = $matches[2];
                    $faqNumber = $matches[3];
                    if (!isset($languageHeaders['faqs'])) {
                        $languageHeaders['faqs'] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode])) {
                        $languageHeaders['faqs'][$langCode] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode][$faqNumber])) {
                        $languageHeaders['faqs'][$langCode][$faqNumber] = [];
                    }
                    $languageHeaders['faqs'][$langCode][$faqNumber][$type] = $columnIndex;
                }
                // Match other images
                elseif (preg_match('/^Other Image\[(\d+)\]$/', $header, $matches)) {
                    $other_image_number = $matches[1];
                    $other_image_Headers[$other_image_number] = $OtherImagecolumnIndex;
                }
                // Match files
                elseif (preg_match('/^Files\[(\d+)\]$/', $header, $matches)) {
                    $fileNumber = $matches[1];
                    $FilesHeaders[$fileNumber] = $FilescolumnIndex;
                }

                $columnIndex++;
                $OtherImagecolumnIndex++;
                $FilescolumnIndex++;
            }
            $data = $sheet->toArray();
            array_shift($data);
            $data = array_filter($data, function ($row) {
                return !empty(array_filter($row));
            });
            // Reindex data array to ensure sequential numeric indices match header indices
            $data = array_values($data);
            // Validate default language has required fields
            $requiredFields = ['title', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($languageHeaders[$field][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('MULTILANGUAGE_MISSING_REQUIRED_FIELD', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers"),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
            }

            $services = [];
            $serviceTranslations = [];
            $serviceSeoTranslations = []; // Store SEO translations for each service

            foreach ($data as $rowIndex => $row) {
                // Ensure row has sequential numeric indices to match header indices
                $row = array_values($row);

                // NEW CSV Structure for UPDATE (same as INSERT but with ID at index 0):
                // Index 0: ID (SERVICE ID - required for update)
                // Index 1: Provider ID
                // Index 2: Category ID
                // Index 3: Duration
                // Index 4: Members Required
                // Index 5: Max Quantity
                // Index 6: Price Type
                // Index 7: Tax ID
                // Index 8: Price
                // Index 9: Discounted Price
                // Index 10: Is Cancelable
                // Index 11: Cancelable before
                // Index 12: Pay Later Allowed
                // Index 13: At Store
                // Index 14: At Doorstep
                // Index 15: Status
                // Index 16: Approve Service
                // Index 17: Image
                // Then: Dynamic language columns...

                $fetch_service_data = fetch_details('services', ['id' => $row[0]], ['image', 'other_images', 'files']);
                if (empty($fetch_service_data)) {
                    return ErrorResponse(labels('Service ID', 'Service ID') . " :: " . $row[0] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }

                $old_other_images = [];
                if (!empty($fetch_service_data)) {
                    $other_images = $fetch_service_data[0]['other_images'];
                    $old_other_images = is_string($other_images) ? json_decode($other_images, true) : $other_images;
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $old_other_images = [];
                    }
                }

                $old_files = [];
                if (!empty($fetch_service_data)) {
                    $old_files = $fetch_service_data[0]['files'];
                    $old_files = is_string($old_files) ? json_decode($old_files, true) : $old_files;
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $old_files = [];
                    }
                }

                // Validate references - UPDATED INDEXES
                $provider = fetch_details('partner_details', ['partner_id' => $row[1]]);
                if (empty($provider)) {
                    return ErrorResponse(labels('Provider ID', 'Provider ID') . " :: " . $row[1] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $category = fetch_details('categories', ['id' => $row[2]]);
                if (empty($category)) {
                    return ErrorResponse(labels('Category ID', 'Category ID') . " :: " . $row[2] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $tax = fetch_details('taxes', ['id' => $row[7]]); // FIXED: was $row[11], now $row[7]
                if (empty($tax)) {
                    return ErrorResponse(labels('Tax ID', 'Tax ID') . " :: " . $row[7] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Extract translated fields per language
                $translatedFields = $this->extractTranslatedFieldsFromBulkRow($row, $languageHeaders, $defaultLanguage);

                // Extract SEO translations from this row
                $seoTranslations = $this->extractSeoTranslationsFromRow($row, $headers, $languages);

                // Store SEO translations for later saving (after service is updated)
                $serviceSeoTranslations[$rowIndex] = $seoTranslations;

                // Validate default language required fields have values
                if (empty($translatedFields['title'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('MULTILANGUAGE_MISSING_TITLE', "Title is required for default language ($defaultLanguage) at row " . ($rowIndex + 2)),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
                if (empty($translatedFields['description'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('MULTILANGUAGE_MISSING_DESCRIPTION', "Description is required for default language ($defaultLanguage) at row " . ($rowIndex + 2)),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
                // Process other images
                $other_images = [];
                foreach ($other_image_Headers as $indexes) {
                    $other_image = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($other_image) && !in_array($other_image, $old_other_images)) {
                        $oi = copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($oi)) {
                            $other_images[] = $oi;
                        }
                    }
                }
                // If no new images, keep old ones
                if (empty($other_images) && !empty($old_other_images)) {
                    $other_images = $old_other_images;
                }

                // Process files
                $files = [];
                foreach ($FilesHeaders as $indexes) {
                    $file = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($file) && !in_array($file, $old_files)) {
                        $uploadedFile = copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($uploadedFile)) {
                            $files[] = $uploadedFile;
                        }
                    }
                }
                // If no new files, keep old ones
                if (empty($files) && !empty($old_files)) {
                    $files = $old_files;
                }

                // Process main service image (FIXED: was $row[21], now $row[17])
                $image = !empty($row[17]) ? copy_image($row[17], '/public/uploads/services/') : "";
                if (empty($image) && !empty($fetch_service_data)) {
                    $image = $fetch_service_data[0]['image'];
                }

                // Generate slug from default language title
                // Exclude current service ID to ensure uniqueness when updating
                // This ensures every service has a unique slug for URL-friendly access
                $serviceId = $row[0];
                $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';
                $existingSlugRow = fetch_details(
                    'services',
                    ['id' => $serviceId],
                    ['slug']
                );

                $currentSlug = $existingSlugRow[0]['slug'] ?? null;

                // Determine base name (default language)
                $baseName = !empty($translatedFields['title'][$defaultLanguage])
                    ? $translatedFields['title'][$defaultLanguage]
                    : $defaultTitle;

                // Resolve final slug
                $slug = $this->slugService->resolve(
                    $currentSlug,     // existing slug
                    null,             // no manual input from CSV
                    $baseName,        // fallback name
                    'services',
                    $serviceId        // exclude current row
                );
                if ($this->slugService->isLegacySlug($currentSlug)) {
                    $slug = $this->slugService->generate(
                        $baseName,
                        $baseName,
                        'services',
                        $serviceId
                    );
                }

                // Prepare service data INCLUDING default language values for fallback
                // Store default language translatable fields in main table as fallback
                // These same values will also be stored in translated_service_details table
                $serviceData = [
                    'id' => $serviceId, // Service ID
                    'user_id' => $row[1], // Index 1: Provider ID
                    'category_id' => $row[2], // Index 2: Category ID
                    // ✅ Include default language translatable fields as fallback
                    'title' => $defaultTitle,
                    'description' => $translatedFields['description'][$defaultLanguage] ?? '',
                    'long_description' => $translatedFields['long_description'][$defaultLanguage] ?? '',
                    'tags' => $translatedFields['tags'][$defaultLanguage] ?? '',
                    'faqs' => $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE),
                    // Auto-generate slug from default language title
                    'slug' => $slug,
                    // Non-translatable fields (FIXED: all indexes corrected)
                    'duration' => $row[3], // FIXED: was $row[6], now $row[3]
                    'number_of_members_required' => $row[4], // FIXED: was $row[7], now $row[4]
                    'max_quantity_allowed' => $row[5], // FIXED: was $row[8], now $row[5]
                    'tax_type' => $row[6], // FIXED: was $row[10], now $row[6]
                    'tax_id' => $row[7], // FIXED: was $row[11], now $row[7]
                    'price' => $row[8], // FIXED: was $row[12], now $row[8]
                    'discounted_price' => $row[9], // FIXED: was $row[13], now $row[9]
                    'is_cancelable' => $row[10], // FIXED: was $row[14], now $row[10]
                    'cancelable_till' => ($row[10] == 1) ? $row[11] : "", // FIXED: indexes corrected
                    'is_pay_later_allowed' => $row[12], // FIXED: was $row[16], now $row[12]
                    'at_store' => $row[13], // FIXED: was $row[17], now $row[13]
                    'at_doorstep' => $row[14], // FIXED: was $row[18], now $row[14]
                    'status' => $row[15], // FIXED: was $row[19], now $row[15]
                    'approved_by_admin' => ($provider[0]['need_approval_for_the_service'] == "1") ? "0" : "1",
                    'other_images' => json_encode($other_images),
                    'image' => $image,
                    'files' => json_encode($files),
                ];

                $services[] = $serviceData;
                $serviceTranslations[] = $translatedFields;
            }

            // Update services and their translations
            $serviceModel = new Service_model();
            $db = \Config\Database::connect();
            $db->transStart();

            try {
                foreach ($services as $index => $service) {
                    $serviceId = $service['id'];
                    unset($service['id']);

                    // Update service with default language values as fallback
                    if (!$serviceModel->update($serviceId, $service)) {
                        throw new \Exception("Failed to update service ID " . $serviceId . " at row " . ($index + 2));
                    }

                    // Save ALL language translations (including default) in translated_service_details table

                    // Prepare translation data for ServicesService
                    $translationData = $serviceTranslations[$index];

                    // Convert FAQ format for ServicesService if needed
                    // ServicesService expects FAQs grouped by language: ['en' => [...], 'es' => [...]]
                    // The extractTranslatedFieldsFromBulkRow already creates 'faqs' in language-wise format
                    // If 'faqs' is missing, convert 'faqs_by_number' to language-wise format
                    if (empty($translationData['faqs']) && isset($translationData['faqs_by_number']) && !empty($translationData['faqs_by_number'])) {
                        // Convert faqs_by_number (grouped by FAQ number) to language-wise format
                        // Structure: [1 => ['en' => [...], 'es' => [...]], 2 => ['en' => [...], 'es' => [...]]]
                        // Convert to: ['en' => [...], 'es' => [...]]
                        $faqsByLanguage = [];
                        foreach ($translationData['faqs_by_number'] as $faqNumber => $faqByLanguage) {
                            foreach ($faqByLanguage as $langCode => $faqData) {
                                if (!isset($faqsByLanguage[$langCode])) {
                                    $faqsByLanguage[$langCode] = [];
                                }
                                $faqsByLanguage[$langCode][] = $faqData;
                            }
                        }
                        // Convert arrays to JSON strings to match the expected format
                        foreach ($faqsByLanguage as $langCode => $languageFaqs) {
                            $translationData['faqs'][$langCode] = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                        }
                        unset($translationData['faqs_by_number']);
                    } else if (isset($translationData['faqs_by_number'])) {
                        // Remove faqs_by_number if faqs already exists (faqs is the correct format)
                        unset($translationData['faqs_by_number']);
                    }

                    $translationResult = $this->serviceService->saveTranslatedFields(
                        $serviceId,
                        $translationData
                    );

                    if (!$translationResult['success']) {
                        $errors = implode(', ', $translationResult['errors']);
                        throw new \Exception("Failed to save translations for service ID $serviceId: " . $errors);
                    }

                    // Save SEO settings for this service (at the end, after translations)
                    if (isset($serviceSeoTranslations[$index])) {
                        $seoResult = $this->saveBulkServiceSeoSettings(
                            $serviceId,
                            $serviceSeoTranslations[$index],
                            $languages
                        );

                        if (!$seoResult) {
                            log_message('error', "Row {$index}: Failed to save SEO settings for service {$serviceId}");
                            // Continue with other services even if SEO save fails
                        }
                    }
                }

                $db->transComplete();

                if ($db->transStatus() === false) {
                    throw new \Exception('Transaction failed');
                }

                return successResponse("Services updated successfully", false, [], [], 200, csrf_token(), csrf_hash());
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Bulk update service error: ' . $e->getMessage());
                return ErrorResponse($e->getMessage(), true, [], [], 200, csrf_token(), csrf_hash());
            }
        }
    }

    public function downloadSampleForInsert()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            $_SESSION['toastMessage'] = $result['message'];
            $_SESSION['toastMessageType'] = 'error';
            $this->session->markAsFlashdata('toastMessage');
            $this->session->markAsFlashdata('toastMessageType');
            return redirect()->to('admin/services/bulk_import_services')->withCookies();
        }
        try {
            // Get available languages from database
            $languages = fetch_details('languages', [], ['code', 'language', 'is_default'], "", '0', 'id', 'ASC');

            // Build headers with non-translatable fields first
            $headers = [
                'Provider ID',
                'Category ID',
                'Duration to perform task',
                'Members Required to Perform Task',
                'Max Quantity allowed for services',
                'Price Type',
                'Tax ID',
                'Price',
                'Discounted Price',
                'Is Cancelable',
                'Cancelable before',
                'Pay Later Allowed',
                'At Store',
                'At Doorstep',
                'Status',
                'Approve Service',
                'Image',
            ];

            // Add translatable fields for each language
            // Format: Title[en], Title[es], Description[en], Description[es], etc.
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $langName = $language['language'];

                // Add headers for each translatable field per language
                $headers[] = "Title[$langCode]"; // translatable
                $headers[] = "Description[$langCode]"; // translatable (short description)
                $headers[] = "Long Description[$langCode]"; // translatable
                $headers[] = "Tags[$langCode]"; // translatable

                // Add FAQ headers for this language (2 FAQs as example)
                $headers[] = "faq[$langCode][question][1]";
                $headers[] = "faq[$langCode][answer][1]";
                $headers[] = "faq[$langCode][question][2]";
                $headers[] = "faq[$langCode][answer][2]";
            }

            // Add non-translatable fields at the end
            $headers[] = 'Other Image[1]';
            $headers[] = 'Other Image[2]';
            $headers[] = 'Files[1]';
            $headers[] = 'Files[2]';

            // Add SEO language headers at the very end, after all other columns
            // Format: "SEO Title (en)", "SEO Description (en)", etc.
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "SEO Title ($langCode)";
                $headers[] = "SEO Description ($langCode)";
                $headers[] = "SEO Keywords ($langCode)";
                $headers[] = "SEO Schema Markup ($langCode)";
            }

            // Build sample data row
            $sampleRow = [
                '1', // Provider ID
                '2', // Category ID
                '60', // Duration to perform task
                '1', // Members Required to Perform Task
                '5', // Max Quantity allowed for services
                'included', // Price Type
                '1', // Tax ID
                '100', // Price
                '80', // Discounted Price
                '1', // Is Cancelable
                '24', // Cancelable before (hours)
                '1', // Pay Later Allowed
                '1', // At Store
                '1', // At Doorstep
                '1', // Status (1=active)
                '1', // Approve Service
                'public/uploads/services/sample.jpg', // Image
            ];

            // Add sample translatable fields for each language
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $langName = $language['language'];

                // Sample data varies by language for demonstration
                if ($langCode === 'en') {
                    $sampleRow[] = 'House Cleaning Service'; // Title[en]
                    $sampleRow[] = 'Professional house cleaning service'; // Description[en]
                    $sampleRow[] = 'We provide thorough cleaning of your home including all rooms, kitchen, and bathrooms'; // Long Description[en]
                    $sampleRow[] = 'cleaning,house,professional'; // Tags[en]
                    $sampleRow[] = 'What areas do you clean?'; // faq[en][question][1]
                    $sampleRow[] = 'We clean all rooms, kitchen, bathrooms, and common areas'; // faq[en][answer][1]
                    $sampleRow[] = 'How long does it take?'; // faq[en][question][2]
                    $sampleRow[] = 'Typically 2-3 hours depending on home size'; // faq[en][answer][2]
                } else {
                    // For other languages, leave empty (optional)
                    $sampleRow[] = 'Other language title'; // Title
                    $sampleRow[] = 'Other language description'; // Description
                    $sampleRow[] = 'Other language long description'; // Long Description
                    $sampleRow[] = 'Other language tags'; // Tags
                    $sampleRow[] = 'Other language faq[question][1]'; // faq[question][1]
                    $sampleRow[] = 'Other language faq[answer][1]'; // faq[answer][1]
                    $sampleRow[] = 'Other language faq[question][2]'; // faq[question][2]
                    $sampleRow[] = 'Other language faq[answer][2]'; // faq[answer][2]
                }
            }

            // Add sample other images and files
            $sampleRow[] = 'public/uploads/services/image1.jpg'; // Other Image[1]
            $sampleRow[] = 'public/uploads/services/image2.jpg'; // Other Image[2]
            $sampleRow[] = 'public/uploads/services/document1.pdf'; // Files[1]
            $sampleRow[] = 'public/uploads/services/document2.pdf'; // Files[2]

            // Add SEO sample data for each language at the end
            // IMPORTANT: Order must match headers - group by language (Title, Description, Keywords, Schema per language)
            foreach ($languages as $language) {
                $langCode = $language['code'];
                // Sample SEO Title for this language
                $sampleRow[] = 'Sample SEO Title (' . $langCode . ')';
                // Sample SEO Description for this language
                $sampleRow[] = 'Sample SEO Description (' . $langCode . ')';
                // Sample SEO Keywords for this language
                $sampleRow[] = 'keyword1, keyword2, keyword3 (' . $langCode . ')';
                // Sample SEO Schema Markup for this language
                $sampleRow[] = '{"@type":"Service"} (' . $langCode . ')';
            }

            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \Exception('Failed to open output stream.');
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="service_sample_without_data_multilanguage.csv"');
            fputcsv($output, $headers);
            fputcsv($output, $sampleRow); // Add sample data row
            fclose($output);
            exit;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - download-sample-for-insert()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function downloadSampleForUpdate()
    {
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            $response = [
                'type' => 'error',
                'message' => $result['message']
            ];
            return $this->response->setJSON($response);
        }
        try {
            // Get active languages for multilanguage support
            $languageModel = new Language_model();
            $languages = $languageModel->findAll();

            // Build headers - simple string array
            $headers = [];
            $headers[] = 'ID';
            $headers[] = 'Provider ID';
            $headers[] = 'Category ID';
            $headers[] = 'Duration to perform task';
            $headers[] = 'Members Required to Perform Task';
            $headers[] = 'Max Quantity allowed for services';
            $headers[] = 'Price Type';
            $headers[] = 'Tax ID';
            $headers[] = 'Price';
            $headers[] = 'Discounted Price';
            $headers[] = 'Is Cancelable';
            $headers[] = 'Cancelable before';
            $headers[] = 'Pay Later Allowed';
            $headers[] = 'At Store';
            $headers[] = 'At Doorstep';
            $headers[] = 'Status';
            $headers[] = 'Approve Service';
            $headers[] = 'Image';

            // Add language-specific headers
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "Title[$langCode]";
                $headers[] = "Description[$langCode]";
                $headers[] = "Long Description[$langCode]";
                $headers[] = "Tags[$langCode]";
                $headers[] = "faq[$langCode][question][1]";
                $headers[] = "faq[$langCode][answer][1]";
                $headers[] = "faq[$langCode][question][2]";
                $headers[] = "faq[$langCode][answer][2]";
            }

            $headers[] = 'Other Image[1]';
            $headers[] = 'Other Image[2]';
            $headers[] = 'Files[1]';
            $headers[] = 'Files[2]';

            // Add SEO language headers at the very end, after all other columns
            // Format: "SEO Title (en)", "SEO Description (en)", etc.
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "SEO Title ($langCode)";
                $headers[] = "SEO Description ($langCode)";
                $headers[] = "SEO Keywords ($langCode)";
                $headers[] = "SEO Schema Markup ($langCode)";
            }

            // Fetch services
            $partners = $this->request->getPost('partners');
            // fetch_details() -> whereIn() expects an array.
            // Normalize partner IDs from POST (single value, CSV string, or array).
            if (is_string($partners)) {
                $partners = array_filter(array_map('trim', explode(',', $partners)), static function ($value) {
                    return $value !== '';
                });
            } elseif (!empty($partners) && !is_array($partners)) {
                $partners = [$partners];
            }
            // log_message('debug', 'PARTNERS WHOSE SERVICES ARE BEING UPDATED: ' . var_export($partners, true));
            if (!empty($partners)) {
                $services = fetch_details('services', [], [], "", 0, 'id', 'DESC', 'user_id', $partners);
            } else {
                $services = fetch_details('services');
            }

            // log_message('debug', 'SERVICES DATA TO BE UPDATED: ' . var_export($services, true));

            // Prepare data
            $all_data = [];
            $translationModel = new TranslatedServiceDetails_model();

            foreach ($services as $service) {
                $row = [];

                // log_message('debug', '=== Processing Service ID: ' . ($service['id'] ?? 'UNKNOWN') . ' ===');

                // Add basic service fields - force to string immediately
                $row[] = strval($service['id'] ?? '');
                $row[] = strval($service['user_id'] ?? '');
                $row[] = strval($service['category_id'] ?? '');
                $row[] = strval($service['duration'] ?? '');
                $row[] = strval($service['number_of_members_required'] ?? '');
                $row[] = strval($service['max_quantity_allowed'] ?? '');
                $row[] = strval($service['tax_type'] ?? '');
                $row[] = strval($service['tax_id'] ?? '');
                $row[] = strval($service['price'] ?? '');
                $row[] = strval($service['discounted_price'] ?? '');
                $row[] = strval($service['is_cancelable'] ?? '');
                $row[] = strval($service['cancelable_till'] ?? '');
                $row[] = strval($service['is_pay_later_allowed'] ?? '');
                $row[] = strval($service['at_store'] ?? '');
                $row[] = strval($service['at_doorstep'] ?? '');
                $row[] = strval($service['status'] ?? '');
                $row[] = strval($service['approved_by_admin'] ?? '');
                $row[] = strval($service['image'] ?? '');

                // log_message('debug', 'Basic fields added for service ID: ' . $service['id'] . '. Row count: ' . count($row));

                // Get translations
                $translations = $translationModel->where('service_id', $service['id'])->findAll();
                // log_message('debug', 'Found ' . count($translations) . ' translations for service ID: ' . $service['id']);

                $translationsByLang = [];
                foreach ($translations as $trans) {
                    $translationsByLang[$trans['language_code']] = $trans;
                    // log_message('debug', 'Translation for language: ' . $trans['language_code'] . ' for service ID: ' . $service['id']);
                }

                // Find default language code for fallback
                $defaultLangCode = null;
                foreach ($languages as $lang) {
                    if (isset($lang['is_default']) && $lang['is_default'] == 1) {
                        $defaultLangCode = $lang['code'];
                        break;
                    }
                }

                // Add language-specific data
                foreach ($languages as $language) {
                    $langCode = $language['code'];
                    $isDefault = isset($language['is_default']) && $language['is_default'] == 1;
                    // log_message('debug', 'Processing language: ' . $langCode . ' (is_default: ' . ($isDefault ? 'yes' : 'no') . ')');

                    if (isset($translationsByLang[$langCode])) {
                        // Translation exists for this language - use it
                        $trans = $translationsByLang[$langCode];

                        // log_message('debug', 'Translation data types - title: ' . gettype($trans['title'] ?? null) . ', tags: ' . gettype($trans['tags'] ?? null));

                        // Add translatable text fields
                        $row[] = strval($trans['title'] ?? '');
                        $row[] = strval($trans['description'] ?? '');
                        $row[] = strval(strip_tags(htmlspecialchars_decode(stripslashes($trans['long_description'] ?? ''))));
                        $row[] = strval($trans['tags'] ?? '');

                        // Handle FAQs
                        $faqsJson = $trans['faqs'] ?? '[]';
                        // log_message('debug', 'FAQs JSON length: ' . strlen($faqsJson) . ', First 100 chars: ' . substr($faqsJson, 0, 100));

                        $faqs = @json_decode($faqsJson, true);
                        // log_message('debug', 'FAQs decoded - type: ' . gettype($faqs) . ', count: ' . (is_array($faqs) ? count($faqs) : 0));

                        if (!is_array($faqs)) {
                            // log_message('debug', 'FAQs is not array, resetting to empty');
                            $faqs = [];
                        }

                        // Add first FAQ
                        if (isset($faqs[0]) && is_array($faqs[0])) {
                            // log_message('debug', 'FAQ[0] keys: ' . implode(', ', array_keys($faqs[0])));
                            // log_message('debug', 'FAQ[0] question type: ' . gettype($faqs[0]['question'] ?? null));
                            $row[] = strval($faqs[0]['question'] ?? '');
                            $row[] = strval($faqs[0]['answer'] ?? '');
                        } else {
                            // log_message('debug', 'FAQ[0] not found or not array');
                            $row[] = '';
                            $row[] = '';
                        }

                        // Add second FAQ
                        if (isset($faqs[1]) && is_array($faqs[1])) {
                            $row[] = strval($faqs[1]['question'] ?? '');
                            $row[] = strval($faqs[1]['answer'] ?? '');
                        } else {
                            $row[] = '';
                            $row[] = '';
                        }
                    } else {
                        // No translation exists for this language

                        if ($isDefault) {
                            $fallbackTrans = null;

                            if ($defaultLangCode && isset($translationsByLang[$defaultLangCode])) {
                                $fallbackTrans = $translationsByLang[$defaultLangCode];
                            }

                            // log_message('debug', 'Fallback translation: ' . var_export($fallbackTrans, true));

                            $title = $fallbackTrans['title'] ?? $service['title'] ?? '';
                            $description = $fallbackTrans['description'] ?? $service['description'] ?? '';
                            $longDescription = $fallbackTrans['long_description'] ?? $service['long_description'] ?? '';
                            $tags = $fallbackTrans['tags'] ?? $service['tags'] ?? '';
                            $faqsJson = $fallbackTrans['faqs'] ?? $service['faqs'] ?? '[]';
                        } else {
                            // 🚫 Non-default language → NO fallback
                            $title = '';
                            $description = '';
                            $longDescription = '';
                            $tags = '';
                            $faqsJson = '[]';
                        }

                        // Push values
                        $row[] = strval($title);
                        $row[] = strval($description);
                        $row[] = strval(strip_tags(htmlspecialchars_decode(stripslashes($longDescription))));
                        $row[] = strval($tags);

                        // FAQs
                        $faqs = @json_decode($faqsJson, true);
                        if (!is_array($faqs)) $faqs = [];

                        // FAQ 1
                        $row[] = isset($faqs[0]['question']) ? strval($faqs[0]['question']) : '';
                        $row[] = isset($faqs[0]['answer']) ? strval($faqs[0]['answer']) : '';

                        // FAQ 2
                        $row[] = isset($faqs[1]['question']) ? strval($faqs[1]['question']) : '';
                        $row[] = isset($faqs[1]['answer']) ? strval($faqs[1]['answer']) : '';
                    }
                }

                // Handle other_images
                $otherImagesJson = $service['other_images'] ?? '[]';
                // log_message('debug', 'Other images JSON: ' . substr($otherImagesJson, 0, 100));
                $otherImages = @json_decode($otherImagesJson, true);
                // log_message('debug', 'Other images decoded - type: ' . gettype($otherImages) . ', count: ' . (is_array($otherImages) ? count($otherImages) : 0));

                if (!is_array($otherImages)) {
                    $otherImages = [];
                }

                // if (isset($otherImages[0])) {
                //     log_message('debug', 'OtherImages[0] type: ' . gettype($otherImages[0]));
                // }

                $row[] = isset($otherImages[0]) && is_string($otherImages[0]) ? $otherImages[0] : '';
                $row[] = isset($otherImages[1]) && is_string($otherImages[1]) ? $otherImages[1] : '';

                // Handle files
                $filesJson = $service['files'] ?? '[]';
                // log_message('debug', 'Files JSON: ' . substr($filesJson, 0, 100));
                $files = @json_decode($filesJson, true);
                if (!is_array($files)) {
                    $files = [];
                }
                $row[] = isset($files[0]) && is_string($files[0]) ? $files[0] : '';
                $row[] = isset($files[1]) && is_string($files[1]) ? $files[1] : '';

                // Get SEO settings for this service
                $this->seoModel->setTableContext('services');
                $baseSeoSettings = $this->seoModel->getSeoSettingsByReferenceId($service['id']);

                // Get SEO translations
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $seoTranslations = $seoTranslationModel->where('service_id', $service['id'])->findAll();

                $seoTranslationsByLang = [];
                foreach ($seoTranslations as $seoTrans) {
                    $seoTranslationsByLang[$seoTrans['language_code']] = $seoTrans;
                }

                // Add SEO data for each language
                foreach ($languages as $language) {
                    $langCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    // Find SEO translation for this language
                    $seoTranslation = null;
                    if (!empty($seoTranslationsByLang[$langCode])) {
                        $seoTranslation = $seoTranslationsByLang[$langCode];
                    }

                    // Use translation if available, otherwise use base settings for default language
                    $row[] = strval($seoTranslation['seo_title'] ?? ($isDefault ? ($baseSeoSettings['title'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_description'] ?? ($isDefault ? ($baseSeoSettings['description'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_keywords'] ?? ($isDefault ? ($baseSeoSettings['keywords'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_schema_markup'] ?? ($isDefault ? ($baseSeoSettings['schema_markup'] ?? '') : ''));
                }

                // Log final row to check for any non-string values
                // log_message('debug', 'Final row count: ' . count($row));
                foreach ($row as $index => $value) {
                    $type = gettype($value);
                    if ($type !== 'string') {
                        log_message('error', 'NON-STRING VALUE at index ' . $index . ' - Type: ' . $type . ', Value: ' . print_r($value, true));
                    }
                }

                $all_data[] = $row;
            }

            // log_message('debug', 'Total services processed: ' . count($all_data));

            // Output CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="service_update_sample_with_data_multilanguage.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($all_data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - downloadSampleForUpdate()');
            header('Content-Type: application/json');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function ServiceAddInstructions()
    {
        try {
            $filePath = (FCPATH . '/public/uploads/site/Service-Add-Instructions.pdf');
            $fileName = 'Service-Add-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels('cant_download', 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services/bulk_import_services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - download_service_add_instruction_file()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function ServiceUpdateInstructions()
    {
        try {
            $filePath = (FCPATH . '/public/uploads/site/Service-Update-Instructions.pdf');
            $fileName = 'Service-Update-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels('cant_download', 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - download_service_add_instruction_file()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Remove SEO image for a service
     * This method handles AJAX requests to remove SEO images
     * @return \CodeIgniter\HTTP\Response
     */
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse("Unauthorized access", true, [], [], 200, csrf_token(), csrf_hash());
            }

            $serviceId = $this->request->getPost('service_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$serviceId) {
                return ErrorResponse("Service ID is required", true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Set SEO model context for services
            $this->seoModel->setTableContext('services');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

            if (!$existingSettings) {
                return ErrorResponse("SEO settings not found for this service", true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if there's an image to remove
            if (empty($existingSettings['image'])) {
                return ErrorResponse("No SEO image found to remove", true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Store the image name for cleanup
            $imageToDelete = $existingSettings['image'];

            // Prepare update data - remove image but keep other fields
            $updateData = [
                'title' => $existingSettings['title'] ?? '',
                'description' => $existingSettings['description'] ?? '',
                'keywords' => $existingSettings['keywords'] ?? '',
                'schema_markup' => $existingSettings['schema_markup'] ?? '',
                'image' => '', // Clear the image field
                'service_id' => $serviceId
            ];

            // Check if all other SEO fields are empty
            $hasOtherSeoData = !empty($updateData['title']) ||
                !empty($updateData['description']) ||
                !empty($updateData['keywords']) ||
                !empty($updateData['schema_markup']);

            // If all other fields are empty, we should NOT delete the record
            // Instead, we keep the record with empty image but preserve the structure
            // This ensures the SEO record exists for future use
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $updateData);

            if (!empty($result['error'])) {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Clean up the image file from storage
            if (!empty($imageToDelete)) {
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('service_seo_settings', $imageToDelete, $disk);
            }

            return successResponse("SEO image removed successfully", false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - remove_seo_image()');
            return ErrorResponse("Something went wrong while removing SEO image", true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    private function saveServiceSeoSettings(int $serviceId): void
    {
        try {
            // Get default language
            $defaultLanguage = get_default_language();

            // Log all POST data for debugging
            $allPostData = $this->request->getPost();

            // Get multilingual SEO data from form - try both approaches
            $translatedFields = $this->request->getPost('translated_fields');

            // If translated fields are provided as JSON string, decode it
            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);
            }

            // If translated_fields is null/empty, extract SEO data directly from POST data
            if (empty($translatedFields)) {
                $translatedFields = $this->extractSeoDataFromPost($allPostData);
            }

            // Extract default language SEO data for base SEO settings
            $defaultSeoTitle = '';
            $defaultSeoDescription = '';
            $defaultSeoKeywords = '';
            $defaultSeoSchema = '';


            if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
                $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
            }

            if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
                $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
            }

            if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
                $keywordsData = $translatedFields['seo_keywords'][$defaultLanguage];

                // Handle different data structures for keywords
                if (is_array($keywordsData)) {
                    // If it's an array, it might contain JSON strings or direct values
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        // Single JSON string in array format
                        $defaultSeoKeywords = $keywordsData[0];
                    } else {
                        // Multiple values, join them
                        $defaultSeoKeywords = implode(',', $keywordsData);
                    }
                } else {
                    // Direct string value
                    $defaultSeoKeywords = $keywordsData;
                }
            }

            if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
                $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
            }

            // Parse meta keywords (Tagify or comma-separated)
            $keywords = $defaultSeoKeywords ? $this->parseKeywords($defaultSeoKeywords) : '';

            // Build SEO data array
            $seoData = [
                'title'         => $defaultSeoTitle,
                'description'   => $defaultSeoDescription,
                'keywords'      => $keywords,
                'schema_markup' => $defaultSeoSchema,
                'service_id'    => $serviceId,
            ];


            // Check if any SEO field is filled (excluding service_id)
            $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $serviceId);

            // Check if all SEO fields are intentionally cleared
            $allFieldsCleared = empty($seoData['title']) &&
                empty($seoData['description']) &&
                empty($seoData['keywords']) &&
                empty($seoData['schema_markup']);

            // Handle SEO image upload
            $seoImage = $this->request->getFile('meta_image');
            $hasImage = $seoImage && $seoImage->isValid();

            // Use Seo_model for service context
            $this->seoModel->setTableContext('services');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

            $newSeoData = $seoData;
            if ($hasImage) {
                $uploadResult = upload_file(
                    $seoImage,
                    'public/uploads/seo_settings/service_seo_settings/',
                    'Failed to upload SEO image for service',
                    'service_seo_settings'
                );
                if ($uploadResult['error']) {
                    throw new Exception('SEO image upload failed: ' . $uploadResult['message']);
                }
                $newSeoData['image'] = $uploadResult['file_name'];
            } else {
                $newSeoData['image'] = $existingSettings['image'] ?? '';
            }

            // If no existing settings, create new if data or image exists
            if (!$existingSettings) {
                if ($hasSeoData || $hasImage) {
                    $result = $this->seoModel->createSeoSettings($newSeoData);
                    if (!empty($result['error'])) {
                        $errors = $result['validation_errors'] ?? [];
                        throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                    }
                }

                // Process SEO translations after creating base SEO settings
                $this->processSeoTranslations($serviceId, $translatedFields);
                return;
            }

            // If existing settings exist and all fields are cleared (and no new image), delete the record
            // BUT: If there's an existing image, we should NOT delete the record even if all other fields are empty
            // This preserves the SEO record structure for future use
            if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
                $result = $this->seoModel->delete($existingSettings['id']);
                if ($result) {
                    // Clean up old image if it exists
                    if (!empty($existingSettings['image'])) {
                        $disk = fetch_current_file_manager();
                        delete_file_based_on_server('service_seo_settings', $existingSettings['image'], $disk);
                    }
                }
                // Also clean up SEO translations when deleting base SEO settings
                $this->cleanupSeoTranslations($serviceId);
                return;
            }

            // Force clearing removed SEO fields
            $emptyDefaults = [
                'title' => '',
                'description' => '',
                'keywords' => '',
                'schema_markup' => '',
                'image' => $existingSettings['image'] ?? '' // keep old image only if not changed
            ];

            foreach ($emptyDefaults as $key => $defaultVal) {
                if (!array_key_exists($key, $newSeoData) || empty($newSeoData[$key])) {
                    $newSeoData[$key] = $defaultVal;
                }
            }
            // Compare existing and new settings
            $settingsChanged = false;
            foreach ($newSeoData as $key => $value) {
                $existingValue = $existingSettings[$key] ?? '';
                $newValue = $value ?? '';
                if ($existingValue !== $newValue) {
                    $settingsChanged = true;
                    break;
                }
            }

            // Also check if a new image was uploaded (this forces an update)
            if (!$settingsChanged && $hasImage) {
                $settingsChanged = true;
            }

            if (!$settingsChanged) {
                // Even if base SEO settings haven't changed, process translations
                $this->processSeoTranslations($serviceId, $translatedFields);
                return;
            }

            // Update existing settings with new data
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
            if (!empty($result['error'])) {
                $errors = $result['validation_errors'] ?? [];
                throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
            }

            // Process SEO translations after updating base SEO settings
            $this->processSeoTranslations($serviceId, $translatedFields);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - saveServiceSeoSettings()');
            throw $th; // Re-throw to handle in add_service
        }
    }

    private function parseKeywords($input): string
    {
        // If input is empty, return empty string
        if (empty($input)) {
            return '';
        }

        // If input is a string, it might be JSON or comma-separated
        if (is_string($input)) {
            // Check if it's a JSON string
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Treat as comma-separated string
            return trim($input);
        }

        // If input is an array
        if (is_array($input)) {
            // Handle case where array contains a single JSON string (e.g., ['[{value: "tag1"}, {value: "tag2"}]'])
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
            $tags = array_map(function ($item) {
                return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
            }, $input);
            return implode(',', $tags);
        }

        // Fallback: return empty string for unexpected input
        return '';
    }

    /**
     * Process SEO translations from form data
     * 
     * @param int $serviceId Service ID
     * @param array|null $translatedFields Translated fields data
     * @return void
     */
    private function processSeoTranslations(int $serviceId, ?array $translatedFields = null): void
    {
        try {

            // Use provided translated fields or get from POST request (fallback)
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');

                // If translated fields are provided as JSON string, decode it
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }
            }

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {

                // Load the SEO translation model
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');

                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($serviceId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            throw new Exception('Exception in processSeoTranslations for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * @param int $serviceId The service ID
     * @return void
     */
    private function cleanupSeoTranslations(int $serviceId): void
    {
        try {
            // Load the SEO translation model
            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');

            // Delete all SEO translations for this service
            $seoTranslationModel->deleteServiceSeoTranslations($serviceId);
        } catch (\Exception $e) {
            throw new Exception('Exception in cleanupSeoTranslations for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }

    /**
     * Restructure translated fields for SEO model
     * Convert from field[lang] format to lang[field] format
     * 
     * @param array $translatedFields Translated fields in field[lang] format
     * @return array Restructured data in lang[field] format
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {

        $restructured = [];

        // SEO fields we want to process
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Get all available languages from the translated fields
        $languages = [];
        foreach ($seoFields as $field) {
            if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                $fieldLanguages = array_keys($translatedFields[$field]);
                $languages = array_merge($languages, $fieldLanguages);
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                if (isset($translatedFields[$field][$languageCode]) && !empty($translatedFields[$field][$languageCode])) {
                    $value = $translatedFields[$field][$languageCode] ?? '';

                    // Special handling for keywords - parse them using parseKeywords function
                    if ($field === 'seo_keywords') {
                        $restructured[$languageCode][$field] = !empty($value)
                            ? $this->parseKeywords($value)
                            : '';
                    } else {
                        $restructured[$languageCode][$field] = $value !== null
                            ? $value
                            : '';
                    }
                }
            }

            // Remove language only if *everything* truly empty
            if (implode('', $restructured[$languageCode]) === '') {
                unset($restructured[$languageCode]);
            }
        }
        return $restructured;
    }

    /**
     * Extract SEO data directly from POST data when translated_fields is not available
     * 
     * @param array $postData All POST data
     * @return array Extracted SEO data in translated_fields format
     */
    private function extractSeoDataFromPost(array $postData): array
    {

        $seoData = [];

        // SEO fields mapping from form field names to SEO field names
        $seoFields = [
            'meta_title' => 'seo_title',
            'meta_description' => 'seo_description',
            'meta_keywords' => 'seo_keywords',
            'schema_markup' => 'seo_schema_markup'
        ];

        foreach ($seoFields as $formField => $seoField) {

            if (isset($postData[$formField]) && is_array($postData[$formField])) {
                foreach ($postData[$formField] as $languageCode => $value) {
                    // Skip invalid language codes
                    if (empty($languageCode) || $languageCode === '0') {
                        continue;
                    }


                    if ($seoField === 'seo_keywords') {
                        // Process keywords using parseKeywords function
                        $processedValue = $this->parseKeywords($value);
                        $seoData[$seoField][$languageCode] = $processedValue;
                    } else {
                        // For other SEO fields, just trim the value
                        $processedValue = trim($value);
                        $seoData[$seoField][$languageCode] = $processedValue;
                    }
                }
            }
        }
        return $seoData;
    }

    /**
     * Process tags value and convert to comma-separated string
     * 
     * @param mixed $tagsValue The tags value from form data
     * @return string Comma-separated string of tag values
     */
    private function processTags($value): string
    {
        if (empty($value)) {
            return '';
        }

        // 1️⃣ Unwrap array like [0 => '...']
        if (is_array($value) && count($value) === 1) {
            $value = reset($value);
        }

        // 2️⃣ Decode HTML entities (&quot;)
        if (is_string($value)) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // 3️⃣ Decode JSON if possible
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return trim($value);
            }
        }

        // 4️⃣ Extract values
        $tags = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item) && isset($item['value'])) {
                    $tags[] = trim($item['value']);
                } elseif (is_string($item)) {
                    $tags[] = trim($item);
                }
            }
        }

        return implode(', ', array_filter($tags));
    }

    /**
     * Process clean, language-grouped FAQ data structure
     * 
     * @param array $faqData The FAQ data in clean format: {"en": [["q1", "a1"], ["q2", "a2"]], "hi": [["q1", "a1"]]}
     * @return array Processed FAQ data for translation fields
     */
    private function processCleanFAQData(array $faqData): array
    {
        $translatedFields = [];
        $defaultLanguage = get_default_language();

        // Process FAQs for each language
        foreach ($faqData as $languageCode => $languageFaqs) {
            // Skip invalid language codes
            if (empty($languageCode) || !is_array($languageFaqs)) {
                continue;
            }

            $processedFaqs = [];

            // Process each FAQ pair in the language
            foreach ($languageFaqs as $faqPair) {
                // Skip if FAQ pair is not an array or doesn't have exactly 2 elements
                if (!is_array($faqPair) || count($faqPair) !== 2) {
                    continue;
                }

                $question = trim($faqPair[0] ?? '');
                $answer = trim($faqPair[1] ?? '');

                // Only add FAQ if either question or answer is not empty
                if (!empty($question) || !empty($answer)) {
                    $processedFaqs[] = [
                        'question' => $question,
                        'answer' => $answer
                    ];
                }
            }

            // Store processed FAQs for this language
            if (!empty($processedFaqs)) {
                $translatedFields['faqs'][$languageCode] = $processedFaqs; // Store as array, not JSON string
            } else {
                // Store empty array for languages with no FAQs
                $translatedFields['faqs'][$languageCode] = []; // Store as array, not JSON string
            }
        }

        // Ensure all languages have FAQ entries (even if empty)
        $languages = fetch_details('languages', [], ['code']);
        foreach ($languages as $language) {
            $languageCode = $language['code'];
            if (!isset($translatedFields['faqs'][$languageCode])) {
                $translatedFields['faqs'][$languageCode] = json_encode([], JSON_UNESCAPED_UNICODE);
            }
        }

        // log_message('debug', 'Admin - Processed clean FAQ data: ' . json_encode($translatedFields['faqs']));

        return $translatedFields;
    }

    /**
     * Transform form data to translated fields structure with clean FAQ handling
     * 
     * @param array $postData The form post data
     * @param string $defaultLanguage The default language code
     * @param int|null $serviceId The service ID for updates
     * @param array $existingTranslations Existing translations data
     * @return array Transformed fields for translation
     */
    private function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage, ?int $serviceId = null, array $existingTranslations = []): array
    {
        // LOG: Start of transformation
        // log_message('debug', '[TRANSLATION_TRANSFORM] Starting transformation for service ID: ' . ($serviceId ?? 'new'));
        // log_message('debug', '[TRANSLATION_TRANSFORM] Default language: ' . $defaultLanguage);
        // log_message('debug', '[TRANSLATION_TRANSFORM] Has existing translations: ' . (empty($existingTranslations) ? 'No' : 'Yes'));

        $translatedFields = [];
        $translatableFields = ['title', 'description', 'long_description', 'tags', 'faqs'];
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Check if we have clean FAQ data from the new structure
        if (isset($postData['faqs']) && is_string($postData['faqs'])) {
            $faqData = json_decode(htmlspecialchars_decode($postData['faqs']), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($faqData)) {
                // Process clean FAQ data
                $cleanFaqFields = $this->processCleanFAQData($faqData);
                $translatedFields = array_merge($translatedFields, $cleanFaqFields);

                // Remove the raw FAQ data from postData to avoid double processing
                unset($postData['faqs']);
            }
        } else {
            // Check if FAQs are in array format
            if (isset($postData['faqs']) && is_array($postData['faqs'])) {
                // Process FAQs from array format
                foreach ($postData['faqs'] as $languageCode => $languageFaqs) {
                    if (is_array($languageFaqs)) {
                        $translatedFields['faqs'][$languageCode] = $languageFaqs;
                    }
                }
            }
        }

        // Process other translatable fields
        foreach ($translatableFields as $field) {
            // Skip FAQs as they're already processed above
            if ($field === 'faqs') {
                continue;
            }

            if (isset($postData[$field]) && is_array($postData[$field])) {

                foreach ($postData[$field] as $languageCode => $value) {
                    // Skip invalid language codes
                    if (empty($languageCode) || $languageCode === '0') {
                        continue;
                    }

                    if ($field === 'tags') {
                        // Process tags to comma-separated string
                        $processedValue = $this->processTags($value);
                        $translatedFields[$field][$languageCode] = $processedValue;
                        // log_message('debug', '[TRANSLATION_TRANSFORM] Processed tags for ' . $languageCode . ': ' . $processedValue);
                    } else {
                        // For other fields, just trim the value
                        $processedValue = trim($value);
                        $translatedFields[$field][$languageCode] = $processedValue;
                        // log_message('debug', '[TRANSLATION_TRANSFORM] Processed ' . $field . ' for ' . $languageCode . ': ' . $processedValue);
                    }
                }
            } else {
                // log_message('debug', '[TRANSLATION_TRANSFORM] Field ' . $field . ' not found in POST data or not array. Checking existing translations...');

                // If field is not in form data but this is an update, preserve existing data
                if ($serviceId && isset($existingTranslations)) {
                    foreach ($existingTranslations as $languageCode => $translation) {
                        if (isset($translation[$field]) && !empty($translation[$field])) {
                            $translatedFields[$field][$languageCode] = $translation[$field];
                            // log_message('debug', '[TRANSLATION_TRANSFORM] Preserved existing ' . $field . ' for ' . $languageCode . ': ' . $translation[$field]);
                        }
                    }
                } else {
                    // log_message('debug', '[TRANSLATION_TRANSFORM] No existing translations to preserve for field: ' . $field);
                }
            }
        }

        // Process SEO fields
        foreach ($seoFields as $field) {
            // Map form field names to SEO field names
            $formFieldName = str_replace('seo_', 'meta_', $field);

            if (isset($postData[$formFieldName]) && is_array($postData[$formFieldName])) {

                foreach ($postData[$formFieldName] as $languageCode => $value) {
                    // Skip invalid language codes
                    if (empty($languageCode) || $languageCode === '0') {
                        continue;
                    }


                    if ($field === 'seo_keywords') {
                        // Process keywords using parseKeywords function
                        $processedValue = $this->parseKeywords($value);
                        $translatedFields[$field][$languageCode] = $processedValue;
                    } else {
                        // For other SEO fields, just trim the value
                        $processedValue = trim($value);
                        $translatedFields[$field][$languageCode] = $processedValue;
                    }
                }
            }
        }

        return $translatedFields;
    }

    /**
     * Extract translated fields from bulk CSV row data
     * 
     * This method extracts translatable fields (title, description, long_description, tags, faqs)
     * from a CSV row and organizes them by language code for bulk import operations.
     * 
     * @param array $row CSV row data
     * @param array $languageHeaders Parsed language header mappings from CSV
     * @param string $defaultLanguage Default language code
     * @return array Translated fields organized by field name and language code
     */
    private function extractTranslatedFieldsFromBulkRow(array $row, array $languageHeaders, string $defaultLanguage): array
    {
        // Initialize structure for all translatable fields
        $translatedFields = [
            'title' => [],
            'description' => [],
            'long_description' => [],
            'tags' => [],
            'faqs' => []
        ];

        // Extract simple translatable fields: title, description, long_description, tags
        foreach (['title', 'description', 'long_description', 'tags'] as $field) {
            if (isset($languageHeaders[$field])) {
                foreach ($languageHeaders[$field] as $langCode => $columnIndex) {
                    $value = isset($row[$columnIndex]) ? trim($row[$columnIndex]) : '';
                    if (!empty($value)) {
                        // Process tags to handle JSON format like [{"value":"Test"}] and convert to comma-separated string
                        // This ensures bulk operations store tags the same way as manual operations
                        if ($field === 'tags') {
                            $value = $this->processTags($value);
                        }
                        $translatedFields[$field][$langCode] = $value;
                    }
                }
            }
        }

        // Extract FAQs (more complex structure)
        // FAQs are stored as: faq[en][question][1], faq[en][answer][1], etc.
        // We need to process FAQs for both main table storage and translations table
        if (isset($languageHeaders['faqs'])) {
            // Group FAQs by language first for main table storage
            $faqsByLanguage = [];
            // Also group by FAQ number for ServicesService format
            $faqsByNumber = [];

            foreach ($languageHeaders['faqs'] as $langCode => $faqNumbers) {
                $languageFaqs = [];

                foreach ($faqNumbers as $faqNumber => $questionAnswer) {
                    $question = isset($questionAnswer['question']) && isset($row[$questionAnswer['question']])
                        ? trim($row[$questionAnswer['question']])
                        : '';
                    $answer = isset($questionAnswer['answer']) && isset($row[$questionAnswer['answer']])
                        ? trim($row[$questionAnswer['answer']])
                        : '';

                    // Only add FAQ if question or answer is not empty
                    if (!empty($question) || !empty($answer)) {
                        $faqData = [
                            'question' => $question,
                            'answer' => $answer
                        ];

                        // Add to language-based structure for main table
                        $languageFaqs[] = $faqData;

                        // Add to FAQ number-based structure for ServicesService
                        if (!isset($faqsByNumber[$faqNumber])) {
                            $faqsByNumber[$faqNumber] = [];
                        }
                        $faqsByNumber[$faqNumber][$langCode] = $faqData;
                    }
                }

                // Store FAQs for this language (for main table access)
                if (!empty($languageFaqs)) {
                    $faqsByLanguage[$langCode] = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                } else {
                    $faqsByLanguage[$langCode] = json_encode([], JSON_UNESCAPED_UNICODE);
                }
            }

            // Store both formats
            $translatedFields['faqs'] = $faqsByLanguage; // For main table access
            $translatedFields['faqs_by_number'] = $faqsByNumber; // For ServicesService
        }

        return $translatedFields;
    }

    /**
     * Extract SEO translations from bulk CSV row data
     * 
     * Processes SEO translation columns from Excel row and organizes them by language and field
     * Format: "SEO Title (en)", "SEO Description (ar)", etc.
     * 
     * @param array $row Excel row data
     * @param array $headers Excel column headers
     * @param array $languages Available languages
     * @return array SEO translation data organized by language code
     */
    private function extractSeoTranslationsFromRow(array $row, array $headers, array $languages): array
    {
        $seoTranslations = [];

        // Initialize SEO translation structure for each language
        foreach ($languages as $language) {
            $seoTranslations[$language['code']] = [
                'seo_title' => null,
                'seo_description' => null,
                'seo_keywords' => null,
                'seo_schema_markup' => null
            ];
        }

        // Map of SEO field names to database columns
        $seoFieldMapping = [
            'SEO Title' => 'seo_title',
            'SEO Description' => 'seo_description',
            'SEO Keywords' => 'seo_keywords',
            'SEO Schema Markup' => 'seo_schema_markup'
        ];

        // Process each header to find SEO translation columns
        foreach ($headers as $index => $header) {
            // Check if this is a SEO translation column
            // Format: "SEO Title (en)", "SEO Description (ar)", etc.
            if (preg_match('/^(.+?)\s*\(([a-z]{2,3})\)$/i', $header, $matches)) {
                $fieldName = trim($matches[1]);
                $languageCode = strtolower(trim($matches[2]));

                // Check if this is a SEO field (case-insensitive match)
                $matchedField = null;
                foreach ($seoFieldMapping as $mappingField => $dbFieldName) {
                    if (strcasecmp(trim($mappingField), $fieldName) === 0) {
                        $matchedField = $mappingField;
                        break;
                    }
                }

                if ($matchedField !== null) {
                    $dbField = $seoFieldMapping[$matchedField];

                    // Check if this language exists
                    $languageExists = false;
                    foreach ($languages as $language) {
                        if ($language['code'] === $languageCode) {
                            $languageExists = true;
                            break;
                        }
                    }

                    if ($languageExists && isset($row[$index]) && $row[$index] !== null && $row[$index] !== '') {
                        // Store the SEO translation value
                        $value = $row[$index];

                        // Process keywords field specially (handle comma-separated or JSON)
                        if ($dbField === 'seo_keywords' && !empty($value)) {
                            $value = $this->parseKeywords($value);
                        }

                        // Only store non-empty values to avoid overwriting with empty strings
                        $trimmedValue = trim((string)$value);
                        if (!empty($trimmedValue)) {
                            $seoTranslations[$languageCode][$dbField] = $trimmedValue;
                        }
                    }
                }
            }
        }

        return $seoTranslations;
    }

    /**
     * Save SEO settings for bulk service operations
     * 
     * Saves base SEO settings (default language) to services_seo_settings table
     * and all language SEO translations to translated_service_seo_settings table
     * 
     * @param int $serviceId Service ID
     * @param array $seoTranslations SEO translation data organized by language code
     * @param array $languages Available languages
     * @return bool Success status
     */
    private function saveBulkServiceSeoSettings(int $serviceId, array $seoTranslations, array $languages): bool
    {
        try {
            // Get default language
            $defaultLanguage = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            if (empty($defaultLanguage)) {
                log_message('error', "No default language found for saving SEO settings for service {$serviceId}");
                return false;
            }

            // Extract default language SEO data for base SEO settings
            $defaultSeoTitle = '';
            $defaultSeoDescription = '';
            $defaultSeoKeywords = '';
            $defaultSeoSchema = '';

            if (!empty($seoTranslations[$defaultLanguage]['seo_title'])) {
                $defaultSeoTitle = trim($seoTranslations[$defaultLanguage]['seo_title']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_description'])) {
                $defaultSeoDescription = trim($seoTranslations[$defaultLanguage]['seo_description']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_keywords'])) {
                $defaultSeoKeywords = trim($seoTranslations[$defaultLanguage]['seo_keywords']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_schema_markup'])) {
                $defaultSeoSchema = trim($seoTranslations[$defaultLanguage]['seo_schema_markup']);
            }

            // Check if we have any SEO data to save
            $hasSeoData = !empty($defaultSeoTitle) || !empty($defaultSeoDescription) ||
                !empty($defaultSeoKeywords) || !empty($defaultSeoSchema);

            // Only save base SEO settings if we have data
            if ($hasSeoData) {
                // Use Seo_model for service context
                $this->seoModel->setTableContext('services');
                $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

                $seoData = [
                    'service_id' => $serviceId,
                    'title' => $defaultSeoTitle,
                    'description' => $defaultSeoDescription,
                    'keywords' => $defaultSeoKeywords,
                    'schema_markup' => $defaultSeoSchema,
                    'image' => '' // No image in bulk operations
                ];

                if ($existingSettings) {
                    // Update existing settings
                    $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to update SEO settings for service {$serviceId}: " . $result['message']);
                        return false;
                    }
                } else {
                    // Create new settings
                    $result = $this->seoModel->createSeoSettings($seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to create SEO settings for service {$serviceId}: " . $result['message']);
                        return false;
                    }
                }
            }

            // Process SEO translations for all languages
            // Restructure data from lang[field] to field[lang] format for processSeoTranslations
            $restructuredSeoData = [];
            $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

            foreach ($seoFields as $field) {
                $restructuredSeoData[$field] = [];
                foreach ($seoTranslations as $languageCode => $fields) {
                    $restructuredSeoData[$field][$languageCode] = $fields[$field] ?? '';
                }
            }

            // Save SEO translations using the existing processSeoTranslations method
            $this->processSeoTranslations($serviceId, $restructuredSeoData);

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Exception in saveBulkServiceSeoSettings: ' . $e->getMessage());
            log_message('error', 'Exception trace: ' . $e->getTraceAsString());
            return false;
        }
    }
}
