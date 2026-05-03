<!-- Main Content -->
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
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('sms_gateways', "SMS Gateways") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('/admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item">
                    <a href="<?= base_url('/admin/settings/system-settings') ?>">
                        <?= labels('system_settings', "System Settings") ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('sms_gateways', "SMS Gateways") ?></div>
            </div>
        </div>
        <?php
        $settings = get_settings('system_settings', true);
        $sms_gateway_setting = get_settings('sms_gateway_setting');
        $sms_gateway_data = is_string($sms_gateway_setting) ? json_decode($sms_gateway_setting, true) : [];
        ?>
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" id="twilio-tab" data-toggle="tab" href="#twilio" role="tab"><?= labels('sms_gateways_configuration', "SMS Gateways Configuration") ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sms-template-tab" data-toggle="tab" href="#sms_template" role="tab"><?= labels('sms_templates', "SMS Templates") ?></a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="twilio" role="tabpanel">
                <input type="hidden" id="sms_gateway_data" value='<?= json_encode($sms_gateway_data) ?>' />
                <?php
                // Use current_sms_gateway as source of truth; fallback to legacy status flags for old data
                $twilio = $twilio ?? [];
                $fast2sms = $fast2sms ?? [];
                $msg91 = $msg91 ?? [];
                $twofactor = $twofactor ?? [];
                $current_sms_gateway = $current_sms_gateway ?? '';
                if ($current_sms_gateway === '' && !empty($twilio['twilio_status']) && $twilio['twilio_status'] == '1') {
                    $current_sms_gateway = 'twilio';
                } elseif ($current_sms_gateway === '' && !empty($fast2sms['fast2sms_status']) && $fast2sms['fast2sms_status'] == '1') {
                    $current_sms_gateway = 'fast2sms';
                } elseif ($current_sms_gateway === '' && !empty($msg91['msg91_status']) && $msg91['msg91_status'] == '1') {
                    $current_sms_gateway = 'msg91';
                } elseif ($current_sms_gateway === '' && !empty($twofactor['2factor_status']) && $twofactor['2factor_status'] == '1') {
                    $current_sms_gateway = '2factor';
                }
                ?>
                <form method="POST" action="<?= base_url('admin/settings/sms-gateway-settings') ?>">
                    <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                    <!-- Top row: Fast2SMS and 2Factor -->
                    <div class="row mb-4">
                        <!-- Fast2SMS gateway card: authorization key + sender ID (shorter card, placed first for visual alignment) -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('fast2sms', 'Fast2SMS') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php
                                            // Use current_sms_gateway as source of truth so only one gateway can be active
                                            $fast2sms_status = ($current_sms_gateway === 'fast2sms') ? 1 : 0;
                                            ?>
                                            <input id="fast2sms_status" class="custom-control-input toggle-switch" type="checkbox" name="fast2sms_status" <?= ($fast2sms_status) == 1 ? 'checked' : '' ?>>
                                            <label for="fast2sms_status" class="custom-control-label"><?= labels('fast2sms', 'Fast2SMS') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Visual text guide: helps users find Fast2SMS credentials -->
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="Fast2SMS setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('fast2sms_guide_intro', 'Get these credentials from your') ?>
                                        <a href="https://fast2sms.com/dashboard/dev-api" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>Fast2SMS Dev API</strong></a> <?= labels('fast2sms_guide_dashboard', 'dashboard') ?>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="fast2sms_api_key"><?= labels('authorization_key', 'Authorization Key') ?></label>
                                                <input type="text" value="<?= isset($fast2sms['fast2sms_api_key']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $fast2sms['fast2sms_api_key']) : '' ?>" name='fast2sms_api_key' id='fast2sms_api_key' placeholder='<?= labels('enter_authorization_key', 'Enter Authorization Key') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('fast2sms_api_key_help', 'API key from Dev API section. Keep it secret.') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="fast2sms_sender_id"><?= labels('sender_id', 'Sender ID') ?></label>
                                                <input type="text" value="<?= isset($fast2sms['fast2sms_sender_id']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $fast2sms['fast2sms_sender_id']) : '' ?>" name='fast2sms_sender_id' id='fast2sms_sender_id' placeholder='<?= labels('enter_sender_id', 'Enter Sender ID') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('fast2sms_sender_id_help', 'DLT-approved 3–6 letter Sender ID, e.g. FSTSMS') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- 2Factor gateway card: API key + Sender ID (shorter card, placed second for visual alignment) -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('2factor', '2Factor') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php
                                            $twofactor_status = ($current_sms_gateway === '2factor') ? 1 : 0;
                                            ?>
                                            <input id="twofactor_status" class="custom-control-input toggle-switch" type="checkbox" name="2factor_status" <?= ($twofactor_status) == 1 ? 'checked' : '' ?>>
                                            <label for="twofactor_status" class="custom-control-label"><?= labels('2factor', '2Factor') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Visual text guide: helps users find 2Factor credentials -->
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="2Factor setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('2factor_guide_intro', 'Get these credentials from your') ?>
                                        <a href="https://2factor.in" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>2Factor.in</strong></a> <?= labels('2factor_guide_dashboard', 'dashboard') ?>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="2factor_api_key"><?= labels('api_key', 'API Key') ?></label>
                                                <input type="text" value="<?= isset($twofactor['2factor_api_key']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twofactor['2factor_api_key']) : '' ?>" name='2factor_api_key' id='2factor_api_key' placeholder='<?= labels('enter_api_key', 'Enter API Key') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('2factor_api_key_help', 'API key from 2Factor dashboard. Keep it secret.') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="2factor_sender_id"><?= labels('sender_id', 'Sender ID') ?></label>
                                                <input type="text" value="<?= isset($twofactor['2factor_sender_id']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twofactor['2factor_sender_id']) : '' ?>" name='2factor_sender_id' id='2factor_sender_id' placeholder='<?= labels('enter_sender_id', 'Enter Sender ID') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('2factor_sender_id_help', 'Sender ID shown to recipient (e.g. 2FACTOR)') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Bottom row: Twilio and MSG91 -->
                    <div class="row mb-4">
                        <!-- Twilio gateway card: 3 fields; placed before MSG91 -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('twilio', 'Twilio') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php
                                            // Use current_sms_gateway as source of truth so only one gateway can be active
                                            $twilio_status = ($current_sms_gateway === 'twilio') ? 1 : 0;
                                            ?>

                                            <input id="twilio_status" class="custom-control-input toggle-switch" type="checkbox" name="twilio_status" <?= ($twilio_status) == 1 ? 'checked' : '' ?>>
                                            <label for="twilio_status" class="custom-control-label"><?= labels('twilio', 'Twilio') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Visual text guide: helps users find and enter Twilio credentials correctly -->
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="Twilio setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('twilio_guide_intro', 'Get these credentials from your') ?>
                                        <a href="https://console.twilio.com" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>Twilio Console</strong></a>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="twilio_account_sid"><?= labels('account_sid', 'Account SID') ?></label>
                                                <input type="text" value="<?= isset($twilio['twilio_account_sid']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twilio['twilio_account_sid']) : '' ?>" name='twilio_account_sid' id='twilio_account_sid' placeholder='<?= labels('enter_account_sid', 'Enter Account SID') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('twilio_sid_help', 'Starts with AC. Found in Console Dashboard.') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="twilio_auth_token"><?= labels('auth_token', 'Auth Token') ?></label>
                                                <input type="text" value="<?= isset($twilio['twilio_auth_token']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twilio['twilio_auth_token']) : '' ?>" name='twilio_auth_token' id='twilio_auth_token' placeholder='<?= labels('enter_auth_token', 'Enter Auth Token') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('twilio_token_help', 'Click "Show" next to Auth Token in the Console. Keep it secret.') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="twilio_from"><?= labels('from', 'From') ?></label>
                                                <input type="text" value="<?= isset($twilio['twilio_from']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twilio['twilio_from']) : '' ?>" name='twilio_from' id='twilio_from' placeholder='<?= labels('enter_from', 'Enter From') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('twilio_from_help', 'Your Twilio phone number in E.164 format, e.g. +1234567890') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- MSG91 gateway card: authkey only; h-100 matches Twilio height in same row -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('msg91', 'MSG91') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php
                                            $msg91_status = ($current_sms_gateway === 'msg91') ? 1 : 0;
                                            ?>
                                            <input id="msg91_status" class="custom-control-input toggle-switch" type="checkbox" name="msg91_status" <?= ($msg91_status) == 1 ? 'checked' : '' ?>>
                                            <label for="msg91_status" class="custom-control-label"><?= labels('msg91', 'MSG91') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Visual text guide: helps users find MSG91 credentials -->
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="MSG91 setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('msg91_guide_intro', 'Get your authkey from the') ?>
                                        <a href="https://control.msg91.com" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>MSG91 Control Panel</strong></a>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="msg91_authkey"><?= labels('authkey', 'Auth Key') ?></label>
                                                <input type="text" value="<?= isset($msg91['msg91_authkey']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $msg91['msg91_authkey']) : '' ?>" name='msg91_authkey' id='msg91_authkey' placeholder='<?= labels('enter_authkey', 'Enter Auth Key') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('msg91_authkey_help', 'Auth key from MSG91 dashboard. Keep it secret.') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <?php if ($permissions['update']['settings'] == 1) : ?>
                            <div class="col-md d-flex justify-content-lg-end mr-1">
                                <div class="form-group">
                                    <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save Changes") ?>' class='btn btn-primary' />
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php if ($permissions['read']['settings'] == 1) : ?>
                <div class="tab-pane fade show " id="sms_template" role="tabpanel">
                    <div class="card">
                        <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                            <!-- Header: title on left, export on right -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="toggleButttonPostition"><?= labels('sms_templates', "SMS Templates") ?></div>

                                <div class="dropdown d-inline ml-2">
                                    <button class="btn export_download dropdown-toggle" type="button" id="smsTemplatesDownloadDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <?= labels('download', 'Download') ?>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="smsTemplatesDownloadDropdown">
                                        <!-- No inline JS handlers (blocked by GlobalSanitizer). -->
                                        <a class="dropdown-item sms-templates-export" href="#" data-export-type="pdf"><?= labels('pdf', 'PDF') ?></a>
                                        <a class="dropdown-item sms-templates-export" href="#" data-export-type="excel"><?= labels('excel', 'Excel') ?></a>
                                        <a class="dropdown-item sms-templates-export" href="#" data-export-type="csv"><?= labels('csv', 'CSV') ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">

                            <!--
                              Export button moved to the card header (right side).
                              We keep this note here to document the reasoning.
                            -->
                            <div class="col-md-12">
                                <table class="table " data-fixed-columns="true" id="user_list" data-pagination-successively-size="2" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/settings/sms-templates-list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="desc" data-query-params="sms_query_params">
                                    <thead>
                                        <tr>
                                            <th data-field="id" class="text-center" data-visible="true" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="title" class="text-center"><?= labels('title', 'Title') ?></th>
                                            <th data-field="type" class="text-center"><?= labels('type', 'Type') ?></th>
                                            <th data-field="truncatedtemplate" class="text-center" data-visible="true"><?= labels('template', 'template') ?></th>
                                            <th data-field="parameters" class="text-center" data-visible="true"><?= labels('parameters', 'Parameters') ?></th>
                                            <th data-field="operations" class="text-center" data-events="sms_gateway_events"><?= labels('operations', 'Operations') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    <?php endif; ?>
</div>
</section>
</div>
<script>
    $('.parameters .btn').click(function() {
        let variableName = $(this).data('variable');
        let formattedText = `[[${variableName}]]`;
        let textarea = document.getElementById('template');
        if (textarea.selectionStart || textarea.selectionStart === 0) {
            // For modern browsers
            let startPos = textarea.selectionStart;
            let endPos = textarea.selectionEnd;
            let scrollTop = textarea.scrollTop;
            textarea.value = textarea.value.substring(0, startPos) + formattedText + textarea.value.substring(endPos, textarea.value.length);
            textarea.focus();
            textarea.selectionStart = startPos + formattedText.length;
            textarea.selectionEnd = startPos + formattedText.length;
            textarea.scrollTop = scrollTop;
        } else {
            // For IE < 9
            textarea.value += formattedText;
            textarea.focus();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        const smsData = JSON.parse(document.getElementById('sms_gateway_data').value || '{}');

        function createInputRow(containerId, keyName, valueName, keyPlaceholder, valuePlaceholder, removeClass, key = '', value = '') {
            const container = document.getElementById(containerId);
            const row = document.createElement('div');
            row.classList.add('form-group', 'row');
            row.innerHTML = `
            <div class="col-md-5">
                <input type="text" name="${keyName}" class="form-control" placeholder="${keyPlaceholder}" value="${key}">
            </div>
            <div class="col-md-5">
                <input type="text" name="${valueName}" class="form-control" placeholder="${valuePlaceholder}" value="${value}">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger ${removeClass}"><i class="fas fa-minus-circle"></i></button>
            </div>
        `;
            container.appendChild(row);
        }
        document.querySelector('.add-header').addEventListener('click', function() {
            createInputRow('header-container', 'header_key[]', 'header_value[]', 'Enter Key', 'Enter Value', 'remove-header');
        });
        document.querySelector('.add-body').addEventListener('click', function() {
            createInputRow('body-container', 'body_key[]', 'body_value[]', 'Enter Key', 'Enter Value', 'remove-body');
        });
        document.querySelector('.add-param').addEventListener('click', function() {
            createInputRow('param-container', 'params_key[]', 'params_value[]', 'Enter Key', 'Enter Value', 'remove-param');
        });

        function loadSmsHeaderSection() {
            if (smsData.header_key && smsData.header_value) {
                for (let i = 0; i < smsData.header_key.length; i++) {
                    createInputRow('header-container', 'header_key[]', 'header_value[]', 'Enter Key', 'Enter Value', 'remove-header', smsData.header_key[i], smsData.header_value[i]);
                }
            }
            if (smsData.body_key && smsData.body_value) {
                for (let i = 0; i < smsData.body_key.length; i++) {
                    createInputRow('body-container', 'body_key[]', 'body_value[]', 'Enter Key', 'Enter Value', 'remove-body', smsData.body_key[i], smsData.body_value[i]);
                }
            }
            if (smsData.params_key && smsData.params_value) {
                for (let i = 0; i < smsData.params_key.length; i++) {
                    createInputRow('param-container', 'params_key[]', 'params_value[]', 'Enter Key', 'Enter Value', 'remove-param', smsData.params_key[i], smsData.params_value[i]);
                }
            }
        }
        document.addEventListener('click', function(event) {
            if (event.target.closest('.remove-header')) {
                event.target.closest('.row').remove();
            }
            if (event.target.closest('.remove-body')) {
                event.target.closest('.row').remove();
            }
            if (event.target.closest('.remove-param')) {
                event.target.closest('.row').remove();
            }
        });
        window.createHeader = function() {
            const accountSID = document.getElementById('twilio_account_sid').value;
            const authToken = document.getElementById('twilio_auth_token').value;
            const base64Encoded = btoa(`${accountSID}:${authToken}`);
            document.getElementById('basicToken').innerText = `Authorization: Basic ${base64Encoded}`;
        };
        loadSmsHeaderSection();
    });
    $('.provider_registration_request,.withdraw_request,.payment_settlement,.service_request,.user_account,.booking_status,.new_booking,.rating_module').hide();
    $('#type').change(function() {
        let email_type = this.value;
        if (email_type == "provider_approved" || email_type == "provider_disapproved" || email_type == "provider_update_information" || email_type == "new_provider_registerd") {
            $('.provider_registration_request').show();
        } else {
            $('.provider_registration_request').hide();
        }
        if (email_type == "withdraw_request_approved" || email_type == "withdraw_request_disapproved" || email_type == "withdraw_request_received" || email_type == "withdraw_request_send") {
            $('.withdraw_request').show();
        } else {
            $('.withdraw_request').hide();
        }
        if (email_type == "payment_settlement") {
            $('.payment_settlement').show();
        } else {
            $('.payment_settlement').hide();
        }
        if (email_type == "service_approved" || email_type == "service_disapproved") {
            $('.service_request').show();
        } else {
            $('.service_request').hide();
        }
        if (email_type == "user_account_active" || email_type == "user_account_deactive") {
            $('.user_account').show();
        } else {
            $('.user_account').hide();
        }
        // Show booking_status field for all status-specific booking templates
        if (email_type == "booking_confirmed" || email_type == "booking_rescheduled" ||
            email_type == "booking_cancelled" || email_type == "booking_completed" ||
            email_type == "booking_started" || email_type == "booking_ended") {
            $('.booking_status').show();
        } else {
            $('.booking_status').hide();
        }
        if (email_type == "new_booking_confirmation_to_customer" || email_type == "new_booking_received_for_provider") {
            $('.new_booking').show();
        } else {
            $('.new_booking').hide();
        }
        if (email_type == "new_rating_given_by_customer" || email_type == "rating_request_to_customer") {
            $('.rating_module').show();
        } else {
            $('.rating_module').hide();
        }
    });
</script>

<script>
    /**
     * Export ONLY selected SMS template columns.
     *
     * We intentionally export only the fields the business requested:
     * - Title
     * - Type
     * - Template
     *
     * Notes:
     * - The table is a bootstrap-table with server-side pagination.
     *   Like the existing pages, export works on the currently loaded page rows.
     * - Do NOT export from the rendered HTML cells because the UI intentionally shows truncated
     *   values (e.g. `truncatedtemplate`). We export from the underlying row data instead,
     *   so full `title` and full `template` are exported.
     */
    /**
     * Export ALL SMS template rows (not just the current page).
     *
     * We page through the server endpoint using limit/offset until we reach `total`.
     * This keeps memory usage reasonable and avoids relying on UI pagination state.
     */
    async function export_sms_templates(type) {
        var exportTableId = 'sms_templates_export_table';

        // Remove a previous temp table if it exists (safety on repeated clicks).
        $('#' + exportTableId).remove();

        // Fetch ALL rows from the backend (includes full `title` and full `template`).
        var rows = await fetch_all_sms_template_rows();

        // Build a clean export table from data (not from UI DOM) to avoid truncation.
        var $exportTable = $('<table>').attr('id', exportTableId).addClass('table');
        var $thead = $('<thead>');
        var $headRow = $('<tr>');
        $headRow.append($('<th>').text('Title'));
        $headRow.append($('<th>').text('Type'));
        $headRow.append($('<th>').text('Template'));
        $thead.append($headRow);
        $exportTable.append($thead);

        var $tbody = $('<tbody>');
        rows.forEach(function(row) {
            // Use `.text()` to ensure values are escaped (no HTML injection in export table).
            var $tr = $('<tr>');
            $tr.append($('<td>').text(row && row.title ? row.title : ''));
            $tr.append($('<td>').text(row && row.type ? row.type : ''));
            $tr.append($('<td>').text(row && row.template ? row.template : ''));
            $tbody.append($tr);
        });
        $exportTable.append($tbody);

        // Hide the temp table off-screen but keep it in the DOM for tableExport.
        $exportTable.css({
            position: 'absolute',
            left: '-100000px',
            top: '0'
        });
        $('body').append($exportTable);

        // Export via the existing shared helper used throughout the admin.
        custome_export(type, 'SMS templates', exportTableId);

        // Cleanup immediately after triggering export.
        // tableExport is synchronous for CSV/Excel in our usage; for PDF it also runs synchronously here.
        $('#' + exportTableId).remove();
    }

    /**
     * Fetch all SMS templates from the same endpoint used by the table.
     * Uses current search/sort/order so export matches what admin is viewing.
     */
    async function fetch_all_sms_template_rows() {
        var $table = $('#user_list');

        // Read current table config (URL, sort, order, etc.)
        var options = {};
        try {
            options = $table.bootstrapTable('getOptions') || {};
        } catch (e) {
            options = {};
        }

        var url = options.url || $table.data('url');
        if (!url) return [];

        var sort = options.sortName || 'id';
        var order = options.sortOrder || 'desc';
        var search = $('#customSearch').val() || '';

        // Pull everything in chunks.
        var limit = 1000;
        var offset = 0;
        var allRows = [];
        var total = null;

        // Helper: jQuery ajax -> Promise (keeps compatibility with existing stack).
        function getJson(params) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: url,
                    method: 'GET',
                    dataType: 'json',
                    data: params,
                    success: function(data) {
                        resolve(data);
                    },
                    error: function(xhr) {
                        reject(xhr);
                    }
                });
            });
        }

        // Loop until we got all rows.
        // If backend changes or returns unexpected payload, we fail safe with what we already collected.
        while (total === null || allRows.length < total) {
            var params = {
                search: search,
                limit: limit,
                sort: sort,
                order: order,
                offset: offset
            };

            var payload;
            try {
                payload = await getJson(params);
            } catch (e) {
                break;
            }

            if (!payload) break;
            if (typeof payload.total !== 'undefined' && total === null) total = parseInt(payload.total, 10) || 0;
            if (!Array.isArray(payload.rows) || payload.rows.length === 0) break;

            allRows = allRows.concat(payload.rows);
            offset += limit;

            // Safety guard: prevent infinite loops if backend reports wrong totals.
            if (offset > 1000000) break;
        }

        return allRows;
    }

    /**
     * Bind export actions without inline `onclick`.
     * We use delegated handling so it still works even if the dropdown is re-rendered.
     */
    document.addEventListener('click', function(e) {
        var el = e.target.closest('.sms-templates-export');
        if (!el) return;

        e.preventDefault();
        var exportType = el.getAttribute('data-export-type');
        if (!exportType) return;

        // Trigger export (async). Keep UI responsive.
        export_sms_templates(exportType);
    });
</script>

<script>
    /**
     * Enforce single active SMS gateway: only one toggle can be checked at a time.
     * When user checks one gateway, all others are unchecked.
     */
    document.addEventListener('DOMContentLoaded', function() {
        const switches = document.querySelectorAll('.toggle-switch');

        switches.forEach(switchInput => {
            // Set initial value based on checked status
            switchInput.value = switchInput.checked ? 'true' : 'false';

            switchInput.addEventListener('change', function() {
                this.value = this.checked ? 'true' : 'false';

                // When enabling one gateway, disable all others (only one active at a time)
                if (this.checked) {
                    switches.forEach(input => {
                        if (input !== this) {
                            input.checked = false;
                            input.value = 'false';
                        }
                    });
                }
            });
        });
    });
</script>