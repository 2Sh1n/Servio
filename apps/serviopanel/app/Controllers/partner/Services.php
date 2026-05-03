<?php

namespace App\Controllers\partner;

use App\Models\Service_model;
use App\Models\Seo_model;
use App\Models\TranslatedServiceDetails_model;
use App\Models\Language_model;
use App\Services\ServicesService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\utility\SlugService;
use Exception;

class Services extends Partner
{
    public $service, $validations, $db, $seoModel;
    protected ServicesService $serviceService;
    protected SlugService $slugService;

    public function __construct()
    {
        parent::__construct();
        $this->service = new Service_model();
        $this->seoModel = new Seo_model();
        $this->validation = \Config\Services::validation();
        $this->db      = \Config\Database::connect();
        $this->serviceService = new ServicesService();
        $this->slugService = new SlugService();
        helper('ResponceServices');
    }

    public function index()
    {
        if ($this->isLoggedIn) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            $tax_details = fetch_details('taxes', ['status' => 1]);
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('provider_panel', 'Provider Panel'), 'services');
            $this->data['tax_details'] = $tax_details;
            $this->data['tax'] = get_settings('system_tax_settings', true);
            // $this->data['categories'] = fetch_details('categories', []);
            $this->data['categories'] = get_categories_with_translated_names();

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    public function add()
    {
        if ($this->isLoggedIn) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'add_services');
            // $this->data['categories'] = fetch_details('categories', []);
            $this->data['categories'] = get_categories_with_translated_names();
            $this->data['tax'] = get_settings('system_tax_settings', true);
            $tax_details = fetch_details('taxes', ['status' => 1]);
            $this->data['tax_details'] = $tax_details;
            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;

            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    public function add_service()
    {
        try {
            if ($this->isLoggedIn) {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $response['error'] = true;
                    $response['message'] = DEMO_MODE_ERROR;
                    $response['csrfName'] = csrf_token();
                    $response['csrfHash'] = csrf_hash();
                    return $this->response->setJSON($response);
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

                    // Check if form data is in new format (arrays) or old format (strings)
                    if (isset($postData['title']) && is_array($postData['title'])) {
                        // New format: field[language] is parsed as arrays
                        $defaultTitle = $postData['title'][$defaultLanguage] ?? '';
                        $defaultDescription = $postData['description'][$defaultLanguage] ?? '';
                        $defaultLongDescription = $postData['long_description'][$defaultLanguage] ?? '';
                        $defaultTags = $postData['tags'][$defaultLanguage] ?? '';
                    } else {
                        // Old format: field[language] is parsed as strings
                        $defaultTitle = $this->request->getPost('title[' . $defaultLanguage . ']') ?: $this->request->getPost('title');
                        $defaultDescription = $this->request->getPost('description[' . $defaultLanguage . ']') ?: $this->request->getPost('description');
                        $defaultLongDescription = $this->request->getPost('long_description[' . $defaultLanguage . ']') ?: $this->request->getPost('long_description');
                        $defaultTags = $this->request->getPost('tags[' . $defaultLanguage . ']') ?: $this->request->getPost('tags');
                    }

                    // Check if default language fields are filled
                    if (empty($defaultTitle)) {
                        return ErrorResponse(labels(SERVICE_TITLE_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service title in default language is required!"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    if (empty($defaultDescription)) {
                        return ErrorResponse(labels(SERVICE_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service description in default language is required!"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    if (empty($defaultLongDescription)) {
                        return ErrorResponse(labels(SERVICE_LONG_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service long description in default language is required!"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    if (empty($defaultTags)) {
                        return ErrorResponse(labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, "Service tags in default language are required!"), true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    $this->validation->setRules(
                        [
                            'categories' => [
                                "rules" => 'required',
                                "errors" => [
                                    "required" => labels(PLEASE_SELECT_CATEGORY, "Please select category")
                                ]
                            ],
                            'price' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_PRICE, "Please enter price"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_PRICE, "Please enter numeric value for price")
                                ]
                            ],
                            'discounted_price' => [
                                "rules" => 'required|numeric|less_than[' . $price . ']',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_DISCOUNTED_PRICE, "Please enter discounted price"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DISCOUNTED_PRICE, "Please enter numeric value for discounted price"),
                                    "less_than" => labels(DISCOUNTED_PRICE_SHOULD_BE_LESS_THAN_PRICE, "Discounted price should be less than price")
                                ]
                            ],
                            'members' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_REQUIRED_MEMBER_FOR_SERVICE, "Please enter required member for service"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_REQUIRED_MEMBER, "Please enter numeric value for required member")
                                ]
                            ],
                            'duration' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_DURATION_TO_PERFORM_TASK, "Please enter duration to perform task"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DURATION_OF_TASK, "Please enter numeric value for duration of task")
                                ]
                            ],
                            'max_qty' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_MAX_QUANTITY_ALLOWED_FOR_SERVICES, "Please enter max quantity allowed for services"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MAX_QUANTITY_ALLOWED_FOR_SERVICES, "Please enter numeric value for max quantity allowed for services")
                                ]
                            ],
                            'service_slug' => [
                                "rules" => 'required',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_SERVICE_SLUG, "Please enter service slug"),
                                ]
                            ],
                            'meta_title' => [
                                "rules" => 'permit_empty',
                                "errors" => [
                                    "permit_empty" => labels(META_TITLE_IS_OPTIONAL, "Meta title is optional")
                                ]
                            ],
                            'meta_description' => [
                                "rules" => 'permit_empty',
                                "errors" => [
                                    "permit_empty" => labels(META_DESCRIPTION_IS_OPTIONAL, "Meta description is optional")
                                ]
                            ],
                            'meta_keywords' => [
                                "rules" => 'permit_empty',
                                "errors" => [
                                    "permit_empty" => labels(META_KEYWORDS_ARE_OPTIONAL, "Meta keywords are optional")
                                ]
                            ],
                            'meta_image' => [
                                "rules" => 'permit_empty|uploaded[meta_image]|is_image[meta_image]',
                                "errors" => [
                                    "permit_empty" => labels(META_IMAGE_IS_OPTIONAL, "Meta image is optional"),
                                    "uploaded" => labels(INVALID_META_IMAGE, "Invalid meta image"),
                                    "is_image" => labels(META_IMAGE_MUST_BE_A_VALID_IMAGE, "Meta image must be a valid image")
                                ]
                            ],
                            'schema_markup' => [
                                "rules" => 'permit_empty',
                                "errors" => [
                                    "permit_empty" => labels(SCHEMA_MARKUP_IS_OPTIONAL, "Schema markup is optional")
                                ]
                            ],
                        ],
                    );
                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors  = $this->validation->getErrors();
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        // Process default language tags
                        // Use the same robust approach for getting default tags
                        if (isset($postData['tags']) && is_array($postData['tags'])) {
                            // New format: field[language] is parsed as arrays
                            $defaultTags = $postData['tags'][$defaultLanguage] ?? '';
                        } else {
                            // Old format: field[language] is parsed as strings
                            $defaultTags = $this->request->getPost('tags[' . $defaultLanguage . ']') ?: $this->request->getPost('tags');
                        }
                        // Process tags to comma-separated string for default language
                        $tagsString = $this->processTagsValue($defaultTags);

                        if (empty($tagsString)) {
                            return ErrorResponse(labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, "Service tags in default language are required!"), true, [], [], 200, csrf_token(), csrf_hash());
                        }

                        // Get default language fields for main table
                        $title = $this->removeScript($defaultTitle);
                        $description = $this->removeScript($defaultDescription);
                        $path = "./public/uploads/services/";
                        if (isset($_POST['service_id']) && !empty($_POST['service_id'])) {
                            $service_id = $_POST['service_id'];
                            $old_icon_data = fetch_details('services', ['id' => $service_id], ['image']);
                            $old_icon = !empty($old_icon_data) ? $old_icon_data[0]['image'] : '';
                        } else {
                            $service_id = "";
                            $old_icon = "";
                        }

                        $paths = [
                            'image' => [
                                'file' => $this->request->getFile('image'),
                                'path' => 'public/uploads/services/',
                                'error' => labels(FAILED_TO_CREATE_SERVICES_FOLDERS, "Failed to create services folders"),
                                'folder' => 'services'
                            ],
                        ];
                        // Process uploads
                        $uploadedFiles = [];
                        foreach ($paths as $key => $upload) {
                            // Check if file exists before uploading
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

                        // Process existing images and check for removals first
                        $existing_images = $this->request->getPost('existing_other_images');
                        $remove_image_flags = $this->request->getPost('remove_other_images');
                        $disk = fetch_current_file_manager();
                        $updated_images = [];

                        // Strip base URL from existing image paths to get correct relative paths
                        if (!empty($existing_images)) {
                            foreach ($existing_images as $key => $image_path) {
                                // Remove base URL if present
                                $base_url = base_url();
                                if (strpos($image_path, $base_url) === 0) {
                                    $existing_images[$key] = substr($image_path, strlen($base_url));
                                }
                            }

                            // Process existing images based on removal flags
                            foreach ($existing_images as $index => $image) {
                                // Check if this image is marked for removal
                                if (isset($remove_image_flags[$index]) && $remove_image_flags[$index] === "1") {
                                    // Skip this image (it will be excluded from the updated list)
                                } else {
                                    // Keep images not marked for removal
                                    $updated_images[] = $image;
                                }
                            }
                        }

                        $otherImagesConfig = [
                            'path' => 'public/uploads/services/',
                            'error' => labels(FAILED_TO_UPLOAD_OTHER_IMAGES, "Failed to upload other images"),
                            'folder' => 'services'
                        ];
                        $uploadedOtherImages = $updated_images; // Start with existing images that weren't removed

                        if (isset($multipleFiles['other_service_image_selector'])) {
                            $files = $multipleFiles['other_service_image_selector'];
                            foreach ($files as $file) {
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
                            }
                        }
                        $other_images = [
                            'other_images' => !empty($uploadedOtherImages) ? json_encode($uploadedOtherImages) : "",
                        ];

                        // Process existing files and check for removals
                        $existing_files = $this->request->getPost('existing_files');
                        $remove_file_flags = $this->request->getPost('remove_files');
                        $updated_files = [];

                        // Strip base URL from existing file paths to get correct relative paths
                        if (!empty($existing_files)) {
                            foreach ($existing_files as $key => $file_path) {
                                // Remove base URL if present
                                $base_url = base_url();
                                if (strpos($file_path, $base_url) === 0) {
                                    $existing_files[$key] = substr($file_path, strlen($base_url));
                                }
                            }

                            // Process existing files based on removal flags
                            foreach ($existing_files as $index => $file) {
                                // Check if this file is marked for removal
                                if (isset($remove_file_flags[$index]) && $remove_file_flags[$index] === "1") {
                                    // Skip this file (it will be excluded from the updated list)
                                } else {
                                    // Keep files not marked for removal
                                    $updated_files[] = $file;
                                }
                            }
                        }

                        $uploadedFilesDocuments = $updated_files; // Start with existing files that weren't removed
                        $FilesDocumentsConfig = [
                            'path' => 'public/uploads/services/',
                            'error' => labels(FAILED_TO_UPLOAD_FILES, "Failed to upload files"),
                            'folder' => 'services'
                        ];

                        if (isset($multipleFiles['files'])) {
                            $files = $multipleFiles['files'];
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
                        }
                        $files = [
                            'files' => !empty($uploadedFilesDocuments) ? json_encode($uploadedFilesDocuments) : "",
                        ];
                        if (isset($_POST['sub_category']) && !empty($_POST['sub_category'])) {
                            $category_id = $_POST['sub_category'];
                        } else {
                            $category_id = $_POST['categories'];
                        }
                        $discounted_price = $this->request->getPost('discounted_price');
                        $price = $this->request->getPost('price');
                        if ($discounted_price >= $price && $discounted_price == $price) {
                            return ErrorResponse(labels(DISCOUNTED_PRICE_CANNOT_BE_HIGHER_THAN_OR_EQUAL_TO_PRICE, "Discounted price can not be higher than or equal to the price"), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $partner_data = fetch_details('partner_details', ['partner_id' => $this->ionAuth->getUserId()]);
                        if ($this->request->getVar('members') > $partner_data[0]['number_of_members']) {
                            return ErrorResponse(labels('number_of_members_cannot_be_greater_than', "Number Of member could not greater than") . " " . $partner_data[0]['number_of_members'], true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $user_id = $this->ionAuth->getUserId();
                        if (isset($_POST['is_cancelable']) && $_POST['is_cancelable'] == 'on') {
                            $is_cancelable = "1";
                        } else {
                            $is_cancelable = "0";
                        }
                        if ($is_cancelable == "1" && $this->request->getVar('cancelable_till') == "") {
                            return ErrorResponse(labels(PLEASE_ADD_MINUTES, "Please Add Minutes"), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        // FAQs are now handled as translatable fields, so we don't need to process them here
                        // The ServiceService will handle FAQ storage in the translated_service_details table
                        $status = ($this->request->getPost('status') == "on") ? "1" : "0";
                        $partner_details = fetch_details('partner_details', ['partner_id' => $user_id]);
                        if ($partner_details[0]['need_approval_for_the_service'] == 1) {
                            $approved_by_admin = 0;
                        } else {
                            $approved_by_admin = 1;
                        }

                        // Set default image if not provided
                        $image = '';
                        if (isset($uploadedFiles['image'])) {
                            if (isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server') {
                                $image = 'public/uploads/services/' . $uploadedFiles['image']['url'];
                            } else {
                                $image = $uploadedFiles['image']['url'];
                            }
                        } else if (!empty($service_id)) {
                            // If duplicating a service, use the old image if no new image provided
                            $old_image = fetch_details('services', ['id' => $service_id], ['image']);
                            if (!empty($old_image) && !empty($old_image[0]['image'])) {
                                $image = $old_image[0]['image'];
                            }
                        }

                        // Get default language data for main table storage
                        $defaultTitle = $defaultTitle ?? '';
                        $defaultDescription = $defaultDescription ?? '';
                        $defaultLongDescription = $defaultLongDescription ?? '';
                        $defaultTags = $defaultTags ?? '';

                        // Process default language FAQs for main table
                        $defaultFaqs = '';
                        if (!empty($postData['faqs'])) {
                            $defaultFaqsData = json_decode(htmlspecialchars_decode($postData['faqs'] ?? ""), true);
                            if (!empty($defaultFaqsData)) {
                                $defaultFaqs = json_encode($defaultFaqsData[$defaultLanguage], JSON_UNESCAPED_UNICODE);
                            }
                        }

                        $postedSlug = $this->request->getPost('service_slug');
                        $resolvedSlug = $this->slugService->resolve(
                            currentSlug: null,
                            inputSlug: $postedSlug,
                            fallbackName: $defaultTitle,
                            table: 'services'
                        );

                        $service = array(
                            'id' => $service_id,
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
                            'status' => $status,
                            'is_pay_later_allowed' => ($this->request->getPost('pay_later') == "on") ? 1 : 0,
                            'is_cancelable' => $is_cancelable,
                            'cancelable_till' => ($is_cancelable == "1") ? $this->request->getVar('cancelable_till') : '',
                            'max_quantity_allowed' => $this->request->getPost('max_qty'),
                            'files' => $files['files'] ?? '',
                            'at_store' => ($this->request->getPost('at_store') == "on") ? 1 : 0,
                            'at_doorstep' => ($this->request->getPost('at_doorstep') == "on") ? 1 : 0,
                            'approved_by_admin' => $approved_by_admin,
                            'slug' => $resolvedSlug,
                            // Store default language data in main table
                            'title' => $defaultTitle,
                            'description' => $defaultDescription,
                            'long_description' => $defaultLongDescription,
                            'tags' => $tagsString,
                            'faqs' => $defaultFaqs,
                        );
                        $service_model = new Service_model();
                        if ($service_model->save($service)) {
                            $serviceId = $service_model->insertID(); // Get the inserted service ID

                            // Handle translated fields using ServiceService
                            $postData = $this->request->getPost();

                            // Transform form data to translated_fields structure
                            $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage, null, []);

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
                                log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - add_service() - SEO settings');
                                return ErrorResponse(labels(FAILED_TO_SAVE_SEO_SETTINGS, "Failed to save SEO settings") . ": " . $th->getMessage(), true, [], [], 200, csrf_token(), csrf_hash());
                            }

                            // Get service details for event tracking
                            $serviceData = fetch_details('services', ['id' => $serviceId]);
                            $categoryData = [];
                            if (!empty($serviceData) && isset($serviceData[0]['category_id'])) {
                                $categoryData = fetch_details('categories', ['id' => $serviceData[0]['category_id']], ['id', 'name']);
                            }

                            $eventData = [
                                'clarity_event' => 'service_created',
                                'service_id' => $serviceId,
                                'service_name' => $serviceData[0]['title'] ?? '',
                                'service_price' => $serviceData[0]['price'] ?? '',
                                'category_id' => $serviceData[0]['category_id'] ?? '',
                                'category_name' => !empty($categoryData) ? $categoryData[0]['name'] ?? '' : ''
                            ];

                            // Redirect to service list page after successful save with a slight delay
                            return successResponse(labels(SERVICE_SAVED_SUCCESSFULLY, "Service saved successfully"), false, $eventData, ['redirect_url' => base_url('partner/services')], 200, csrf_token(), csrf_hash());
                        } else {
                            return ErrorResponse(labels(SERVICE_CANNOT_BE_SAVED, "Service can not be Save!"), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                } else {
                    return redirect()->to('partner/services');
                }
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - add_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        // Normalize search term: trim and replace multiple spaces with single space
        // This fixes issues where search doesn't work with multiple spaces in long names
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = trim($_GET['search']);
            // Replace multiple consecutive spaces with a single space
            $search = preg_replace('/\s+/', ' ', $search);
        } else {
            $search = '';
        }
        $service_model = new Service_model();
        $where['s.user_id'] = $_SESSION['user_id'];
        $services =  $service_model->list(false, $search, $limit, $offset, $sort, $order, $where);
        return $services;
    }
    public function update_service()
    {
        try {
            if ($this->isLoggedIn) {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $response['error'] = true;
                    $response['message'] = DEMO_MODE_ERROR;
                    $response['csrfName'] = csrf_token();
                    $response['csrfHash'] = csrf_hash();
                    return $this->response->setJSON($response);
                }

                if ($_POST && !empty($_POST)) {
                    $disk = fetch_current_file_manager();
                    $price = $this->request->getPost('price');

                    // Get the default language from database
                    // This ensures we validate the required fields for the default language
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

                    // Check if form data is in new format (arrays) or old format (strings)
                    if (isset($postData['title']) && is_array($postData['title'])) {
                        // New format: field[language] is parsed as arrays
                        $titleValue = $postData['title'][$defaultLanguage] ?? null;
                        $descriptionValue = $postData['description'][$defaultLanguage] ?? null;
                        $longDescriptionValue = $postData['long_description'][$defaultLanguage] ?? null;
                        $tagsValue = $postData['tags'][$defaultLanguage] ?? null;
                    } else {
                        // Old format: field[language] is parsed as strings
                        $titleField = 'title[' . $defaultLanguage . ']';
                        $descriptionField = 'description[' . $defaultLanguage . ']';
                        $longDescriptionField = 'long_description[' . $defaultLanguage . ']';
                        $tagsField = 'tags[' . $defaultLanguage . ']';

                        $titleValue = $postData[$titleField] ?? null;
                        $descriptionValue = $postData[$descriptionField] ?? null;
                        $longDescriptionValue = $postData[$longDescriptionField] ?? null;
                        $tagsValue = $postData[$tagsField] ?? null;
                    }

                    // Check title
                    if (empty($titleValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_TITLE_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service title in default language is required!");
                    }

                    // Check description
                    if (empty($descriptionValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service description in default language is required!");
                    }

                    // Check long description
                    if (empty($longDescriptionValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_LONG_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, "Service long description in default language is required!");
                    }

                    // Check tags
                    if (empty($tagsValue)) {
                        $defaultLanguageErrors[] = labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, "Service tags in default language are required!");
                    }

                    if (!empty($defaultLanguageErrors)) {
                        return ErrorResponse($defaultLanguageErrors, true, [], [], 200, csrf_token(), csrf_hash());
                    }

                    $this->validation->setRules(
                        [
                            'categories' => [
                                "rules" => 'required',
                                "errors" => [
                                    "required" => labels(PLEASE_SELECT_CATEGORY, "Please select category")
                                ]
                            ],
                            'price' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_PRICE, "Please enter price"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_PRICE, "Please enter numeric value for price")
                                ]
                            ],
                            'discounted_price' => [
                                "rules" => 'required|numeric|less_than[' . $price . ']',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_DISCOUNTED_PRICE, "Please enter discounted price"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DISCOUNTED_PRICE, "Please enter numeric value for discounted price"),
                                    "less_than" => labels(DISCOUNTED_PRICE_SHOULD_BE_LESS_THAN_PRICE, "Discounted price should be less than price")
                                ]
                            ],
                            'members' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_REQUIRED_MEMBER_FOR_SERVICE, "Please enter required member for service"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_REQUIRED_MEMBER, "Please enter numeric value for required member")
                                ]
                            ],
                            'duration' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_DURATION_TO_PERFORM_TASK, "Please enter duration to perform task"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DURATION_OF_TASK, "Please enter numeric value for duration of task")
                                ]
                            ],
                            'max_qty' => [
                                "rules" => 'required|numeric',
                                "errors" => [
                                    "required" => labels(PLEASE_ENTER_MAX_QUANTITY_ALLOWED_FOR_SERVICES, "Please enter max quantity allowed for services"),
                                    "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MAX_QUANTITY_ALLOWED_FOR_SERVICES, "Please enter numeric value for max quantity allowed for services")
                                ]
                            ],


                        ],
                    );
                    $disk = fetch_current_file_manager();

                    if (!$this->validation->withRequest($this->request)->run()) {
                        $errors  = $this->validation->getErrors();
                        return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                    } else {
                        // Process default language tags
                        // Use the same robust approach for getting default tags
                        if (isset($postData['tags']) && is_array($postData['tags'])) {
                            // New format: field[language] is parsed as arrays
                            $defaultTags = $postData['tags'][$defaultLanguage] ?? '';
                        } else {
                            // Old format: field[language] is parsed as strings
                            $defaultTags = $this->request->getPost('tags[' . $defaultLanguage . ']') ?: $this->request->getPost('tags');
                        }
                        // Process tags to comma-separated string for default language
                        $tagsString = $this->processTagsValue($defaultTags);

                        if (empty($tagsString)) {
                            return ErrorResponse(labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, "Service tags in default language are required!"), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $id = $this->request->getPost('service_id');
                        // Include slug so we can resolve/update it when title or manual slug changes
                        $old_images_and_documents = fetch_details('services', ['id' => $id], ['image', 'other_images', 'files', 'slug']);

                        $paths = [
                            'image' => [
                                'file' => $this->request->getFile('service_image_selector_edit'),
                                'path' => 'public/uploads/services/',
                                'error' => labels(FAILED_TO_CREATE_SERVICES_FOLDERS, "Failed to create services folders"),
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
                        $multipleFiles = $this->request->getFiles('filepond');

                        if (isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server') {
                            $image_name = isset($uploadedFiles['image']['url']) ? ('public/uploads/services/' . $uploadedFiles['image']['url']) : $old_images_and_documents[0]['image'];
                        } else {
                            $image_name = isset($uploadedFiles['image']['url']) ? $uploadedFiles['image']['url'] : $old_images_and_documents[0]['image'];
                        }
                        // Always process gallery images and files regardless of where the main image is stored.
                        // This keeps auxiliary data in sync and prevents undefined variables when the main image stays local.

                        // Initialize arrays for images
                        $updated_images = [];

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
                        if (isset($multipleFiles['other_service_image_selector_edit'])) {
                            foreach ($multipleFiles['other_service_image_selector_edit'] as $file) {
                                if ($file->isValid()) {
                                    // Upload new image
                                    $result = upload_file($file, 'public/uploads/services/', labels(FAILED_TO_UPLOAD_OTHER_IMAGES, "Failed to upload other images"), 'services');
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
                                    $result = upload_file($file, 'public/uploads/services/', 'Failed to upload files', 'services');
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

                        $category = $this->request->getPost('categories');
                        if ($category == "select_category" || $category == "Select Category") {
                            return ErrorResponse(labels(PLEASE_SELECT_ANYTHING_OTHER_THAN_SELECT_CATEGORY, "Please select anything other than Select Category"), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $discounted_price = $this->request->getPost('discounted_price');
                        $price = $this->request->getPost('price');
                        if ($discounted_price >= $price && $discounted_price == $price) {
                            return ErrorResponse(labels(DISCOUNTED_PRICE_CANNOT_BE_HIGHER_THAN_OR_EQUAL_TO_PRICE, "Discounted price can not be higher than or equal to the price"), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $user_id = $this->ionAuth->user()->row()->id;
                        if (isset($_POST['is_cancelable']) && $_POST['is_cancelable'] == 'on') {
                            $is_cancelable = "1";
                        } else {
                            $is_cancelable = "0";
                        }
                        if ($is_cancelable == "1" && $this->request->getVar('cancelable_till') == "") {
                            return ErrorResponse(labels(PLEASE_ADD_MINUTES, "Please Add Minutes"), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                        $tax_data = fetch_details('taxes', ['id' => $this->request->getVar('edit_tax_id')], ['id', 'title', 'percentage']);
                        // FAQs are now handled as translatable fields, so we don't need to process them here
                        // The ServiceService will handle FAQ storage in the translated_service_details table


                        $partner_details = fetch_details('partner_details', ['partner_id' => $user_id]);
                        if ($partner_details[0]['need_approval_for_the_service'] == 1) {
                            $approved_by_admin = 0;
                        } else {
                            $approved_by_admin = 1;
                        }

                        // Resolve slug using SlugService (same logic as admin Services update)
                        // Keeps existing slug if no title/manual slug change; otherwise generates from title or manual input
                        $existingSlug = $old_images_and_documents[0]['slug'] ?? '';
                        $defaultTitle = $titleValue ?? '';
                        $resolvedSlug = $this->slugService->resolve(
                            currentSlug: $existingSlug,
                            inputSlug: trim($this->request->getPost('service_slug') ?? ''),
                            fallbackName: $defaultTitle,
                            table: 'services',
                            excludeId: (int) $id
                        );
                        // Legacy slugs (e.g. ending with -123) are regenerated from the default language title
                        if ($this->slugService->isLegacySlug($existingSlug)) {
                            $resolvedSlug = $this->slugService->generate(
                                $defaultTitle,
                                $defaultTitle,
                                'services',
                                (int) $id
                            );
                        }

                        $data['category_id'] = $category;
                        $data['tax_id'] = $this->request->getVar('tax_id');
                        $data['tax'] = $this->request->getPost('tax');
                        $data['tax_type'] = $this->request->getVar('tax_type');
                        $data['price'] = $this->request->getPost('price');
                        $data['discounted_price'] = $this->request->getPost('discounted_price');
                        $data['image'] = $image_name;
                        $data['number_of_members_required'] = $this->request->getPost('members');
                        $data['duration'] = $this->request->getPost('duration');
                        $data['rating'] = 0;
                        $data['number_of_ratings'] = 0;
                        $data['max_quantity_allowed'] = $this->request->getPost('max_qty');
                        $data['is_pay_later_allowed'] = ($this->request->getPost('pay_later') == "on") ? 1 : 0;
                        $data['status'] =  ($this->request->getPost('status') == "on") ? 1 : 0;
                        $data['is_cancelable'] = $is_cancelable;
                        $data['cancelable_till'] = ($is_cancelable == "1") ? $this->request->getVar('cancelable_till') : '';
                        $data['files'] = isset($files) ? $files : "";
                        $data['at_store'] = ($this->request->getPost('at_store') == "on") ? 1 : 0;
                        $data['at_doorstep'] = ($this->request->getPost('at_doorstep') == "on") ? 1 : 0;
                        $data['other_images'] = $other_images;
                        $data['files'] = $files;
                        $data['approved_by_admin'] = $approved_by_admin;

                        // Add slug to update when it was resolved (so we don't overwrite with empty)
                        if ($resolvedSlug !== null && $resolvedSlug !== '') {
                            $data['slug'] = $resolvedSlug;
                        }

                        // Store default language data in main table
                        $data['title'] = $titleValue ?? '';
                        $data['description'] = $descriptionValue ?? '';
                        $data['long_description'] = $longDescriptionValue ?? '';
                        $data['tags'] = $tagsString ?? '';

                        // Process default language FAQs for main table
                        $defaultFaqs = '';

                        if (!empty($postData['faqs'])) {
                            $defaultFaqsData = json_decode(htmlspecialchars_decode($postData['faqs'] ?? ""), true);
                            if (!empty($defaultFaqsData)) {
                                $defaultFaqs = json_encode($defaultFaqsData[$defaultLanguage], JSON_UNESCAPED_UNICODE);
                            }
                        }
                       
                        $data['faqs'] = $defaultFaqs;
                        if ($this->db->table('services')->update($data, ['id' => $id])) {
                            // Handle translated fields using ServiceService
                            $postData = $this->request->getPost();

                            // Get existing translations for the service
                            $existingTranslationsResult = $this->serviceService->getServiceWithTranslations($id);
                            $existingTranslations = $existingTranslationsResult['translated_data'] ?? [];

                            // Transform form data to translated_fields structure
                            $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage, $id, $existingTranslations);

                            // Add translated_fields to postData for ServiceService
                            $postData['translated_fields'] = $translatedFields;

                            $translationResult = $this->serviceService->handleServiceUpdateWithTranslations($postData, $data, $id, $defaultLanguage);

                            if (!$translationResult['success']) {
                                // If translation saving fails, we should handle this appropriately
                                // For now, we'll log the error but continue with the process
                                log_message('error', 'Failed to save service translations: ' . implode(', ', $translationResult['errors']));
                            }

                            try {
                                $this->saveServiceSeoSettings($id); // Save SEO settings
                            } catch (\Throwable $th) {
                                log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - update_service() - SEO settings');
                                return ErrorResponse(labels(FAILED_TO_SAVE_SEO_SETTINGS, "Failed to save SEO settings") . ": " . $th->getMessage(), true, [], [], 200, csrf_token(), csrf_hash());
                            }

                            // Send notification to admin users about provider editing service details
                            try {
                                // log_message('info', '[PROVIDER_EDITS_SERVICE_DETAILS] Starting notification process for service_id: ' . $id);

                                // Get provider name with translation support
                                $providerName = get_translated_partner_field($user_id, 'user_name');
                                if (empty($providerName)) {
                                    $providerData = fetch_details('users', ['id' => $user_id], ['username']);
                                    $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                                }
                                // log_message('info', '[PROVIDER_EDITS_SERVICE_DETAILS] Provider name: ' . $providerName . ', Provider ID: ' . $user_id);

                                // Get category information
                                $categoryData = fetch_details('categories', ['id' => $category], ['name']);
                                $categoryName = !empty($categoryData) ? $categoryData[0]['name'] : 'Category';
                                // log_message('info', '[PROVIDER_EDITS_SERVICE_DETAILS] Category name: ' . $categoryName . ', Category ID: ' . $category);

                                // Get currency from settings
                                $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

                                // Get service title (from data array or fetch from database)
                                $serviceTitle = $data['title'] ?? '';
                                if (empty($serviceTitle)) {
                                    $serviceData = fetch_details('services', ['id' => $id], ['title']);
                                    $serviceTitle = !empty($serviceData) ? $serviceData[0]['title'] : 'Service';
                                }

                                // Prepare context data for the notification template
                                $context = [
                                    'provider_name' => $providerName,
                                    'provider_id' => $user_id,
                                    'service_id' => $id,
                                    'service_title' => $serviceTitle,
                                    'service_description' => $data['description'] ?? '',
                                    'category_name' => $categoryName,
                                    'category_id' => $category,
                                    'service_price' => number_format($data['price'] ?? 0, 2),
                                    'service_discounted_price' => number_format($data['discounted_price'] ?? 0, 2),
                                    'currency' => $currency
                                ];
                                // log_message('info', '[PROVIDER_EDITS_SERVICE_DETAILS] Context prepared: ' . json_encode($context));

                                // Queue notification to admin users (group_id = 1) via all channels
                                // The service will check preferences and configurations to determine which channels to actually send
                                queue_notification_service(
                                    eventType: 'provider_edits_service_details',
                                    recipients: [],
                                    context: $context,
                                    options: [
                                        'user_groups' => [1], // Admin user group
                                        'channels' => ['fcm', 'email', 'sms'] // All channels - service will check preferences
                                    ]
                                );
                                // log_message('info', '[PROVIDER_EDITS_SERVICE_DETAILS] Notification result: ' . json_encode($result));
                            } catch (\Throwable $notificationError) {
                                log_message('error', '[PROVIDER_EDITS_SERVICE_DETAILS] Notification error trace: ' . $notificationError->getTraceAsString());
                            }

                            // Get service details for event tracking
                            $serviceData = fetch_details('services', ['id' => $id]);
                            $eventData = [
                                'clarity_event' => 'service_updated',
                                'service_id' => $id,
                                'service_name' => $serviceData[0]['title'] ?? ''
                            ];

                            // Redirect to service list page after successful update with a slight delay
                            return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, $eventData, ['redirect_url' => base_url('partner/services')], 200, csrf_token(), csrf_hash());
                        }
                    }
                } else {
                    return redirect()->to('partner/services');
                }
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - update_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function delete()
    {
        $disk = fetch_current_file_manager();

        try {
            if ($this->isLoggedIn) {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $response['error'] = true;
                    $response['message'] = DEMO_MODE_ERROR;
                    $response['csrfName'] = csrf_token();
                    $response['csrfHash'] = csrf_hash();
                    return $this->response->setJSON($response);
                }
                $id = $this->request->getPost('id');
                $db      = \Config\Database::connect();
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

                $builder = $db->table('services')->delete(['id' => $id]);
                $builder2 = $this->db->table('cart')->delete(['service_id' => $id]);
                if ($builder) {
                    // Get service details for event tracking before deletion
                    $serviceData = $old_data[0] ?? [];
                    $eventData = [
                        'clarity_event' => 'service_deleted',
                        'service_id' => $id
                    ];

                    return successResponse(labels(SERVICE_DELETED_SUCCESSFULLY, "Service deleted successfully"), false, $eventData, [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(SERVICE_CANNOT_BE_DELETED, "Service can not be deleted!"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - delete()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function edit_service()
    {
        $disk = fetch_current_file_manager();

        try {
            helper('function');
            $uri = service('uri');
            if ($this->isLoggedIn) {
                if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                    return redirect('partner/profile');
                }
                $service_id = $uri->getSegments()[3];
                setPageInfo($this->data, labels('edit_service', 'Edit Service') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'edit_service');
                // $this->data['categories'] = fetch_details('categories', []);
                $this->data['categories'] = get_categories_with_translated_names();
                $this->data['tax'] = get_settings('system_tax_settings', true);
                $tax_details = fetch_details('taxes', ['status' => 1]);
                $this->data['tax_details'] = $tax_details;
                // Fetch taxes with translated names based on current language
                $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
                $service = fetch_details('services', ['id' => $service_id])[0];

                // Normalize FAQ payloads exactly like fetch_cart() does so both places
                // accept the older numeric format and the newer keyed format safely.
                $normalizeFaqData = static function ($rawFaqs) {
                    if (empty($rawFaqs)) {
                        return [];
                    }

                    if (is_string($rawFaqs)) {
                        $decoded = json_decode($rawFaqs, true);
                        $rawFaqs = is_array($decoded) ? $decoded : [];
                    }

                    if (!is_array($rawFaqs)) {
                        return [];
                    }

                    $normalized = [];

                    foreach ($rawFaqs as $faq) {
                        if (!is_array($faq) || empty($faq)) {
                            continue;
                        }

                        if (isset($faq['question'], $faq['answer'])) {
                            $question = trim((string) $faq['question']);
                            $answer = trim((string) $faq['answer']);
                        } elseif (isset($faq[0], $faq[1])) {
                            $question = trim((string) $faq[0]);
                            $answer = trim((string) $faq[1]);
                        } else {
                            continue;
                        }

                        if ($question === '' || $answer === '') {
                            continue;
                        }

                        $normalized[] = [
                            'question' => $question,
                            'answer' => $answer,
                        ];
                    }

                    return $normalized;
                };

                // Check if service belongs to the logged-in partner
                if ($service['user_id'] != $this->userId) {
                    return redirect('partner/services')->with('error', 'Access denied');
                }

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
                    $decodedOtherImages = json_decode($service['other_images'], true);
                    if (is_array($decodedOtherImages)) {
                        $service['other_images'] = array_map(function ($data) use ($service, $disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            }
                            return null;
                        }, $decodedOtherImages);
                    } else {
                        $service['other_images'] = [];
                    }
                } else {
                    $service['other_images'] = [];
                }
                if (!empty($service['files'])) {
                    $decodedFiles = json_decode($service['files'], true);
                    if (is_array($decodedFiles)) {
                        $service['files'] = array_map(function ($data) use ($service, $disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            }
                            return null;
                        }, $decodedFiles);
                    } else {
                        $service['files'] = [];
                    }
                } else {
                    $service['files'] = [];
                }

                // Process FAQs data - decode JSON string to array for proper handling in view
                // Keep the default language FAQs consistent regardless of the legacy format stored.
                $service['faqs'] = $normalizeFaqData(isset($service['faqs']) ? $service['faqs'] : []);

                $this->data['service'] = $service;

                $this->data['tax_data'] = $tax_data;
                $this->data['main_page'] = FORMS . 'edit_service';

                // Prepare event data for service_viewed tracking
                $categoryData = [];
                if (!empty($service['category_id'])) {
                    $categoryData = fetch_details('categories', ['id' => $service['category_id']], ['id', 'name']);
                }
                $this->data['clarity_event_data'] = [
                    'clarity_event' => 'service_viewed',
                    'service_id' => $service_id,
                    'service_name' => $service['title'] ?? '',
                    'service_price' => $service['price'] ?? '',
                    'category_id' => $service['category_id'] ?? '',
                    'category_name' => !empty($categoryData) ? $categoryData[0]['name'] ?? '' : ''
                ];


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

                // Load SEO translations for each language (must be done AFTER $mergedServiceDetails is initialized)
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $seoTranslations = $seoTranslationModel->getAllTranslationsForService($service_id);

                // Attach SEO translations to service data by language code
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        $languageCode = $translation['language_code'];
                        $mergedServiceDetails['translated_seo_' . $languageCode] = [
                            'seo_title' => $translation['seo_title'] ?? '',
                            'seo_description' => $translation['seo_description'] ?? '',
                            'seo_keywords' => $translation['seo_keywords'] ?? '',
                            'seo_schema_markup' => $translation['seo_schema_markup'] ?? ''
                        ];
                    }
                }

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefaultLanguage = $language['is_default'] == 1;

                    // Initialize FAQ data for this language
                    $languageFaqs = [];

                    // First, try to get FAQs from translations table
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Normalize translated FAQs as well so the edit form never receives mixed structures.
                        $languageFaqs = $normalizeFaqData(isset($translation['faqs']) ? $translation['faqs'] : []);
                    }

                    // If no FAQs found in translations table, fall back to main table (for default language)
                    if (empty($languageFaqs) && $isDefaultLanguage) {
                        if (isset($service['faqs']) && is_array($service['faqs']) && !empty($service['faqs'])) {
                            $languageFaqs = $service['faqs'];
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


                return view('backend/partner/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - edit_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function duplicate()
    {
        try {
            helper('function');
            $uri = service('uri');
            if ($this->isLoggedIn) {
                if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                    return redirect('partner/profile');
                }
                $service_id = $uri->getSegments()[3];
                setPageInfo($this->data, labels('duplicate_service', 'Duplicate Service') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'duplicate_service');
                // $this->data['categories'] = fetch_details('categories', []);
                $this->data['categories'] = get_categories_with_translated_names();
                $this->data['tax'] = get_settings('system_tax_settings', true);
                $tax_details = fetch_details('taxes', ['status' => 1]);
                $this->data['tax_details'] = $tax_details;
                // Fetch taxes with translated names based on current language
                $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);

                // Fetch the original service data
                $serviceData = fetch_details('services', ['id' => $service_id]);

                // Check if service exists
                if (empty($serviceData)) {
                    return redirect('partner/services')->with('error', 'Service not found');
                }

                $service = $serviceData[0];

                // Check if service belongs to the logged-in partner
                if ($service['user_id'] != $this->userId) {
                    return redirect('partner/services');
                }

                // Handle file storage configuration
                $disk = fetch_current_file_manager();

                // Handle main image with proper cloud storage support
                if (!empty($service['image'])) {
                    if ($disk == 'local_server') {
                        $service['image'] = base_url($service['image']);
                    } else if ($disk == "aws_s3") {
                        $service['image'] = fetch_cloud_front_url('services', $service['image']);
                    }
                }

                // Handle other images with proper cloud storage support
                if (!empty($service['other_images'])) {
                    $other_images = json_decode($service['other_images'], true);
                    if (is_array($other_images)) {
                        $service['other_images'] = array_map(function ($data) use ($disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            } else {
                                return $data;
                            }
                        }, $other_images);
                    } else {
                        $service['other_images'] = [];
                    }
                } else {
                    $service['other_images'] = [];
                }

                // Handle files with proper cloud storage support
                if (!empty($service['files'])) {
                    $files = json_decode($service['files'], true);
                    if (is_array($files)) {
                        $service['files'] = array_map(function ($data) use ($disk) {
                            if ($disk === "local_server") {
                                return base_url($data);
                            } elseif ($disk === "aws_s3") {
                                return fetch_cloud_front_url('services', $data);
                            } else {
                                return $data;
                            }
                        }, $files);
                    } else {
                        $service['files'] = [];
                    }
                } else {
                    $service['files'] = [];
                }

                // Process FAQs data with enhanced handling for multiple formats
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

                $this->data['service'] = $service;
                $this->data['tax_data'] = $tax_data;

                // Fetch SEO settings for the service
                $this->seoModel->setTableContext('services');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($service_id, 'full');
                $this->data['service_seo_settings'] = $seo_settings;

                // Fetch languages for translation support
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                // Load translated service details using ServiceService
                $translatedData = $this->serviceService->getServiceWithTranslations($service_id);

                // Process FAQ data with proper fallback logic
                // For each language, try to get FAQs from translations table first, then fall back to main table
                $mergedServiceDetails = $service;

                // Load SEO translations for each language (must be done AFTER $mergedServiceDetails is initialized)
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $seoTranslations = $seoTranslationModel->getAllTranslationsForService($service_id);

                // Attach SEO translations to service data by language code
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        $languageCode = $translation['language_code'];
                        $mergedServiceDetails['translated_seo_' . $languageCode] = [
                            'seo_title' => $translation['seo_title'] ?? '',
                            'seo_description' => $translation['seo_description'] ?? '',
                            'seo_keywords' => $translation['seo_keywords'] ?? '',
                            'seo_schema_markup' => $translation['seo_schema_markup'] ?? ''
                        ];
                    }
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

                return view('backend/partner/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - duplicate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function bulk_import_services()
    {
        if ($this->isLoggedIn) {
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('provider_panel', 'Provider Panel'), 'bulk_import_services');
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    public function bulk_import_service_upload()
    {
        $file = $this->request->getFile('file');
        $filePath = FCPATH . 'public/uploads/service_bulk_upload/';
        if (!is_dir($filePath)) {
            if (!mkdir($filePath, 0775, true)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => labels(FAILED_TO_CREATE_FOLDERS, "Failed to create folders")
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
            //insert
            // Parse headers for translatable fields with language codes
            // Format: Title[en], Description[es], faq[en][question][1], etc.
            foreach ($cellIterator as $cell) {
                $header = $cell->getValue();

                // Match multilanguage translatable fields: Title[en], Description[en], etc.
                // Be lenient with header formatting (extra spaces / uppercase codes) so providers do not lose translations.
                // Allow optional whitespace + mixed-case codes so repeated bulk updates never drop a language column.
                if (preg_match('/^(Title|Description|Long Description|Tags)\s*\[([a-z]{2,})\]\s*$/i', $header, $matches)) {
                    $fieldName = strtolower(str_replace(' ', '_', $matches[1]));
                    $langCode = strtolower(trim($matches[2]));
                    if (!isset($languageHeaders[$fieldName])) {
                        $languageHeaders[$fieldName] = [];
                    }
                    $languageHeaders[$fieldName][$langCode] = $columnIndex;
                }
                // Match multilanguage FAQs: faq[en][question][1], faq[es][answer][1]
                // Keep the same relaxed parsing for FAQ columns to retain every language block from the CSV.
                // Match FAQ headers with the same relaxed rules to keep Hindi / other locales intact.
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
                        labels('multilanguage_missing_required_field', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers"),
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
                    return ErrorResponse(labels(PROVIDER_ID, "Provider ID") . " :: " . $row[0] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                } else if ($row[0] != $this->userId) {
                    return ErrorResponse("Provider ID must be logged in user id", true, [], [], 200, csrf_token(), csrf_hash());
                }
                $category = fetch_details('categories', ['id' => $row[1]]);
                if (empty($category)) {
                    return ErrorResponse(labels(CATEGORY_ID, "Category ID") . " :: " . $row[1] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $tax = fetch_details('taxes', ['id' => $row[6]]); // FIXED: was $row[10], now $row[6]
                if (empty($tax)) {
                    return ErrorResponse(labels(TAX_ID, "Tax ID") . " :: " . $row[6] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
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
                        labels("title_is_required_for_default_language", "Title is required for default language") . " ($defaultLanguage) " . labels('at row', 'at row') . " " . ($rowIndex + 2),
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
                        labels("description_is_required_for_default_language", "Description is required for default language") . " ($defaultLanguage) " . labels('at row', 'at row') . " " . ($rowIndex + 2),
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
                        if (!empty($file)) {
                            $files[] = $file;
                        }
                    }
                }

                // Process main service image (FIXED: was $row[21], now $row[16])
                $image = !empty($row[16]) ? copy_image($row[16], '/public/uploads/services/') : "";

                // Generate slug from default language title (same logic as admin bulk upload)
                // Uses SlugService for consistent, unique slugs across admin and partner panels
                $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';
                $resolvedSlug = $this->slugService->generate(
                    $defaultTitle,
                    $defaultTitle,
                    'services'
                );

                // Prepare service data INCLUDING default language values for fallback
                // Store default language translatable fields in main table as fallback
                // These same values will also be stored in translated_service_details table
                $serviceData = [
                    'user_id' => $row[0], // Index 0: Provider ID
                    'category_id' => $row[1], // Index 1: Category ID
                    // ✅ Include default language translatable fields as fallback
                    'title' => $defaultTitle,
                    'description' => $translatedFields['description'][$defaultLanguage] ?? '',
                    'long_description' => $translatedFields['long_description'][$defaultLanguage] ?? '',
                    'tags' => $translatedFields['tags'][$defaultLanguage] ?? '',
                    'faqs' => $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE),
                    // Auto-generate slug from default language title (SlugService)
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
                    'approved_by_admin' => ($provider[0]['need_approval_for_the_service'] == "1") ? "0" : "1",
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
                        throw new \Exception(labels("failed_to_add_service", "Failed to add service") . " " . labels('at row', 'at row') . " " . ($index + 2));
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
                        throw new \Exception(labels("failed_to_save_translations_for_service_id", "Failed to save translations for service ID") . " $serviceId: " . $errors);
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
                        }
                    }
                }

                $db->transComplete();

                if ($db->transStatus() === false) {
                    throw new \Exception(labels(TRANSACTION_FAILED, 'Transaction failed'));
                }

                return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Services added successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Partner bulk import service error: ' . $e->getMessage());
                return ErrorResponse(labels(ERROR_OCCURED, "Error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } else {
            //update
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
            // Validate default language has required fields
            $requiredFields = ['title', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($languageHeaders[$field][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('multilanguage_missing_required_field', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers"),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
            }

            $data = $sheet->toArray();
            array_shift($data);
            $data = array_filter($data, function ($row) {
                return !empty(array_filter($row));
            });

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

                // Include slug so we can resolve/update it when title changes (same as admin bulk update)
                $fetch_service_data = fetch_details('services', ['id' => $row[0]], ['image', 'other_images', 'files', 'slug']);
                if (empty($fetch_service_data)) {
                    return ErrorResponse(labels('service_id', 'Service ID') . " :: " . $row[0] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
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

                // NEW CSV Structure for UPDATE (after multilanguage support):
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

                // Validate references - FIXED: Provider ID is at index 1 for UPDATE operations
                $provider = fetch_details('partner_details', ['partner_id' => $row[1]]);
                if (empty($provider)) {
                    return ErrorResponse(labels(PROVIDER_ID, "Provider ID") . " :: " . $row[1] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                } else if ($row[1] != $this->userId) {
                    return ErrorResponse(labels(THE_PROVIDER_ID_MUST_MATCH_THE_LOGGED_IN_USER_ID, "The provider ID must match the logged-in user ID."), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $category = fetch_details('categories', ['id' => $row[2]]);
                if (empty($category)) {
                    return ErrorResponse(labels(CATEGORY_ID, "Category ID") . " :: " . $row[2] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $tax = fetch_details('taxes', ['id' => $row[7]]);
                if (empty($tax)) {
                    return ErrorResponse(labels(TAX_ID, "Tax ID") . " :: " . $row[7] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Extract translated fields per language
                $translatedFields = $this->extractTranslatedFieldsFromBulkRow($row, $languageHeaders, $defaultLanguage);

                // Extract SEO translations from this row
                $seoTranslations = $this->extractSeoTranslationsFromRow($row, $headers, $languages);

                // Get service ID for storing SEO translations
                $serviceId = $row[0];

                // Store SEO translations for later saving (after service is updated)
                $serviceSeoTranslations[$serviceId] = $seoTranslations;

                // Validate default language required fields have values
                if (empty($translatedFields['title'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('title_is_required_for_default_language', "Title is required for default language") . ($defaultLanguage) . " " . labels('at row', 'at row') . " " . ($rowIndex + 2),
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
                        labels('description_is_required_for_default_language', "Description is required for default language") . ($defaultLanguage) . " " . labels('at row', 'at row') . " " . ($rowIndex + 2),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
                $other_images = [];
                foreach ($other_image_Headers as $indexes) {
                    $other_image = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($other_image) && !in_array($other_image, $old_other_images)) {
                        $oi = copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($other_image)) {
                            $other_images[] = $oi;
                        }
                    } else if (!empty($old_other_images)) {
                        $other_images = $old_other_images;
                    } else {
                        $other_images = [];
                    }
                }
                $files = [];
                foreach ($FilesHeaders as $indexes) {
                    $file = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($file) && !in_array($file, $old_files)) {
                        $oi = copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($file)) {
                            $files[] = $oi;
                        }
                    } else if (!empty($old_files)) {
                        $files = $old_files;
                    } else {
                        $files = [];
                    }
                }
                // FIXED: Use processed FAQs from translated fields instead of empty array
                $faqs = $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE);
                // FIXED: Use correct row indices for UPDATE CSV structure
                // Index 0: Service ID, Index 1: Provider ID, Index 2: Category ID, etc.
                $image = !empty($row[17]) ? copy_image($row[17], '/public/uploads/services/') : "";

                // Resolve slug from default language title (same logic as admin bulk update)
                // Keeps existing slug if title unchanged; regenerates for legacy slugs; excludes current service ID for uniqueness
                $serviceId = $row[0];
                $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';
                $currentSlug = $fetch_service_data[0]['slug'] ?? null;
                $baseName = !empty($translatedFields['title'][$defaultLanguage])
                    ? $translatedFields['title'][$defaultLanguage]
                    : $defaultTitle;
                $slug = $this->slugService->resolve(
                    $currentSlug,
                    null,
                    $baseName,
                    'services',
                    $serviceId
                );
                if ($currentSlug !== null && $this->slugService->isLegacySlug($currentSlug)) {
                    $slug = $this->slugService->generate(
                        $baseName,
                        $baseName,
                        'services',
                        $serviceId
                    );
                }

                $services[] = [
                    'id' => $serviceId, // Service ID
                    'user_id' => $row[1], // Provider ID
                    'category_id' => $row[2], // Category ID
                    'title' => $defaultTitle, // Title from translations
                    'tags' => $translatedFields['tags'][$defaultLanguage] ?? '', // Tags from translations
                    'description' => $translatedFields['description'][$defaultLanguage] ?? '', // Description from translations
                    // Auto-generate slug from default language title
                    'slug' => $slug,
                    'duration' => $row[3], // Duration
                    'number_of_members_required' => $row[4], // Members Required
                    'max_quantity_allowed' => $row[5], // Max Quantity
                    'long_description' => $translatedFields['long_description'][$defaultLanguage] ?? '', // Long Description from translations
                    'tax_type' => $row[6], // Price Type
                    'tax_id' => $row[7], // Tax ID
                    'price' => $row[8], // Price
                    'discounted_price' => $row[9], // Discounted Price
                    'is_cancelable' => $row[10], // Is Cancelable
                    'cancelable_till' => ($row[10] == 1) ? $row[11] : "", // Cancelable before
                    'is_pay_later_allowed' => $row[12], // Pay Later Allowed
                    'at_store' => $row[13], // At Store
                    'at_doorstep' => $row[14], // At Doorstep
                    'status' => $row[15], // Status
                    'image' => $image,
                    'approved_by_admin' => (!empty($provider) && $provider[0]['need_approval_for_the_service'] == "1") ? "0" : "1",
                    'faqs' => json_encode($faqs, JSON_UNESCAPED_UNICODE),
                    'other_images' => json_encode($other_images),
                    'files' => json_encode($files),
                ];
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
                        throw new \Exception(labels(FAILED_TO_UPDATE_SERVICE, 'Failed to update service') . " " . labels('at row', 'at row') . " " . ($index + 2));
                    }

                    // Save ALL language translations (including default) in translated_service_details table
                    // This provides:
                    // 1. Main table: Has default language values for quick access and fallback
                    // 2. Translations table: Has ALL languages including default for consistency

                    // Get the translated fields for this service
                    $translatedFields = $this->extractTranslatedFieldsFromBulkRow($data[$index], $languageHeaders, $defaultLanguage);

                    // Prepare translation data for ServicesService
                    $translationData = $translatedFields;

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
                        throw new \Exception(labels(FAILED_TO_SAVE_TRANSLATIONS_FOR_SERVICE_ID, 'Failed to save translations for service ID') . " $serviceId: " . $errors);
                    }

                    // Save SEO settings for this service (at the end, after translations)
                    if (isset($serviceSeoTranslations[$serviceId])) {
                        $seoResult = $this->saveBulkServiceSeoSettings(
                            $serviceId,
                            $serviceSeoTranslations[$serviceId],
                            $languages
                        );

                        if (!$seoResult) {
                            log_message('error', "Failed to save SEO settings for service {$serviceId}");
                            // Continue with other services even if SEO save fails
                        }
                    }
                }

                $db->transComplete();

                if ($db->transStatus() === false) {
                    throw new \Exception(labels(TRANSACTION_FAILED, 'Transaction failed'));
                }

                return successResponse(labels(SERVICES_UPDATED_SUCCESSFULLY, 'Services updated successfully'), false, [], [], 200, csrf_token(), csrf_hash());
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Partner bulk update service error: ' . $e->getMessage());
                return ErrorResponse($e->getMessage(), true, [], [], 200, csrf_token(), csrf_hash());
            }
        }
    }

    public function downloadSampleForInsert()
    {
        try {
            // Get available languages from database
            $languages = fetch_details('languages', [], ['code', 'language', 'is_default'], "", '0', 'id', 'ASC');

            // Build headers with non-translatable fields first
            $headers = [
                'Provider ID',
                'Category ID',
                // ❌ Removed: Title, Tags, Short Description, Description (these are translatable)
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
                $this->userId, // Provider ID (logged in user)
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
                } elseif ($langCode === 'es') {
                    $sampleRow[] = 'Servicio de Limpieza de Casa'; // Title[es]
                    $sampleRow[] = 'Servicio profesional de limpieza de casa'; // Description[es]
                    $sampleRow[] = 'Proporcionamos limpieza completa de su hogar incluyendo todas las habitaciones, cocina y baños'; // Long Description[es]
                    $sampleRow[] = 'limpieza,casa,profesional'; // Tags[es]
                    $sampleRow[] = '¿Qué áreas limpian?'; // faq[es][question][1]
                    $sampleRow[] = 'Limpiamos todas las habitaciones, cocina, baños y áreas comunes'; // faq[es][answer][1]
                    $sampleRow[] = '¿Cuánto tiempo toma?'; // faq[es][question][2]
                    $sampleRow[] = 'Típicamente 2-3 horas dependiendo del tamaño de la casa'; // faq[es][answer][2]
                } elseif ($langCode === 'ar') {
                    $sampleRow[] = 'خدمة تنظيف المنزل'; // Title[ar]
                    $sampleRow[] = 'خدمة تنظيف منزلية احترافية'; // Description[ar]
                    $sampleRow[] = 'نوفر تنظيفًا شاملاً لمنزلك بما في ذلك جميع الغرف والمطبخ والحمامات'; // Long Description[ar]
                    $sampleRow[] = 'تنظيف,منزل,احترافي'; // Tags[ar]
                    $sampleRow[] = 'ما هي المناطق التي تنظفونها؟'; // faq[ar][question][1]
                    $sampleRow[] = 'ننظف جميع الغرف والمطبخ والحمامات والمناطق المشتركة'; // faq[ar][answer][1]
                    $sampleRow[] = 'كم يستغرق الوقت؟'; // faq[ar][question][2]
                    $sampleRow[] = 'عادة 2-3 ساعات حسب حجم المنزل'; // faq[ar][answer][2]
                } else {
                    // For other languages, leave empty (optional)
                    $sampleRow[] = ''; // Title
                    $sampleRow[] = ''; // Description
                    $sampleRow[] = ''; // Long Description
                    $sampleRow[] = ''; // Tags
                    $sampleRow[] = ''; // faq[question][1]
                    $sampleRow[] = ''; // faq[answer][1]
                    $sampleRow[] = ''; // faq[question][2]
                    $sampleRow[] = ''; // faq[answer][2]
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
                throw new \Exception(labels(FAILED_TO_OPEN_OUTPUT_STREAM, "Failed to open output stream."));
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="service_sample_without_data_multilanguage.csv"');
            fputcsv($output, $headers);
            fputcsv($output, $sampleRow); // Add sample data row
            fclose($output);
            exit;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - download-sample-for-insert()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function downloadSampleForUpdate()
    {
        try {
            // Get active languages for multilanguage support
            $languageModel = new Language_model();
            $languages = $languageModel->findAll();
            $defaultLanguage = get_settings('default_language', true);

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

            // Fetch only the logged-in partner's services
            $services = fetch_details('services', ['user_id' => $this->userId]);

            // Prepare data
            $all_data = [];
            $translationModel = new TranslatedServiceDetails_model();

            foreach ($services as $service) {
                $row = [];

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

                // Get translations
                $translations = $translationModel->where('service_id', $service['id'])->findAll();

                $translationsByLang = [];
                foreach ($translations as $trans) {
                    $translationsByLang[$trans['language_code']] = $trans;
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

                    if (isset($translationsByLang[$langCode])) {
                        // Translation exists for this language - use it
                        $trans = $translationsByLang[$langCode];

                        // Add translatable text fields
                        $row[] = strval($trans['title'] ?? '');
                        $row[] = strval($trans['description'] ?? '');
                        $row[] = strval(strip_tags(htmlspecialchars_decode(stripslashes($trans['long_description'] ?? ''))));
                        $row[] = strval($trans['tags'] ?? '');

                        // Handle FAQs
                        $faqsJson = $trans['faqs'] ?? '[]';
                        $faqs = @json_decode($faqsJson, true);

                        if (!is_array($faqs)) {
                            $faqs = [];
                        }

                        // Add first FAQ
                        if (isset($faqs[0]) && is_array($faqs[0])) {
                            $row[] = strval($faqs[0]['question'] ?? '');
                            $row[] = strval($faqs[0]['answer'] ?? '');
                        } else {
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
                            // Default language - use fallback from base table or default language translation
                            $fallbackTrans = null;

                            if ($defaultLangCode && isset($translationsByLang[$defaultLangCode])) {
                                $fallbackTrans = $translationsByLang[$defaultLangCode];
                            }

                            // Use fallback translation if available, otherwise use base service table data
                            // Base service table stores default language data as fallback
                            $title = $fallbackTrans['title'] ?? $service['title'] ?? '';
                            $description = $fallbackTrans['description'] ?? $service['description'] ?? '';
                            $longDescription = $fallbackTrans['long_description'] ?? $service['long_description'] ?? '';
                            $tags = $fallbackTrans['tags'] ?? $service['tags'] ?? '';
                            $faqsJson = $fallbackTrans['faqs'] ?? $service['faqs'] ?? '[]';
                        } else {
                            // Non-default language → NO fallback
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
                        if (!is_array($faqs)) {
                            $faqs = [];
                        }

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
                $otherImages = @json_decode($otherImagesJson, true);

                if (!is_array($otherImages)) {
                    $otherImages = [];
                }


                $row[] = isset($otherImages[0]) && is_string($otherImages[0]) ? $otherImages[0] : '';
                $row[] = isset($otherImages[1]) && is_string($otherImages[1]) ? $otherImages[1] : '';

                // Handle files
                $filesJson = $service['files'] ?? '[]';
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

                //     $type = gettype($value);
                //     if ($type !== 'string') {
                //         log_message('error', 'NON-STRING VALUE at index ' . $index . ' - Type: ' . $type . ', Value: ' . print_r($value, true));
                //     }
                // }

                $all_data[] = $row;
            }


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
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - downloadSampleForUpdate()');
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
                $_SESSION['toastMessage'] = labels(CANNOT_DOWNLOAD, 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - download_service_add_instruction_file()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
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
                $_SESSION['toastMessage'] = labels(CANNOT_DOWNLOAD, 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - download_service_add_instruction_file()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Remove SEO image for a service (partner side)
     * This method handles AJAX requests to remove SEO images
     * @return \CodeIgniter\HTTP\Response
     */
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $serviceId = $this->request->getPost('service_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$serviceId) {
                return ErrorResponse(labels(SERVICE_ID_IS_REQUIRED, 'Service ID is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Verify that the service belongs to the logged-in partner
            $service = fetch_details('services', ['id' => $serviceId, 'user_id' => $this->userId]);
            if (empty($service)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, 'Data not found'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Set SEO model context for services
            $this->seoModel->setTableContext('services');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

            if (!$existingSettings) {
                return ErrorResponse(labels(DATA_NOT_FOUND, 'Data not found'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if there's an image to remove
            if (empty($existingSettings['image'])) {
                return ErrorResponse(labels(NO_SEO_IMAGE_FOUND_TO_REMOVE, 'No SEO image found to remove'), true, [], [], 200, csrf_token(), csrf_hash());
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

            return successResponse(labels('seo_image_removed_successfully', 'SEO Image Removed Successfully'), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Save service SEO settings with multi-language support
     * 
     * @param int $serviceId The service ID to associate SEO settings with
     * @return void
     * @throws \Exception If SEO settings saving fails
     */
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
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - saveServiceSeoSettings()');
            throw $th; // Re-throw to handle in add_service
        }
    }

    /**
     * Parse keywords from various input formats (Tagify JSON, arrays, strings)
     * 
     * @param mixed $input The input keywords data
     * @return string Comma-separated keywords string
     */
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
        $translatedFields = [];
        $translatableFields = ['title', 'description', 'long_description', 'tags', 'faqs'];

        // Check if we have clean FAQ data from the new structure
        if (isset($postData['faqs']) && is_string($postData['faqs'])) {
            $faqData = json_decode(htmlspecialchars_decode($postData['faqs'] ?? ''), true);
            $jsonError = json_last_error();
            if ($jsonError === JSON_ERROR_NONE && is_array($faqData)) {
                // Process clean FAQ data
                $cleanFaqFields = $this->processCleanFAQData($faqData);
                $translatedFields = array_merge($translatedFields, $cleanFaqFields);

                // Remove the raw FAQ data from postData to avoid double processing
                unset($postData['faqs']);
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
                        $translatedFields[$field][$languageCode] = $this->processTagsValue($value);
                    } else {
                        // For other fields, just trim the value
                        $translatedFields[$field][$languageCode] = trim($value);
                    }
                }
            } else {
                // If field is not in form data but this is an update, preserve existing data
                if ($serviceId && isset($existingTranslations)) {
                    foreach ($existingTranslations as $languageCode => $translation) {
                        if (isset($translation[$field]) && !empty($translation[$field])) {
                            $translatedFields[$field][$languageCode] = $translation[$field];
                        }
                    }
                }
            }
        }

        // Ensure default language values are also stored in translations table
        // This is important for consistency and to support the memory requirement
        if (isset($postData['title']) && is_array($postData['title']) && isset($postData['title'][$defaultLanguage])) {
            $translatedFields['title'][$defaultLanguage] = trim($postData['title'][$defaultLanguage]);
        }
        if (isset($postData['description']) && is_array($postData['description']) && isset($postData['description'][$defaultLanguage])) {
            $translatedFields['description'][$defaultLanguage] = trim($postData['description'][$defaultLanguage]);
        }
        if (isset($postData['long_description']) && is_array($postData['long_description']) && isset($postData['long_description'][$defaultLanguage])) {
            $translatedFields['long_description'][$defaultLanguage] = trim($postData['long_description'][$defaultLanguage]);
        }
        if (isset($postData['tags']) && is_array($postData['tags']) && isset($postData['tags'][$defaultLanguage])) {
            $translatedFields['tags'][$defaultLanguage] = $this->processTagsValue($postData['tags'][$defaultLanguage]);
        }
        if (isset($postData['faqs']) && is_array($postData['faqs']) && isset($postData['faqs'][$defaultLanguage])) {
            $defaultFaqsData = $postData['faqs'][$defaultLanguage];
            if (!empty($defaultFaqsData)) {
                $translatedFields['faqs'][$defaultLanguage] = json_encode($defaultFaqsData, JSON_UNESCAPED_UNICODE);
            }
        }

        return $translatedFields;
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
                $translatedFields['faqs'][$languageCode] = json_encode($processedFaqs, JSON_UNESCAPED_UNICODE);
            } else {
                // Store empty array for languages with no FAQs
                $translatedFields['faqs'][$languageCode] = json_encode([], JSON_UNESCAPED_UNICODE);
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


        return $translatedFields;
    }

    /**
     * Process tags value and convert to comma-separated string
     * 
     * @param mixed $tagsValue The tags value from form data
     * @return string Comma-separated string of tag values
     */
    private function processTagsValue($tagsValue): string
    {
        if (empty($tagsValue)) {
            return '';
        }

        /**
         * Keep this logic aligned with Admin `Services::processTags()`.
         *
         * Why:
         * - Some form submissions send tags as an array wrapper like: [0 => '...'].
         * - Some UIs submit JSON with HTML entities (e.g. &quot;) instead of raw quotes.
         * - Admin normalizes both cases before decoding JSON and extracting `value`.
         *   If partner does not, tags can be stored differently across panels.
         */

        // 1️⃣ Unwrap array like [0 => '...'] (common in some form submits)
        if (is_array($tagsValue) && count($tagsValue) === 1) {
            $tagsValue = reset($tagsValue);
        }

        // 2️⃣ Decode HTML entities (&quot;) so JSON decoding can succeed
        if (is_string($tagsValue)) {
            $tagsValue = html_entity_decode($tagsValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $tags = [];

        // Step 1: Normalize input into array
        if (is_string($tagsValue)) {
            $decoded = json_decode($tagsValue, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $tagsValue = $decoded;
            } else {
                // Plain string like "Test, Demo"
                return trim($tagsValue);
            }
        }

        // Step 2: Extract values
        if (is_array($tagsValue)) {
            foreach ($tagsValue as $item) {
                if (is_string($item)) {
                    $tags[] = trim($item);
                } elseif (is_array($item) && isset($item['value'])) {
                    $tags[] = trim($item['value']);
                }
            }
        }

        return implode(', ', array_filter($tags));
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
                            $value = $this->processTagsValue($value);
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
            log_message('error', 'Exception in saveBulkServiceSeoSettings (partner): ' . $e->getMessage());
            log_message('error', 'Exception trace: ' . $e->getTraceAsString());
            return false;
        }
    }
}
