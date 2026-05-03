<?php
helper('function');
function renderCustomFieldInput(string $fieldKey, string $fieldType, string $requiredAttr, string $placeholder = '', array $fileConfig = [], string $existingValue = ''): string
{
    $inputType = match (true) {
        $fieldType === 'number'         => 'number',
        $fieldKey  === 'account_number' => 'number',
        $fieldType === 'date'           => 'date',
        default                         => 'text',
    };
    $commonAttrs = sprintf('class="form-control" name="%s" id="%s" %s', $fieldKey, $fieldKey, $requiredAttr);
    $valueAttr = $existingValue !== '' ? sprintf(' value="%s"', htmlspecialchars($existingValue, ENT_QUOTES, 'UTF-8')) : '';
    return match ($fieldType) {
        'file' => sprintf(
            '<input type="file" class="filepond-custom-field" name="%s" id="%s" %s%s>',
            $fieldKey,
            $fieldKey,
            $requiredAttr,
            (!empty($fileConfig['max_files']) && (int)$fileConfig['max_files'] > 1) ? ' multiple' : ''
        ),
        'textarea' => sprintf('<textarea %s>%s</textarea>', $commonAttrs, htmlspecialchars($existingValue, ENT_QUOTES, 'UTF-8')),
        default    => sprintf(
            '<input type="%s" %s%s%s>',
            $inputType,
            $commonAttrs,
            $placeholder !== '' ? sprintf(' placeholder="%s"', htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8')) : '',
            $valueAttr
        ),
    };
}

function renderCustomFieldFilePreview(string $url, string $label): string
{
    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    $escaped = esc($url);
    $escapedLabel = esc($label);

    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff'];
    $videoExts = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v'];
    $audioExts = ['mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a', 'wma'];

    if (in_array($ext, $imageExts, true)) {
        return sprintf(
            '<img alt="%s" width="130px" style="border: 1px solid #e5e7eb; border-radius: 12px;" height="100px" class="mt-2" src="%s">',
            $escapedLabel, $escaped
        );
    }

    if (in_array($ext, $videoExts, true)) {
        return sprintf(
            '<video controls width="250" class="mt-2" style="border-radius: 12px; border: 1px solid #e5e7eb;"><source src="%s">%s</video>',
            $escaped, labels('video_not_supported', 'Your browser does not support the video tag.')
        );
    }

    if (in_array($ext, $audioExts, true)) {
        return sprintf(
            '<audio controls class="mt-2" style="max-width: 100%%;"><source src="%s">%s</audio>',
            $escaped, labels('audio_not_supported', 'Your browser does not support the audio tag.')
        );
    }

    if ($ext === 'pdf') {
        return sprintf(
            '<a href="%s" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-file-pdf mr-1"></i>%s</a>',
            $escaped, labels('view_pdf', 'View PDF')
        );
    }

    // All other documents — show icon + filename link for download/preview.
    $filename = basename($url);
    $iconMap = [
        'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'csv' => 'fa-file-csv',
        'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
        'txt' => 'fa-file-alt', 'rtf' => 'fa-file-alt',
    ];
    $icon = $iconMap[$ext] ?? 'fa-file';
    return sprintf(
        '<a href="%s" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas %s mr-1"></i>%s</a>',
        $escaped, $icon, esc($filename)
    );
}

// Compute default language code early (needed for stepperLabels before the language loop)
$sorted_languages = sort_languages_with_default_first($languages);
$current_language = '';
foreach ($sorted_languages as $_lang) {
    if ($_lang['is_default'] == 1) {
        $current_language = $_lang['code'];
        break;
    }
}

$profile_slug_default_language = $sorted_languages[0]['code'] ?? 'en';
$profile_slug_english_language = '';
foreach ($sorted_languages as $language) {
    if ($language['is_default'] == 1) {
        $profile_slug_default_language = $language['code'];
    }
    if ($language['code'] === 'en') {
        $profile_slug_english_language = 'en';
    }
}
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('my_profile', "My Profile") ?></h1>
        </div>
        <?php if (empty($partner_details)) { ?>
            <div class="alert alert-info" role="alert">
                <?= labels('complete_kyc_then_access_panel', 'Please Complete Your KYC Then you can Access Panel') ?>
            </div>
        <?php } else if ($partner_details['is_approved'] == "0") { ?>
            <div class="alert alert-primary" role="alert">
                <?= labels('kyc_request_pending_wait_for_admin_action', 'Your KYC request is pending please wait for admin action') ?>.
            </div>
        <?php } else if ($partner_details['is_approved'] == "2") { ?>
            <div class="alert alert-danger" role="alert">
                <?= labels('kyc_rejected_by_admin', 'Your KYC request is Rejected by Admin Please try again') ?>.
            </div>
        <?php } ?>

        <?= form_open('/partner/update_profile', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_partner', 'enctype' => "multipart/form-data", 'novalidate' => 'novalidate']); ?>

        <script>
        window.stepperMode = 'edit';
        window.stepperLabels = {
            basic_info: "<?= labels('basic_info', 'Basic Info') ?>",
            business_settings: "<?= labels('business_settings', 'Business Settings') ?>",
            location: "<?= labels('location', 'Location') ?>",
            working_hours: "<?= labels('working_hours', 'Working Hours') ?>",
            media_and_docs: "<?= labels('media_and_docs', 'Media & Docs') ?>",
            bank_details: "<?= labels('bank_details', 'Bank Details') ?>",
            seo_settings: "<?= labels('seo_settings', 'SEO Settings') ?>",
            review: "<?= labels('review_step', 'Review') ?>",
            name: "<?= labels('name', 'Name') ?>",
            company_name: "<?= labels('company_name', 'Company Name') ?>",
            about_provider: "<?= labels('about_provider', 'About Provider') ?>",
            email: "<?= labels('email', 'Email') ?>",
            phone_number: "<?= labels('phone_number', 'Phone Number') ?>",
            login_type: "<?= labels('login_type', 'Login Type') ?>",
            slug: "<?= labels('slug', 'Slug') ?>",
            type: "<?= labels('type', 'Type') ?>",
            visiting_charges: "<?= labels('visiting_charges', 'Visiting Charges') ?>",
            advance_booking_days: "<?= labels('advance_booking_days', 'Advance Booking Days') ?>",
            number_Of_members: "<?= labels('number_Of_members', 'Number of Members') ?>",
            at_store: "<?= labels('at_store', 'At Store') ?>",
            at_doorstep: "<?= labels('at_doorstep', 'At Doorstep') ?>",
            allow_post_booking_chat: "<?= labels('allow_post_booking_chat', 'Allow Post Booking Chat') ?>",
            allow_pre_booking_chat: "<?= labels('allow_pre_booking_chat', 'Allow Pre Booking Chat') ?>",
            need_approval_for_the_service: "<?= labels('need_approval_for_the_service', 'Need Approval for Service') ?>",
            city: "<?= labels('city', 'City') ?>",
            address: "<?= labels('address', 'Address') ?>",
            latitude: "<?= labels('latitude', 'Latitude') ?>",
            longitude: "<?= labels('longitude', 'Longitude') ?>",
            working_days: "<?= labels('working_days', 'Working Days') ?>",
            image: "<?= labels('image', 'Image') ?>",
            banner_image: "<?= labels('banner_image', 'Banner Image') ?>",
            other_images: "<?= labels('other_images', 'Other Images') ?>",
            meta_title: "<?= labels('meta_title', 'Meta Title') ?>",
            meta_keywords: "<?= labels('meta_keywords', 'Meta Keywords') ?>",
            meta_description: "<?= labels('meta_description', 'Meta Description') ?>",
            edit: "<?= labels('edit', 'Edit') ?>",
            enabled: "<?= labels('enabled', 'Enabled') ?>",
            disabled_label: "<?= labels('disabled_label', 'Disabled') ?>",
            closed: "<?= labels('closed', 'Closed') ?>",
            defaultLanguageCode: "<?= $current_language ?>",
            provider_identity_contact_account: "<?= labels('provider_identity_contact_account', 'Provider identity, contact details, and account setup') ?>",
            configure_business_charges: "<?= labels('configure_business_charges', 'Configure business type, charges, and operational preferences') ?>",
            set_provider_location: "<?= labels('set_provider_location', 'Set the provider service location on the map') ?>",
            configure_working_schedule: "<?= labels('configure_working_schedule', 'Configure the weekly working schedule') ?>",
            upload_images_documents: "<?= labels('upload_images_documents', 'Upload profile images and required documents') ?>",
            enter_bank_account_details: "<?= labels('enter_bank_account_details', 'Enter bank account and payment details') ?>",
            configure_seo_meta_tags: "<?= labels('configure_seo_meta_tags', 'Configure SEO meta tags for better search visibility') ?>",
            verify_details_before_submitting: "<?= labels('verify_details_before_submitting', 'Verify all provider details before submitting') ?>",
            validation_required: "<?= labels('validation_required', '{field} is required.') ?>",
            validation_invalid_email: "<?= labels('validation_invalid_email', 'Please enter a valid email address.') ?>",
            validation_invalid_value: "<?= labels('validation_invalid_value', 'Please enter a valid value for {field}.') ?>",
            validation_invalid_format: "<?= labels('validation_invalid_format', 'Please enter a valid format for {field}.') ?>",
            validation_too_short: "<?= labels('validation_too_short', '{field} must be at least {min} characters.') ?>",
            validation_min_value: "<?= labels('validation_min_value', '{field} must be at least {min}.') ?>"
        };
        </script>

        <div class="stepper-container">
            <!-- Mobile horizontal step indicator (hidden on desktop) -->
            <div class="stepper-horizontal">
                <?php
                $stepIcons = [
                    1 => 'fa-user', 2 => 'fa-briefcase', 3 => 'fa-map-marker-alt', 4 => 'fa-clock',
                    5 => 'fa-images', 6 => 'fa-university', 7 => 'fa-magnifying-glass', 8 => 'fa-check-circle',
                ];
                $stepLabelsMap = [
                    1 => ['basic_info', 'Basic Info'], 2 => ['business_settings', 'Business Settings'],
                    3 => ['location', 'Location'], 4 => ['working_hours', 'Working Hours'],
                    5 => ['media_and_docs', 'Media & Docs'], 6 => ['bank_details', 'Bank Details'],
                    7 => ['seo_settings', 'SEO Settings'], 8 => ['review_step', 'Review'],
                ];
                foreach ($stepIcons as $num => $icon):
                    $activeClass = $num === 1 ? ' active' : ' disabled';
                ?>
                <div class="step-h-item<?= $activeClass ?>" data-step="<?= $num ?>">
                    <div class="step-h-icon"><i class="fas <?= $icon ?>"></i></div>
                    <span class="step-h-label"><?= labels($stepLabelsMap[$num][0], $stepLabelsMap[$num][1]) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="stepper-sidebar">
                <div class="step-item active" data-step="1">
                    <div class="step-icon"><i class="fas fa-user"></i></div>
                    <span class="step-label"><?= labels('basic_info', 'Basic Info') ?></span>
                </div>
                <div class="step-item disabled" data-step="2">
                    <div class="step-icon"><i class="fas fa-briefcase"></i></div>
                    <span class="step-label"><?= labels('business_settings', 'Business Settings') ?></span>
                </div>
                <div class="step-item disabled" data-step="3">
                    <div class="step-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <span class="step-label"><?= labels('location', 'Location') ?></span>
                </div>
                <div class="step-item disabled" data-step="4">
                    <div class="step-icon"><i class="fas fa-clock"></i></div>
                    <span class="step-label"><?= labels('working_hours', 'Working Hours') ?></span>
                </div>
                <div class="step-item disabled" data-step="5">
                    <div class="step-icon"><i class="fas fa-images"></i></div>
                    <span class="step-label"><?= labels('media_and_docs', 'Media & Docs') ?></span>
                </div>
                <div class="step-item disabled" data-step="6">
                    <div class="step-icon"><i class="fas fa-university"></i></div>
                    <span class="step-label"><?= labels('bank_details', 'Bank Details') ?></span>
                </div>
                <div class="step-item disabled" data-step="7">
                    <div class="step-icon"><i class="fas fa-magnifying-glass"></i></div>
                    <span class="step-label"><?= labels('seo_settings', 'SEO Settings') ?></span>
                </div>
                <div class="step-item disabled" data-step="8">
                    <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                    <span class="step-label"><?= labels('review_step', 'Review') ?></span>
                </div>
            </div>

            <div class="stepper-content">
                <div class="step-content-header">
                    <h2 id="stepper-step-title"><?= labels('basic_info', 'Basic Info') ?></h2>
                    <p id="stepper-step-subtitle"><?= labels('provider_identity_contact_account', 'Provider identity, contact details, and account setup') ?></p>
                </div>
                <div class="stepper-progress-bar">
                    <div class="progress-fill" style="width: 14%"></div>
                </div>

                <!-- STEP 1: Basic Info -->
                <div class="step-panel active" data-step="1">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($sorted_languages as $index => $language) {
                                ?>
                                    <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    foreach ($sorted_languages as $index => $language) {
                    ?>
                        <div id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="username<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('name', 'Name') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <input id="username<?= $language['code'] ?>" class="form-control" value="<?= isset($partner_details['translated_' . $language['code']]['username']) ? $partner_details['translated_' . $language['code']]['username'] : (isset($data['username']) ? $data['username'] : '') ?>" type="text" name="username[<?= $language['code'] ?>]" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('name', 'Name') ?> <?= labels('here', ' Here ') ?>" <?= $language['code'] == $current_language ? 'required' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="company_name<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('company_name', 'Company Name') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <input id="company_name<?= $language['code'] ?>" class="form-control" value="<?= isset($partner_details['translated_' . $language['code']]['company_name']) ? $partner_details['translated_' . $language['code']]['company_name'] : (isset($partner_details['company_name']) ? $partner_details['company_name'] : '') ?>" type="text" name="company_name[<?= $language['code'] ?>]" placeholder="<?= labels('enter', 'Enter ') ?> <?= labels('company_name', 'the company name ') ?> <?= labels('here', ' Here ') ?>" <?= $language['code'] == $current_language ? 'required' : '' ?> <?= $language['is_default'] ? 'data-slug-source data-slug-target="#provider_slug"' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="about<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('about_provider', 'About Provider') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <textarea id="about<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="about[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('about_provider', 'About Provider') ?> <?= labels('here', ' Here ') ?>" <?= $language['code'] == $current_language ? 'required' : '' ?>><?= isset($partner_details['translated_' . $language['code']]['about']) ? $partner_details['translated_' . $language['code']]['about'] : (isset($partner_details['about']) ? $partner_details['about'] : '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label for="long_description<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'class="required"' : '' ?>><?= labels('description', 'Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                    <textarea rows=10 class='form-control h-50 summernotes custome_reset' id="long_description" name="long_description[<?= $language['code'] ?>]"><?= isset($partner_details['translated_' . $language['code']]['long_description']) ? $partner_details['translated_' . $language['code']]['long_description'] : (isset($partner_details['long_description']) ? $partner_details['long_description'] : '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="stepper-section-divider"><p><?= labels('personal_details', 'Personal Details') ?></p></div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="email" class="required"><?= labels('email', 'Email') ?></label>
                                <?php
                                $emailReadonly = (isset($loginType) && $loginType === 'email') ? 'readonly' : '';
                                ?>
                                <input id="email" class="form-control" type="email" name="email" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('email', 'Email') ?> <?= labels('here', ' Here ') ?>" required value="<?= ((defined('ALLOW_VIEW_KEYS') && ALLOW_VIEW_KEYS == 0)) ? "XXXX@gmail.com" : (isset($data['email']) ? $data['email'] : "") ?>" <?= $emailReadonly ?>>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="phone" class="required"><?= labels('phone_number', 'Phone Number') ?></label>
                                <?php
                                $phoneReadonly = (isset($loginType) && $loginType === 'phone') ? 'readonly' : '';
                                $countryCodeDisabled = (isset($loginType) && $loginType === 'phone') ? 'disabled' : '';
                                ?>
                                <div class="row no-gutters phone-input-row">
                                    <div class="col-4 col-md-3">
                                        <select class="form-control" name="country_code" id="country_code" <?= $countryCodeDisabled ?>>
                                            <?php
                                            foreach ($country_codes as $key => $country_code) {
                                                $code = $country_code['calling_code'];
                                                $selected = ($selected_country_code == $country_code['calling_code']) ? "selected" : "";
                                                echo "<option $selected value='$code'>$code</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-8 col-md-9">
                                        <input id="phone" class="form-control" type="number" name="phone" value="<?= $data['phone'] ?>" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('phone_number', 'Phone Number') ?> <?= labels('here', ' Here ') ?>" required <?= $phoneReadonly ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Business Settings -->
                <div class="step-panel" data-step="2">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="provider_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                <input id="provider_slug" class="form-control" type="text" name="provider_slug" value="<?= isset($partner_details['slug']) ? $partner_details['slug'] : '' ?>" placeholder="<?= labels('enter_the_slug', 'Enter the slug') ?> " required>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <?= labels('slug_note', 'Note: The slug must always be in English for better SEO and URL compatibility.') ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="type" class="required"><?= labels('type', 'Type') ?></label>
                                <select class="select2" name="type" id="type" required>
                                    <option disabled selected><?= labels('select_type', 'Select Type') ?></option>
                                    <option value="0" <?= ($partner_details['type'] == 0) ? 'selected' : '' ?>><?= labels('individual', 'Individual') ?></option>
                                    <option value="1" <?= ($partner_details['type'] == 1) ? 'selected' : '' ?>><?= labels('organization', 'Organization') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="visiting_charges" class="required"><?= labels('visiting_charges', 'Visiting Charges') ?><strong>( <?= $currency ?> )</strong></label>
                                <i data-content="<?= labels('data_content_for_visiting_charge', 'The customer will pay these fixed charges for every booking made at their doorstep.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                <input id="visiting_charges" class="form-control" type="number" value="<?= $partner_details['visiting_charges'] ?>" name="visiting_charges" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('visiting_charges', 'Visiting Charges') ?> <?= labels('here', ' Here ') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="advance_booking_days" class="required"><?= labels('advance_booking_days', 'Advance Booking Days') ?></label>
                                <i data-content="<?= labels('data_content_for_advance_booking_day', 'Customers can book a service in advance for up to X days. For example, if you set it to 5 days, customers can book a service starting from today up to the next 5 days. During this period, only the available dates and time slots will be visible for booking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                <input id="advance_booking_days" class="form-control" type="number" value="<?= $partner_details['advance_booking_days'] ?>" name="advance_booking_days" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('advance_booking_days', 'Advance Booking Days') ?> <?= labels('here', ' Here ') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="number_of_members" class="required"><?= labels('number_Of_members', 'Number of Members') ?></label>
                                <i data-content="<?= labels('data_content_for_number_of_member', 'Currently, we\'re only gathering the total number of providers members for reference. Later on, we intend to use this information for future updates.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                <input id="number_of_members" class="form-control" type="number" name="number_of_members" value="<?= $partner_details['number_of_members'] ?>" min="0" oninput="this.value = Math.abs(this.value)" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('number_Of_members', 'Number of Members') ?> <?= labels('here', ' Here ') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="stepper-section-divider"><p><?= labels('preferences_and_toggles', 'Preferences & Toggles') ?></p></div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card stepper-toggle-card <?= $partner_details['at_store'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_store', 'At Store') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_store" name="at_store" <?= $partner_details['at_store'] == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="at_store"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small class="text-muted"><?= labels('at_store_description', 'The provider needs to perform the service at their store. The customer will arrive at the store on a specific date and time.') ?></small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stepper-toggle-card <?= $partner_details['at_doorstep'] == '1' ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('at_doorstep', 'At Doorstep') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="at_doorstep" name="at_doorstep" <?= $partner_details['at_doorstep'] == '1' ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="at_doorstep"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small class="text-muted"><?= labels('at_doorstep_description', "The provider has to go to the customer's place to do the job. They must arrive at the customer's place on a set date and time.") ?></small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($allow_post_booking_chat == "1") { ?>
                        <div class="col-md-4">
                            <div class="card stepper-toggle-card <?= (array_key_exists('chat', $partner_details) && $partner_details['chat'] == '1') ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('allow_post_booking_chat', 'Allow Post Booking Chat') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="post_chat" name="chat" <?php if (array_key_exists('chat', $partner_details)) { echo ($partner_details['chat'] == "1") ? 'checked' : ''; } ?>>
                                                <label class="custom-control-label" for="post_chat"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small class="text-muted"><?= labels('post_booking_chat_description', 'Allow chat between customer and provider after a booking is confirmed') ?></small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        <?php if ($allow_pre_booking_chat == "1") { ?>
                        <div class="col-md-4">
                            <div class="card stepper-toggle-card <?= (array_key_exists('pre_chat', $partner_details) && $partner_details['pre_chat'] == '1') ? 'active' : '' ?>">
                                <div class="card-body p-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col"><strong><?= labels('allow_pre_booking_chat', 'Allow Pre Booking Chat') ?></strong></div>
                                        <div class="col-auto">
                                            <div class="custom-control custom-switch mb-0">
                                                <input type="checkbox" class="custom-control-input" id="pre_chat" name="pre_chat" <?php if (array_key_exists('pre_chat', $partner_details)) { echo ($partner_details['pre_chat'] == "1") ? 'checked' : ''; } ?>>
                                                <label class="custom-control-label" for="pre_chat"></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row no-gutters mt-2">
                                        <div class="col"><small class="text-muted"><?= labels('pre_booking_chat_description', 'Allow chat between customer and provider before a booking is made') ?></small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- STEP 3: Location -->
                <div class="step-panel" data-step="3">
                    <div class="row">
                        <div class="col-md-12">
                            <div id="map_wrapper_div_partner">
                                <div id="partner_map"></div>
                            </div>
                        </div>
                        <div class="col-md-12 mt-3">
                            <div class="form-group">
                                <label for="partner_location" class="required"><?= labels('current_location', 'Current Location') ?></label>
                                <input id="partner_location" class="form-control" type="text" name="places">
                                <ul id="suggestions" class="list-group position-absolute w-100" style="z-index: 1000;"></ul>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <div class="cities" id="cities_select">
                                    <label for="city" class="required"><?= labels('city', 'City') ?></label>
                                    <input type="text" name="city" class="form-control" placeholder="<?= labels('enter_your_providers_city_name', "Enter your provider's city name") ?>" value="<?= isset($data['city']) ? $data['city'] : "" ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="partner_latitude" class="required"><?= labels('latitude', 'Latitude') ?></label>
                                <input id="partner_latitude" class="form-control" type="text" name="latitude" placeholder="<?= labels('latitude', 'Latitude') ?>" value="<?= $data['latitude'] ?>" pattern="^-?(90(\.0{1,7})?|[0-8][0-9](\.[0-9]{1,7})?|[0-9](\.[0-9]{1,7})?)$" title="<?= labels('please_enter_valid_latitude', 'Latitude: -90 to 90, max 7 decimal places') ?>" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="partner_longitude" class="required"><?= labels('longitude', 'Longitude') ?></label>
                                <input id="partner_longitude" class="form-control" type="text" name="longitude" placeholder="<?= labels('longitude', 'Longitude') ?>" value="<?= $data['longitude'] ?>" pattern="^-?(180(\.0{1,7})?|1[0-7][0-9](\.[0-9]{1,7})?|[0-9]{1,2}(\.[0-9]{1,7})?)$" title="<?= labels('please_enter_valid_longitude', 'Longitude: -180 to 180, max 7 decimal places') ?>" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="address" class="required"><?= labels('address', 'Address') ?></label>
                                <textarea id="address" class="form-control" style="min-height:60px" name="address" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('address', 'Address') ?> <?= labels('here', ' Here ') ?>" required><?= isset($partner_details['address']) ? $partner_details['address'] : "" ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Working Hours -->
                <div class="step-panel" data-step="4">
                    <div class="row">
                        <div class="col-12">
                            <?php
                            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                            foreach ($days as $index => $day) {
                                $opening_time = isset($partner_timings[$index]['opening_time']) ? $partner_timings[$index]['opening_time'] : '09:00';
                                $closing_time = isset($partner_timings[$index]['closing_time']) ? $partner_timings[$index]['closing_time'] : '18:00';
                                $is_open = isset($partner_timings[$index]['is_open']) && $partner_timings[$index]['is_open'] == "1" ? 'checked' : '';
                            ?>
                                <div class="row mb-3">
                                    <div class="col-md-2">
                                        <label for="<?= $index ?>"><?= labels($day, ucfirst($day)) ?></label>
                                    </div>
                                    <div class="col-md-3 col-sm-3 col-4">
                                        <input type="time" required id="start_time_<?= $index ?>" class="form-control start_time" name="start_time[]" value="<?= $opening_time ?>">
                                    </div>
                                    <div class="col-md-1 col-sm-2 mt-2 col-4 text-center">
                                        <?= labels('to', 'To') ?>
                                    </div>
                                    <div class="col-md-3 col-sm-3 col-4 endTime">
                                        <input type="time" required id="end_time_<?= $index ?>" class="form-control end_time" name="end_time[]" value="<?= $closing_time ?>">
                                    </div>
                                    <div class="col-md-2 col-sm-3 m-sm-1 mt-3">
                                        <div class="form-check mt-3">
                                            <div class="button b2 working-days_checkbox" id="button-<?= $index ?>">
                                                <input type="checkbox" class="checkbox check_box" name="<?= $day ?>" id="flexCheckDefault_<?= $index ?>" <?= $is_open ?> />
                                                <div class="knobs">
                                                    <span></span>
                                                </div>
                                                <div class="layer"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: Media & Docs -->
                <div class="step-panel" data-step="5">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="image" class="required"><?= labels('image', 'Image') ?> </label> <small>(<?= labels('partner_image_recommended_size', 'We recommend 80x80 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="image" id="image" accept="image/*">
                                <?php
                                    $profileImageSrc = (!empty($data['image']) && $data['image'] !== base_url() && $data['image'] !== base_url('/'))
                                        ? $data['image']
                                        : base_url('public/backend/assets/default.png');
                                ?>
                                <div class="mt-2">
                                    <img src="<?= esc($profileImageSrc) ?>" alt="<?= labels('image', 'Image') ?>" style="max-width: 120px; max-height: 80px; border-radius: 8px; border: 1px solid #d6d6dd;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="banner_image" class="required"><?= labels('banner_image', 'Banner Image') ?></label> <small>(<?= labels('partner_banner_image_recommended_size', 'We recommend 378x190 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="banner" id="banner_image" accept="image/*">
                                <?php
                                    $bannerSrc = !empty($partner_details['banner']) ? $partner_details['banner'] : base_url('public/backend/assets/default.png');
                                ?>
                                <div class="mt-2">
                                    <img src="<?= esc($bannerSrc) ?>" alt="<?= labels('banner_image', 'Banner Image') ?>" style="max-width: 120px; max-height: 80px; border-radius: 8px; border: 1px solid #d6d6dd;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="other_service_image_selector"><?= labels('other_images', 'Other Image') ?></label> <small>(<?= labels('other_image_recommended_size', 'We recommend 960 x 540 pixels') ?>)</small>
                                <input type="file" name="other_service_image_selector_edit[]" class="filepond logo" id="other_service_image_selector_edit" accept="image/*" multiple>
                                <div class="row mt-2" id="other_images_container">
                                    <?php
                                    if (!empty($partner_details['other_images'])) {
                                        if (count($partner_details['other_images']) > 0) { ?>
                                            <div class="col-12 mb-2">
                                                <button type="button" class="btn btn-primary btn-sm remove-all-other-images"><?= labels('remove_all_images', 'Remove All Images') ?></button>
                                            </div>
                                        <?php }
                                        foreach (($partner_details['other_images']) as $index => $image) { ?>
                                            <div class="col-md-4 mb-2 other-image-container">
                                                <div class="position-relative">
                                                    <img alt="no image found" width="130px" style="border: solid #d6d6dd 1px; border-radius: 12px;" height="100px" class="mt-2" src="<?= $image ?>">
                                                    <input type="hidden" name="existing_other_images[]" value="<?= $image ?>">
                                                    <button type="button" class="btn btn-sm btn-danger remove-other-image" data-image-index="<?= $index ?>" style="position: absolute; top: 5px; right: 5px;"><i class="fas fa-times"></i></button>
                                                    <input type="hidden" name="remove_other_images[<?= $index ?>]" value="0" class="remove-flag">
                                                </div>
                                            </div>
                                    <?php }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php $fileFieldConfigs = []; ?>
                    <div class="row">
                        <?php
                        foreach ($documents_custom_fields ?? [] as $field):
                            $cfId            = (int)($field['id'] ?? 0);
                            $inputName       = 'cf_' . $cfId;
                            $fieldType       = strtolower(trim((string)($field['field_type'] ?? 'text')));
                            $required        = !empty($field['required']);
                            $fieldFileConfig = is_array($field['file_config'] ?? null) ? $field['file_config'] : [];
                            $existingValue   = (string)($custom_field_values[$cfId] ?? '');
                            $initialLabel    = $custom_field_labels_by_language[$cfId][$current_language] ?? ($field['field_label'] ?? '');
                            $isDefaultPlaceholder = !empty($existingValue) && (
                                str_contains($existingValue, 'default.png') ||
                                str_contains($existingValue, 'default.jpg') ||
                                str_contains($existingValue, 'default.jpeg')
                            );
                            $hasExistingValue = $existingValue !== '' && !$isDefaultPlaceholder;
                            $requiredAttr = ($required && !($fieldType === 'file' && $hasExistingValue)) ? 'required' : '';
                            if ($fieldType === 'file') { $fileFieldConfigs[$inputName] = $fieldFileConfig; }
                        ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="<?= $inputName ?>" class="<?= $required ? 'required' : '' ?>">
                                    <span class="pcf-custom-label-text" data-custom-field-id="<?= $cfId ?>">
                                        <?= htmlspecialchars($initialLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </label>
                                <?= renderCustomFieldInput($inputName, $fieldType, $requiredAttr, '', $fieldFileConfig, $fieldType !== 'file' ? $existingValue : '') ?>
                                <?php if ($fieldType === 'file' && $hasExistingValue): ?>
                                    <?= renderCustomFieldFilePreview($existingValue, $initialLabel) ?>
                                <?php endif ?>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>

                <!-- STEP 6: Bank Details -->
                <div class="step-panel" data-step="6">
                    <div class="bank-details-section">
                        <?php
                        $bankFields  = array_values($bank_details_custom_fields ?? []);
                        $bankChunks  = array_chunk($bankFields, 3);
                        foreach ($bankChunks as $chunk): ?>
                        <div class="row">
                            <?php foreach ($chunk as $field):
                                $cfId            = (int)($field['id'] ?? 0);
                                $inputName       = 'cf_' . $cfId;
                                $fieldType       = strtolower(trim((string)($field['field_type'] ?? 'text')));
                                $required        = !empty($field['required']);
                                $requiredAttr    = $required ? 'required' : '';
                                $fieldFileConfig = is_array($field['file_config'] ?? null) ? $field['file_config'] : [];
                                $existingValue   = (string)($custom_field_values[$cfId] ?? '');
                                $initialLabel    = $custom_field_labels_by_language[$cfId][$current_language] ?? ($field['field_label'] ?? '');
                                $placeholder     = labels('enter', 'Enter') . ' ' . $initialLabel . ' ' . labels('here', 'Here');
                                if ($fieldType === 'file') { $fileFieldConfigs[$inputName] = $fieldFileConfig; }
                            ?>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="<?= $inputName ?>" class="<?= $required ? 'required' : '' ?>">
                                        <span class="pcf-custom-label-text" data-custom-field-id="<?= $cfId ?>">
                                            <?= htmlspecialchars($initialLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </label>
                                    <?= renderCustomFieldInput($inputName, $fieldType, $requiredAttr, $placeholder, $fieldFileConfig, $fieldType !== 'file' ? $existingValue : '') ?>
                                </div>
                            </div>
                            <?php endforeach ?>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>

                <!-- STEP 7: SEO Settings -->
                <div class="step-panel" data-step="7">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($sorted_languages as $index => $language) {
                                    if ($language['is_default'] == 1) {
                                        $current_language_seo = $language['code'];
                                    }
                                ?>
                                    <div class="language-option-seo position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-seo-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span class="language-text-seo px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-underline-seo"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    foreach ($sorted_languages as $index => $language) {
                    ?>
                        <div id="translationDivSeo-<?= $language['code'] ?>" <?= $language['code'] == $current_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255" value="<?= isset($partner_seo_settings['translated_' . $language['code']]['title']) ? esc($partner_seo_settings['translated_' . $language['code']]['title']) : (isset($partner_seo_settings['title']) ? esc($partner_seo_settings['title']) : '') ?>">
                                        <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>" value="<?= isset($partner_seo_settings['translated_' . $language['code']]['keywords']) ? esc($partner_seo_settings['translated_' . $language['code']]['keywords']) : (isset($partner_seo_settings['keywords']) ? esc($partner_seo_settings['keywords']) : '') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"><?= isset($partner_seo_settings['translated_' . $language['code']]['description']) ? esc($partner_seo_settings['translated_' . $language['code']]['description']) : (isset($partner_seo_settings['description']) ? esc($partner_seo_settings['description']) : '') ?></textarea>
                                        <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ' (' . strtoupper($language['code']) . ')' ?></label>
                                        <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                            data-toggle="popover"
                                            class="fa fa-question-circle"
                                            data-original-title=""
                                            title=""></i>
                                        <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"><?= isset($partner_seo_settings['translated_' . $language['code']]['schema_markup']) ? esc($partner_seo_settings['translated_' . $language['code']]['schema_markup']) : (isset($partner_seo_settings['schema_markup']) ? esc($partner_seo_settings['schema_markup']) : '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                                <?php if (!empty($partner_seo_settings['image'])): ?>
                                    <div class="position-relative d-inline-block mt-2">
                                        <img src="<?= esc($partner_seo_settings['image']) ?>" alt="SEO Image" style="max-width: 120px; max-height: 80px; border-radius: 8px;">
                                        <button type="button" class="btn btn-sm btn-danger remove-partner-profile-seo-image"
                                            data-partner-id="<?= $partner_details['partner_id'] ?>"
                                            data-seo-id="<?= isset($partner_seo_settings['id']) ? $partner_seo_settings['id'] : '' ?>"
                                            style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 8: Review -->
                <div class="step-panel" data-step="8">
                    <div id="stepper-review-content"></div>
                </div>

                <!-- Footer -->
                <div class="stepper-footer">
                    <button type="button" class="btn-step-back" disabled style="visibility:hidden;">
                        <i class="fas fa-chevron-left"></i> <?= labels('back', 'Back') ?>
                    </button>
                    <button type="button" class="btn-step-next">
                        <?= labels('next_step', 'Next Step') ?> <i class="fas fa-chevron-right"></i>
                    </button>
                    <button type="submit" class="btn-step-submit submit_btn" style="display:none;">
                        <i class="fas fa-check"></i> <?= labels('update', 'Update') ?>
                    </button>
                </div>
            </div>
        </div>
        <?= form_close() ?>
    </section>
</div>

<script>
    $('.start_time').change(function() {
        var doc = $(this).val();
        $(this).parent().siblings(".endTime").children().attr('min', doc);
    });

    $('#type').change(function() {
        var doc = document.getElementById("type");
        if (doc.options[doc.selectedIndex].value == 0) {
            $("#number_of_members").val('1');
            $("#number_of_members").attr("readOnly", "readOnly");
        } else if (doc.options[doc.selectedIndex].value == 1) {
            $("#number_of_members").val('');
            $("#number_of_members").removeAttr("readOnly");
        }
    });


</script>

<script>
    $(document).ready(function() {
        // Shared slug helpers
        function generateSlug(text) {
            return text
                .toLowerCase()
                .replace(/\s+/g, "-")
                .replace(/[^\w-]+/g, "");
        }

        function normalizeSlugSource(text) {
            if (!text) {
                return '';
            }
            return text
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^\w\s-]/g, '')
                .trim();
        }

        let slugCounter = 1;

        function generateAutoSlug() {
            return "slug-" + slugCounter++;
        }

        function updateProviderSlugField() {
            const englishCode = "<?= $profile_slug_english_language ?>";
            const defaultCode = "<?= $profile_slug_default_language ?>";
            const fallbackCode = "<?= $sorted_languages[0]['code'] ?? 'en' ?>";

            let slugSource = '';
            if (englishCode) {
                slugSource = normalizeSlugSource($("#company_name" + englishCode).val());
            }
            if (!slugSource && defaultCode) {
                slugSource = normalizeSlugSource($("#company_name" + defaultCode).val());
            }
            if (!slugSource && fallbackCode) {
                slugSource = normalizeSlugSource($("#company_name" + fallbackCode).val());
            }

            if (slugSource) {
                $("#provider_slug").val(generateSlug(slugSource));
            } else {
                $("#provider_slug").val(generateAutoSlug());
            }
        }

        <?php foreach ($sorted_languages as $language) { ?>
            $("#company_name<?= $language['code'] ?>").on("input", function() {
                updateProviderSlugField();
            });
        <?php } ?>

        if (!$("#provider_slug").val()) {
            updateProviderSlugField();
        }
    });
</script>

<script>
    $(document).ready(function() {
        // Handle individual image removal with toggle functionality
        $(document).on('click', '.remove-other-image', function() {
            const button = this;
            const container = $(this).closest('.position-relative');
            const removeFlag = container.find('.remove-flag');

            if (removeFlag.length) {
                if (removeFlag.val() === "0") {
                    removeFlag.val("1");
                    container.find('img').css('opacity', '0.5');
                    $(button).removeClass('btn-danger').addClass('btn-primary');
                    $(button).html('<i class="fas fa-undo"></i>');
                } else {
                    removeFlag.val("0");
                    container.find('img').css('opacity', '1');
                    $(button).removeClass('btn-primary').addClass('btn-danger');
                    $(button).html('<i class="fas fa-times"></i>');
                }
            }
        });

        // Handle remove all images button
        $(document).on('click', '.remove-all-other-images', function() {
            if (confirm('<?= labels('are_you_sure_to_remove_all_images', 'Are you sure you want to remove all images?') ?>')) {
                const otherImagesContainer = $('#other_images_container');
                const imageContainers = otherImagesContainer.find('.other-image-container');

                imageContainers.each(function() {
                    const container = $(this).find('.position-relative');
                    const removeFlag = container.find('.remove-flag');
                    const button = container.find('.remove-other-image');

                    if (removeFlag.length) {
                        removeFlag.val("1");
                        container.find('img').css('opacity', '0.5');
                        button.removeClass('btn-danger').addClass('btn-primary');
                        button.html('<i class="fas fa-undo"></i>');
                    }
                });
            }
        });

        // Initialize Tagify for meta keywords field
        $(document).ready(function() {
            <?php foreach ($sorted_languages as $language) { ?>
                var metaKeywordsInput<?= $language['code'] ?> = document.querySelector('input[id=meta_keywords<?= $language['code'] ?>]');
                if (metaKeywordsInput<?= $language['code'] ?> != null) {
                    new Tagify(metaKeywordsInput<?= $language['code'] ?>);
                }
            <?php } ?>
        });

        // SEO language switching
        let default_language_seo = '<?= $current_language_seo ?>';

        $(document).on('click', '.language-option-seo', function() {
            const language = $(this).data('language');

            $('.language-underline-seo').css('width', '0%');
            $('#language-seo-' + language).find('.language-underline-seo').css('width', '100%');

            $('.language-text-seo').removeClass('text-primary fw-medium');
            $('.language-text-seo').addClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-text-seo').addClass('text-primary');

            if (language != default_language_seo) {
                $('#translationDivSeo-' + language).show();
                $('#translationDivSeo-' + default_language_seo).hide();
            }

            default_language_seo = language;
        });

        // Handle partner profile SEO image removal
        $(document).on('click', '.remove-partner-profile-seo-image', function() {
            const button = $(this);
            const seoId = button.data('seo-id');

            if (confirm('<?= labels('are_you_sure_to_remove_seo_image', 'Are you sure you want to remove this SEO image?') ?>')) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: '<?= base_url('partner/profile/remove_seo_image') ?>',
                    type: 'POST',
                    data: {
                        seo_id: seoId,
                        <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error === false) {
                            button.closest('.position-relative').remove();
                            alert(response.message || 'SEO image removed successfully');
                            button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        } else {
                            alert(response.message || 'Failed to remove SEO image');
                            button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('An error occurred while removing the SEO image');
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                });
            }
        });

        // Handle file input change to reset button state when new image is uploaded
        $(document).on('change', '#meta_image', function() {
            $('.remove-partner-profile-seo-image').prop('disabled', false).html('<i class="fas fa-times"></i>');
        });

        // Ensure FilePond plugins are registered before creating custom field instances,
        // since this inline script runs before partner.js which normally registers them.
        if (typeof FilePondPluginFileValidateType !== 'undefined') {
            FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateSize, FilePondPluginFileValidateType);
        }

        // FilePond initialization for custom field file inputs
        const customFieldFileConfigs = <?= json_encode($fileFieldConfigs ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        Object.keys(customFieldFileConfigs).forEach(function(fieldKey) {
            var el = document.getElementById(fieldKey);
            if (!el) { return; }
            var cfg = customFieldFileConfigs[fieldKey] || {};
            var allowedTypes = Array.isArray(cfg.allowed_types) ? cfg.allowed_types : [];
            // Convert file extensions to MIME types for FilePond's acceptedFileTypes.
            var extToMime = {
                '.jpg':'image/jpeg','.jpeg':'image/jpeg','.png':'image/png','.gif':'image/gif',
                '.webp':'image/webp','.bmp':'image/bmp','.svg':'image/svg+xml',
                '.tif':'image/tiff','.tiff':'image/tiff',
                '.mp4':'video/mp4','.mov':'video/quicktime','.avi':'video/x-msvideo',
                '.mkv':'video/x-matroska','.webm':'video/webm','.wmv':'video/x-ms-wmv',
                '.flv':'video/x-flv','.m4v':'video/x-m4v',
                '.mp3':'audio/mpeg','.wav':'audio/wav','.aac':'audio/aac',
                '.ogg':'audio/ogg','.flac':'audio/flac','.m4a':'audio/x-m4a','.wma':'audio/x-ms-wma',
                '.pdf':'application/pdf',
                '.doc':'application/msword','.docx':'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                '.xls':'application/vnd.ms-excel','.xlsx':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                '.ppt':'application/vnd.ms-powerpoint','.pptx':'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                '.txt':'text/plain','.csv':'text/csv','.rtf':'application/rtf'
            };
            allowedTypes = allowedTypes.map(function(t) { return extToMime[t.toLowerCase()] || t; })
                .filter(function(v, i, a) { return a.indexOf(v) === i; });
            var maxSizeMb = parseInt(cfg.max_size_mb, 10);
            var maxFiles  = Math.max(1, parseInt(cfg.max_files, 10) || 1);

            var opts = {
                credits: null,
                storeAsFile: true,
                allowMultiple: maxFiles > 1,
                maxFiles: maxFiles,
                labelIdle: drag_and_drop_files_here + ' ' + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
                allowFileSizeValidation: maxSizeMb > 0,
                labelMaxFileSizeExceeded: file_is_too_large,
                labelMaxFileSize: maximum_file_size_is + ' {filesize}',
                allowFileTypeValidation: allowedTypes.length > 0,
                labelFileTypeNotAllowed: file_of_invalid_type,
                fileValidateTypeLabelExpectedTypes: 'Expects {allButLastType} or {lastType}',
                allowPdfPreview: true,
                pdfPreviewHeight: 320,
                pdfComponentExtraParams: 'toolbar=0&navpanes=0&scrollbar=0&view=fitH',
                allowVideoPreview: true,
                allowAudioPreview: true,
            };
            if (maxSizeMb > 0) { opts.maxFileSize = maxSizeMb + 'MB'; }
            if (allowedTypes.length > 0) { opts.acceptedFileTypes = allowedTypes; }

            $(el).filepond(opts);
        });

        // Language switching for basic info
        $(document).ready(function() {
            let default_language = '<?= $current_language ?>';
            const customFieldFallbackLanguage = default_language;
            const customFieldLabelsByLanguage = <?= json_encode($custom_field_labels_by_language ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            function updateCustomFieldLabels(language) {
                document.querySelectorAll('.pcf-custom-label-text[data-custom-field-key]').forEach(function(el) {
                    const key = el.getAttribute('data-custom-field-key');
                    const byLang = customFieldLabelsByLanguage?.[key] ?? null;
                    const nextLabel = (byLang && byLang[language]) ? byLang[language] :
                        (byLang && byLang[customFieldFallbackLanguage]) ? byLang[customFieldFallbackLanguage] : null;
                    if (nextLabel !== null) { el.textContent = nextLabel; }
                });
            }

            $(document).on('click', '.language-option', function() {
                const language = $(this).data('language');

                $('.language-underline').css('width', '0%');
                $('#language-' + language).find('.language-underline').css('width', '100%');

                $('.language-text').removeClass('text-primary fw-medium');
                $('.language-text').addClass('text-muted');
                $('#language-' + language).find('.language-text').removeClass('text-muted');
                $('#language-' + language).find('.language-text').addClass('text-primary');

                if (language != default_language) {
                    $('#translationDiv-' + language).show();
                    $('#translationDiv-' + default_language).hide();
                }

                updateCustomFieldLabels(language);

                default_language = language;
            });
        });
    });
</script>

<script>
    $(function() {
        let popoverTimer;
        let currentPopover = null;
        let isOverPopover = false;
        let isOverTrigger = false;

        $('[data-toggle="popover"]').popover({
            html: true,
            trigger: 'manual',
            container: 'body'
        }).on('mouseenter', function() {
            const $this = $(this);
            isOverTrigger = true;
            clearTimeout(popoverTimer);

            if (currentPopover && currentPopover[0] !== $this[0]) {
                currentPopover.popover('hide');
            }

            currentPopover = $this;
            $this.popover('show');

        }).on('mouseleave', function() {
            isOverTrigger = false;
            startHideTimer();
        });

        $(document).on('mouseenter', '.popover', function() {
            isOverPopover = true;
            clearTimeout(popoverTimer);
        }).on('mouseleave', '.popover', function() {
            isOverPopover = false;
            startHideTimer();
        });

        function startHideTimer() {
            clearTimeout(popoverTimer);
            popoverTimer = setTimeout(function() {
                if (!isOverTrigger && !isOverPopover && currentPopover) {
                    currentPopover.popover('hide');
                    currentPopover = null;
                }
            }, 150);
        }
    });
</script>
