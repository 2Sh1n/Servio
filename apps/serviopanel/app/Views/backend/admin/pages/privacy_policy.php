<!-- Main Content -->

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
            <h1> <?= labels('partner_privacy_policy', "Partner Privacy Policy") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>

                <div class="breadcrumb-item"><?= labels('privacy_policy', 'Privacy Policy') ?></div>
            </div>
        </div>
        <div class="">
            <!-- tab section -->
            <ul class="nav nav-pills justify-content-center py-2 nav-fill" id="gen-list">


                <li class="nav-item">
                    <a class="nav-link " href="<?= base_url('admin/settings/customer-terms-and-conditions') ?>" id="pills-customer_terms_and_conditions" aria-selected="false">
                        <?= labels('customer_terms_and_conditions', "Customer Terms and Conditions") ?></a>
                </li>

                <li class="nav-item">
                    <a class="nav-link " href="<?= base_url('admin/settings/terms-and-conditions') ?>" id="pills-partner_terms_and_conditions" aria-selected="false">
                        <?= labels('partner_terms_and_conditions', "Partner Terms and Conditions") ?></a>

                </li>

                <li class="nav-item">
                    <a class="nav-link " href="<?= base_url('admin/settings/customer-privacy-policy') ?>" id="pills-customer_privacy_policy" aria-selected="false">
                        <?= labels('customer_privacy_policy', "Customer Privacy Policy") ?></a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="<?= base_url('admin/settings/privacy-policy') ?>" id="pills-partner_privacy_policy" aria-selected="false">
                        <?= labels('partner_privacy_policy', "Partner Privacy Policy") ?></a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="<?= base_url('admin/settings/refund-policy') ?>" id="pills-partner_privacy_policy" aria-selected="false">
                    <?= labels('refund_policy', "Refund Policy") ?></a>
                </li> -->
            </ul>

        </div>
        <form action="<?= base_url('admin/settings/privacy-policy') ?>" method="post">


            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <div class="container-fluid card p-3">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <?php 
                            foreach ($languages as $index => $language) { 
                                if($language['is_default'] == 1) {  
                                    $current_language = $language['code'];
                                }
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
                                        style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <?php 
                    foreach ($languages as $index => $language) { 
                    ?>
                    <div class="col-lg" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                        <textarea rows=50 class='form-control h-50 summernotes' name="privacy_policy[<?= $language['code'] ?>]"><?= isset($privacy_policy[$language['code']]) ? $privacy_policy[$language['code']] : '' ?></textarea>
                    </div>
                    <?php } ?>
                </div>
                <div class="row mt-2">

                    <div class="col-md-6 mt-3 mb-4">
                        <a href="<?= base_url('admin/settings/partner_privacy_policy_page'); ?>" class="btn btn-primary"><i class="fa fa-eye"></i> <?= labels('preview', 'Preview') ?></a>
                    </div>
                    <?php if ($permissions['update']['settings'] == 1) : ?>
                        <div class="col-md d-flex justify-content-end  mt-3">

                            <div class="form-group">
                                <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Update") ?>' class='btn btn-primary' />
                                <!-- <input type='reset' name='clear' id='clear' value='<?= labels('Reset', "Clear") ?>' class='btn btn-danger' /> -->
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </form>
    </section>
</div>

<script>
    $(document).ready(function() {
        // select default language
        let default_language = '<?= $current_language ?>';

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            if(language != default_language){
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + default_language).hide();
            }

            default_language = language;
        });
    });
</script>