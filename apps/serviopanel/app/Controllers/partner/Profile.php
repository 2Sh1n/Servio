<?php

namespace App\Controllers\partner;

use App\Models\Seo_model;
use App\Services\PartnerService;
use App\Services\utility\SlugService;
use Exception;

class Profile extends Partner
{
    protected $validationListTemplate = 'list';
    protected $seoModel;
    protected $partnerService;
    protected SlugService $slugService;

    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
        $this->seoModel = new Seo_model();
        $this->partnerService = new PartnerService();
        $this->slugService = new SlugService();
    }
    public function index()
    {
        if ($this->isLoggedIn) {
            setPageInfo($this->data, labels('profile', 'Profile') . ' | ' . labels('provider_panel', 'Provider Panel'), 'profile');
            $partner_details = !empty(fetch_details('partner_details', ['partner_id' => $this->userId])) ? fetch_details('partner_details', ['partner_id' => $this->userId])[0] : [];
            $partner_timings = !empty(fetch_details('partner_timings', ['partner_id' => $this->userId])) ? fetch_details('partner_timings', ['partner_id' => $this->userId]) : [];
            $disk = fetch_current_file_manager();

            $partner_details['banner'] = get_file_url($disk, $partner_details['banner'], 'public/backend/assets/default.png', 'banner');

            // fetch languages early so loadCustomFieldDefinitions can use them
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            $cfDefs = $this->loadCustomFieldDefinitions($languages);

            // Build the full list of custom-field ids from both groups dynamically.
            $allCfIds = array_map(static fn ($f) => $f['id'], array_merge($cfDefs['documents'], $cfDefs['bank_details']));

            // Fetch custom field values keyed by custom_field_id.
            $customFieldValues = $this->getPartnerCustomFieldValuesById($this->userId, $allCfIds);

            // Resolve file URLs generically for all file-type custom fields.
            foreach (array_merge($cfDefs['documents'], $cfDefs['bank_details']) as $cfField) {
                if ($cfField['field_type'] === 'file') {
                    $cfId = $cfField['id'];
                    $rawVal = $customFieldValues[$cfId] ?? '';
                    if ($rawVal !== '') {
                        $customFieldValues[$cfId] = get_file_url($disk, $rawVal, 'public/backend/assets/default.png', 'custom_fields');
                    }
                }
            }

            $this->data['custom_field_values'] = $customFieldValues;

            // Process other images
            if (!empty($partner_details['other_images'])) {
                $decodedImages = json_decode($partner_details['other_images'], true);
                $updatedImages = [];
                foreach ($decodedImages as $data) {
                    // Ensure we're not adding base URL to a path that already has it
                    if (strpos($data, 'http') === 0) {
                        $updatedImages[] = $data;
                    } else {
                        $updatedImages[] = get_file_url($disk, $data, '', 'partner');
                    }
                }
                $partner_details['other_images'] = $updatedImages;
            } else {
                $partner_details['other_images'] = [];
            }


            // Process user details
            // NOTE: We now also pass loginType to the view so that the UI
            // can decide which identity fields (email / phone / country_code)
            // should be readonly based on how the provider originally registered.
            $user_details = fetch_details('users', ['id' => $this->userId])[0];
            $user_details['image'] = get_file_url($disk,  $user_details['image'],  '',  'profile');
            $this->data['data'] = $user_details;
            // Expose loginType separately for clearer usage in views.
            $this->data['loginType'] = $user_details['loginType'] ?? null;

            // Don't assign partner_details to data yet - we need to add translations first
            $this->data['partner_timings'] = array_reverse($partner_timings);
            $settings = get_settings('general_settings', true);
            $user_id = $this->ionAuth->getUserId();
            $admin_commission = fetch_details('partner_details', ['partner_id' => $user_id], 'admin_commission');
            $this->data['city_id']  = fetch_details('users', ['id' => $user_id], 'city')[0]['city'];
            $this->data['city'] = $this->data['city_id'];
            $this->data['admin_commission'] = $admin_commission[0]['admin_commission'];
            $this->data['currency'] = $settings['currency'];
            $this->data['city_name'] = $this->data['city_id'];
            $this->data['passport_verification_status'] = $settings['passport_verification_status'] ?? 0;
            $this->data['national_id_verification_status'] = $settings['national_id_verification_status'] ?? 0;
            $this->data['address_id_verification_status'] = $settings['address_id_verification_status'] ?? 0;
            $this->data['passport_required_status'] = $settings['passport_required_status'] ?? 0;
            $this->data['national_id_required_status'] = $settings['national_id_required_status'] ?? 0;
            $this->data['address_id_required_status'] = $settings['address_id_required_status'] ?? 0;

            $this->data['allow_pre_booking_chat'] = $settings['allow_pre_booking_chat'] ?? 0;
            $this->data['allow_post_booking_chat'] = $settings['allow_post_booking_chat'] ?? 0;

            $this->seoModel->setTableContext('providers');
            $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($this->userId, 'full');

            // Load SEO translations and merge with main SEO settings
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');
            $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($this->userId);

            // Always merge SEO translations with main SEO settings (even if no translations exist)
            $mergedSeoSettings = $seo_settings;

            // ($languages is already fetched and set above via loadCustomFieldDefinitions)

            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefault = $language['is_default'] == 1;

                // Find SEO translation for this language
                $seoTranslation = null;
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        if ($translation['language_code'] === $languageCode) {
                            $seoTranslation = $translation;
                            break;
                        }
                    }
                }

                if ($seoTranslation) {
                    // Create language-specific SEO settings from translation
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $seoTranslation['seo_title'] ?? '',
                        'description' => $seoTranslation['seo_description'] ?? '',
                        'keywords' => $seoTranslation['seo_keywords'] ?? '',
                        'schema_markup' => $seoTranslation['seo_schema_markup'] ?? ''
                    ];
                } else {
                    // If no SEO translation exists, use base table data for default language, empty for others
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $isDefault ? ($seo_settings['title'] ?? '') : '',
                        'description' => $isDefault ? ($seo_settings['description'] ?? '') : '',
                        'keywords' => $isDefault ? ($seo_settings['keywords'] ?? '') : '',
                        'schema_markup' => $isDefault ? ($seo_settings['schema_markup'] ?? '') : ''
                    ];
                }
            }

            $this->data['partner_seo_settings'] = $mergedSeoSettings;

            // Prepare country code data for the view
            $user_country_code = $this->data['data']['country_code'] ?? '';
            $country_code_data = prepare_country_code_data($user_country_code);
            $this->data['country_codes'] = $country_code_data['country_codes'];
            $this->data['selected_country_code'] = $country_code_data['selected_country_code'];

            // ($this->data['languages'] already set above)

            // Pass custom-field definitions and labels to the view.
            $this->data['documents_custom_fields']         = $cfDefs['documents'];
            $this->data['bank_details_custom_fields']      = $cfDefs['bank_details'];
            $this->data['custom_field_labels_by_language'] = $cfDefs['labels_by_language'];

            // Load translated partner details using PartnerService
            if (!empty($partner_details)) {
                $translatedData = $this->partnerService->getPartnerWithTranslations($this->userId);

                if ($translatedData['success']) {
                    // Merge translated data with partner details for each language
                    foreach ($languages as $language) {
                        $languageCode = $language['code'];
                        if (isset($translatedData['translated_data'][$languageCode])) {
                            $translation = $translatedData['translated_data'][$languageCode];

                            // Create language-specific partner details
                            $partner_details['translated_' . $languageCode] = [
                                'username' => $translation['username'] ?? $data['username'],
                                'company_name' => $translation['company_name'] ?? $partner_details['company_name'],
                                'about' => $translation['about'] ?? $partner_details['about'],
                                'long_description' => $translation['long_description'] ?? $partner_details['long_description']
                            ];
                        } else {
                            // If no translation exists, create default structure
                            $partner_details['translated_' . $languageCode] = [
                                'username' => $user_details['username'],
                                'company_name' => $partner_details['company_name'],
                                'about' => $partner_details['about'],
                                'long_description' => $partner_details['long_description']
                            ];
                        }
                    }
                }
            }

            // Now assign the partner_details with translations to the data array
            $this->data['partner_details'] = $partner_details;

            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }
    public function update_profile()
    {
        try {
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                $response['error'] = true;
                $response['message'] = DEMO_MODE_ERROR;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                return $this->response->setJSON($response);
            }
            if (isset($_POST) && !empty($_POST)) {
                helper('function');
                try {
                    $config = new \Config\IonAuth();
                    $tables  = $config->tables;
                    $postData = $this->request->getPost();

                    // Dynamically fetch all visible file-type custom fields for validation and upload.
                    $visibleFileCustomFields = $this->getVisibleFileCustomFields();
                    $fileFieldIds = array_map(fn ($cf) => (int) $cf['id'], $visibleFileCustomFields);
                    $existingDocValues = !empty($fileFieldIds) ? $this->getPartnerCustomFieldValuesById($this->userId, $fileFieldIds) : [];
 
                     // Base validation rules that are always required
                     $validationRules = [
                         'email' => [
                             "rules" => 'required|trim',
                             "errors" => [
                                 "required" => labels(PLEASE_ENTER_PROVIDERS_EMAIL, "Please enter providers email"),
                             ]
                         ],
                         'phone' => [
                             "rules" => 'required|numeric|',
                             "errors" => [
                                 "required" => labels(PLEASE_ENTER_PROVIDERS_PHONE_NUMBER, "Please enter providers phone number"),
                                 "numeric" => labels(PLEASE_ENTER_NUMERIC_PHONE_NUMBER, "Please enter numeric phone number"),
                                 "is_unique" => labels(THIS_PHONE_NUMBER_IS_ALREADY_REGISTERED, "This phone number is already registered")
                             ]
                         ],
                         'address' => [
                             "rules" => 'required|trim',
                             "errors" => [
                                 "required" => labels(PLEASE_ENTER_ADDRESS, "Please enter address"),
                             ]
                         ],
                         'latitude' => [
                             "rules" => 'required|trim',
                             "errors" => [
                                 "required" => labels(PLEASE_CHOOSE_PROVIDER_LOCATION, "Please choose provider location"),
                             ]
                         ],
                         'longitude' => [
                             "rules" => 'required|trim',
                             "errors" => [
                                 "required" => labels(PLEASE_CHOOSE_PROVIDER_LOCATION, "Please choose provider location"),
                             ]
                         ],
                         'type' => [
                             "rules" => 'required',
                             "errors" => [
                                 "required" => labels(PLEASE_SELECT_PROVIDERS_TYPE, "Please select providers type"),
                             ]
                         ],
                         'visiting_charges' => [
                             "rules" => 'required|numeric',
                             "errors" => [
                                 "required" => labels(PLEASE_ENTER_VISITING_CHARGES, "Please enter visiting charges"),
                                 "numeric" => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_VISITING_CHARGES, "Please enter numeric value for visiting charges")
                             ]
                         ],
                         'advance_booking_days' => [
                             "rules" => 'required|numeric',
                             "errors" => [
                                 "required" => labels(PLEASE_ENTER_ADVANCE_BOOKING_DAYS, "Please enter advance booking days"),
                                 "numeric" => labels(PLEASE_ENTER_NUMERIC_ADVANCE_BOOKING_DAYS, "Please enter numeric advance booking days")
                             ]
                         ],
                         'start_time' => [
                             "rules" => 'required',
                             "errors" => [
                                 "required" => labels(PLEASE_ENTER_PROVIDERS_WORKING_DAYS, "Please enter providers working days"),
                                 "valid_time" => labels(PLEASE_ENTER_VALID_TIME_FOR_PROVIDERS_WORKING_DAYS, "Please enter valid time for providers working days")
                             ]
                         ],
                         'end_time' => [
                             "rules" => 'required',
                             "errors" => [
                                 "required" => labels(PLEASE_ENTER_PROVIDERS_WORKING_PROPERLY, "Please enter providers working properly "),
                                 "valid_time" => labels(PLEASE_ENTER_VALID_TIME_FOR_PROVIDERS_WORKING_PROPERLY, "Please enter valid time for providers working properly")
                             ]
                         ],
                     ];
 
                     // Dynamically add validation rules for all visible + required file-type custom fields.
                     // For updates: if document already exists in DB, permit_empty; otherwise require upload.
                     foreach ($visibleFileCustomFields as $cfRow) {
                         if (!$cfRow['required']) {
                             continue;
                         }
                         $cfId = (int) $cfRow['id'];
                         $inputName = 'cf_' . $cfId;
                         $fileConfig = $cfRow['file_config'];
                         $maxSizeKb = (int)(($fileConfig['max_size_mb'] ?? 2) * 1024);
                         $mimeTypes = $this->extensionsToMimeTypes($fileConfig['allowed_types'] ?? []);
                         $label = 'Custom Field ' . $cfId;
                         $hasExisting = !empty($existingDocValues[$cfId]);

                         if ($hasExisting) {
                             $rules = "permit_empty|max_size[{$inputName},{$maxSizeKb}]";
                             $errors = [
                                 'max_size' => labels('custom_field_file_size_exceeds_limit', "File size should not exceed " . ($fileConfig['max_size_mb'] ?? 2) . "MB"),
                             ];
                         } else {
                             $rules = "uploaded[{$inputName}]|max_size[{$inputName},{$maxSizeKb}]";
                             $errors = [
                                 'uploaded' => labels('please_upload_a_valid_custom_field_document', "Please upload a valid document"),
                                 'max_size' => labels('custom_field_file_size_exceeds_limit', "File size should not exceed " . ($fileConfig['max_size_mb'] ?? 2) . "MB"),
                             ];
                         }
                         if (!empty($mimeTypes)) {
                             $rules .= "|mime_in[{$inputName}," . implode(',', $mimeTypes) . "]";
                             $errors['mime_in'] = labels('custom_field_must_be_a_valid_file', "File must be a valid file type");
                         }
                         $validationRules[$inputName] = [
                             'rules' => $rules,
                             'errors' => $errors,
                         ];
                     }
 
                     $this->validation->setRules($validationRules);
                     if (!$this->validation->withRequest($this->request)->run()) {
                         $errors = $this->validation->getErrors();
                         return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
                     } else {
                         $latitude = number_format($this->request->getPost('latitude'), 6, '.', '');
                         $longitude = number_format($this->request->getPost('longitude'), 6, '.', '');
 
                         // Validate coordinates
                         $this->validateCoordinates(
                             $latitude,
                             $longitude
                         );
 
                         if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                             $response['error'] = true;
                             $response['message'] = labels(DEMO_MODE_ERROR, "Demo mode error");
                             $response['csrfName'] = csrf_token();
                             $response['csrfHash'] = csrf_hash();
                             return $this->response->setJSON($response);
                         }
                         // Fetch current user record to enforce loginType-based
                         // restrictions on which identity fields are allowed to change.
                         // We always trust the database as the source of truth here.
                        $data = fetch_details('users', ['id' => $this->userId])[0];
                        $IdProofs = fetch_details(
                            'partner_details',
                            ['partner_id' => $this->userId],
                            ['other_images', 'banner', 'company_name', 'slug']
                        )[0];
                         $old_image = $data['image'];
                         $old_banner = $IdProofs['banner'];

                        // Dynamically fetch old values for all visible file-type custom fields.
                        $oldDocValues = !empty($fileFieldIds) ? $this->getPartnerCustomFieldValuesById($this->userId, $fileFieldIds) : [];
                         $old_other_images = fetch_details('partner_details', ['partner_id' => $this->userId], ['other_images']);
                         $disk = fetch_current_file_manager();

                         $paths = [
                             'image' => [
                                 'file' => $this->request->getFile('image'),
                                 'path' => 'public/backend/assets/profile/',
                                 'error' => labels(FAILED_TO_CREATE_PROFILE_FOLDERS, "Failed to create profile folders"),
                                 'folder' => 'profile',
                                 'old_file' => $old_image,
                                 'disk' => $disk,
                             ],
                             'banner' => [
                                 'file' => $this->request->getFile('banner'),
                                 'path' => 'public/backend/assets/banner/',
                                 'error' => labels(FAILED_TO_CREATE_BANNER_FOLDERS, "Failed to create banner folders"),
                                 'folder' => 'banner',
                                 'old_file' => $old_banner,
                                 'disk' => $disk,
                             ],
                         ];

                         // Dynamically add upload paths for all visible file-type custom fields.
                         foreach ($visibleFileCustomFields as $cfRow) {
                             $cfId = (int) $cfRow['id'];
                             $inputName = 'cf_' . $cfId;
                             $paths[$inputName] = [
                                 'file' => $this->request->getFile($inputName),
                                 'path' => 'public/uploads/custom_fields/',
                                 'error' => labels('failed_to_create_custom_fields_folders', "Failed to create custom fields folders"),
                                 'folder' => 'custom_fields',
                                 'old_file' => $oldDocValues[$cfId] ?? '',
                                 'disk' => $disk,
                             ];
                         }
 
                         // Process single file uploads
                         $uploadedFiles = [];
                         foreach ($paths as $key => $config) {
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
                                         return ErrorResponse(labels($result['message'], $result['message']), true, [], [], 200, csrf_token(), csrf_hash());
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
 
                         $multipleFiles = $this->request->getFiles('filepond');
                         $uploadedOtherImages = [];
                         $old_other_images_array = json_decode($IdProofs['other_images'], true);
                         $other_images_disk = $disk;
 
                         // Process existing images - handle removals
                         $existingOtherImages = [];
                         $removeOtherImages = $this->request->getPost('remove_other_images');
 
                         if ($this->request->getPost('existing_other_images')) {
                             $existingImagesArr = $this->request->getPost('existing_other_images');
 
                             foreach ($existingImagesArr as $index => $img) {
                                 // Check if this image is marked for removal
                                 if (isset($removeOtherImages[$index]) && $removeOtherImages[$index] == '1') {
                                     // Delete image
                                     // Remove base URL if it exists in the image path
                                     $cleanImg = str_replace(base_url(), '', $img);
                                     delete_file_based_on_server('partner', $cleanImg, $other_images_disk);
                                 } else {
                                     // Keep image - remove base URL if present
                                     $cleanImg = str_replace(base_url(), '', $img);
                                     $existingOtherImages[] = $cleanImg;
                                 }
                             }
                         }
 
                         // Handle new uploads
                         if (isset($multipleFiles['other_service_image_selector_edit'])) {
                             foreach ($multipleFiles['other_service_image_selector_edit'] as $file) {
                                 if ($file->isValid()) {
                                     $result = upload_file($file, 'public/uploads/partner/', labels(FAILED_TO_UPLOAD_OTHER_IMAGES, "Failed to upload other images"), 'partner');
                                     if ($result['error'] == false) {
                                         $uploadedOtherImages[] = $result['disk'] === "local_server"
                                             ? 'public/uploads/partner/' . $result['file_name']
                                             : $result['file_name'];
                                     } else {
                                         return ErrorResponse(labels($result['message'], $result['message']), true, [], [], 200, csrf_token(), csrf_hash());
                                     }
                                 }
                             }
                         }
 
                         // Combine existing and new images
                         $finalOtherImages = array_merge($existingOtherImages, $uploadedOtherImages);
                         $other_images = !empty($finalOtherImages) ? json_encode($finalOtherImages) : '[]';
 
                         $banner = $uploadedFiles['banner']['url'] ?? 'public/backend/assets/banner/' . $this->request->getFile('banner_image')->getName();

                         $bannerUrl = $uploadedFiles['banner']['url'] ?? '';
                         if ($bannerUrl !== null && $bannerUrl !== '') {
                             if (isset($uploadedFiles['banner']['disk']) && $uploadedFiles['banner']['disk'] == 'local_server') {
                                 $uploadedFiles['banner']['url'] = preg_replace('#(public/backend/assets/banner/)+#', '', $bannerUrl);
                                 $banner = 'public/backend/assets/banner/' . $uploadedFiles['banner']['url'];
                             } else if (isset($uploadedFiles['banner']['disk']) && $uploadedFiles['banner']['disk'] == 'aws_s3') {
                                 $banner = $bannerUrl;
                             } else {
                                 $banner = 'public/backend/assets/banner/' . $bannerUrl;
                                 $uploadedFiles['banner']['url'] = preg_replace('#(public/backend/assets/banner/)+#', '', $bannerUrl);
                                 $banner = 'public/backend/assets/banner/' . $uploadedFiles['banner']['url'];
                             }
                         } else {
                             $banner = $old_banner ?? $banner;
                         }
                         // Dynamically resolve uploaded file paths for all visible file-type custom fields.
                         $uploadedFileCustomFieldValues = [];
                         foreach ($visibleFileCustomFields as $cfRow) {
                             $cfId = (int) $cfRow['id'];
                             $inputName = 'cf_' . $cfId;
                             if (!isset($uploadedFiles[$inputName])) {
                                 continue;
                             }
                             $oldVal = $oldDocValues[$cfId] ?? '';
                             $url = $uploadedFiles[$inputName]['url'] ?? $oldVal;
                             $fileDisk = $uploadedFiles[$inputName]['disk'] ?? '';

                             if ($fileDisk === 'local_server') {
                                 if ($url !== null && $url !== '') {
                                     $filename = basename($url);
                                     $url = 'public/uploads/custom_fields/' . $filename;
                                 } else {
                                     $url = '';
                                 }
                             }
                             $uploadedFileCustomFieldValues[$cfId] = $url;
                         }

                         // Update partner details
                         $partnerIDS = [
                             'banner' => $banner,
                         ];

                         if ($partnerIDS) {
                             update_details(
                                 $partnerIDS,
                                 ['partner_id' => $this->userId],
                                 'partner_details',
                                 false
                             );
                         }

                        // Persist all visible document custom field values into `partner_custom_fields`.
                        $this->upsertPartnerCustomFieldsValues($this->userId, $uploadedFileCustomFieldValues);
                         $image = $uploadedFiles['image']['url'] ?? 'public/backend/assets/profile/' . $this->request->getFile('image')->getName();
                         $imageUrl = $uploadedFiles['image']['url'] ?? '';
                         if ($imageUrl !== null && $imageUrl !== '' && isset($uploadedFiles['image']['disk']) && $uploadedFiles['image']['disk'] == 'local_server') {
                             $uploadedFiles['image']['url'] = preg_replace('#^public/backend/assets/profile/#', '', $imageUrl);
                             $image = 'public/backend/assets/profile/' . $uploadedFiles['image']['url'];
                         }
                         // Get default language username for users table
                         $defaultLanguage = 'en'; // fallback
                         $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
                         foreach ($languages as $language) {
                             if ($language['is_default'] == 1) {
                                 $defaultLanguage = $language['code'];
                                 break;
                             }
                         }
                         $defaultUsername = $this->request->getPost('username[' . $defaultLanguage . ']') ?? $this->request->getPost('username') ?? '';
 
                         // Prepare base user payload from the request.
                         // We will later lock down specific fields based on loginType
                         // so that a provider cannot change the credential that they
                         // use to authenticate (email vs phone/country_code).
                         $userData = [
                             'username' => $defaultUsername,
                             'email' => $this->request->getPost('email'),
                             'phone' => $this->request->getPost('phone'),
                             'country_code' => $this->request->getPost('country_code'),
                             'image' => $image,
                             'latitude' => $latitude,
                             'longitude' => $longitude,
                             'city' => $this->request->getPost('city'),
                         ];

                        // Enforce loginType-based immutability for identity fields.
                        // - If loginType is "phone": phone + country_code must remain the
                        //   same as originally stored; email can still be updated.
                        // - If loginType is "email": email must remain unchanged; phone
                        //   and country_code can be updated.
                        // This protects the primary login identifier from being altered
                        // while still allowing the provider to manage secondary contact
                        // information.
                        $currentLoginType = $data['loginType'] ?? null;
                        $currentEmail = $data['email'] ?? null;
                        $currentPhone = $data['phone'] ?? null;
                        $currentCountryCode = $data['country_code'] ?? null;

                        if ($currentLoginType === 'phone') {
                            // Lock phone and country_code to stored values.
                            $userData['phone'] = $currentPhone;
                            $userData['country_code'] = $currentCountryCode;
                        } elseif ($currentLoginType === 'email') {
                            // Lock email to stored value.
                            $userData['email'] = $currentEmail;
                        }

                        if ($userData) {
                            update_details($userData, ['id' => $this->userId], 'users');
                        }
                        // Get default language values for main table storage
                        $defaultCompanyName = $this->request->getPost('company_name[' . $defaultLanguage . ']') ?? $this->request->getPost('company_name') ?? '';
                        $defaultAbout = $this->request->getPost('about[' . $defaultLanguage . ']') ?? $this->request->getPost('about') ?? '';
                        $defaultLongDescription = $this->request->getPost('long_description[' . $defaultLanguage . ']') ?? $this->request->getPost('long_description') ?? '';

                        // Slug generation logic aligned with admin Partners::update_partner
                        $existingSlug = $IdProofs['slug'] ?? '';
                        $resolvedSlug = $this->slugService->resolve(
                            currentSlug: $existingSlug,
                            inputSlug: trim($this->request->getPost('provider_slug') ?? ''),
                            fallbackName: $defaultCompanyName,
                            table: 'partner_details',
                            excludeId: $this->userId
                        );

                        if ($this->slugService->isLegacySlug($existingSlug)) {
                            $resolvedSlug = $this->slugService->generate(
                                $defaultCompanyName,
                                $defaultCompanyName,
                                'partner_details',
                                $this->userId
                            );
                        }

                        $partner_details = [
                            'company_name' => $defaultCompanyName,
                            'type' => $this->request->getPost('type'),
                            'visiting_charges' => $this->request->getPost('visiting_charges'),
                            'about' => $defaultAbout,
                            'advance_booking_days' => $this->request->getPost('advance_booking_days'),
                            'number_of_members' => $this->request->getPost('number_of_members'),
                            'long_description' => $defaultLongDescription,
                            'address' => $this->request->getPost('address'),
                            'at_store' => (isset($_POST['at_store'])) ? 1 : 0,
                            'at_doorstep' => (isset($_POST['at_doorstep'])) ? 1 : 0,
                            'chat' => (isset($_POST['chat'])) ? 1 : 0,
                            'pre_chat' => (isset($_POST['pre_chat'])) ? 1 : 0,
                            'other_images' => $other_images,
                            'slug' => $resolvedSlug,
                        ];
                        if ($partner_details) {
                            update_details($partner_details, ['partner_id' => $this->userId], 'partner_details', false);
                        }

                        // Persist all visible non-file custom field values (bank_details + any text-type
                        // documents fields) into `partner_custom_fields` dynamically.
                        $textCustomFieldValues = $this->collectTextCustomFieldValuesFromPost();
                        $this->upsertPartnerCustomFieldsValues($this->userId, $textCustomFieldValues);

                        // Handle translations for partner details
                        $this->handlePartnerTranslations();

                        // Handle SEO translations
                        $this->handleSeoTranslations();

                        $days = [
                            0 => 'monday',
                            1 => 'tuesday',
                            2 => 'wednesday',
                            3 => 'thursday',
                            4 => 'friday',
                            5 => 'saturday',
                            6 => 'sunday'
                        ];
                        for ($i = 0; $i < count($_POST['start_time']); $i++) {
                            $partner_timing = [];
                            $partner_timing['day'] = $days[$i];
                            if (isset($_POST['start_time'][$i])) {
                                $partner_timing['opening_time'] = $_POST['start_time'][$i];
                            }
                            if (isset($_POST['end_time'][$i])) {
                                $partner_timing['closing_time'] = $_POST['end_time'][$i];
                            }
                            $partner_timing['is_open'] = (isset($_POST[$days[$i]])) ? 1 : 0;
                            $timing_data = fetch_details('partner_timings', ['partner_id' => $this->userId, 'day' => $days[$i]]);
                            if (count($timing_data) > 0) {
                                update_details($partner_timing, ['partner_id' => $this->userId, 'day' => $days[$i]], 'partner_timings');
                            } else {
                                $partner_timing['partner_id'] = $this->userId;
                                insert_details($partner_timing, 'partner_timings');
                            }
                        }

                        $this->saveSeoSettings($this->userId);

                        // Send FCM notification to admin users about provider updating their information
                        // The FCM template with key 'provider_update_information' is already configured
                        try {
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Starting FCM notification process for provider_id: ' . $this->userId);

                            // Get provider name with translation support
                            $providerName = get_translated_partner_field($this->userId, 'user_name');
                            if (empty($providerName)) {
                                $providerData = fetch_details('users', ['id' => $this->userId], ['username']);
                                $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                            }
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Provider name: ' . $providerName . ', Provider ID: ' . $this->userId);

                            // Prepare context data for the notification template
                            $context = [
                                'provider_name' => $providerName,
                                'provider_id' => $this->userId
                            ];
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] Context prepared: ' . json_encode($context));

                            // Queue notification to admin users (group_id = 1) via FCM channel
                            // The service will check preferences and configurations to determine if FCM should be sent
                            queue_notification_service(
                                eventType: 'provider_update_information',
                                recipients: [],
                                context: $context,
                                options: [
                                    'user_groups' => [1], // Admin user group
                                    'channels' => ['fcm'] // FCM channel only
                                ]
                            );
                            // log_message('info', '[PROVIDER_UPDATE_INFORMATION] FCM notification result: ' . json_encode($result));
                        } catch (\Throwable $notificationError) {
                            log_message('error', '[PROVIDER_UPDATE_INFORMATION] FCM notification error trace: ' . $notificationError->getTraceAsString());
                        }

                        // Get partner details for event tracking
                        $partnerData = fetch_details('partner_details', ['partner_id' => $this->userId], ['company_name']);
                        $companyName = !empty($partnerData) ? $partnerData[0]['company_name'] ?? '' : '';

                        // Prepare event data
                        $eventData = [
                            'clarity_event' => 'profile_updated',
                            'provider_id' => $this->userId,
                            'company_name' => $companyName
                        ];

                        // When login type is email, the user is allowed to change phone/country_code.
                        // After such a change we log them out so they must sign in again with the
                        // updated account (session identity may no longer match).
                        $customData = [];
                        if ($currentLoginType === 'email') {
                            $newPhone = trim((string)($userData['phone'] ?? ''));
                            $newCountryCode = trim((string)($userData['country_code'] ?? ''));
                            if ($newCountryCode !== '' && $newCountryCode[0] !== '+') {
                                $newCountryCode = '+' . $newCountryCode;
                            }
                            $oldCountryCode = trim((string)($currentCountryCode ?? ''));
                            if ($oldCountryCode !== '' && $oldCountryCode[0] !== '+') {
                                $oldCountryCode = '+' . $oldCountryCode;
                            }
                            $phoneChanged = $newPhone !== trim((string)($currentPhone ?? ''));
                            $countryCodeChanged = $newCountryCode !== $oldCountryCode;
                            if ($phoneChanged || $countryCodeChanged) {
                                helper('session');
                                safe_destroy_session();
                                $customData['require_relogin'] = true;
                                $customData['redirect_url'] = base_url('partner/login');
                            }
                        }

                        return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Profile updated successfully!"), false, $eventData, $customData, 200, csrf_token(), csrf_hash());
                    }
                } catch (\Throwable $th) {
                    log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - update_profile()');
                    return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }
        } catch (\Throwable $th) {

            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - update_profile()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
    public function update()
    {
        try {
            $national_id = $this->request->getFile('national_id');
            $address_id = $this->request->getFile('address_id');
            $passport = $this->request->getFile('passport');
            if ($this->isLoggedIn) {
                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                    $response['error'] = true;
                    $response['message'] = DEMO_MODE_ERROR;
                    $response['csrfName'] = csrf_token();
                    $response['csrfHash'] = csrf_hash();
                    return $this->response->setJSON($response);
                }
                if ($this->request->getFile('national_id') && !empty($this->request->getFile('national_id'))) {
                    $file = $this->request->getFile('national_id');
                    if (!$file->isValid()) {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $type = $file->getMimeType();
                    if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg') {
                        $path = FCPATH . 'public/backend/assets/kyc-details/';
                        if (!empty($check_image)) {
                            $image_name = $check_image[0]['image'];
                            unlink($path . '' . $image_name);
                        }
                        $image = $file->getName();
                        $newName = $file->getRandomName();
                        $file->move($path, $newName);
                        $data['national_id'] =  $newName;
                    } else {
                        return ErrorResponse(labels(INVALID_IMAGE_FILE, "Please attach a valid image file."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                if ($this->request->getFile('address_id') && !empty($this->request->getFile('address_id'))) {
                    $file = $this->request->getFile('address_id');
                    if (!$file->isValid()) {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $type = $file->getMimeType();
                    if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg') {
                        $path = FCPATH . 'public/backend/assets/kyc-details/';
                        if (!empty($check_image)) {
                            $image_name = $check_image[0]['image'];
                            unlink($path . '' . $image_name);
                        }
                        $image = $file->getName();
                        $newName = $file->getRandomName();
                        $file->move($path, $newName);
                        $data['address_id'] =  $newName;
                    } else {
                        return ErrorResponse(labels(INVALID_IMAGE_FILE, "Please attach a valid image file."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                if ($this->request->getFile('passport') && !empty($this->request->getFile('passport'))) {
                    $file = $this->request->getFile('passport');
                    if (!$file->isValid()) {
                        return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $type = $file->getMimeType();
                    if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/jpg') {
                        $path = FCPATH . 'public/backend/assets/kyc-details/';
                        if (!empty($check_image)) {
                            $image_name = $check_image[0]['image'];
                            unlink($path . '' . $image_name);
                        }
                        $image = $file->getName();
                        $newName = $file->getRandomName();
                        $file->move($path, $newName);
                        $data['passport'] =  $newName;
                    } else {
                        return ErrorResponse(labels(INVALID_IMAGE_FILE, "Please attach a valid image file."), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                }
                if (isset($_POST['bank_name']) && !empty($_POST['bank_name'])) {
                    $data['bank_name'] = $_POST['bank_name'];
                }
                if (isset($_POST['account_number']) && !empty($_POST['account_number'])) {
                    $data['account_number'] = $_POST['account_number'];
                }
                if (isset($_POST['account_name']) && !empty($_POST['account_name'])) {
                    $data['account_name'] = $_POST['account_name'];
                }
                if (isset($_POST['bank_code']) && !empty($_POST['bank_code'])) {
                    $data['bank_code'] = $_POST['bank_code'];
                }
                if (isset($_POST['advance_booking_days']) && !empty($_POST['advance_booking_days'])) {
                    $data['advance_booking_days'] = $_POST['advance_booking_days'];
                }
                if (isset($_POST['type']) && !empty($_POST['type'])) {
                    $data['type'] = $_POST['type'];
                }
                if (isset($_POST['visiting_charges']) && !empty($_POST['visiting_charges'])) {
                    $data['visiting_charges'] = $_POST['visiting_charges'];
                }
                $days = [
                    0 => 'monday',
                    1 => 'tuesday',
                    2 => 'wednsday',
                    3 => 'thursday',
                    4 => 'friday',
                    5 => 'staturday',
                    6 => 'sunday'
                ];
                for ($i = 0; $i < count($_POST['start_time']); $i++) {
                    $partner_timing = [];
                    $partner_timing['day'] = $days[$i];
                    if (isset($_POST['start_time'][$i])) {
                        $partner_timing['opening_time'] = $_POST['start_time'][$i];
                    }
                    if (isset($_POST['end_time'][$i])) {
                        $partner_timing['closing_time'] = $_POST['end_time'][$i];
                    }
                    $partner_timing['is_open'] = (isset($_POST[$days[$i]])) ? 1 : 0;
                    if (exists(['partner_id' => $this->userId, 'day' => $days[$i]], 'partner_timings')) {
                        update_details($partner_timing, ['partner_id' => $this->userId, 'day' => $days[$i]], 'partner_timings');
                    } else {
                        $partner_timing['partner_id'] = $this->userId;
                        insert_details($partner_timing, 'partner_timings');
                    }
                }
                if (exists(['partner_id' => $this->userId], 'partner_details')) {
                    update_details($data, ['partner_id' => $this->userId], 'partner_details');
                } else {
                    $data['partner_id'] = $this->userId;
                    insert_details($data, 'partner_details');
                }

                // Prepare base user payload from the legacy update flow.
                // We will enforce the same loginType-based immutability rules here
                // so that the primary login credential cannot be changed through
                // any profile update endpoint.
                $userRow = fetch_details('users', ['id' => $this->userId], ['loginType', 'email', 'phone', 'country_code']);
                $currentLoginType = $userRow[0]['loginType'] ?? null;
                $currentEmail = $userRow[0]['email'] ?? null;
                $currentPhone = $userRow[0]['phone'] ?? null;
                $currentCountryCode = $userRow[0]['country_code'] ?? null;

                $data = [
                    'username' => $_POST['username'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone'],
                ];

                // Apply loginType rules:
                // - phone login: keep phone (and implicitly country_code) fixed.
                // - email login: keep email fixed.
                if ($currentLoginType === 'phone') {
                    $data['phone'] = $currentPhone;
                    $data['country_code'] = $currentCountryCode;
                } elseif ($currentLoginType === 'email') {
                    $data['email'] = $currentEmail;
                }

                if ($this->request->getPost('profile')) {
                    $img = $this->request->getPost('profile');
                    $f = finfo_open();
                    $mime_type = finfo_buffer($f, $img, FILEINFO_MIME_TYPE);
                    if ($mime_type != 'text/plain') {
                        $response['error'] = true;
                        return $this->response->setJSON([
                            'csrfName' => csrf_token(),
                            'csrfHash' => csrf_hash(),
                            'error' => true,
                            'message' => labels(INVALID_IMAGE_FILE, "Please Insert valid image"),
                            "data" => []
                        ]);
                    }
                    $data_photo = $img;
                    $img_dir = './public/backend/assets/profiles/';
                    list($type, $data_photo) = explode(';', $data_photo);
                    list(, $data_photo) = explode(',', $data_photo);
                    $data_photo = base64_decode($data_photo);
                    $filename = microtime(true) . '.jpg';
                    if (!is_dir($img_dir)) {
                        mkdir($img_dir, 0777, true);
                    }
                    if (file_put_contents($img_dir . $filename, $data_photo)) {
                        $profile = $filename;
                        $data['image'] = $filename;
                        $old_image = fetch_details('users', ['id' => $this->userId], ['image']);
                        if ($old_image[0]['image'] != "") {
                            if (is_readable("public/backend/assets/profiles/" . $old_image[0]['image']) && unlink("public/backend/assets/profiles/" . $old_image[0]['image'])) {
                            }
                        }
                    } else {
                        $data['image'] = $this->request->getPost('old_profile');
                        $profile = $this->request->getPost('old_profile');
                    }
                }
                $status = update_details(
                    $data,
                    ['id' => $this->userId],
                    'users'
                );
                if ($status) {
                    if (isset($_POST['old']) && isset($_POST['new']) && ($_POST['new'] != "") && ($_POST['old'] != "")) {
                        $identity = session()->get('identity');
                        $change = $this->ionAuth->changePassword($identity, $this->request->getPost('old'), $this->request->getPost('new'), $this->userId);
                        if ($change) {
                            // Load session helper and destroy session files
                            helper('session');
                            safe_destroy_session();
                            return successResponse(labels(USER_UPDATED_SUCCESSFULLY, "User updated successfully"), false, $_POST, [], 200, csrf_token(), csrf_hash());
                        } else {
                            return ErrorResponse(labels(OLD_PASSWORD_DID_NOT_MATCH, "Old password did not matched."), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                    return successResponse(labels(USER_UPDATED_SUCCESSFULLY, "User updated successfully"), false, $_POST, [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function remove_other_images()
    {
        try {
            if (!$this->isLoggedIn) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                return ErrorResponse(labels(DEMO_MODE_ERROR, "Demo mode error"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->userId;
            $image_url = $this->request->getPost('image_url');

            if (empty($id) || empty($image_url)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Remove base URL if it exists in the image URL
            $clean_image_url = str_replace(base_url(), '', $image_url);

            $partner_details = fetch_details('partner_details', ['partner_id' => $id], 'other_images');
            if (empty($partner_details)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $other_images = json_decode($partner_details[0]['other_images'], true);

            // Check if image exists in the array (try both with and without the base URL)
            $key = array_search($clean_image_url, $other_images);
            if ($key === false) {
                $key = array_search($image_url, $other_images);
            }

            if ($key !== false) {
                // Remove the image from storage
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('partner', $other_images[$key], $disk);

                // Remove from array and update database
                unset($other_images[$key]);
                $other_images = array_values($other_images); // Re-index array

                $data = ['other_images' => json_encode($other_images)];
                update_details($data, ['partner_id' => $id], 'partner_details');

                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(DATA_NOT_FOUND, "Data not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - remove_other_images()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    private function saveSeoSettings(int $partnerId): void
    {
        // Get default language for SEO data
        $defaultLanguage = get_default_language();

        // Get all POST data and transform it to translated fields structure
        $postData = $this->request->getPost();

        // Transform form data to translated fields structure
        $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

        // Extract default language SEO data
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
            'partner_id'    => $partnerId,
        ];

        // Check if any SEO field is filled (excluding partner_id)
        $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $partnerId);

        // Check if all SEO fields are intentionally cleared
        $allFieldsCleared = empty($seoData['title']) &&
            empty($seoData['description']) &&
            empty($seoData['keywords']) &&
            empty($seoData['schema_markup']);

        // Handle SEO image upload
        $seoImage = $this->request->getFile('meta_image');
        $hasImage = $seoImage && $seoImage->isValid();

        // Use Seo_model for provider context
        $this->seoModel->setTableContext('providers');
        $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

        $newSeoData = $seoData;
        if ($hasImage) {
            try {
                $uploadResult = $this->uploadFile(
                    $seoImage,
                    'public/uploads/seo_settings/provider_seo_settings/',
                    labels(FAILED_TO_UPLOAD_SEO_IMAGE, "Failed to upload SEO image"),
                    'seo_settings'
                );
                $newSeoData['image'] = $uploadResult['url'];
            } catch (\Throwable $t) {
                throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, "SEO image upload failed: " . $t->getMessage()));
            }
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
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // If existing settings exist and all fields are cleared (and no new image), delete the record
        // BUT: If there's an existing image, we should NOT delete the record even if all other fields are empty
        // This preserves the SEO record structure for future use
        if ($existingSettings && $allFieldsCleared && !$hasImage && !empty($existingSettings['image'])) {
            // Even if base SEO settings haven't changed, process translations
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // Delete the record if all fields are cleared and no image exists
        if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
            $result = $this->seoModel->deleteSeoSettings($existingSettings['id']);
            if (!empty($result['error'])) {
                throw new Exception(labels(FAILED_TO_DELETE_SEO_SETTINGS, "Failed to delete SEO settings: " . $result['message']));
            }
            // Also clean up SEO translations when deleting base SEO settings
            $this->cleanupSeoTranslations($partnerId);
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

        if (!$settingsChanged) {
            // Even if base SEO settings haven't changed, process translations
            $this->processSeoTranslations($partnerId, $translatedFields);
            return;
        }

        // Update existing settings with new data
        $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
        if (!empty($result['error'])) {
            $errors = $result['validation_errors'] ?? [];
            throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
        }

        // Process SEO translations after updating base SEO settings
        $this->processSeoTranslations($partnerId, $translatedFields);
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
     * Remove SEO image for a partner profile
     * This method handles AJAX requests to remove SEO images
     * @return \CodeIgniter\HTTP\Response
     */
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $partnerId = $this->userId; // Use the logged-in partner's ID
            $seoId = $this->request->getPost('seo_id');

            if (!$partnerId) {
                return ErrorResponse(labels(PARTNER_ID_IS_REQUIRED, "Partner ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Set SEO model context for providers
            $this->seoModel->setTableContext('providers');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);

            if (!$existingSettings) {
                return ErrorResponse(labels(SEO_SETTINGS_NOT_FOUND_FOR_THIS_PARTNER, "SEO settings not found for this partner"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if there's an image to remove
            if (empty($existingSettings['image'])) {
                return ErrorResponse(labels(NO_SEO_IMAGE_FOUND_TO_REMOVE, "No SEO image found to remove"), true, [], [], 200, csrf_token(), csrf_hash());
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
                'partner_id' => $partnerId
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
                return ErrorResponse(labels($result['message'], $result['message']), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Clean up the image file from storage
            if (!empty($imageToDelete)) {
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('provider_seo_settings', $imageToDelete, $disk);
            }

            return successResponse(labels(SEO_IMAGE_REMOVED_SUCCESSFULLY, "SEO image removed successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Profile.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something went wrong while removing SEO image"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Handle partner translations from form data
     * 
     * @return void
     */
    private function handlePartnerTranslations()
    {
        try {
            // Get languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            if (empty($languages)) {
                return;
            }

            // Get default language
            $defaultLanguage = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            if (empty($defaultLanguage)) {
                return;
            }

            // Process translations for each language (including default language)
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Get translated data from POST
                $username = $this->request->getPost('username[' . $languageCode . ']') ?? '';
                $companyName = $this->request->getPost('company_name[' . $languageCode . ']') ?? '';
                $about = $this->request->getPost('about[' . $languageCode . ']') ?? '';
                $longDescription = $this->request->getPost('long_description[' . $languageCode . ']') ?? '';

                // Only save if there's actual translated content
                if (!empty($username) || !empty($companyName) || !empty($about) || !empty($longDescription)) {
                    $translatedData = [
                        'username' => $username,
                        'company_name' => $companyName,
                        'about' => $about,
                        'long_description' => $longDescription
                    ];

                    // Save or update translation (including default language)
                    $this->partnerService->saveTranslations($this->userId, $languageCode, $translatedData);
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error handling partner translations: ' . $e->getMessage());
        }
    }

    /**
     * Handle SEO translations from form data
     * 
     * @return void
     */
    private function handleSeoTranslations()
    {
        try {
            // Get languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            if (empty($languages)) {
                return;
            }

            // Transform form data to translated fields structure
            $postData = $this->request->getPost();
            $translatedFields = $this->transformFormDataToTranslatedFields($postData, get_default_language());

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Load the SEO translation model
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($this->userId, $translatedFields);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    // Log the errors but don't fail the entire operation
                    log_message('error', 'SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }

                // Log successful SEO translations for debugging
                if (!empty($seoTranslationResult['processed_languages'])) {
                    log_message('info', 'Successfully processed SEO translations for partner ' . $this->userId . ': ' . json_encode($seoTranslationResult['processed_languages']));
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error handling SEO translations: ' . $e->getMessage());
        }
    }

    /**
     * Transform form data to translated fields structure
     * 
     * @param array $postData POST data from form
     * @param string $defaultLanguage Default language code
     * @return array Transformed translated fields
     */
    private function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage): array
    {
        $translatedFields = [
            'username' => [],
            'company_name' => [],
            'about_provider' => [],
            'long_description' => [],
            'seo_title' => [],
            'seo_description' => [],
            'seo_keywords' => [],
            'seo_schema_markup' => []
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['meta_title']) && is_array($postData['meta_title'])) {
            // Copy the data directly since it's already in the right structure
            $translatedFields['username'] = $postData['username'] ?? [];
            $translatedFields['company_name'] = $postData['company_name'] ?? [];
            $translatedFields['about_provider'] = $postData['about'] ?? []; // Note: 'about' not 'about_provider'
            $translatedFields['long_description'] = $postData['long_description'] ?? [];
            $translatedFields['seo_title'] = $postData['meta_title'] ?? [];
            $translatedFields['seo_description'] = $postData['meta_description'] ?? [];

            // Process keywords data properly - handle array structure with JSON strings
            $metaKeywords = $postData['meta_keywords'] ?? [];
            $processedKeywords = [];
            foreach ($metaKeywords as $langCode => $keywordsData) {
                if (is_array($keywordsData)) {
                    // Handle array format (like from Tagify)
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        // Single JSON string in array format - keep as is for parseKeywords to handle
                        $processedKeywords[$langCode] = $keywordsData[0];
                    } else {
                        // Multiple values, join them
                        $processedKeywords[$langCode] = implode(',', $keywordsData);
                    }
                } else {
                    // Direct string value
                    $processedKeywords[$langCode] = $keywordsData;
                }
            }
            $translatedFields['seo_keywords'] = $processedKeywords;

            $translatedFields['seo_schema_markup'] = $postData['schema_markup'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process SEO fields (meta_ prefixed from form)
            $seoTitleField = 'meta_title[' . $languageCode . ']';
            if (array_key_exists($seoTitleField, $postData)) {
                // Record even empty strings so cleared titles wipe old translations
                $seoTitleValue = $postData[$seoTitleField];
                $translatedFields['seo_title'][$languageCode] = trim((string)$seoTitleValue);
            }

            $seoDescriptionField = 'meta_description[' . $languageCode . ']';
            if (array_key_exists($seoDescriptionField, $postData)) {
                // Preserve user intent when they submit blank descriptions
                $seoDescriptionValue = $postData[$seoDescriptionField];
                $translatedFields['seo_description'][$languageCode] = trim((string)$seoDescriptionValue);
            }

            $seoKeywordsField = 'meta_keywords[' . $languageCode . ']';
            if (array_key_exists($seoKeywordsField, $postData)) {
                $seoKeywordsValue = $postData[$seoKeywordsField];
                // Handle array format from Tagify
                if (is_array($seoKeywordsValue)) {
                    if (count($seoKeywordsValue) === 1 && is_string($seoKeywordsValue[0])) {
                        // Single JSON string in array format - keep as is for parseKeywords to handle
                        $translatedFields['seo_keywords'][$languageCode] = $seoKeywordsValue[0];
                    } else {
                        // Multiple values, join them
                        $translatedFields['seo_keywords'][$languageCode] = implode(',', $seoKeywordsValue);
                    }
                } else {
                    // Store trimmed string even if empty so backend clears previous keywords
                    $translatedFields['seo_keywords'][$languageCode] = trim((string)$seoKeywordsValue);
                }
            }

            $seoSchemaField = 'schema_markup[' . $languageCode . ']';
            if (array_key_exists($seoSchemaField, $postData)) {
                // Ensure blank schema submissions overwrite stale data
                $seoSchemaValue = $postData[$seoSchemaField];
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string)$seoSchemaValue);
            }
        }

        return $translatedFields;
    }

    private function uploadFile($file, string $path, string $errorMessage, string $folder): array
    {
        if ($file && $file->isValid()) {
            $result = upload_file($file, $path, $errorMessage, $folder);
            if ($result['error']) {
                throw new Exception($result['message']);
            }

            return [
                'url' => $result['file_name'],
                'disk' => $result['disk'],
            ];
        }
        throw new Exception($errorMessage);
    }

    /**
     * Validate coordinates
     * @param string $latitude
     * @param string $longitude
     * @return void
     */
    private function validateCoordinates(string $latitude, string $longitude): void
    {
        // Match register method: latitude -90 to 90, longitude -180 to 180, max 7 decimal places
        if (!preg_match('/^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$/', $latitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LATITUDE, "Please enter valid latitude"));
        }
        if (!preg_match('/^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$/', $longitude)) {
            throw new Exception(labels(PLEASE_ENTER_VALID_LONGITUDE, "Please enter a valid Longitude"));
        }
    }

    /**
     * Process SEO translations for partner if provided in the request
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function processSeoTranslations(int $partnerId, ?array $translatedFields = null): void
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
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($partnerId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            throw new Exception('Exception in processSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function cleanupSeoTranslations(int $partnerId): void
    {
        try {
            // Load the SEO translation model
            $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

            // Delete all SEO translations for this partner
            $seoTranslationModel->deletePartnerSeoTranslations($partnerId);

            log_message('info', 'Cleaned up SEO translations for partner ' . $partnerId);
        } catch (\Exception $e) {
            throw new Exception('Exception in cleanupSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
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
                $languages = array_merge($languages, array_keys($translatedFields[$field]));
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                $value = $translatedFields[$field][$languageCode] ?? '';

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
            // Keep language entry even when every field is empty.
            // This lets the translation model actively clear stale values in DB.
        }

        return $restructured;
    }

    /**
     * Build the documents/bank_details custom-field definitions and their
     * per-language labels (same logic as the admin Partners controller).
     * Cannot be shared since the two controllers have different base classes.
     *
     * @param  array $languages  Rows from the `languages` table.
     * @return array{documents: array, bank_details: array, labels_by_language: array}
     */
    private function loadCustomFieldDefinitions(array $languages): array
    {
        $db = \Config\Database::connect();

        $documentsCustomFields   = [];
        $bankDetailsCustomFields = [];
        $customFieldLabelsByLanguage = [];

        $languageCodes       = [];
        $defaultLanguageCode = '';
        foreach ($languages as $langRow) {
            $code = (string) ($langRow['code'] ?? '');
            if ($code !== '') {
                $languageCodes[] = $code;
            }
            if (!empty($langRow['is_default']) && $code !== '') {
                $defaultLanguageCode = $code;
            }
        }
        if ($defaultLanguageCode === '') {
            $defaultLanguageCode = (string) get_default_language();
        }
        $languageCodes = array_values(array_unique($languageCodes));

        if ($db->tableExists('custom_fields')) {
            $toBool = static function ($v): bool {
                if (is_bool($v)) { return $v; }
                if (is_int($v))  { return $v === 1; }
                $s = strtolower(trim((string) $v));
                return $s === '1' || $s === 'true' || $s === 'yes' || $s === 'on';
            };

            $customFieldRows = $db->table('custom_fields')
                ->select(['id', 'field_label', 'field_type', 'field_group', 'file_config', 'required', 'visible', 'sort_order'])
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $customFieldIds = array_values(array_unique(array_map(
                static fn ($r) => (int) ($r['id'] ?? 0),
                array_filter($customFieldRows, static fn ($r) => (int) ($r['id'] ?? 0) > 0)
            )));

            $translationsByFieldId = [];
            if (!empty($customFieldIds) && $db->tableExists('translated_custom_fields') && !empty($languageCodes)) {
                $translationRows = $db->table('translated_custom_fields tcf')
                    ->select(['tcf.custom_field_id', 'l.code as language_code', 'tcf.field_label'])
                    ->join('languages l', 'l.id = tcf.language_id')
                    ->whereIn('tcf.custom_field_id', $customFieldIds)
                    ->whereIn('l.code', $languageCodes)
                    ->get()
                    ->getResultArray();

                foreach ($translationRows as $tr) {
                    $fieldId  = (int) ($tr['custom_field_id'] ?? 0);
                    $langCode = (string) ($tr['language_code'] ?? '');
                    if ($fieldId <= 0 || $langCode === '') { continue; }
                    $translationsByFieldId[$fieldId][$langCode] = (string) ($tr['field_label'] ?? '');
                }
            }

            foreach ($customFieldRows as $field) {
                $fieldId       = (int) ($field['id'] ?? 0);
                $fieldLabelBase = (string) ($field['field_label'] ?? '');
                $fieldType     = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                $fieldGroup    = strtolower(trim((string) ($field['field_group'] ?? '')));

                if (!in_array($fieldGroup, ['documents', 'bank_details'], true)) { continue; }

                $required  = $toBool($field['required'] ?? 0);
                $visible   = $toBool($field['visible'] ?? 0);
                $sortOrder = (int) ($field['sort_order'] ?? 0);

                if (!$visible)               { continue; }
                if ($fieldId <= 0) { continue; }

                $customFieldLabelsByLanguage[$fieldId] = [];
                foreach ($languageCodes as $langCode) {
                    $label = $translationsByFieldId[$fieldId][$langCode]
                        ?? ($defaultLanguageCode !== '' ? ($translationsByFieldId[$fieldId][$defaultLanguageCode] ?? null) : null)
                        ?? $fieldLabelBase;
                    $customFieldLabelsByLanguage[$fieldId][$langCode] = $label;
                }

                $fileConfigRaw = (string) ($field['file_config'] ?? '');
                $fileConfig    = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];

                $entry = [
                    'id'          => $fieldId,
                    'field_label' => $fieldLabelBase,
                    'field_type'  => $fieldType,
                    'field_group' => $fieldGroup,
                    'file_config' => $fileConfig,
                    'required'    => $required ? 1 : 0,
                    'sort_order'  => $sortOrder,
                ];

                if ($fieldGroup === 'documents') {
                    $documentsCustomFields[] = $entry;
                } else {
                    $bankDetailsCustomFields[] = $entry;
                }
            }

            usort($documentsCustomFields,   static fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
            usort($bankDetailsCustomFields, static fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        }

        return [
            'documents'          => $documentsCustomFields,
            'bank_details'       => $bankDetailsCustomFields,
            'labels_by_language' => $customFieldLabelsByLanguage,
        ];
    }

    private function getPartnerCustomFieldValuesById(int $partnerId, array $fieldIds): array
    {
        if ($partnerId <= 0 || empty($fieldIds)) {
            return [];
        }

        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), fn ($id) => $id > 0));
        if (empty($fieldIds)) {
            return [];
        }

        $db = \Config\Database::connect();

        $valueRows = $db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', (int) $partnerId)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($valueRows as $row) {
            $out[(int) $row['custom_field_id']] = $row['value'] ?? null;
        }
        return $out;
    }

    private function upsertPartnerCustomFieldsValues(int $partnerId, array $valuesById): void
    {
        if ($partnerId <= 0 || empty($valuesById)) {
            return;
        }

        $db = \Config\Database::connect();

        $valuesSql = [];
        $deletionIds = [];

        foreach ($valuesById as $customFieldId => $rawValue) {
            $customFieldId = (int) $customFieldId;
            if ($customFieldId <= 0) {
                continue;
            }

            if (is_array($rawValue)) {
                $rawValue = json_encode($rawValue, JSON_UNESCAPED_SLASHES);
            }

            if ($rawValue === '' || $rawValue === [] || $rawValue === null) {
                $deletionIds[] = $customFieldId;
                continue;
            }

            $valuesSql[] = '(' . (int) $partnerId . ',' . (int) $customFieldId . ',' . $db->escape((string) $rawValue) . ')';
        }

        if (!empty($deletionIds)) {
            $db->table('partner_custom_fields')
                ->where('partner_id', (int) $partnerId)
                ->whereIn('custom_field_id', array_values(array_unique($deletionIds)))
                ->delete();
        }

        if (empty($valuesSql)) {
            return;
        }

        $sql = 'INSERT INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
            . implode(',', $valuesSql)
            . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';

        $db->query($sql);
    }

    /**
     * Fetch all visible file-type custom fields (documents + bank_details groups).
     */
    private function getVisibleFileCustomFields(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $rows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group', 'required', 'file_config'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->where('field_type', 'file')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $fileConfigRaw = (string) ($row['file_config'] ?? '');
            $row['file_config'] = $fileConfigRaw !== '' ? (json_decode($fileConfigRaw, true) ?? []) : [];
            $row['required'] = (int) ($row['required'] ?? 0) === 1;
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Convert file extensions (e.g. ['.jpg', '.pdf']) to MIME types for CI4 validation.
     */
    private function extensionsToMimeTypes(array $extensions): array
    {
        $map = [
            '.jpg'  => 'image/jpeg',
            '.jpeg' => 'image/jpeg',
            '.png'  => 'image/png',
            '.gif'  => 'image/gif',
            '.webp' => 'image/webp',
            '.bmp'  => 'image/bmp',
            '.tif'  => 'image/tiff',
            '.tiff' => 'image/tiff',
            '.svg'  => 'image/svg+xml',
            '.pdf'  => 'application/pdf',
        ];

        $mimes = [];
        foreach ($extensions as $ext) {
            $ext = strtolower(trim((string) $ext));
            if (isset($map[$ext])) {
                $mimes[] = $map[$ext];
            }
        }
        return array_values(array_unique($mimes));
    }

    /**
     * Collect all visible non-file custom field values from POST data.
     * Used for bank_details and any text/number/textarea/date fields.
     */
    private function collectTextCustomFieldValuesFromPost(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('custom_fields')) {
            return [];
        }

        $customFieldRows = $db->table('custom_fields')
            ->select(['id', 'field_type', 'field_group'])
            ->whereIn('field_group', ['documents', 'bank_details'])
            ->where('visible', 1)
            ->where('field_type !=', 'file')
            ->get()
            ->getResultArray();

        $valuesById = [];
        foreach ($customFieldRows as $fieldRow) {
            $cfId = (int) ($fieldRow['id'] ?? 0);
            if ($cfId <= 0) {
                continue;
            }
            $inputName = 'cf_' . $cfId;
            $valuesById[$cfId] = $this->request->getPost($inputName) ?? '';
        }

        return $valuesById;
    }
}
