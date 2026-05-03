<!-- Start Signup Section-->
<!-- End Breadcrumbs -->
<?php
$data = get_settings('general_settings', true);
isset($data['primary_color']) && $data['primary_color'] != "" ?  $primary_color = $data['primary_color'] : $primary_color =  '#05a6e8';
isset($data['secondary_color']) && $data['secondary_color'] != "" ?  $secondary_color = $data['secondary_color'] : $secondary_color =  '#003e64';
isset($data['primary_shadow']) && $data['primary_shadow'] != "" ?  $primary_shadow = $data['primary_shadow'] : $primary_shadow =  '#05A6E8';
$authentication_mode = $data['authentication_mode'];

// Provider registration OTP verification can be done via phone and/or email.
// Keep step 1 aligned with the provider login options:
// - If both modes are enabled => allow entering Email OR Phone
// - If only one is enabled => keep it restricted to that mode
$loginSettings = get_settings('login_settings', true);
$phone_authentication_enabled = (int)($loginSettings['phone_authentication_enabled'] ?? 1);
$email_authentication_enabled = (int)($loginSettings['email_authentication_enabled'] ?? 0);

// Keep same behavior as provider login (`login.php`):
// - both enabled => smart switch (PHP boolean true becomes "1" in JS)
// - only email enabled => force email mode
// - only phone enabled => force phone mode
$smart_register = false;
if ($phone_authentication_enabled && $email_authentication_enabled) {
    $smart_register = true;
} elseif ($email_authentication_enabled) {
    $smart_register = 'email';
} elseif ($phone_authentication_enabled) {
    $smart_register = 'phone';
}
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);

// Only check default language's RTL status if no language is set in session
// Otherwise, use the explicit is_rtl value from session
if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $default_language[0]['is_rtl'];
} elseif ($is_rtl === null) {
    // Fallback if is_rtl is not set but language is
    $is_rtl = 0;
}

// Convert to integer value for consistency
$is_rtl = (int)$is_rtl;

?>
<style>
    body {
        --primary-color: <?= $primary_color ?>;
        --secondary-color: <?= $secondary_color ?>;
    }

    .bg-primary {
        background-color: <?= $primary_color ?> !important;
    }

    .input-group .form-control {
        border: 1px solid #ced4da;
    }

    .input-group .form-control:focus {
        border-color: <?= $primary_color ?>;
        box-shadow: 0 0 0 0.2rem rgba(5, 166, 232, 0.25);
    }

    .input-group-text {
        border: 1px solid #ced4da;
        background-color: #fff;
    }

    .input-group-text:hover {
        background-color: #f8f9fa;
    }

    /* Phone number input group styling */
    .phone-input-group {
        display: flex;
        border: 1px solid #ced4da;
        border-radius: 8px;
        overflow: hidden;
    }

    .phone-input-group:focus-within {
        border-color: <?= $primary_color ?>;
        box-shadow: 0 0 0 0.2rem rgba(5, 166, 232, 0.25);
    }

    .phone-input-group .country_code {
        flex: 0 0 auto;
        width: auto;
        min-width: 120px;
    }

    /* Country code select: caret on the right so it's clear it's a dropdown (step 1 and step 2) */
    .phone-input-group .country_code select {
        border: none;
        border-radius: 0;
        border-right: 1px solid #ced4da;
        background-color: #f8f9fa;
        padding: 0.375rem 0.75rem;
        padding-right: 1.75rem;
        height: 100%;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M1 4l5 5 5-5H1z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.5rem center;
    }

    .phone-input-group .country_code select:focus {
        border-color: transparent;
        box-shadow: none;
        background-color: #fff;
    }

    .phone-input-group .country_code input[readonly] {
        border: none;
        border-radius: 0;
        border-right: 1px solid #ced4da;
        background-color: #f8f9fa;
        padding: 0.375rem 0.75rem;
        height: 100%;
    }

    .phone-input-group .country_code input[readonly]:focus {
        border-color: transparent;
        box-shadow: none;
        background-color: #f8f9fa;
    }

    /* Ensure read-only country code input is always visible */
    .phone-input-group .country_code input[readonly] {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    .phone-input-group #numberInputDiv,
    .phone-input-group .number-input-wrap {
        flex: 1;
    }

    .phone-input-group #numberInputDiv input,
    .phone-input-group .number-input-wrap input {
        border: none;
        border-radius: 0;
        height: 100%;
    }

    .phone-input-group #numberInputDiv input:focus,
    .phone-input-group .number-input-wrap input:focus {
        border-color: transparent;
        box-shadow: none;
    }

    /* Base styling - smaller on desktop */
    .phone-input-group .country_code {
        flex: 0 0 auto;
        width: auto;
        min-width: 90px;
        /* compact */
        max-width: 100px;
    }

    .phone-input-group .country_code input[readonly],
    .phone-input-group .country_code select {
        width: 100%;
        text-align: center;
        padding: 0.375rem 0.5rem;
        font-size: 0.9rem;
    }

    /* Mobile-friendly adjustments */
    @media (max-width: 576px) {
        .phone-input-group {
            flex-direction: column;
            /* stack vertically */
        }

        .phone-input-group .country_code {
            min-width: 100%;
            /* full width on mobile */
            max-width: 100%;
            margin-bottom: 0.5rem;
            /* spacing before phone number field */
        }

        .phone-input-group .country_code input[readonly],
        .phone-input-group .country_code select {
            text-align: left;
            font-size: 1rem;
            /* slightly larger for touch */
        }
    }
</style>
<?php helper('function'); $password_rules = get_password_rules(); ?>
<script>
window.PASSWORD_RULES = {
    minLength: <?= (int)$password_rules['min_length'] ?>,
    requireUppercase: <?= (int)$password_rules['require_uppercase'] ?>,
    requireLowercase: <?= (int)$password_rules['require_lowercase'] ?>,
    requireNumber: <?= (int)$password_rules['require_number'] ?>,
    requireSpecial: <?= (int)$password_rules['require_special'] ?>
};
</script>
<!-- Password strength indicator: shared CSS and JS (used also on forgot_password) -->
<link rel="stylesheet" href="<?= base_url('public/frontend/retro/css/password-strength.css') ?>">
<script src="<?= base_url('public/frontend/retro/js/password-strength.js') ?>"></script>
<div class="auth " style="overflow: hidden;">
    <div class="join_us_as_provider">
        <section class="section">
            <section class="" data-aos='fade-up'>

                <div class="d-flex justify-content-<?= $is_rtl == 1 ? 'start' : 'end' ?> m-3 row">
                    <div class="col-12 col-sm-10 col-md-8 col-lg-8  col-xl-4 mt-5 me-md-5">
                        <?php
                        $data = get_settings('general_settings', true);
                        ?>

                        <div class="card  p-3 mb-5  " style="border-radius:8px">

                            <div class="">
                                <div id="" class='alert text-danger'><?php echo $message; ?></div>
                            </div>
                            <div class="row">

                                <div class="col-md-3 d-flex justify-content-<?= $is_rtl == 1 ? 'start' : 'end' ?> w-100"><?= labels('step', 'Step') ?>&nbsp;<span class="step">1&nbsp;</span>&nbsp;<?= labels('of', 'of') ?>&nbsp;3</div>

                            </div>
                            <div class="row" style="text-align:center;margin-bottom:65px;margin-top:30px;width:100%;padding:0!important;display:block;">
                                <?php if (current_url() == base_url() . '/admin/login') { ?>
                                    <img style="width: 60%;" src=" <?= isset($data['logo']) && $data['logo'] != "" ? base_url("public/uploads/site/" . $data['logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>" class="" alt="">
                                <?php } else { ?>
                                    <img style="width: 60%;" src=" <?= isset($data['partner_logo']) && $data['partner_logo'] != "" ? base_url("public/uploads/site/" . $data['partner_logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>" class="" alt="">

                                <?php } ?>
                            </div>
                            <div class="col-md">

                                <div class="card-body" id="step_1">
                                    <div id="send">
                                        <div class="form-group">
                                            <label for="number" class="mb-2"><?= labels('username', 'Username') ?></label>

                                            <?php
                                            // Use the model to get country codes (handles soft deletes automatically)
                                            $country_code_model = new \App\Models\Country_code_model();
                                            $country_codes = $country_code_model->findAll();
                                            $system_country_code = $country_code_model->where('is_default', 1)->first();
                                            $default_calling_code = '';
                                            if (!empty($system_country_code['calling_code'])) {
                                                $default_calling_code = $system_country_code['calling_code'];
                                            } else {
                                                $default_calling_code = '+91';
                                            }

                                            // Check if there's only one country code available
                                            $single_country_code = (count($country_codes) == 1);
                                            $single_country_data = $single_country_code ? $country_codes[0] : null;
                                            ?>
                                            <div class="phone-input-group">
                                                <div class="country_code">
                                                    <?php if ($single_country_code): ?>
                                                        <!-- Show as read-only input when only one country code exists -->
                                                        <?php
                                                        $calling_code_value = '';
                                                        if ($single_country_data && isset($single_country_data['calling_code'])) {
                                                            $calling_code_value = $single_country_data['calling_code'];
                                                        } elseif ($single_country_data && isset($single_country_data['calling_code'])) {
                                                            $calling_code_value = $single_country_data['calling_code'];
                                                        } else {
                                                            $calling_code_value = $default_calling_code;
                                                        }
                                                        ?>
                                                        <input type="text" class="form-control" name="country_code" id="country_code" value="<?= $calling_code_value ?>" readonly>
                                                    <?php else: ?>
                                                        <!-- Show as dropdown when multiple country codes exist -->
                                                        <select class="form-control" name="country_code" id="country_code">
                                                            <?php
                                                            foreach ($country_codes as $key => $country_code) {
                                                                $code = $country_code['calling_code'];
                                                                $selected = ($default_calling_code == $country_code['calling_code']) ? "selected" : "";
                                                                echo "<option $selected value='$code'>$code</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                                <div id="numberInputDiv">
                                                    <!--
                                                      NOTE:
                                                      We intentionally keep the same `#number` id to reuse existing OTP JS.
                                                      Using `type="text"` allows email input when email OTP is enabled.
                                                    -->
                                                    <!--
                                                      Default placeholder is kept generic.
                                                      The JS at the bottom of this file updates placeholder/type dynamically
                                                      to match the same behavior as `login.php` (email/phone smart input).
                                                    -->
                                                    <input id="number" class="form-control" type="text" name="number1" placeholder="<?= labels('enter_email_or_phone', 'Email or phone') ?>" required>
                                                    <!-- Stores the verified identity (email or phone) for OTP verification logic in JS -->
                                                    <input type="hidden" id="otp_identity" value="">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group re_captcha">
                                            <div id="rec" class=""></div>
                                        </div>
                                        <div class="form-group ">
                                            <button type="button" class="btn bg-primary  text-white btn-lg w-100 mt-2" id="sender"><?= labels('submit', 'Submit') ?></button>
                                        </div>
                                    </div>

                                    <div class="otp_show">
                                        <div class="form-group">
                                            <label for="otp"><?= labels('received_otp', 'Received OTP') ?></label>
                                            <input id="otp" class="form-control" type="number" name="otp">
                                        </div>
                                        <div class="form-group">

                                            <?php
                                            if ($authentication_mode == "sms_gateway") {
                                                $function_name = "sms_codeverify()";
                                            } else {
                                                $function_name = "codeverify()";
                                            }

                                            ?>
                                            <button type="button" class="btn bg-primary btn-lg text-white w-100" id="check" onclick="<?= $function_name; ?>"><?= labels('verify_otp', 'Verify Otp') ?></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body" id="step_2">
                                    <?= form_open('auth/create_partner', ['id' => 'registerdff']); ?>
                                    <!--
                                      Persist provider verification method:
                                      - email OTP => loginType=email
                                      - phone OTP => loginType=phone
                                      JS sets this after successful OTP verification.
                                    -->
                                    <input type="hidden" id="loginType" name="loginType" value="phone" />
                                    <div class="row">
                                        <div class="col-md-6">

                                            <div class="form-group">
                                                <label for='first_name' class="mb-2"><?= labels('user_name', 'User name') ?></label>
                                                <input type="text" id="first_name" class="form-control" name='username' placeholder="<?= labels('first_name', 'First name') ?>" required />
                                            </div>

                                        </div>
                                        <div class="col-md-6">

                                            <div class="form-group">
                                                <label for='email' class="mb-2"><?= labels('email', 'Email') ?></label>

                                                <input type="email" id="email" class="form-control" name='email' placeholder="<?= labels('email_id', 'Email Id') ?>" required />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">

                                            <div class="form-group">
                                                <label for='phone' class="mb-2"><?= labels('phone_number', 'Phone Number') ?></label>
                                                <!--
                                                  Country code dropdown from DB; phone number saved with country_code in users table.
                                                  Editable for email-OTP flow (phone collected in step 2).
                                                  For phone-OTP flow, JS copies step 1 country code into this field.
                                                -->
                                                <div class="phone-input-group">
                                                    <div class="country_code store-country-code-wrap">
                                                        <?php if ($single_country_code): ?>
                                                            <input type="text" class="form-control" name="store_country_code" id="store_country_code" value="<?= esc($single_country_data['calling_code'] ?? $default_calling_code) ?>" readonly>
                                                        <?php else: ?>
                                                            <select class="form-control" name="store_country_code" id="store_country_code">
                                                                <?php
                                                                foreach ($country_codes as $key => $country_code) {
                                                                    $code = $country_code['calling_code'];
                                                                    $selected = ($default_calling_code == $country_code['calling_code']) ? "selected" : "";
                                                                    echo "<option $selected value='" . esc($code) . "'>" . esc($code) . "</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="number-input-wrap">
                                                        <input type="text" id="phone" class="form-control" name="phone" placeholder="<?= labels('mobile_number', 'Mobile Number') ?>" required min="0" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">

                                            <div class="form-group">
                                                <label for='company_name' class="mb-2"><?= labels('company_name', 'Company Name') ?></label>
                                                <input type="text" id="company_name" class="form-control" name='company_name' placeholder="<?= labels('company_name', 'Company Name') ?>" required />
                                            </div>
                                        </div>

                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">

                                            <div class="form-group">
                                                <label for='password' class="mb-2"><?= labels('password', 'Password') ?></label>
                                                <input type="password" id="password" class="form-control" name='password' placeholder="<?= labels('password', 'Password') ?>" required />
                                                <!-- Password strength: rules from Authentication Settings (conditional). -->
                                                <div id="password-strength-indicator" class="mt-2 small" <?= ($password_rules['min_length'] === 0) ? 'style="display:none;"' : '' ?>>
                                                    <?php if ($password_rules['min_length'] > 0) { ?>
                                                    <div class="password-rule" id="rule-length" data-rule="length">
                                                        <span class="rule-icon" aria-hidden="true">○</span>
                                                        <span class="rule-label"><?= sprintf(labels('password_strength_min_length_n', 'At least %s characters'), (int)$password_rules['min_length']) ?></span>
                                                    </div>
                                                    <?php } ?>
                                                    <?php if (!empty($password_rules['require_number'])) { ?>
                                                    <div class="password-rule" id="rule-number" data-rule="number">
                                                        <span class="rule-icon" aria-hidden="true">○</span>
                                                        <span class="rule-label"><?= labels('password_strength_contains_number', 'Contains a number') ?></span>
                                                    </div>
                                                    <?php } ?>
                                                    <?php if (!empty($password_rules['require_uppercase'])) { ?>
                                                    <div class="password-rule" id="rule-uppercase" data-rule="uppercase">
                                                        <span class="rule-icon" aria-hidden="true">○</span>
                                                        <span class="rule-label"><?= labels('password_strength_contains_uppercase', 'Contains an uppercase letter') ?></span>
                                                    </div>
                                                    <?php } ?>
                                                    <?php if (!empty($password_rules['require_lowercase'])) { ?>
                                                    <div class="password-rule" id="rule-lowercase" data-rule="lowercase">
                                                        <span class="rule-icon" aria-hidden="true">○</span>
                                                        <span class="rule-label"><?= labels('password_strength_contains_lowercase', 'Contains a lowercase letter') ?></span>
                                                    </div>
                                                    <?php } ?>
                                                    <?php if (!empty($password_rules['require_special'])) { ?>
                                                    <div class="password-rule" id="rule-special" data-rule="special">
                                                        <span class="rule-icon" aria-hidden="true">○</span>
                                                        <span class="rule-label"><?= labels('password_strength_contains_special', 'Contains a special character') ?></span>
                                                    </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">

                                            <div class="form-group ">
                                                <label for='password_confirm' class="mb-2"><?= labels('confirm_password', 'Confirm Password') ?></label>
                                                <input type="password" id="password_confirm" class="form-control" name='password_confirm' placeholder="<?= labels('confirm_password', 'Confirm Password') ?>" required />
                                            </div>
                                        </div>

                                    </div>

                                    <div class="form-group">


                                        <button type="submit" class="btn bg-primary btn-lg w-100 mt-3 text-white">
                                            <?= labels('register', 'Register') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md d-flex justify-content-center">
                                    <?= labels('back_to', ' Back To') ?> &nbsp; <a href="<?= base_url('partner/login') ?>" class=""><b><?= labels('login', 'Login') ?></b></a><br>
                                </div>
                                <?php
                                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                                ?>
                                    <div class="col-sm-12 mt-3">
                                        <div class="alert alert-warning mb-0">
                                            <b>Note:</b> If you cannot Register here, please close the codecanyon frame by clicking on <b>x Remove Frame</b> button from top right corner on the page or <a href="https://edemand.erestro.me/auth/create_user" target="_blank">&gt;&gt; Click here &lt;&lt;</a>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                    </div>
            </section>

        </section>
    </div>
</div>


<!-- Signup Section End -->
<script>
    $(document).ready(function() {
        // Password requirements validation on submit (Authentication Settings)
        $('#registerdff').on('submit', function(e) {
            if (typeof window.passwordStrengthValid === 'function' && !window.passwordStrengthValid()) {
                e.preventDefault();
                if (typeof toastr !== 'undefined') {
                    toastr.warning("<?= labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.') ?>");
                } else {
                    alert("<?= labels('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.') ?>");
                }
                return false;
            }
        });

        // Match the same email/phone input behavior as `login.php`
        var isSingleCountryCode = <?= $single_country_code ? 'true' : 'false' ?>;
        var smartRegister = "<?= $smart_register ?>";

        // Set default country code (when dropdown exists)
        if (!isSingleCountryCode) {
            var selectedCountryCode = "<?php echo $default_calling_code; ?>";
            $('#country_code').val(selectedCountryCode).trigger('change');
        }

        // Apply initial mode (email-only / phone-only / smart).
        // Only show/hide step 1's country code (#step_1 .country_code), so step 2's dropdown is never hidden.
        if (smartRegister === 'email') {
            $('#number').attr('type', 'email').attr('placeholder', 'Enter your email');
            $('#step_1 .country_code').hide();
        } else if (smartRegister === 'phone') {
            $('#number').attr('type', 'tel').attr('placeholder', 'Enter phone number');
            $('#step_1 .country_code').show();
        } else if (smartRegister === '1') {
            // Start in neutral mode (smart switch based on input)
            $('#number').attr('type', 'text').attr('placeholder', 'Email or phone');
            $('#step_1 .country_code').show();
        }

        // Smart switch (only when both are enabled). Scope to step 1 so step 2 country dropdown stays visible.
        $('#number').on('input', function() {
            if (smartRegister !== '1') return;

            var input = $(this);
            var val = input.val();
            var currentType = input.attr('type');
            var newType = 'text';
            var showCountryCode = true;

            // If empty, reset to neutral state
            if (!val || val.length === 0) {
                newType = 'text';
                showCountryCode = true;
            }
            // If it contains letters or @ => email
            else if (val.includes('@') || /[a-zA-Z]/.test(val)) {
                newType = 'email';
                showCountryCode = false;
            }
            // If digits only => phone
            else if (/^\d+$/.test(val)) {
                newType = 'tel';
                showCountryCode = true;
            }

            // Apply changes only if necessary
            if (currentType !== newType) {
                input.attr('type', newType);
                input.val(val); // Restore value to prevent data loss during type switch
            }

            if (showCountryCode) {
                $('#step_1 .country_code').show();
            } else {
                $('#step_1 .country_code').hide();
            }
        });

        // Prevent any interference with single country code display
        if (isSingleCountryCode) {
            // Ensure the country code element is an input, not a select
            var countryCodeElement = $('#country_code');
            if (countryCodeElement.length > 0 && countryCodeElement.prop('tagName') !== 'INPUT') {
                // Force it to be an input if it's not
                var currentValue = countryCodeElement.val();
                countryCodeElement.replaceWith('<input type="text" class="form-control" name="country_code" id="country_code" value="' + currentValue + '" readonly>');
            }
        }

        // Default country code for step 2 (used when email OTP is used and no phone country was selected)
        window.registerDefaultCallingCode = "<?= addslashes($default_calling_code) ?>";
    });
</script>