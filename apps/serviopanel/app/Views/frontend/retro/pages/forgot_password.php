<?php
$data = get_settings('general_settings', true);
isset($data['primary_color']) && $data['primary_color'] != "" ?  $primary_color = $data['primary_color'] : $primary_color =  '#05a6e8';
isset($data['secondary_color']) && $data['secondary_color'] != "" ?  $secondary_color = $data['secondary_color'] : $secondary_color =  '#003e64';
isset($data['primary_shadow']) && $data['primary_shadow'] != "" ?  $primary_shadow = $data['primary_shadow'] : $primary_shadow =  '#05A6E8';
$authentication_mode = $data['authentication_mode'];

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

// Detect smart login mode - similar to login.php
// Check if we're on partner forgot password page to determine authentication options
$smart_login = false;

// Get query parameter to check user type
$request = \Config\Services::request();
$userType = $request->getGet('userType');

if ($userType == 'partner') {
    // Check if both phone and email authentication are enabled
    if ($phone_authentication_enabled && $email_authentication_enabled) {
        $smart_login = true;
    } elseif ($email_authentication_enabled) {
        $smart_login = 'email';
    } elseif ($phone_authentication_enabled) {
        $smart_login = 'phone';
    }
}

?>
<style>
    body {
        --primary-color: <?= $primary_color ?>;
        --secondary-color: <?= $secondary_color ?>;
    }

    .bg-primary {
        background-color: <?= $primary_color ?> !important;
        color: white;
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

    .phone-input-group .country_code select {
        border: none;
        border-radius: 0;
        border-right: 1px solid #ced4da;
        background-color: #f8f9fa;
        padding: 0.375rem 0.75rem;
        height: 100%;
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
        cursor: not-allowed;
    }

    .phone-input-group .country_code input[readonly]:focus {
        border-color: transparent;
        box-shadow: none;
        background-color: #f8f9fa;
    }

    /* Ensure read-only country code input is always visible - except when hidden */
    .phone-input-group .country_code input[readonly]:not(.hidden) {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* When country_code is hidden (email mode), adjust the input to take full width */
    .phone-input-group .country_code.hidden {
        display: none !important;
    }

    .phone-input-group #numberInputDiv {
        flex: 1;
    }

    .phone-input-group #numberInputDiv input {
        border: none;
        border-radius: 0;
        height: 100%;
    }

    .phone-input-group #numberInputDiv input:focus {
        border-color: transparent;
        box-shadow: none;
    }

    /* Base styling - smaller on desktop */
    .phone-input-group .country_code {
        flex: 0 0 auto;
        width: auto;
        min-width: 80px;
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
<!-- Password strength indicator: shared CSS and JS (same as register.php) -->
<link rel="stylesheet" href="<?= base_url('public/frontend/retro/css/password-strength.css') ?>">
<script src="<?= base_url('public/frontend/retro/js/password-strength.js') ?>"></script>
<div class="auth " style="overflow: hidden;">
    <div class="join_us_as_provider">
        <section class="section">
            <section class=" " data-aos='fade-up'>

                <div class="d-flex justify-content-<?= $is_rtl == 1 ? 'start' : 'end' ?> m-3 row mt-5">
                    <div class="col-12 col-sm-10 col-md-8 col-lg-8  col-xl-4 mt-5 <?= $is_rtl == 1 ? 'ms-md-5' : 'me-md-5' ?> ">
                        <?php
                        $data = get_settings('general_settings', true);
                        ?>
                        <div class="">
                            <div id="" class='alert'><?php echo $message; ?></div>
                        </div>
                        <div class="card  p-3 mb-5   " style="border-radius:8px">

                            <div class="row">

                                <div class="col-md-3 d-flex justify-content-end w-100"><?= labels('step', 'Step') ?>&nbsp;<span class="step">1&nbsp;</span>&nbsp;<?= labels('of', 'of') ?>&nbsp;3</div>
                            </div>
                            <div class="row" style="text-align:center;margin-bottom:50px;margin-top:30px;width:100%;padding:0!important;display:block;">
                                <img style="width:60%" src=" <?= isset($data['logo']) && $data['logo'] != "" ? base_url("public/uploads/site/" . $data['logo']) : base_url('public/backend/assets/img/news/img01.jpg') ?>" class="" alt="">
                            </div>
                            <div class="col-md">

                                <div class="card-body" id="step_1">
                                    <div id="send">

                                        <div class="form-group">
                                            <label for="number" id="identityLabel"><?= labels('username', 'Username') ?></label>

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
                                                    <input id="number" class="form-control" type="number" name="number" placeholder="<?= labels('enter_mobile_number', 'Enter Mobile Number') ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div id="rec"></div>
                                        </div>
                                        <div class="form-group">
                                            <button type="button" class="btn bg-primary btn-lg w-100 mt-2" id="sender_forgot_password"><?= labels('submit', 'Submit') ?></button>
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
                                            <button type="button" class="btn bg-primary  text-white btn-lg w-100 mt-2" id="check" onclick="<?= $function_name; ?>"><?= labels('verify_otp', 'Verify Otp') ?></button>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body" id="step_2">
                                    <?= form_open('/auth/reset_password_otp', ['id' => 'forgot_password']); ?>

                                    <div class="row">
                                        <div class="form-group ">
                                            <input type="hidden" id="phone" class="form-control" name='phone' placeholder="Mobile Number" required min="0" readOnly />
                                            <input id="store_country_code" class="form-control" type="hidden" name="store_country_code">

                                            <label for='password' class="mb-2"><?= labels('password', 'Password') ?></label>
                                            <div class="position-relative">
                                                <input type="password" id="password" class="form-control" name='password' placeholder="<?= labels('password', 'Password') ?>" required style="padding-right: 2.5rem;" />
                                                <button type="button" class="btn btn-link position-absolute" id="togglePassword" style="right: 0; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0.5rem; cursor: pointer; z-index: 10; color: #6c757d;" title="<?= labels('show_password', 'Show Password') ?>">
                                                    <i class="fas fa-eye" id="passwordToggleIcon"></i>
                                                </button>
                                            </div>
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

                                        <div class="form-group " class="">
                                            <label for='password_confirm' class="mb-2"><?= labels('confirm_password', 'Confirm Password') ?></label>
                                            <div class="position-relative">
                                                <input type="password" id="password_confirm" class="form-control" name='password_confirm' placeholder="<?= labels('confirm_password', 'Confirm Password') ?>" required style="padding-right: 2.5rem;" />
                                                <button type="button" class="btn btn-link position-absolute" id="toggleConfirmPassword" style="right: 0; top: 50%; transform: translateY(-50%); border: none; background: none; padding: 0.5rem; cursor: pointer; z-index: 10; color: #6c757d;" title="<?= labels('show_password', 'Show Password') ?>">
                                                    <i class="fas fa-eye" id="confirmPasswordToggleIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <input type="hidden" id="userType" name="userType" value="">

                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="btn bg-primary btn-lg w-100 mt-2">
                                            <?= labels('submit', 'Submit') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md d-flex justify-content-center">
                                    <?= labels('back_to', 'Back To') ?> &nbsp; <a href="<?= base_url('partner/login') ?>" class=""><b><?= labels('login', 'Login') ?></b></a><br>
                                </div>
                                <?php
                                if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                                ?>
                                    <div class="col-sm-12 mt-3">
                                        <div class="alert alert-warning mb-0">
                                            <b><?= labels('note', 'Note') ?>:</b> <?= labels('cannot_register_close_codecanyon_frame', 'If you cannot Register here, please close the codecanyon frame by clicking on') ?> <b>x <?= labels('remove_frame', 'Remove Frame') ?></b> <?= labels('button_top_right_corner', 'button from top right corner on the page or') ?> <a href="https://edemand.erestro.me/auth/create_user" target="_blank">&gt;&gt; <?= labels('click_here', 'Click here') ?> &lt;&lt;</a>
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
<!-- Start Signup Section-->

<!-- Signup Section End -->
<script>
    $(document).ready(function() {
        // Password requirements validation on submit (Authentication Settings)
        $('#forgot_password').on('submit', function(e) {
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

        // Function to get query parameters from the URL
        function getQueryParam(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        // Get the value of the "partner" query parameter
        const userType = getQueryParam("userType");
        if (userType === "partner") {
            $('#userType').val("partner");
        } else {
            $('#userType').val("admin");
        }

        // Handle single country code display logic
        var isSingleCountryCode = <?= $single_country_code ? 'true' : 'false' ?>;
        var currentURL = window.location.href;
        var isAdminLogin = (window.location.href.indexOf('/admin/login') !== -1);
        var isPartnerLogin = (window.location.href.indexOf('/partner/login') !== -1);
        var smartLogin = "<?= $smart_login ?>";

        // Smart login setup for partner forgot password
        if (userType === "partner") {
            if (smartLogin === 'email') {
                // Email only mode
                $('#number').attr('type', 'email').attr('placeholder', '<?= labels('enter_email', 'Enter your email') ?>');
                $('.country_code').hide();
            } else if (smartLogin === 'phone') {
                // Phone only mode
                $('#number').attr('type', 'tel').attr('placeholder', '<?= labels('enter_mobile_number', 'Enter Mobile Number') ?>');
                $('.country_code').show();
            } else if (smartLogin === '1') {
                // Both modes - start in neutral mode
                $('#number').attr('type', 'text').attr('placeholder', '<?= labels('email_or_phone', 'Email or phone') ?>');
            }
        }

        // Dynamic input detection for smart login (when both email and phone are enabled)
        $('#number').on('input', function() {
            if (smartLogin !== '1') return;

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
            // Check for letters or @ symbol - definitely an email
            else if (val.includes('@') || /[a-zA-Z]/.test(val)) {
                newType = 'email';
                showCountryCode = false;
            }
            // If it's only digits - treat as phone (using 'tel' for better UX)
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
                $('.country_code').show();
            } else {
                $('.country_code').hide();
            }
        });

        // Debug removed for production

        if (isAdminLogin) {
            $('.country_code').hide();
            $('#country_code').val("");
        } else if (isPartnerLogin) {
            if (!isSingleCountryCode) {
                // Only set dropdown value if it's not a single country code (dropdown exists)
                var selectedCountryCode = "<?php echo $default_calling_code; ?>";
                $('#country_code').val(selectedCountryCode).trigger('change');
            }
        }

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

        // Password visibility toggle for password field
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');
        const passwordToggleIcon = document.getElementById('passwordToggleIcon');

        if (passwordInput && togglePasswordButton && passwordToggleIcon) {
            togglePasswordButton.addEventListener('click', function() {
                const showingPassword = passwordInput.type === 'text';
                passwordInput.type = showingPassword ? 'password' : 'text';

                passwordToggleIcon.classList.toggle('fa-eye', showingPassword);
                passwordToggleIcon.classList.toggle('fa-eye-slash', !showingPassword);

                togglePasswordButton.setAttribute(
                    'title',
                    showingPassword ?
                    "<?= labels('show_password', 'Show Password') ?>" :
                    "<?= labels('hide_password', 'Hide Password') ?>"
                );
            });
        }

        // Password visibility toggle for confirm password field
        const confirmPasswordInput = document.getElementById('password_confirm');
        const toggleConfirmPasswordButton = document.getElementById('toggleConfirmPassword');
        const confirmPasswordToggleIcon = document.getElementById('confirmPasswordToggleIcon');

        if (confirmPasswordInput && toggleConfirmPasswordButton && confirmPasswordToggleIcon) {
            toggleConfirmPasswordButton.addEventListener('click', function() {
                const showingPassword = confirmPasswordInput.type === 'text';
                confirmPasswordInput.type = showingPassword ? 'password' : 'text';

                confirmPasswordToggleIcon.classList.toggle('fa-eye', showingPassword);
                confirmPasswordToggleIcon.classList.toggle('fa-eye-slash', !showingPassword);

                toggleConfirmPasswordButton.setAttribute(
                    'title',
                    showingPassword ?
                    "<?= labels('show_password', 'Show Password') ?>" :
                    "<?= labels('hide_password', 'Hide Password') ?>"
                );
            });
        }
    });
</script>