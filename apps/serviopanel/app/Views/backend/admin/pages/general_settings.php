<!-- Main Content new-->
<?php
$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);
?>
<style>
    .toggleButttonPostition {
        margin-left: 10px;
    }
</style>
<div class="main-content">
    <section class="section" id="pill-general_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('general_settings', 'General Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('admin/settings/general-settings') ?>"><?= labels('general_settings', "General Settings") ?></a></div>
            </div>
        </div>
        <ul class="justify-content-start nav nav-fill nav-pills pl-3 py-2 setting" id="gen-list">
            <div class="row">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="<?= base_url('admin/settings/general-settings') ?>" id="pills-general_settings-tab" aria-selected="true">
                        <?= labels('general_settings', "General Settings") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/about-us') ?>" id="pills-about_us" aria-selected="false">
                        <?= labels('about_us', "About Us") ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/contact-us') ?>" id="pills-about_us" aria-selected="false">
                        <?= labels('support_details', "Support Details") ?></a>
                </li>
            </div>
        </ul>
        <?= form_open_multipart(base_url('admin/settings/general-settings')) ?>
        <div class="row mb-3 mb-sm-3 mb-md-3 mb-xxs-12">
            <div class="col-lg-4 col-md-12 col-sm-12 col-xl-4 mb-md-3 mb-sm-3  mb-3">
                <div class="card h-100 ">
                    <div class="row m-0 border_bottom_for_cards">
                        <div class="col  ">
                            <div class="toggleButttonPostition"><?= labels('business_settings', 'Business settings') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <input type="hidden" id="set" value="<?= isset($system_timezone) ? $system_timezone : 'Asia/Kolkata' ?>">
                                    <input type="hidden" name="system_timezone_gmt" value="<?= isset($system_timezone_gmt) ? $system_timezone_gmt : '' ?>" id="system_timezone_gmt" value="<?= isset($system_timezone_gmt) ? $system_timezone_gmt : '+05:30' ?>" />
                                    <label for='timezone'><?= labels('select_time_zone', "Select Time Zone") ?></label>
                                    <select class='form-control selectric ' name='system_timezone' id='timezone' value="">
                                        <option value="">-- <?= labels('select_time_zone', "Select Time Zone") ?> --</option>
                                        <?php foreach ($timezones as $row): ?>
                                            <option
                                                value="<?= esc($row['timezone_id']) ?>"
                                                data-gmt="<?= esc($row['offset_text']) ?>">
                                                <?= esc($row['offset_text']) ?>
                                                -
                                                <?= esc($row['time']) ?>
                                                -
                                                <?= esc($row['timezone_id']) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="max_serviceable_distance"><?= labels('max_Serviceable_distance_in_kms', "Max Serviceable Distance") ?></label>
                                    <i data-content=" <?= labels('data_content_for_max_serviceable_distance', 'The system will use the distance values (KM) you provide to find providers in Xkms within the location chosen by the customer. For instance, if you set it to 100 KM, customers will see providers within 100 KM of their chosen location. If there are no providers within 100 KM, it\'ll say, We are not available here.') ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                    <div class="input-group">
                                        <input type="number" class="form-control custome_reset" name="max_serviceable_distance" id="max_serviceable_distance" value="<?= isset($max_serviceable_distance) ? $max_serviceable_distance : '' ?>" />
                                        <div class="input-group-append">
                                            <select class="form-control" name="distance_unit" id="distance_unit">
                                                <option value="km" <?= isset($distance_unit) && $distance_unit == 'km' ? 'selected' : '' ?>><?= labels('kms', 'Kms') ?></option>
                                                <option value="miles" <?= isset($distance_unit) && $distance_unit == 'miles' ? 'selected' : '' ?>><?= labels('miles', 'Miles') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <label for="max_serviceable_distance" class="text-danger"><?= labels('note_this_distance_is_used_while_search_nearby_partner_for_customer', " This distance is used while search nearby partner for customer") ?></label>
                                </div>
                            </div>
                            <div class="col-md-12 ">
                                <div class="form-group">
                                    <label for='logo'><?= labels('login_image', "Login Image") ?></label>
                                    <i data-content="<?= labels('data_content_for_login_image', "This picture will appear as the background on the login pages for the admin and provider panels.") ?>" class="fa fa-question-circle" data-original-title="" title=""></i></span> <small>(<?= labels('login_image_recommended_size', 'We recommend 1920 x 1080 pixels') ?>)</small>
                                </div>
                                <input type="file" name="login_image" class="filepond logo" id="login_image" accept="image/*">
                                <img class="settings_logo" style="border-radius: 8px" src="<?= isset($login_image) && $login_image != "" ? $login_image : base_url('public/frontend/retro/Login_BG.jpg') ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="primary_color"><?= labels('primary_color', "Primary Color") ?></label>
                                    <input type="text" onkeyup="change_color('change_color',this)" oninput="change_color('change_color',this)" class=" form-control" name="primary_color" id="primary_color" value="<?= isset($primary_color) ? $primary_color : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="secondary_color"><?= labels('secondary_color', "Secondary Color") ?></label>
                                    <input type="text" class=" form-control" name="secondary_color" id="secondary_color" value="<?= isset($secondary_color) ? $secondary_color : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-6 ">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('booking_auto_cancel', "Booking auto cancel Duration") ?> <span class="breadcrumb-item p-3 pt-2 text-primary">
                                            <i data-content="<?= labels('data_content_booking_auto_cancel_duration', 'If the booking is not accepted by the provider before the added cancelable duration from the actual booking time, the booking will be automatically canceled. If the booking is pre-paid, the amount will be credited to the customer’s bank account.For example, if a customer books a service at 4:00 PM, and the cancelable duration is 30 minutes, if the provider does not accept the booking by 3:30 PM, the booking will be canceled') ?>." class="fa fa-question-circle" data-original-title="" title=""></i></span></div>
                                    <input type="number" class="form-control" name="booking_auto_cancle_duration" id="booking_auto_cancle_duration" value="<?= isset($booking_auto_cancle_duration) ? $booking_auto_cancle_duration : '30' ?>" />
                                </div>
                            </div>
                            <div class="col-md-6 ">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('image_compression_preference', "Image Compression Preference") ?> <span class="breadcrumb-item p-3 pt-2 text-primary">
                                            <i data-content="<?= labels('data_content_image_compression_preference', 'If enabled, This high-quality image has been compressed to a lower quality, as per the quality provided in Image Compression Quality.') ?>" class="fa fa-question-circle" data-original-title="" title=""></i></span></div>
                                    <select name="image_compression_preference" class="form-control" id="image_compression_preference">
                                        <option value="0" <?php echo  isset($image_compression_preference) && $image_compression_preference == '0' ? 'selected' : '' ?>><?= labels('disable', 'Disable') ?></option>
                                        <option value="1" <?php echo  isset($image_compression_preference) && $image_compression_preference == '1' ? 'selected' : '' ?>><?= labels('enable', 'Enable') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12 mt-2" id="image_compression_quality_input">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('image_compression_quality', "Image Compression Quality") ?> <span class="breadcrumb-item p-3 pt-2 text-primary">
                                            <i data-content="<?= labels('data_content_image_compression_quality', 'This high-quality image has been compressed to a lower quality, as per the quality provided here.') ?>" class="fa fa-question-circle" data-original-title="" title=""></i></span></div>
                                    <input type="number" max=100 min=0 class="form-control" name="image_compression_quality" id="image_compression_quality" value="<?= isset($image_compression_quality) ? $image_compression_quality : '70' ?>" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- admin logos  -->
            <div class="col-lg-4 col-md-12 col-sm-12 col-xl-4 mb-md-3 mb-sm-3 mb-3">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col">
                            <div class="toggleButttonPostition"><?= labels('admin_logos', "Admin Logos") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 ">
                                <div class="form-group">
                                    <label for='logo'><?= labels('logo', "Logo") ?></label> <small>(<?= labels('logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                    <input type="file" name="logo" class="filepond logo" id="file" accept="image/*">
                                    <img class="settings_logo" src="<?= isset($logo) && $logo != "" ? $logo : base_url('public/backend/assets/img/news/img01.jpg') ?>">
                                </div>
                            </div>
                            <div class="col-md-12 ">
                                <div class="form-group">
                                    <label for='favicon'><?= labels('favicon', "Favicon") ?></label> <small>(<?= labels('half_logo_recommended_size', 'We recommend 40 x 40 pixels') ?>)</small>
                                    <input type="file" name="favicon" class="filepond logo" id="favicon" accept="image/*">
                                    <img class="settings_logo" src="<?= isset($favicon) && $favicon != "" ? $favicon : base_url('public/backend/assets/img/news/img01.jpg') ?>">
                                </div>
                            </div>
                            <div class="col-md-12 ">
                                <div class="form-group">
                                    <label for='half_logo'><?= labels('half_logo', "Half Logo") ?></label> <small>(<?= labels('half_logo_recommended_size', 'We recommend 40 x 40 pixels') ?>)</small>
                                    <input type="file" name="half_logo" class="filepond logo" id="half_logo" accept="image/*">
                                    <img class="settings_logo" src="<?= isset($half_logo) && $half_logo != "" ? $half_logo : base_url('public/backend/assets/img/news/img01.jpg') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- provider logos  -->
            <div class="col-lg-4 col-md-12 col-sm-12 col-xl-4 mb-md-3 mb-sm-3 mb-3">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('provider_logos', "Provider Logos") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 ">
                                <div class="form-group">
                                    <label for='logo'><?= labels('logo', "Logo") ?></label> <small>(<?= labels('logo_recommended_size', 'We recommend 182 x 60 pixels') ?>)</small>
                                    <input type="file" name="partner_logo" class="filepond logo" id="partner_logo" accept="image/*">
                                    <img class="settings_logo" src="<?= isset($partner_logo) && $partner_logo != "" ? $partner_logo : base_url('public/backend/assets/img/news/img01.jpg') ?>">
                                </div>
                            </div>
                            <div class="col-md-12 ">
                                <label for='favicon'><?= labels('favicon', "Favicon") ?></label>
                                <input type="file" name="partner_favicon" class="filepond logo" id="partner_favicon" accept="image/*">
                                <img class="settings_logo" src="<?= isset($partner_favicon) && $partner_favicon != "" ? $partner_favicon : base_url('public/backend/assets/img/news/img01.jpg') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 ">
                                <div class="form-group">
                                    <label for='halfLogo'><?= labels('half_logo', "Half Logo") ?></label> <small>(<?= labels('half_logo_recommended_size', 'We recommend 40 x 40 pixels') ?>)</small>
                                    <input type="file" name="partner_half_logo" class="filepond logo" id="partner_half_logo" accept="image/*">
                                    <img class="settings_logo" src="<?= isset($partner_half_logo) && $partner_half_logo != "" ? $partner_half_logo : base_url('public/backend/assets/img/news/img01.jpg') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xl-12 mb-md-3 mb-sm-3 mb-3">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('company_setting', "Company Settings") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Sort languages so default language appears first for better UI
                                    $sorted_company_languages = sort_languages_with_default_first($languages);
                                    foreach ($sorted_company_languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_company_language = $language['code'];
                                        }
                                    ?>
                                        <div class="language-company-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-company-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-company-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-company-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-3">
                                <div class="form-group">
                                    <label for='company_title' id="company_title_label"><?= labels('company_title', "Company Title") ?></label>
                                    <?php
                                    // Use the same sorted languages for form fields
                                    foreach ($sorted_company_languages as $index => $language) {
                                    ?>
                                        <input type='text' class="form-control custome_reset"
                                            name='company_title[<?= $language['code'] ?>]'
                                            id='company_title_<?= $language['code'] ?>'
                                            value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($company_title[$language['code']])) {
                                                        echo $company_title[$language['code']];
                                                    } else if (is_string($company_title) && $language['is_default'] == 1) {
                                                        echo $company_title;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>"
                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>" />
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for='support_email'><?= labels('support_email', "support Email") ?></label>
                                    <input type='email' class="form-control custome_reset" name='support_email' id='support_email' value="<?= isset($support_email) ? $support_email : '' ?>" />
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-group">
                                    <label for="phone"><?= labels('mobile', "Phone") ?></label>
                                    <input type="number" min="0" class="form-control custome_reset" name="phone" id="phone" value="<?= isset($phone) ? $phone : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="support_hours"><?= labels('support_hours', "Support Hours") ?></label>
                                    <input type="text" class="form-control custome_reset" name="support_hours" id="support_hours" value="<?= isset($support_hours) ? $support_hours : '09:00 to 18:00' ?>" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="copyright_details" id="copyright_details_label"><?= labels('copyright_details', "Copyright Details") ?></label>
                                    <?php
                                    // Use the same sorted languages for form fields
                                    foreach ($sorted_company_languages as $index => $language) {
                                    ?>
                                        <input type="text" class="form-control"
                                            name="copyright_details[<?= $language['code'] ?>]"
                                            id="copyright_details_<?= $language['code'] ?>"
                                            value="<?php
                                                    // Handle both new multi-language format and old single string format
                                                    if (isset($copyright_details[$language['code']])) {
                                                        echo $copyright_details[$language['code']];
                                                    } else if (is_string($copyright_details) && $language['is_default'] == 1) {
                                                        echo $copyright_details;
                                                    } else {
                                                        echo "";
                                                    }
                                                    ?>"
                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>" />
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="company_map_location"><?= labels('company_map_location', "Company Map Location") ?></label>
                                    <input type="text" class="form-control" name="company_map_location" id="company_map_location" value="<?= htmlentities(isset($company_map_location) ? $company_map_location : '') ?>" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="address" id="address_label"><?= labels('address', "Address") ?></label>
                                    <?php
                                    // Use the same sorted languages for form fields
                                    foreach ($sorted_company_languages as $index => $language) {
                                    ?>
                                        <textarea rows=1 class='form-control custome_reset'
                                            name="address[<?= $language['code'] ?>]"
                                            id="address_<?= $language['code'] ?>"
                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>"><?php
                                                                                                                                        // Handle both new multi-language format and old single string format
                                                                                                                                        if (isset($address[$language['code']])) {
                                                                                                                                            echo $address[$language['code']];
                                                                                                                                        } else if (is_string($address) && $language['is_default'] == 1) {
                                                                                                                                            echo $address;
                                                                                                                                        } else {
                                                                                                                                            echo "";
                                                                                                                                        }
                                                                                                                                        ?></textarea>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="short_description" id="short_description_label"><?= labels('short_description', "Short Description") ?></label>
                                    <?php
                                    // Use the same sorted languages for form fields
                                    foreach ($sorted_company_languages as $index => $language) {
                                    ?>
                                        <textarea rows=1 class='form-control custome_reset'
                                            name="short_description[<?= $language['code'] ?>]"
                                            id="short_description_<?= $language['code'] ?>"
                                            style="display: <?= $language['code'] == $current_company_language ? 'block' : 'none' ?>"><?php
                                                                                                                                        // Handle both new multi-language format and old single string format
                                                                                                                                        if (isset($short_description[$language['code']])) {
                                                                                                                                            echo $short_description[$language['code']];
                                                                                                                                        } else if (is_string($short_description) && $language['is_default'] == 1) {
                                                                                                                                            echo $short_description;
                                                                                                                                        } else {
                                                                                                                                            echo "";
                                                                                                                                        }
                                                                                                                                        ?></textarea>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6 col-sm-12 mb-md-3 mb-sm-3 mb-3">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('otp_settings', "OTP Settings") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 ">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('otp_system', "OTP System") ?> <span class="breadcrumb-item pt-2 text-primary">
                                            <i data-content="<?= labels('data_content_otp_system', 'If enabled, both the provider and admin need to obtain an OTP from the customer in order to mark the booking as completed. Otherwise, if no OTP verification is required, the booking can be directly marked as completed.') ?>" class="fa fa-question-circle" data-original-title="" title=""></i></span></div>
                                    <select name="otp_system" class="form-control">
                                        <option value="0" <?php echo  isset($otp_system) && $otp_system == '0' ? 'selected' : '' ?>><?= labels('disable', 'Disable') ?></option>
                                        <option value="1" <?php echo  isset($otp_system) && $otp_system == '1' ? 'selected' : '' ?>><?= labels('enable', 'Enable') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-12 mb-md-3 mb-sm-3 mb-3">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col-md-12 ">
                            <div class="toggleButttonPostition"><?= labels('deep_link_settings', "Deep Link Settings") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="schema"><?= labels('schema', "Schema") ?></label>
                                    <small class="text-grey"><?= labels('note', 'Note:') ?> <?= labels('note_for_deeplink', 'Please add your schema here using a single word in lowercase (e.g., edemand)') ?>.</small>
                                    <input type="text" class=" form-control" name="schema_for_deeplink" id="schema" value="<?= isset($schema_for_deeplink) ? htmlspecialchars($schema_for_deeplink, ENT_QUOTES, 'UTF-8') : '' ?>" placeholder="<?= labels('your_schema', 'your schema') ?>" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <!-- Country Currency -->
            <div class="col-md-6 col-sm-12">
                <div class="card h-100">
                    <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="toggleButttonPostition"><?= labels('country_currency', "Country Currency") ?></div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label><?= labels('country_currency', "Country Currency Code") ?></label>
                                    <select class="form-control" name="country_currency_code">
                                        <option value=AFN <?php echo  isset($country_currency_code)  && $country_currency_code == 'AFN' ? 'selected' : '' ?>> AFN - Afghanistan Afghani” </option>
                                        <option value=AED <?php echo  isset($country_currency_code) && $country_currency_code == 'AED' ? 'selected' : '' ?>> AED - United Arab Emirates Dirham </option>
                                        <option value=ALL <?php echo  isset($country_currency_code) && $country_currency_code == 'ALL' ? 'selected' : '' ?>> ALL - Albania Lek </option>
                                        <option value=AMD <?php echo  isset($country_currency_code) && $country_currency_code == 'AMD' ? 'selected' : '' ?>> AMD - Armenia Dram </option>
                                        <option value=ANG <?php echo  isset($country_currency_code) && $country_currency_code == 'ANG' ? 'selected' : '' ?>> ANG - Netherlands Antilles Guilder </option>
                                        <option value=AOA <?php echo  isset($country_currency_code) && $country_currency_code == 'AOA' ? 'selected' : '' ?>> AOA - Angola Kwanza </option>
                                        <option value=ARS <?php echo  isset($country_currency_code) && $country_currency_code == 'ARS' ? 'selected' : '' ?>> ARS - Argentina Peso </option>
                                        <option value=AUD <?php echo  isset($country_currency_code) && $country_currency_code == 'AUD' ? 'selected' : '' ?>> AUD - Australia Dollar </option>
                                        <option value=AWG <?php echo  isset($country_currency_code) && $country_currency_code == 'AWG' ? 'selected' : '' ?>> AWG - Aruba Guilder </option>
                                        <option value=AZN <?php echo  isset($country_currency_code) && $country_currency_code == 'AZN' ? 'selected' : '' ?>> AZN - Azerbaijan Manat </option>
                                        <option value=BAM <?php echo  isset($country_currency_code) && $country_currency_code == 'BAM' ? 'selected' : '' ?>> BAM - Bosnia and Herzegovina Convertible Mark </option>
                                        <option value=BBD <?php echo  isset($country_currency_code) && $country_currency_code == 'BBD' ? "selected" : '' ?>> BBD - Barbados Dollar </option>
                                        <option value=BDT <?php echo  isset($country_currency_code) && $country_currency_code == 'BDT' ? 'selected' : '' ?>> BDT - Bangladesh Taka </option>
                                        <option value=BGN <?php echo  isset($country_currency_code) && $country_currency_code == 'BGN' ? 'selected' : '' ?>> BGN - Bulgaria Lev </option>
                                        <option value=BHD <?php echo  isset($country_currency_code) && $country_currency_code == 'BHD' ? 'selected' : '' ?>> BHD - Bahrain Dinar </option>
                                        <option value=BIF <?php echo  isset($country_currency_code) && $country_currency_code == 'BIF' ? 'selected' : '' ?>> BIF - Burundi Franc </option>
                                        <option value=BMD <?php echo  isset($country_currency_code) && $country_currency_code == 'BMD' ? 'selected' : '' ?>> BMD - Bermuda Dollar </option>
                                        <option value=BND <?php echo  isset($country_currency_code) && $country_currency_code == 'BND' ? 'selected' : '' ?>> BND - Brunei Darussalam Dollar </option>
                                        <option value=BOB <?php echo  isset($country_currency_code) && $country_currency_code == 'BOB' ? 'selected' : '' ?>> BOB - Bolivia Bolíviano </option>
                                        <option value=BRL <?php echo  isset($country_currency_code) && $country_currency_code == 'BRL' ? 'selected' : '' ?>> BRL - Brazil Real </option>
                                        <option value=BSD <?php echo  isset($country_currency_code) && $country_currency_code == 'BSD' ? 'selected' : '' ?>> BSD - Bahamas Dollar </option>
                                        <option value=BTN <?php echo  isset($country_currency_code) && $country_currency_code == 'BTN' ? 'selected' : '' ?>> BTN - Bhutan Ngultrum </option>
                                        <option value=BWP <?php echo  isset($country_currency_code) && $country_currency_code == 'BWP' ? 'selected' : '' ?>> BWP - Botswana Pula </option>
                                        <option value=BYN <?php echo  isset($country_currency_code) && $country_currency_code == 'BYN' ? 'selected' : '' ?>> BYN - Belarus Ruble </option>
                                        <option value=BZD <?php echo  isset($country_currency_code) && $country_currency_code == 'BZD' ? 'selected' : '' ?>> BZD - Belize Dollar </option>
                                        <option value=CAD <?php echo  isset($country_currency_code) && $country_currency_code == 'CAD' ? 'selected' : '' ?>> CAD - Canada Dollar </option>
                                        <option value=CDF <?php echo  isset($country_currency_code) && $country_currency_code == 'CDF' ? 'selected' : '' ?>> CDF - Congo/Kinshasa Franc” </option>
                                        <option value=CHF <?php echo  isset($country_currency_code) && $country_currency_code == 'CHF' ? 'selected' : '' ?>> CHF - Switzerland Franc </option>
                                        <option value=CLP <?php echo  isset($country_currency_code) && $country_currency_code == 'CLP' ? 'selected' : '' ?>> CLP - Chile Peso </option>
                                        <option value=CNY <?php echo  isset($country_currency_code) && $country_currency_code == 'CNY' ? 'selected' : '' ?>> CNY - China Yuan Renminbi </option>
                                        <option value=COP <?php echo  isset($country_currency_code) && $country_currency_code == 'COP' ? 'selected' : '' ?>> COP - Colombia Peso </option>
                                        <option value=CRC <?php echo  isset($country_currency_code) && $country_currency_code == 'CRC' ? 'selected' : '' ?>> CRC - Costa Rica Colon </option>
                                        <option value=CUC <?php echo  isset($country_currency_code) && $country_currency_code == 'CUC' ? 'selected' : '' ?>> CUC - Cuba Convertible Peso </option>
                                        <option value=CUP <?php echo  isset($country_currency_code) && $country_currency_code == 'CUP' ? 'selected' : '' ?>> CUP - Cuba Peso </option>
                                        <option value=CVE <?php echo  isset($country_currency_code) && $country_currency_code == 'CVE' ? 'selected' : '' ?>> CVE - Cape Verde Escudo </option>
                                        <option value=CZK <?php echo  isset($country_currency_code) && $country_currency_code == 'CZK' ? 'selected' : '' ?>> CZK - Czech Republic Koruna </option>
                                        <option value=DJF <?php echo  isset($country_currency_code) && $country_currency_code == 'DJF' ? 'selected' : '' ?>> DJF - Djibouti Franc </option>
                                        <option value=DKK <?php echo  isset($country_currency_code) && $country_currency_code == 'DKK' ? 'selected' : '' ?>> DKK - Denmark Krone </option>
                                        <option value=DOP <?php echo  isset($country_currency_code) && $country_currency_code == 'DOP' ? 'selected' : '' ?>> DOP - Dominican Republic Peso </option>
                                        <option value=DZD <?php echo  isset($country_currency_code) && $country_currency_code == 'DZD' ? 'selected' : '' ?>> DZD - Algeria Dinar </option>
                                        <option value=EGP <?php echo  isset($country_currency_code) && $country_currency_code == 'EGP' ? 'selected' : '' ?>> EGP - Egypt Pound </option>
                                        <option value=ERN <?php echo  isset($country_currency_code) && $country_currency_code == 'ERN' ? 'selected' : '' ?>> ERN - Eritrea Nakfa </option>
                                        12                  <option value=ETB <?php echo  isset($country_currency_code) && $country_currency_code == 'ETB' ? 'selected' : '' ?>> ETB - Ethiopia Birr </option>
                                        <option value=EUR <?php echo  isset($country_currency_code) && $country_currency_code == 'EUR' ? 'selected' : '' ?>> EUR - Euro Member Countries </option>
                                        <option value=FJD <?php echo  isset($country_currency_code) && $country_currency_code == 'FJD' ? 'selected' : '' ?>> FJD - Fiji Dollar </option>
                                        <option value=FKP <?php echo  isset($country_currency_code) && $country_currency_code == 'FKP' ? 'selected' : '' ?>> FKP - Falkland Islands (Malvinas) Pound” </option>
                                        <option value=GBP <?php echo  isset($country_currency_code) && $country_currency_code == 'GBP' ? 'selected' : '' ?>> GBP - United Kingdom Pound </option>
                                        <option value=GEL <?php echo  isset($country_currency_code) && $country_currency_code == 'GEL' ? 'selected' : '' ?>> GEL - Georgia Lari </option>
                                        <option value=GGP <?php echo  isset($country_currency_code) && $country_currency_code == 'GGP' ? 'selected' : '' ?>> GGP - Guernsey Pound </option>
                                        <option value=GHS <?php echo  isset($country_currency_code) && $country_currency_code == 'GHS' ? 'selected' : '' ?>> GHS - Ghana Cedi </option>
                                        <option value=GIP <?php echo  isset($country_currency_code) && $country_currency_code == 'GIP' ? 'selected' : '' ?>> GIP - Gibraltar Pound </option>
                                        <option value=GMD <?php echo  isset($country_currency_code) && $country_currency_code == 'GMD' ? 'selected' : '' ?>> GMD - Gambia Dalasi </option>
                                        <option value=GNF <?php echo  isset($country_currency_code) && $country_currency_code == 'GNF' ? 'selected' : '' ?>> GNF - Guinea Franc </option>
                                        <option value=GTQ <?php echo  isset($country_currency_code) && $country_currency_code == 'GTQ' ? 'selected' : '' ?>> GTQ - Guatemala Quetzal </option>
                                        <option value=GYD <?php echo  isset($country_currency_code) && $country_currency_code == 'GYD' ? 'selected' : '' ?>> GYD - Guyana Dollar </option>
                                        <option value=HKD <?php echo  isset($country_currency_code) && $country_currency_code == 'HKD' ? 'selected' : '' ?>> HKD - Hong Kong Dollar </option>
                                        <option value=HNL <?php echo  isset($country_currency_code) && $country_currency_code == 'HNL' ? 'selected' : '' ?>> HNL - Honduras Lempira </option>
                                        <option value=HRK <?php echo  isset($country_currency_code) && $country_currency_code == 'HRK' ? 'selected' : '' ?>> HRK - Croatia Kuna </option>
                                        <option value=HTG <?php echo  isset($country_currency_code) && $country_currency_code == 'HTG' ? 'selected' : '' ?>> HTG - Haiti Gourde </option>
                                        <option value=HUF <?php echo  isset($country_currency_code) && $country_currency_code == 'HUF' ? 'selected' : '' ?>> HUF - Hungary Forint </option>
                                        <option value=IDR <?php echo  isset($country_currency_code) && $country_currency_code == 'IDR' ? 'selected' : '' ?>> IDR - Indonesia Rupiah </option>
                                        <option value=ILS <?php echo  isset($country_currency_code) && $country_currency_code == 'ILS' ? 'selected' : '' ?>> ILS - Israel Shekel </option>
                                        <option value=IMP <?php echo  isset($country_currency_code) && $country_currency_code == 'IMP' ? 'selected' : '' ?>> IMP - Isle of Man Pound </option>
                                        <option value=INR <?php echo  isset($country_currency_code) && $country_currency_code == 'INR' ? 'selected' : '' ?>> INR - India Rupee </option>
                                        <option value=IQD <?php echo  isset($country_currency_code) && $country_currency_code == 'IQD' ? 'selected' : '' ?>> IQD - Iraq Dinar </option>
                                        <option value=IRR <?php echo  isset($country_currency_code) && $country_currency_code == 'IRR' ? 'selected' : '' ?>> IRR - Iran Rial </option>
                                        <option value=ISK <?php echo  isset($country_currency_code) && $country_currency_code == 'ISK' ? 'selected' : '' ?>> ISK - Iceland Krona </option>
                                        <option value=JEP <?php echo  isset($country_currency_code) && $country_currency_code == 'JEP' ? 'selected' : '' ?>> JEP - Jersey Pound </option>
                                        <option value=JMD <?php echo  isset($country_currency_code) && $country_currency_code == 'JMD' ? 'selected' : '' ?>> JMD - Jamaica Dollar </option>
                                        <option value=JOD <?php echo  isset($country_currency_code) && $country_currency_code == 'JOD' ? 'selected' : '' ?>> JOD - Jordan Dinar </option>
                                        <option value=JPY <?php echo  isset($country_currency_code) && $country_currency_code == 'JPY' ? 'selected' : '' ?>> JPY - Japan Yen </option>
                                        <option value=KES <?php echo  isset($country_currency_code) && $country_currency_code == 'KES' ? 'selected' : '' ?>> KES - Kenya Shilling </option>
                                        <option value=KGS <?php echo  isset($country_currency_code) && $country_currency_code == 'KGS' ? 'selected' : '' ?>> KGS - Kyrgyzstan Som </option>
                                        <option value=KHR <?php echo  isset($country_currency_code) && $country_currency_code == 'KHR' ? 'selected' : '' ?>> KHR - Cambodia Riel </option>
                                        <option value=KMF <?php echo  isset($country_currency_code) && $country_currency_code == 'KMF' ? 'selected' : '' ?>> KMF - Comorian Franc </option>
                                        <option value=KPW <?php echo  isset($country_currency_code) && $country_currency_code == 'KPW' ? 'selected' : '' ?>> KPW - Korea (North) Won </option>
                                        <option value=KRW <?php echo  isset($country_currency_code) && $country_currency_code == 'KRW' ? 'selected' : '' ?>> KRW - Korea (South) Won </option>
                                        <option value=KWD <?php echo  isset($country_currency_code) && $country_currency_code == 'KWD' ? 'selected' : '' ?>> KWD - Kuwait Dinar </option>
                                        <option value=KYD <?php echo  isset($country_currency_code) && $country_currency_code == 'KYD' ? 'selected' : '' ?>> KYD - Cayman Islands Dollar </option>
                                        <option value=KZT <?php echo  isset($country_currency_code) && $country_currency_code == 'KZT' ? 'selected' : '' ?>> KZT - Kazakhstan Tenge </option>
                                        <option value=LAK <?php echo  isset($country_currency_code) && $country_currency_code == 'LAK' ? 'selected' : '' ?>> LAK - Laos Kip </option>
                                        <option value=LBP <?php echo  isset($country_currency_code) && $country_currency_code == 'LBP' ? 'selected' : '' ?>> LBP - Lebanon Pound </option>
                                        <option value=LKR <?php echo  isset($country_currency_code) && $country_currency_code == 'LKR' ? 'selected' : '' ?>> LKR - Sri Lanka Rupee </option>
                                        <option value=LRD <?php echo  isset($country_currency_code) && $country_currency_code == 'LRD' ? 'selected' : '' ?>> LRD - Liberia Dollar </option>
                                        <option value=LSL <?php echo  isset($country_currency_code) && $country_currency_code == 'LSL' ? 'selected' : '' ?>> LSL - Lesotho Loti </option>
                                        <option value=LYD <?php echo  isset($country_currency_code) && $country_currency_code == 'LYD' ? 'selected' : '' ?>> LYD - Libya Dinar </option>
                                        <option value=MAD <?php echo  isset($country_currency_code) && $country_currency_code == 'MAD' ? 'selected' : '' ?>> MAD - Morocco Dirham </option>
                                        <option value=MDL <?php echo  isset($country_currency_code) && $country_currency_code == 'MDL' ? 'selected' : '' ?>> MDL - Moldova Leu </option>
                                        <option value=MGA <?php echo  isset($country_currency_code) && $country_currency_code == 'MGA' ? 'selected' : '' ?>> MGA - Madagascar Ariary </option>
                                        <option value=MKD <?php echo  isset($country_currency_code) && $country_currency_code == 'MKD' ? 'selected' : '' ?>> MKD - Macedonia Denar” </option>
                                        <option value=MMK <?php echo  isset($country_currency_code) && $country_currency_code == 'MMK' ? 'selected' : '' ?>> MMK - Myanmar (Burma) Kyat” </option>
                                        <option value=MNT <?php echo  isset($country_currency_code) && $country_currency_code == 'MNT' ? 'selected' : '' ?>> MNT - Mongolia Tughrik” </option>
                                        <option value=MOP <?php echo  isset($country_currency_code) && $country_currency_code == 'MOP' ? 'selected' : '' ?>> MOP - Macau Pataca” </option>
                                        <option value=MRU <?php echo  isset($country_currency_code) && $country_currency_code == 'MRU' ? 'selected' : '' ?>> MRU - Mauritania Ouguiya” </option>
                                        <option value=MUR <?php echo  isset($country_currency_code) && $country_currency_code == 'MUR' ? 'selected' : '' ?>> MUR - Mauritius Rupee” </option>
                                        <option value=MVR <?php echo  isset($country_currency_code) && $country_currency_code == 'MVR' ? 'selected' : '' ?>> MVR - Maldives (Maldive Islands) Rufiyaa” </option>
                                        <option value=MWK <?php echo  isset($country_currency_code) && $country_currency_code == 'MWK' ? 'selected' : '' ?>> MWK - Malawi Kwacha” </option>
                                        <option value=MXN <?php echo  isset($country_currency_code) && $country_currency_code == 'MXN' ? 'selected' : '' ?>> MXN - Mexico Peso” </option>
                                        <option value=MYR <?php echo  isset($country_currency_code) && $country_currency_code == 'MYR' ? 'selected' : '' ?>> MYR - Malaysia Ringgit” </option>
                                        <option value=MZN <?php echo  isset($country_currency_code) && $country_currency_code == 'MZN' ? 'selected' : '' ?>> MZN - Mozambique Metical” </option>
                                        <option value=NAD <?php echo  isset($country_currency_code) && $country_currency_code == 'NAD' ? 'selected' : '' ?>> NAD - Namibia Dollar </option>
                                        <option value=NGN <?php echo  isset($country_currency_code) && $country_currency_code == 'NGN' ? 'selected' : '' ?>> NGN - Nigeria Naira </option>
                                        <option value=NIO <?php echo  isset($country_currency_code) && $country_currency_code == 'NIO' ? 'selected' : '' ?>> NIO - Nicaragua Cordoba </option>
                                        <option value=NOK <?php echo  isset($country_currency_code) && $country_currency_code == 'NOK' ? 'selected' : '' ?>> NOK - Norway Krone </option>
                                        <option value=NPR <?php echo  isset($country_currency_code) && $country_currency_code == 'NPR' ? 'selected' : '' ?>> NPR - Nepal Rupee </option>
                                        <option value=NZD <?php echo  isset($country_currency_code) && $country_currency_code == 'NZD' ? 'selected' : '' ?>> NZD - New Zealand Dollar </option>
                                        <option value=OMR <?php echo  isset($country_currency_code) && $country_currency_code == 'OMR' ? 'selected' : '' ?>> OMR - Oman Rial </option>
                                        <option value=PAB <?php echo  isset($country_currency_code) && $country_currency_code == 'PAB' ? 'selected' : '' ?>> PAB - Panama Balboa </option>
                                        <option value=PEN <?php echo  isset($country_currency_code) && $country_currency_code == 'PEN' ? 'selected' : '' ?>> PEN - Peru Sol </option>
                                        <option value=PGK <?php echo  isset($country_currency_code) && $country_currency_code == 'PGK' ? 'selected' : '' ?>> PGK - Papua New Guinea Kina </option>
                                        <option value=PHP <?php echo  isset($country_currency_code) && $country_currency_code == 'PHP' ? 'selected' : '' ?>> PHP - Philippines Peso </option>
                                        <option value=PKR <?php echo  isset($country_currency_code) && $country_currency_code == 'PKR' ? 'selected' : '' ?>> PKR - Pakistan Rupee </option>
                                        <option value=PLN <?php echo  isset($country_currency_code) && $country_currency_code == 'PLN' ? 'selected' : '' ?>> PLN - Poland Zloty </option>
                                        <option value=PYG <?php echo  isset($country_currency_code) && $country_currency_code == 'PYG' ? 'selected' : '' ?>> PYG - Paraguay Guarani </option>
                                        <option value=QAR <?php echo  isset($country_currency_code) && $country_currency_code == 'QAR' ? 'selected' : '' ?>> QAR - Qatar Riyal </option>
                                        <option value=RON <?php echo  isset($country_currency_code) && $country_currency_code == 'RON' ? 'selected' : '' ?>> RON - Romania Leu </option>
                                        <option value=RSD <?php echo  isset($country_currency_code) && $country_currency_code == 'RSD' ? 'selected' : '' ?>> RSD - Serbia Dinar </option>
                                        <option value=RUB <?php echo  isset($country_currency_code) && $country_currency_code == 'RUB' ? 'selected' : '' ?>> RUB - Russia Ruble </option>
                                        <option value=RWF <?php echo  isset($country_currency_code) && $country_currency_code == 'RWF' ? 'selected' : '' ?>> RWF - Rwanda Franc </option>
                                        <option value=SAR <?php echo  isset($country_currency_code) && $country_currency_code == 'SAR' ? 'selected' : '' ?>> SAR - Saudi Arabia Riyal </option>
                                        <option value=SBD <?php echo  isset($country_currency_code) && $country_currency_code == 'SBD' ? 'selected' : '' ?>> SBD - Solomon Islands Dollar </option>
                                        <option value=SCR <?php echo  isset($country_currency_code) && $country_currency_code == 'SCR' ? 'selected' : '' ?>> SCR - Seychelles Rupee </option>
                                        <option value=SDG <?php echo  isset($country_currency_code) && $country_currency_code == 'SDG' ? 'selected' : '' ?>> SDG - Sudan Pound </option>
                                        <option value=SEK <?php echo  isset($country_currency_code) && $country_currency_code == 'SEK' ? 'selected' : '' ?>> SEK - Sweden Krona </option>
                                        <option value=SGD <?php echo  isset($country_currency_code) && $country_currency_code == 'SGD' ? 'selected' : '' ?>> SGD - Singapore Dollar </option>
                                        <option value=SHP <?php echo  isset($country_currency_code) && $country_currency_code == 'SHP' ? 'selected' : '' ?>> SHP - Saint Helena Pound </option>
                                        <option value=SLL <?php echo  isset($country_currency_code) && $country_currency_code == 'SLL' ? 'selected' : '' ?>> SLL - Sierra Leone Leone </option>
                                        <option value=SOS <?php echo  isset($country_currency_code) && $country_currency_code == 'SOS' ? 'selected' : '' ?>> SOS - Somalia Shilling </option>
                                        <option value=SPL <?php echo  isset($country_currency_code) && $country_currency_code == '“SP' ? 'selected' : '' ?>”> SPL - Seborga Luigino </option>
                                        <option value=SRD <?php echo  isset($country_currency_code) && $country_currency_code == 'SRD' ? 'selected' : '' ?>> SRD - Suriname Dollar </option>
                                        <option value=STN <?php echo  isset($country_currency_code) && $country_currency_code == 'STN' ? 'selected' : '' ?>> STN - São Tomé and Príncipe Dobra </option>
                                        <option value=SVC <?php echo  isset($country_currency_code) && $country_currency_code == 'SVC' ? 'selected' : '' ?>> SVC - El Salvador Colon </option>
                                        <option value=SYP <?php echo  isset($country_currency_code) && $country_currency_code == 'SYP' ? 'selected' : '' ?>> SYP - Syria Pound </option>
                                        <option value=SZL <?php echo  isset($country_currency_code) && $country_currency_code == 'SZL' ? 'selected' : '' ?>> SZL - eSwatini Lilangeni </option>
                                        <option value=THB <?php echo  isset($country_currency_code) && $country_currency_code == 'THB' ? 'selected' : '' ?>> THB - Thailand Baht </option>
                                        <option value=TJS <?php echo  isset($country_currency_code) && $country_currency_code == 'TJS' ? 'selected' : '' ?>> TJS - Tajikistan Somoni </option>
                                        <option value=TMT <?php echo  isset($country_currency_code) && $country_currency_code == 'TMT' ? 'selected' : '' ?>> TMT - Turkmenistan Manat </option>
                                        <option value=TND <?php echo  isset($country_currency_code) && $country_currency_code == 'TND' ? 'selected' : '' ?>> TND - Tunisia Dinar
                                        <option value=TOP <?php echo  isset($country_currency_code) && $country_currency_code == 'TOP' ? 'selected' : '' ?>> TOP - Tonga Pa’anga </option>
                                        <option value=TRY <?php echo  isset($country_currency_code) && $country_currency_code == 'TRY' ? 'selected' : '' ?>> TRY - Turkey Lira </option>
                                        <option value=TTD <?php echo  isset($country_currency_code) && $country_currency_code == 'TTD' ? 'selected' : '' ?>> TTD - Trinidad and Tobago Dollar </option>
                                        <option value=TVD <?php echo  isset($country_currency_code) && $country_currency_code == 'TVD' ? 'selected' : '' ?>> TVD - Tuvalu Dollar </option>
                                        <option value=TWD <?php echo  isset($country_currency_code) && $country_currency_code == 'TWD' ? 'selected' : '' ?>> TWD - Taiwan New Dollar </option>
                                        <option value=TZS <?php echo  isset($country_currency_code) && $country_currency_code == 'TZS' ? 'selected' : '' ?>> TZS - Tanzania Shilling </option>
                                        <option value=UAH <?php echo  isset($country_currency_code) && $country_currency_code == 'UAH' ? 'selected' : '' ?>> UAH - Ukraine Hryvnia </option>
                                        <option value=UGX <?php echo  isset($country_currency_code) && $country_currency_code == 'UGX' ? 'selected' : '' ?>> UGX - Uganda Shilling </option>
                                        <option value=USD <?php echo  isset($country_currency_code) && $country_currency_code == 'USD' ? 'selected' : '' ?>> USD - United States Dollar </option>
                                        <option value=UYU <?php echo  isset($country_currency_code) && $country_currency_code == 'UYU' ? 'selected' : '' ?>> UYU - Uruguay Peso” </option>
                                        <option value=UZS <?php echo  isset($country_currency_code) && $country_currency_code == 'UZS' ? 'selected' : '' ?>> UZS - Uzbekistan Som </option>
                                        <option value=VEF <?php echo  isset($country_currency_code) && $country_currency_code == 'VEF' ? 'selected' : '' ?>> VEF - Venezuela Bolívar </option>
                                        <option value=VND <?php echo  isset($country_currency_code) && $country_currency_code == 'VND' ? 'selected' : '' ?>> VND - Viet Nam Dong” </option>
                                        <option value=VUV <?php echo  isset($country_currency_code) && $country_currency_code == 'VUV' ? 'selected' : '' ?>> VUV - Vanuatu Vatu </option>
                                        <option value=WST <?php echo  isset($country_currency_code) && $country_currency_code == 'WST' ? 'selected' : '' ?>> WST - Samoa Tala </option>
                                        <option value=XAF <?php echo  isset($country_currency_code) && $country_currency_code == 'XAF' ? 'selected' : '' ?>> XAF - Communauté Financière Africaine (BEAC) CFA Franc BEAC </option>
                                        <option value=XCD <?php echo  isset($country_currency_code) && $country_currency_code == 'XCD' ? 'selected' : '' ?>> XCD - East Caribbean Dollar </option>
                                        <option value=XDR <?php echo  isset($country_currency_code) && $country_currency_code == 'XDR' ? 'selected' : '' ?>> XDR - International Monetary Fund (IMF) Special Drawing Rights </option>
                                        <option value=XOF <?php echo  isset($country_currency_code) && $country_currency_code == 'XOF' ? 'selected' : '' ?>> XOF - Communauté Financière Africaine (BCEAO) Franc </option>
                                        <option value=XPF <?php echo  isset($country_currency_code) && $country_currency_code == 'XPF' ? 'selected' : '' ?>> XPF - Comptoirs Français du Pacifique (CFP) Franc </option>
                                        <option value=YER <?php echo  isset($country_currency_code) && $country_currency_code == 'YER' ? 'selected' : '' ?>> YER - Yemen Rial </option>
                                        <option value=ZAR <?php echo  isset($country_currency_code) && $country_currency_code == 'ZAR' ? 'selected' : '' ?>> ZAR - South Africa Rand </option>
                                        <option value=ZMW <?php echo  isset($country_currency_code) && $country_currency_code == 'ZMW' ? 'selected' : '' ?>> ZMW - Zambia Kwacha </option>
                                        <option value=ZWD <?php echo  isset($country_currency_code) && $country_currency_code == 'ZWD' ? 'selected' : '' ?>> ZWD - Zimbabwe Dollar </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for='currency'><?= labels('currency_symbol', "Currency Symbol") ?></label>
                                    <input type='text' class='form-control' name='currency' id='currency' value="<?= isset($currency) ? $currency : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label><?= labels('decimal_point', "Decimal Point") ?></label>
                                <select class="form-control" name="decimal_point">
                                    <option value="0" <?php echo  isset($decimal_point)  && $decimal_point == '0' ? 'selected' : '' ?>>0</option>
                                    <option value="1" <?php echo  isset($decimal_point)  && $decimal_point == '1' ? 'selected' : '' ?>>1</option>
                                    <option value="2" <?php echo  isset($decimal_point)  && $decimal_point == '2' ? 'selected' : '' ?>>2</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-12">
                <div class="card h-100">
                    <div class="row border_bottom_for_cards m-0">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('file_manager_settings', "File Manager Settings") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="type" class="required"><?= labels('file_manager', 'File Manager') ?></label>
                                    <select class="select2" name="file_manager" id="file_manager" required>
                                        <option value="local_server" <?php echo  isset($file_manager) && $file_manager == 'local_server' ? 'selected' : '' ?>><?= labels('local_server', 'Local Server') ?></option>
                                        <option value="aws_s3" <?php echo  isset($file_manager) && $file_manager == 'aws_s3' ? 'selected' : '' ?>><?= labels('aws_s3', 'AWS S3') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('file_transfer_process', "File Transfer Process") ?></div>
                                    <label class="mt-2">
                                        <input type="hidden" name="file_transfer_process" value="<?= isset($file_transfer_process) && $file_transfer_process == 1 ? '1' : '0' ?>" id="file_transfer_process_value">
                                        <input
                                            type="checkbox"
                                            class="status-switch app-toggle" id="file_transfer_process" data-hidden="#file_transfer_process_value"
                                            data-note="#file_transfer_note"
                                            data-value="<?= isset($file_transfer_process) && $file_transfer_process == 1 ? 1 : 0 ?>"
                                            value="0"
                                            <?= isset($file_transfer_process) && $file_transfer_process == 1 ? 'checked' : '' ?>>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12" id="file_transfer_note">
                                <div class="alert alert-light alert-has-icon">
                                    <div class="alert-icon"><i class="far fa-lightbulb"></i></div>
                                    <div class="alert-body">
                                        <div class="alert-title"><?= labels('note', 'Note') ?></div>
                                        <?= labels('enable_file_transfer_need_to_set_below_command_cron_job', 'If you enable file transfer process then you need to set below command to your cron job') ?> ::
                                        <br>
                                        <p class="danger">* * * * * cd /path/to/your/project && php spark queue:work filemanagerchanges --sleep=3 --tries=3 -max-jobs=20 --stop-when-empty >> /dev/null 2>&1</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row aws_s3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="aws_access_key_id"><?= labels('aws_access_key_id', "AWS Access Key ID") ?></label>
                                    <input type="text" class=" form-control" name="aws_access_key_id" id="aws_access_key_id" value="<?= (isset($aws_access_key_id) && (ALLOW_VIEW_KEYS == 1)) ? $aws_access_key_id : 'your aws access key id' ?>" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="aws_access_key_id"><?= labels('aws_secret_access_key', "AWS Secret Access Key") ?></label>
                                    <input type="text" class=" form-control" name="aws_secret_access_key" id="aws_secret_access_key" value="<?= (isset($aws_secret_access_key) && (ALLOW_VIEW_KEYS == 1)) ? $aws_secret_access_key : 'you aws secret access key' ?>" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="aws_access_key_id"><?= labels('aws_default_region', "AWS Default Region") ?></label>
                                    <select name="aws_default_region" class="select2" id="aws_default_region">
                                        <option value="us-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-east-1' ? 'selected' : '' ?>>US East (N. Virginia) - us-east-1</option>
                                        <option value="us-east-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-east-2' ? 'selected' : '' ?>>US East (Ohio) - us-east-2</option>
                                        <option value="us-west-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-west-1' ? 'selected' : '' ?>>US West (N. California) - us-west-1</option>
                                        <option value="us-west-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-west-2' ? 'selected' : '' ?>>US West (Oregon) - us-west-2</option>
                                        <option value="ca-central-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ca-central-1' ? 'selected' : '' ?>>Canada (Central) - ca-central-1</option>
                                        <option value="ca-central-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ca-central-1' ? 'selected' : '' ?>>Canada (West) - ca-central-1</option>
                                        <option value="us-gov-west-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-gov-west-1' ? 'selected' : '' ?>>GovCloud (US-West) - us-gov-west-1</option>
                                        <option value="us-gov-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-gov-east-1' ? 'selected' : '' ?>>GovCloud (US-East) - us-gov-east-1</option>
                                        <option value="mx-central-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'mx-central-1' ? 'selected' : '' ?>>Mexico (Central) - mx-central-1</option>
                                        <option value="sa-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'sa-east-1' ? 'selected' : '' ?>>Sao Paulo, Brazil - sa-east-1</option>
                                        <option value="eu-west-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-west-2' ? 'selected' : '' ?>>London, UK - eu-west-2</option>
                                        <option value="eu-central-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-central-1' ? 'selected' : '' ?>>Frankfurt, Germany - eu-central-1</option>
                                        <option value="eu-west-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-west-1' ? 'selected' : '' ?>>Ireland - eu-west-1</option>
                                        <option value="eu-west-3" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-west-3' ? 'selected' : '' ?>>Paris, France - eu-west-3</option>
                                        <option value="eu-north-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-north-1' ? 'selected' : '' ?>>Stockholm, Sweden - eu-north-1</option>
                                        <option value="eu-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-south-1' ? 'selected' : '' ?>>Milan, Italy - eu-south-1</option>
                                        <option value="eu-south-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-south-2' ? 'selected' : '' ?>>Spain - eu-south-2</option>
                                        <option value="me-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'me-south-1' ? 'selected' : '' ?>>Bahrain - me-south-1</option>
                                        <option value="af-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-east-1' ? 'selected' : '' ?>>Cape Town, South Africa - af-south-1</option>
                                        <option value="ap-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-east-1' ? 'selected' : '' ?>>Hong Kong SAR, China - ap-east-1</option>
                                        <option value="ap-northeast-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-northeast-1' ? 'selected' : '' ?>>Tokyo, Japan - ap-northeast-1</option>
                                        <option value="ap-northeast-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-northeast-2' ? 'selected' : '' ?>>Seoul, South Korea - ap-northeast-2</option>
                                        <option value="ap-southeast-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-southeast-2' ? 'selected' : '' ?>>Singapore - ap-southeast-1</option>
                                        <option value="ap-southeast-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-east-1' ? 'selected' : '' ?>>Sydney, Australia - ap-southeast-2</option>
                                        <option value="ap-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-south-1' ? 'selected' : '' ?>>Mumbai, India - ap-south-1</option>
                                        <option value="ap-southeast-3" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-southeast-3' ? 'selected' : '' ?>>Jakarta, Indonesia - ap-southeast-3</option>
                                        <option value="cn-north-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'cn-north-1' ? 'selected' : '' ?>>Beijing, China - cn-north-1</option>
                                        <option value="cn-northwest-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'cn-northwest-1' ? 'selected' : '' ?>>Ningxia, China - cn-northwest-1</option>
                                        <option value="ap-northeast-3" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-northeast-3' ? 'selected' : '' ?>>Osaka-Local, Japan - ap-northeast-3</option>
                                        <option value="ap-southeast-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-southeast-1' ? 'selected' : '' ?>>Singapore - ap-southeast-1</option>
                                        <option value="ap-southeast-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-southeast-2' ? 'selected' : '' ?>>Sydney, Australia - ap-southeast-2</option>
                                        <option value="ap-southeast-3" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-southeast-3' ? 'selected' : '' ?>>Jakarta, Indonesia - ap-southeast-3</option>
                                        <option value="ap-northeast-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-northeast-1' ? 'selected' : '' ?>>Tokyo, Japan - ap-northeast-1</option>
                                        <option value="ap-northeast-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-northeast-2' ? 'selected' : '' ?>>Seoul, South Korea - ap-northeast-2</option>
                                        <option value="ap-northeast-3" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-northeast-3' ? 'selected' : '' ?>>Osaka-Local, Japan - ap-northeast-3</option>
                                        <option value="ap-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-south-1' ? 'selected' : '' ?>>Mumbai, India - ap-south-1</option>
                                        <option value="ap-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ap-east-1' ? 'selected' : '' ?>>Hong Kong SAR, China - ap-east-1</option>
                                        <option value="cn-north-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'cn-north-1' ? 'selected' : '' ?>>Beijing, China - cn-north-1</option>
                                        <option value="cn-northwest-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'cn-northwest-1' ? 'selected' : '' ?>>Ningxia, China - cn-northwest-1</option>
                                        <option value="eu-central-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-central-1' ? 'selected' : '' ?>>Frankfurt, Germany - eu-central-1</option>
                                        <option value="eu-west-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-west-1' ? 'selected' : '' ?>>Ireland - eu-west-1</option>
                                        <option value="eu-west-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-west-2' ? 'selected' : '' ?>>London, UK - eu-west-2</option>
                                        <option value="eu-west-3" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-west-3' ? 'selected' : '' ?>>Paris, France - eu-west-3</option>
                                        <option value="eu-north-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-north-1' ? 'selected' : '' ?>>Stockholm, Sweden - eu-north-1</option>
                                        <option value="eu-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-south-1' ? 'selected' : '' ?>>Milan, Italy - eu-south-1</option>
                                        <option value="eu-south-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'eu-south-2' ? 'selected' : '' ?>>Spain - eu-south-2</option>
                                        <option value="me-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'me-south-1' ? 'selected' : '' ?>>Bahrain - me-south-1</option>
                                        <option value="af-south-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'af-south-1' ? 'selected' : '' ?>>Cape Town, South Africa - af-south-1</option>
                                        <option value="sa-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'sa-east-1' ? 'selected' : '' ?>>Sao Paulo, Brazil - sa-east-1</option>
                                        <option value="ca-central-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'ca-central-1' ? 'selected' : '' ?>>Canada (Central) - ca-central-1</option>
                                        <option value="us-gov-west-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-gov-west-1' ? 'selected' : '' ?>>GovCloud (US-West) - us-gov-west-1</option>
                                        <option value="us-gov-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-gov-east-1' ? 'selected' : '' ?>>GovCloud (US-East) - us-gov-east-1</option>
                                        <option value="us-east-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-east-1' ? 'selected' : '' ?>>US East (N. Virginia) - us-east-1</option>
                                        <option value="us-east-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-east-2' ? 'selected' : '' ?>>US East (Ohio) - us-east-2</option>
                                        <option value="us-west-1" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-west-1' ? 'selected' : '' ?>>US West (N. California) - us-west-1</option>
                                        <option value="us-west-2" <?php echo  isset($aws_default_region) && $aws_default_region == 'us-west-2' ? 'selected' : '' ?>>US West (Oregon) - us-west-2</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="aws_access_key_id"><?= labels('aws_bucket', "AWS Bucket") ?></label>
                                    <input type="text" class=" form-control" name="aws_bucket" id="aws_bucket" value="<?= (isset($aws_bucket) && (ALLOW_VIEW_KEYS == 1)) ? $aws_bucket : 'your aws bucket' ?>" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="aws_access_key_id"><?= labels('aws_url', "AWS URL") ?></label>
                                    <input type="text" class=" form-control" name="aws_url" id="aws_url" value="<?= (isset($aws_url) && (ALLOW_VIEW_KEYS == 1)) ? $aws_url : 'your_aws_url' ?>" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Provider Verification Settings removed.
             Kept in DB for backwards compatibility. -->
        <?php if ($permissions['update']['settings'] == 1) : ?>
            <div class="row mb-3">
                <div class="col-md d-flex justify-content-end">
                    <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save") ?>' class='btn btn-lg bg-new-primary' />
                </div>
            </div>
        <?php endif; ?>
        <?= form_close() ?>
    </section>
</div>
<script>
    function test() {
        $('.custome_reset').attr('value', '');
    }
    $('#otp_system').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
</script>
<script>
    $(document).ready(function() {
        $('.app-toggle').each(function() {
            initToggle(this);
        });
    });

    function initToggle(checkbox) {
        const $checkbox = $(checkbox);
        const value = Number($checkbox.data('value')) || 0;
        const hiddenSelector = $checkbox.data('hidden');
        const noteSelector = $checkbox.data('note');

        const $hiddenInput = $(hiddenSelector);
        const $switchery = $checkbox.siblings('.switchery');
        const $note = noteSelector ? $(noteSelector) : null;

        if (!$hiddenInput.length) return;

        // Initial state
        checkbox.checked = value === 1;
        $hiddenInput.val(value);
        updateUI(value);

        // Change handler
        $checkbox.on('change', function() {
            const state = this.checked ? 1 : 0;
            $hiddenInput.val(state);
            updateUI(state);
        });

        function updateUI(state) {
            $switchery
                .toggleClass('yes-content', state === 1)
                .toggleClass('no-content', state !== 1);

            if ($note) {
                $note.toggle(state === 1);
            }
        }
    }

    $(function() {
        $('.fa').popover({
            trigger: "hover"
        });
    })
    if (<?= isset($image_compression_preference) && $image_compression_preference == 1 ? 'true' : 'false' ?>) {
        $("#image_compression_quality_input").show();
    } else {
        $("#image_compression_quality_input").hide();
    }
    $("#image_compression_preference").change(function() {
        if (this.value == 1) {
            $("#image_compression_quality_input").show();
        } else {
            $("#image_compression_quality_input").hide();
        }
    });
    $(document).ready(function() {
        // Assuming the PHP variable `$file_manager` is passed to the JavaScript as a global variable
        var fileManager = '<?php echo  isset($file_manager) ? $file_manager : 'local_server' ?>';
        // Check if `fileManager` is defined and equals 'aws_s3'
        if (typeof fileManager !== 'undefined' && fileManager === 'aws_s3') {
            $('.aws_s3').show();
        } else {
            $('.aws_s3').hide();
        }
        // Handle changes to the file_manager select element
        $('#file_manager').change(function() {
            var selectedValue = $(this).val();
            $('.aws_s3').toggle(selectedValue === 'aws_s3');
        });
    });
</script>
<script>
    function handleSwitchChange(checkbox) {
        const $checkbox = $(checkbox);
        const isChecked = checkbox.checked ? 1 : 0;

        const hiddenSelector = $checkbox.data('hidden');
        const noteSelector = $checkbox.data('note');

        const $hiddenInput = hiddenSelector ? $(hiddenSelector) : null;
        const $note = noteSelector ? $(noteSelector) : null;
        const $switchery = $checkbox.siblings('.switchery');

        // Sync hidden input
        if ($hiddenInput && $hiddenInput.length) {
            $hiddenInput.val(isChecked);
        }

        // Toggle note (optional)
        if ($note && $note.length) {
            $note.toggle(isChecked === 1);
        }

        // Toggle UI
        $switchery
            .toggleClass('yes-content', isChecked === 1)
            .toggleClass('no-content', isChecked !== 1);
    }

    // function handleSwitchChange(checkbox) {
    //     var isChecked = checkbox.checked;
    //     var checkboxId = checkbox.id;

    //     // FILE TRANSFER SWITCH
    //     if (checkboxId === 'file_transfer_process') {
    //         $('#file_transfer_process_value').val(isChecked ? "1" : "0");

    //         if (isChecked) {
    //             $('#file_transfer_note').show();
    //         } else {
    //             $('#file_transfer_note').hide();
    //         }
    //     }

    //     // CHAT IMAGE UPLOAD SWITCH
    //     if (checkboxId === 'enable_chat_image_upload') {
    //         $('#enable_chat_image_upload_value').val(isChecked ? "1" : "0");
    //     }

    //     // Switchery UI update (only THIS switch)
    //     var switchery = $(checkbox).siblings('.switchery');
    //     switchery
    //         .toggleClass('yes-content', isChecked)
    //         .toggleClass('no-content', !isChecked);
    // }

    $(document).ready(function() {
        // var checkbox = $('#file_transfer_process')[0];
        // handleSwitchChange(checkbox); // Initialize state
        $('#file_transfer_process').on('change', '.app-toggle', function() {
            handleSwitchChange(this);
        });

        $('#enable_chat_image_upload').on('change', '.app-toggle', function() {
            handleSwitchChange(this);
        });

        $('#enable_chat_file_upload').on('change', '.app-toggle', function() {
            handleSwitchChange(this);
        });

        // Language tab functionality for company settings - single tab system
        $('.language-company-option').on('click', function() {
            var selectedLanguage = $(this).data('language');

            // Update active tab
            $('.language-company-option').removeClass('selected');
            $('.language-company-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('.language-company-underline').css('width', '0');

            $(this).addClass('selected');
            $(this).find('.language-company-text').removeClass('text-muted').addClass('text-primary fw-medium');
            $(this).find('.language-company-underline').css('width', '100%');

            // Update labels with language code
            var isDefault = $(this).find('.language-company-text').text().includes('(Default)');
            var languageCode = isDefault ? '' : ' (' + selectedLanguage.toUpperCase() + ')';

            $('#company_title_label').text('<?= labels("company_title", "Company Title") ?>' + languageCode);
            $('#copyright_details_label').text('<?= labels("copyright_details", "Copyright Details") ?>' + languageCode);
            $('#address_label').text('<?= labels("address", "Address") ?>' + languageCode);
            $('#short_description_label').text('<?= labels("short_description", "Short Description") ?>' + languageCode);

            // Show/hide all company multilingual fields for the selected language
            $('input[name^="company_title["]').hide();
            $('input[name="company_title[' + selectedLanguage + ']"]').show();

            $('input[name^="copyright_details["]').hide();
            $('input[name="copyright_details[' + selectedLanguage + ']"]').show();

            $('textarea[name^="address["]').hide();
            $('textarea[name="address[' + selectedLanguage + ']"]').show();

            $('textarea[name^="short_description["]').hide();
            $('textarea[name="short_description[' + selectedLanguage + ']"]').show();
        });
    });
</script>