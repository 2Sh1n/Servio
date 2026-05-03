<!-- Main Content -->
<div class="main-content">
    <section class="section" id="pill-general_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('partner_details', 'Partner Details') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><?= labels('partner_details', 'Partner Details') ?></div>
                <div class="breadcrumb-item "><?= labels('company_information', 'Company Information') ?></div>
                <div class="breadcrumb-item "><?= htmlspecialchars($partner['rows'][0]['translated_company_name'] ?? $partner['rows'][0]['company_name'] ?? '') ?></div>
            </div>
        </div>
        <?php include "provider_details.php"; ?>
        <div class="section-body">
            <div id="output-status"></div>
            <div class="row mt-3">
                <!-- Company Details start -->
                <div class="col-md-12 col-sm-12 col-xl-8 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('company_details', 'Company Details') ?></div>
                            </div>
                            <div class="col d-flex justify-content-end mr-3 mt-4">
                                <?php
                                $label = ($partner['rows'][0]['is_approved_edit'] == 1) ?
                                    "<div class='tag border-0 rounded-md  bg-emerald-success text-emerald-success mx-2'>" . labels('approved', 'Approved') . "</div>" :
                                    "<div class='tag border-0 rounded-md  bg-emerald-danger text-emerald-danger mx-2'>" . labels('disapproved', 'Disapproved') . "</div>";
                                echo $label;
                                ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-building fa-lg text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('company_name', 'Company Name') ?></span>
                                            <p class="m-0"><?= htmlspecialchars($partner['rows'][0]['translated_company_name'] ?? $partner['rows'][0]['company_name'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fa-solid fa-t text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('type', 'Type') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['type'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fa-thin fa-dollar text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('visiting_charges', 'Visiting Charges') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['visiting_charges'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-map-marker-alt text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('company_address', 'Company Address') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['address'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-users text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('number_Of_members', 'Number Of Members') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['number_of_members'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="far fa-calendar-check text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('advance_booking_days', 'Advance Booking Days') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['advance_booking_days'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-city text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('city', 'City') ?></span>
                                            <p class="m-0" style="white-space:nowrap;"><?= $partner['rows']['0']['city'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-location text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('latitude', 'Latitude') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['latitude'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-location text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('longitude', 'Longitude') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['longitude'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-info text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('at_store_available', 'Available at store') ?></span>
                                            <p class="m-0"><?= ($partner['rows']['0']['at_store'] == "1")  ? labels('yes', 'Yes') : labels('no', 'No') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-info text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('at_doorstep_available', 'Available at doorstep') ?></span>
                                            <p class="m-0"><?= ($partner['rows']['0']['at_doorstep'] == "1")  ? labels('yes', 'Yes') : labels('no', 'No') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-city text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('about_company', 'About Company') ?></span>
                                            <p class="m-0">
                                                <?php
                                                // Clean and decode the about text - use translated version with fallback
                                                // Model already handles fallback: selected language → default language → main table
                                                $about_text = html_entity_decode(trim($partner['rows'][0]['translated_about'] ?? $partner['rows'][0]['about'] ?? ''), ENT_QUOTES, 'UTF-8');
                                                $about_length = strlen($about_text);

                                                // Debug: Check if text exists and has content
                                                if (!empty($about_text) && $about_length > 100) {
                                                    // Text is long enough to need read more functionality
                                                    $short_about = substr($about_text, 0, 100);
                                                    $full_about = $about_text;
                                                ?>
                                                    <span id="shortDescription1"><?= htmlspecialchars($short_about) ?></span>
                                                    <span id="fullDescription1" style="display: none;"><?= htmlspecialchars($full_about) ?></span>
                                                    <span id="dots1">...</span>
                                                    <a href="javascript:void(0)" id="readMoreLink1" onclick="toggleDescription(1)"><?= labels('read_more', 'Read more') ?></a>
                                                <?php } else if (!empty($about_text)) {
                                                    // Text is short, show full text without read more
                                                    echo htmlspecialchars($about_text);
                                                } else {
                                                    // No text available
                                                    echo '<em>' . labels('no_description_available', 'No description available') . '</em>';
                                                } ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-city text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('long_description', 'Long Description') ?></span>
                                            <p class="m-0">
                                                <?php
                                                // Clean and decode the long description text, strip HTML tags - use translated version with fallback
                                                // Model already handles fallback: selected language → default language → main table
                                                $long_desc_text = html_entity_decode(trim($partner['rows'][0]['translated_long_description'] ?? $partner['rows'][0]['long_description'] ?? ''), ENT_QUOTES, 'UTF-8');
                                                $long_desc_text = strip_tags($long_desc_text); // Remove HTML tags
                                                $long_desc_length = strlen($long_desc_text);

                                                // Debug: Check if text exists and has content
                                                if (!empty($long_desc_text) && $long_desc_length > 100) {
                                                    // Text is long enough to need read more functionality
                                                    $short_long_desc = substr($long_desc_text, 0, 100);
                                                    $full_long_desc = $long_desc_text;
                                                ?>
                                                    <span id="shortDescription2"><?= htmlspecialchars($short_long_desc) ?></span>
                                                    <span id="fullDescription2" style="display: none;"><?= htmlspecialchars($full_long_desc) ?></span>
                                                    <span id="dots2">...</span>
                                                    <a href="javascript:void(0)" id="readMoreLink2" onclick="toggleDescription(2)"><?= labels('read_more', 'Read more') ?></a>
                                                <?php } else if (!empty($long_desc_text)) {
                                                    // Text is short, show full text without read more
                                                    echo htmlspecialchars($long_desc_text);
                                                } else {
                                                    // No text available
                                                    echo '<em>' . labels('no_description_available', 'No description available') . '</em>';
                                                } ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="col-xl-12 col-md-12">
                                        <span class="title"><?= labels('logo', 'Logo') ?></span>
                                    </div>
                                    <div class="col-xl-12 col-md-12">
                                        <?php
                                        // Extract image URL from partner_profile HTML markup
                                        $partnerProfileHtml = $partner['rows']['0']['partner_profile'] ?? '';
                                        $logoUrl = base_url('public/backend/assets/default.png'); // Default fallback

                                        if (!empty($partnerProfileHtml)) {
                                            // Use regex to extract src attribute from img tag
                                            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $partnerProfileHtml, $matches)) {
                                                $extractedUrl = $matches[1];

                                                // Check if it's already a full URL
                                                if (strpos($extractedUrl, 'http://') === 0 || strpos($extractedUrl, 'https://') === 0) {
                                                    $logoUrl = $extractedUrl;
                                                } else {
                                                    // Add base_url if it's a relative path
                                                    $logoUrl = base_url($extractedUrl);
                                                }
                                            }
                                        }
                                        ?>
                                        <img src="<?= $logoUrl ?>" class="img-fluid" style="border-radius:8px; max-width: 200px;" alt="">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="col-xl-12 col-md-12">
                                        <span class="title"><?= labels('banner_image', 'Banner Image') ?></span>
                                    </div>
                                    <div class="col-xl-12 col-md-12">
                                        <?php
                                        $bannerUrl = buildImageUrl($partner['rows']['0']['banner_image'] ?? '', 'banner');
                                        ?>
                                        <img src="<?= $bannerUrl ?>" class="img-fluid" style="border-radius:8px; max-width: 200px;" alt="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Company Details end -->
                <!-- Personal Information start -->
                <div class="col-md-12 col-sm-12 col-xl-4 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('personal_information', ' Personal Information') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('name', 'Name') ?></span>
                                            <p class="m-0 text-break"><?= htmlspecialchars($partner['rows'][0]['translated_partner_name'] ?? $partner['rows'][0]['partner_name'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-envelope text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('email', 'Email') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['email'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-phone-alt text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('phone', 'Phone') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['mobile'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-percent text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('commission', 'Commission') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['admin_commission'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <?php $disk = fetch_current_file_manager(); ?>
                            <?php
                            // Helper function to build image URL safely using the same logic as the model
                            function buildImageUrl($imagePath, $type = 'profile')
                            {
                                if (empty($imagePath)) {
                                    return base_url('public/backend/assets/default.png');
                                }

                                // Use the same get_file_url function that's used in the model
                                return get_file_url(fetch_current_file_manager(), $imagePath, 'public/backend/assets/default.png', $type);
                            }
                            ?>
                            <div class="row mb-3 g-3">
                                <?php if ($passport_verification_status == 1) { ?>
                                    <div class="col-12 col-sm-6 col-xl-4">
                                        <div class="h-100 d-flex flex-column">
                                            <span class="title mb-2 fw-bold text-dark">
                                                <?= labels('passport', 'Passport') ?>
                                            </span>

                                            <div class="flex-grow-1 d-flex align-items-center justify-content-center border rounded p-2 bg-light">
                                                <img
                                                    src="<?= buildImageUrl($partner['rows'][0]['passport'] ?? '', 'passport') ?>"
                                                    class="img-fluid rounded"
                                                    style="max-height: 180px; object-fit: contain;"
                                                    alt="Passport">
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>

                                <?php if ($national_id_verification_status == 1) { ?>
                                    <div class="col-12 col-sm-6 col-xl-4">
                                        <div class="h-100 d-flex flex-column">
                                            <span class="title mb-2 fw-bold text-dark">
                                                <?= labels('national_id', 'National ID') ?>
                                            </span>

                                            <div class="flex-grow-1 d-flex align-items-center justify-content-center border rounded p-2 bg-light">
                                                <img
                                                    src="<?= buildImageUrl($partner['rows'][0]['national_id'] ?? '', 'national_id') ?>"
                                                    class="img-fluid rounded"
                                                    style="max-height: 180px; object-fit: contain;"
                                                    alt="National ID">
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>

                                <?php if ($address_id_verification_status == 1) { ?>
                                    <div class="col-12 col-sm-6 col-xl-4">
                                        <div class="h-100 d-flex flex-column">
                                            <span class="title mb-2 fw-bold text-dark">
                                                <?= labels('address_id', 'Address ID') ?>
                                            </span>

                                            <div class="flex-grow-1 d-flex align-items-center justify-content-center border rounded p-2 bg-light">
                                                <img
                                                    src="<?= buildImageUrl($partner['rows'][0]['address_id'] ?? '', 'address_id') ?>"
                                                    class="img-fluid rounded"
                                                    style="max-height: 180px; object-fit: contain;"
                                                    alt="Address ID">
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                            <?php
                            // Check if other images exist and are not empty
                            $other_images_available = false;
                            if (!empty($partner['rows']['0']['other_images'])) {
                                $partner_details['other_images'] = array_map(function ($data) {
                                    return ($data);
                                }, json_decode(json_encode($partner['rows']['0']['other_images']), true));

                                // Check if the array has actual images (not empty after processing)
                                if (!empty($partner_details['other_images']) && is_array($partner_details['other_images'])) {
                                    $other_images_available = true;
                                }
                            } else {
                                $partner_details['other_images'] = [];
                            }

                            // Only show the other images section if images are available
                            if ($other_images_available) { ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="col-xl-12 col-md-12 mb-3">
                                            <span class="title"><b class="text-dark"><?= labels('other_images', 'other Images') ?></b></span>
                                        </div>
                                        <div class="row">
                                            <?php
                                            foreach ($partner_details['other_images'] as $image) { ?>
                                                <div class="col-6 mb-3">
                                                    <?php $otherImageUrl = buildImageUrl($image, 'partner'); ?>
                                                    <img src="<?= $otherImageUrl ?>" class="img-fluid" style="border: solid #d6d6dd 1px; background-color:#f4f6f9; border-radius:4px; padding:5px;" alt="">
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <!-- Personal Information end -->
            </div>
            <div class="row mt-3 ">
                <!-- Bank Details start -->
                <div class="col-md-12 col-sm-12 col-xl-4 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('bank_details', 'Bank Details') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-hashtag text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('provider_id', 'Provider ID ') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['partner_id'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-city text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('city', 'City Name ') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['city'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-scroll text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('tax_name', 'Tax Name ') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['tax_name'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-scroll text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('tax_number', 'Tax Number') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['tax_number'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-university text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('bank_name', 'Bank Name') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['bank_name'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-list-ol text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('account_number', 'Account Number') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['account_number'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-university text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('account_name', 'Account Name') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['account_name'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-university text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('bank_code', 'Bank Code') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['bank_code'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-university text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('swift_code', 'Swift Code') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['swift_code'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Bank Details end -->
                <!-- Timing Details start -->
                <div class="col-md-12 col-sm-12 col-xl-8 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('timing_details', 'Timing Details') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-xl-12 col-md-12 col-sm-6 mb-sm-2">
                                    <table class="table table-hover table-bordered " id="payment_table" data-sort-name="day" data-sort-order="desc" data-toggle="table" data-url="<?= base_url('admin/partners/timing_details/' . $partner['rows'][0]['partner_id']); ?>">
                                        <thead>
                                            <tr>
                                                <th data-field="day" data-visible="true" data-sortable="true"><?= labels('day', 'Day') ?></th>
                                                <th data-field="opening_time" data-visible="true"><?= labels('opening_time', 'Opening Time') ?></th>
                                                <th data-field="closing_time" data-visible="true"><?= labels('closing_time', 'Closing Time') ?></th>
                                                <th data-field="is_open_new" data-visible="true"><?= labels('open_close', 'Open / Close') ?></th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Timing Details end -->
            </div>
        </div>
</div>
</section>
</div>
<script>
    function toggleDescription(section) {
        var shortDescription = $("#shortDescription" + section);
        var fullDescription = $("#fullDescription" + section);
        var dots = $("#dots" + section);
        var readMoreLink = $("#readMoreLink" + section);

        // Debug: Check if elements exist
        if (shortDescription.length === 0 || fullDescription.length === 0 || dots.length === 0 || readMoreLink.length === 0) {
            console.error("Toggle elements not found for section " + section);
            return;
        }

        if (fullDescription.is(":visible")) {
            fullDescription.hide();
            dots.show();
            readMoreLink.text("<?= labels('read_more', 'Read more') ?>");
        } else {
            fullDescription.show();
            dots.hide();
            readMoreLink.text("<?= labels('read_less', 'Read less') ?>");
        }
    }
</script>
<style>
    /* Icon box styling - ensures perfectly square icons with consistent sizing */
    .icon_box {
        width: 48px;
        height: 48px;
        min-width: 48px;
        min-height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
    }

    /* Icon font size consistency */
    .icon_box i {
        font-size: 20px !important;
    }

    /* Responsive image styling - prevents overflow and maintains aspect ratio */
    .card img {
        max-width: 100%;
        height: auto;
        object-fit: cover;
        border-radius: 8px;
    }
</style>