
<?php

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Calculation\Category;

// Detect RTL so UI elements (like directional icons) respect language direction.
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);
$default_is_rtl = (!empty($default_language) && isset($default_language[0]['is_rtl']))
    ? (int) $default_language[0]['is_rtl']
    : 0; // Fallback prevents notices when defaults are missing.

if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $default_is_rtl;
} elseif ($is_rtl === null) {
    // Safe fallback when language exists but RTL flag is missing.
    $is_rtl = 0;
}

$is_rtl = (int) $is_rtl; // Normalize for strict comparisons.

?>
<style>
    /* === Avatar & Fallback: Always Perfect Circle === */
    .avatar,
    .avatar-fallback {
        width: 50px !important;
        height: 50px !important;
        min-width: 50px !important;
        min-height: 50px !important;

        aspect-ratio: 1 / 1;
        border-radius: 50% !important;

        flex: 0 0 50px !important;
        /* flexbox: don't stretch me */
        box-sizing: border-box;

        overflow: hidden;
    }

    /* Image avatar */
    .avatar {
        object-fit: cover;
        display: block;
    }

    /* Initials fallback avatar */
    .avatar-fallback {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;

        font-weight: 700;
        font-size: 20px;
        line-height: 1;
        /* THIS is the silent killer */
        text-transform: uppercase;
        color: #fff;
    }

    /* Direction-safe spacing (kills LTR/RTL duplication) */
    .o-media .avatar,
    .o-media .avatar-fallback {
        margin-inline-end: 1.5rem;
    }

    /* === Responsive Card Layout Fixes === */

    /* Make columns stretch to equal height using flexbox */
    .job-card-col {
        display: flex !important;
        flex-direction: column;
    }

    /* Cards stretch to fill column height */
    .job-card-wrapper {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    /* Card body takes available space and uses flexbox for content */
    .job-card-body {
        display: flex;
        flex-direction: column;
        flex: 1 0 auto;
        /* Grow to fill space, don't shrink */
    }

    /* Title: limit to 2 lines with ellipsis */
    .job_request_title {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        /* Standard property for compatibility */
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.4;
        max-height: 2.8em;
        /* 2 lines * 1.4 line-height */
        word-break: break-word;
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }

    /* Description container */
    .job_request_desc {
        margin-bottom: 0.75rem;
        min-height: 3em;
        /* Reserve space for description */
    }

    /* Budget row: prevent wrapping */
    .budget {
        white-space: nowrap;
    }

    .budget>div {
        display: inline-block;
    }

    /* Username: truncate with ellipsis */
    .provider_name_table {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 150px;
        /* Adjust based on your design */
        font-weight: 600;
    }

    /* Time ago: truncate with ellipsis */
    .provider_email_table {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 150px;
        /* Adjust based on your design */
        font-size: 0.9rem;
        color: #6c757d;
    }

    /* Footer section pushed to bottom and allows wrapping */
    .job-card-footer {
        margin-top: auto;
        /* Push to bottom */
        flex-wrap: wrap;
        /* Allow items to wrap on smaller screens */
        gap: 1rem;
        /* Space between wrapped items */
    }

    /* Media object container - allow wrapping */
    .o-media {
        flex-wrap: wrap;
        /* Allow wrapping if needed */
        min-width: 0;
        /* Prevent overflow */
    }

    /* Ensure button doesn't overflow on small screens */
    .job_request_apply_now_btn {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        flex-shrink: 0;
        /* Don't shrink button too much */
    }

    /* Prevent card content from overflowing */
    .card {
        overflow: hidden;
        word-wrap: break-word;
    }

    /* Media object body should allow text truncation */
    .o-media__body {
        min-width: 0;
        /* Allow flexbox truncation */
        flex: 1 1 auto;
    }

    /* Responsive adjustments for smaller screens */
    @media (max-width: 992px) {

        /* On medium screens, allow footer to wrap */
        .job-card-footer {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .job-card-footer>div {
            width: 100%;
        }

        .job_request_apply_now_btn {
            width: 100%;
            margin-top: 0.5rem;
        }
    }

    @media (max-width: 768px) {

        .provider_name_table,
        .provider_email_table {
            max-width: 200px;
            /* More space since button is below */
        }

        .job_request_apply_now_btn {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    }

    /* Extra small screens */
    @media (max-width: 576px) {
        .job_request_title {
            font-size: 1rem;
        }

        .provider_name_table,
        .provider_email_table {
            max-width: 180px;
        }

        .job_request_apply_now_btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.75rem;
        }
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2 ">
            <h1><?= labels('job_requests', "Job Request's") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="text-center mr-3">
                    <button class="btn job_request_apply_now_btn"
                        data-toggle="modal"
                        data-target="#manage_custom_job_setting">
                        <?php if ($is_accepting_custom_jobs == 1) : ?>
                            <i class="fas fa-times-circle mr-1 mt-2"></i><?= labels('disable_custom_job_request', 'Disable Custom Job Request') ?>
                        <?php else : ?>
                            <i class="fas fa-check-circle mr-1 mt-2"></i><?= labels('enable_custom_job_request', 'Enable Custom Job Request') ?>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between">
            <div>
                <!-- Tabs -->
                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pills-open-jobs-tab" data-toggle="pill" href="#pills-open-jobs" role="tab" aria-controls="pills-open-jobs" aria-selected="true">
                            <?= labels('open_jobs', "Open Jobs") ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="pills-applied-jobs-tab" data-toggle="pill" href="#pills-applied-jobs" role="tab" aria-controls="pills-applied-jobs" aria-selected="false">
                            <?= labels('applied_jobs', "Applied Jobs") ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="d-flex">
                <div class="text-center">
                    <button class="btn job_request_apply_now_btn"
                        data-toggle="modal"
                        data-target="#manage_categories">
                        <i class="fas fa-tasks mr-1 mt-2"></i><?= labels('manage_category_preference', 'Manage Category Preference') ?>
                    </button>
                </div>
            </div>
        </div>
        <!-- Tab Content -->
        <div class="tab-content" id="pills-tabContent">
            <!-- Open Jobs Content -->
            <div class="tab-pane fade show active" id="pills-open-jobs" role="tabpanel" aria-labelledby="pills-open-jobs-tab">
                <div class="row">
                    <?php if (empty($custom_job_requests)) : ?>
                        <div class="row w-100">
                            <div class="col-md-12 d-flex justify-content-center">
                                <div class="empty-state" data-height="400" style="height: 400px;">
                                    <img src="<?= base_url('public/uploads/site/design.png'); ?>" alt="" srcset="">
                                    <h2><?= labels('no_jobs_available', "Sahh...! No Job’s Available") ?></h2>
                                    <p class="lead">
                                    <h2><?= labels('no_jobs_message', 'There is no jobs available at the moment please visit us again after some time') ?></h2>
                                    <h2><?= labels('custom_job_request_note', "If you are not seeing the custom job requests, ensure you have enabled the button shown showing 'Enable Custom Job Request' above.") ?></h2>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php foreach ($custom_job_requests as $key => $request) { ?>
                            <div class="col-md-4 job-card-col">
                                <div class="card mt-2 job-card-wrapper">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="card-body job-card-body">
                                                <div class="job_request_title">
                                                    <?= $request['service_title']; ?>
                                                </div>

                                                <div class="job_request_desc">
                                                    <?php
                                                    $content = $request['service_short_description'];
                                                    $unique_id = 'read-more-' . uniqid();
                                                    $char_limit = 100; // Character limit
                                                    $needs_read_more = strlen(strip_tags($content)) > $char_limit;
                                                    ?>
                                                    <div class="read-more-container" id="<?= $unique_id ?>">
                                                        <div class="content-wrapper">
                                                            <div class="short-text" style="<?= !$needs_read_more ? 'display: none;' : '' ?>">
                                                                <?= htmlspecialchars(mb_substr(strip_tags($content), 0, $char_limit)) ?>
                                                                <?= $needs_read_more ? '...' : '' ?>
                                                            </div>
                                                            <div class="full-text" style="<?= !$needs_read_more ? 'display: block;' : 'display: none;' ?>">
                                                                <?= htmlspecialchars($content) ?>
                                                            </div>
                                                        </div>
                                                        <?php if ($needs_read_more): ?>
                                                            <button class="read-more-btn" onclick="toggleReadMore('<?= $unique_id ?>')"><?= labels('read_more', 'Read more') ?></button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>



                                                <div class="budget mt-2">
                                                    <div><?= labels('budget', 'Budget') ?></div>
                                                    <div class="align-items-center d-flex">
                                                        <div class="mr-2 text-dark"><?= $currency . $request['min_price']; ?></div>
                                                        <div class="mr-2"><?= labels('to', 'To') ?></div>
                                                        <div class="mr-2 text-dark"><?= $currency . $request['max_price']; ?></div>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="align-items-center d-flex justify-content-between job-card-footer">
                                                    <div class="o-media o-media--middle">
                                                        <?php if ($request['has_image']): ?>
                                                            <a href="<?= $request['image_url']; ?>" data-lightbox="image-1">
                                                                <img class="o-media__img avatar"
                                                                    src="<?= $request['image_url']; ?>"
                                                                    alt="">
                                                            </a>
                                                        <?php else: ?>
                                                            <div class="avatar-fallback"
                                                                style="background-color:<?= $request['fallback']['bgColor']; ?>;"
                                                                title="<?= htmlspecialchars($request['fallback']['username']); ?>">
                                                                <?= $request['fallback']['initial']; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="o-media__body">
                                                            <div class="provider_name_table"><?= $request['username'] ?></div>
                                                            <div class="provider_email_table"><?= $request['time_ago'] ?? ''; ?></div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $disk = fetch_current_file_manager();

                                                        if ($disk == 'local_server') {

                                                            $localPath = base_url('/public/uploads/categories/' . $request['category_image']);

                                                            if (check_exists($localPath)) {
                                                                $category_image = $localPath; // Use the local server image URL
                                                            } else {
                                                                $category_image = ''; // File not found, return an empty string
                                                            }
                                                        } else if ($disk == "aws_s3") {
                                                            $category_image = fetch_cloud_front_url('categories', $request['category_image']); // Construct the CloudFront URL
                                                        } else {
                                                            $category_image = $request['category_image'];
                                                        }

                                                        ?>
                                                        <button class="btn job_request_apply_now_btn"
                                                            data-toggle="modal"
                                                            data-target="#applyNowModal"
                                                            data-id="<?= $request['id']; ?>"
                                                            data-title="<?= $request['service_title']; ?>"
                                                            data-min-price="<?= $request['min_price']; ?>"
                                                            data-max-price="<?= $request['max_price']; ?>"
                                                            data-username="<?= $request['username']; ?>"
                                                            data-desc="<?= $request['service_short_description']; ?>"
                                                            data-category_name="<?= $request['category_name']; ?>"
                                                            data-category_image="<?= $category_image; ?>"
                                                            data-user_image="<?= base_url() . $request['image']; ?>"
                                                            data-expiresat="<?= $request['requested_end_date'] . ' ' . $request['requested_end_time']; ?>"
                                                            data-created-at="<?= $request['created_at'] ?>">
                                                            <?= labels('apply_now', 'Apply Now') ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Applied Jobs Content -->
            <div class="tab-pane fade" id="pills-applied-jobs" role="tabpanel" aria-labelledby="pills-applied-jobs-tab">
                <div class="">
                    <?php if (empty($applied_jobs)): ?>
                        <div class="row w-100">
                            <div class="col-md-12 d-flex justify-content-center">
                                <div class="empty-state" data-height="400" style="height: 400px;">
                                    <img src="<?= base_url('public/uploads/site/design.png'); ?>" alt="" srcset="">
                                    <h2><?= labels('no_jobs_available', 'Sahh...! No Job’s Available') ?></h2>
                                    <p class="lead">
                                        <?= labels('no_custom_jobs_applied', 'You have not applied for any custom jobs yet. Please explore available jobs and apply to get started.') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="row">
                            <?php foreach ($applied_jobs as $key => $request) { ?>
                                <div class="col-md-4 job-card-col">
                                    <div class="card mt-2 job-card-wrapper">
                                        <div class="">
                                            <div class="col-md-12">
                                                <div class="card-body job-card-body">
                                                    <div class="job_request_title">
                                                        <?= $request['service_title']; ?>
                                                    </div>

                                                    <div class="job_request_desc">
                                                        <?php
                                                        $content = $request['service_short_description'];
                                                        $unique_id = 'read-more-' . uniqid();
                                                        $char_limit = 100; // Character limit
                                                        $needs_read_more = strlen(strip_tags($content)) > $char_limit;
                                                        ?>
                                                        <div class="read-more-container" id="<?= $unique_id ?>">
                                                            <div class="content-wrapper">
                                                                <div class="short-text" style="<?= !$needs_read_more ? 'display: none;' : '' ?>">
                                                                    <?= htmlspecialchars(mb_substr(strip_tags($content), 0, $char_limit)) ?>
                                                                    <?= $needs_read_more ? '...' : '' ?>
                                                                </div>
                                                                <div class="full-text" style="<?= !$needs_read_more ? 'display: block;' : 'display: none;' ?>">
                                                                    <?= htmlspecialchars($content) ?>
                                                                </div>
                                                            </div>
                                                            <?php if ($needs_read_more): ?>
                                                                <button class="read-more-btn" onclick="toggleReadMore('<?= $unique_id ?>')"><?= labels('read_more', 'Read more') ?></button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>


                                                    <hr>

                                                    <?php
                                                    $disk = fetch_current_file_manager();

                                                    if ($disk == 'local_server') {

                                                        $localPath = base_url('/public/uploads/categories/' . $request['category_image']);

                                                        if (check_exists($localPath)) {
                                                            $category_image = $localPath; // Use the local server image URL
                                                        } else {
                                                            $category_image = ''; // File not found, return an empty string
                                                        }
                                                    } else if ($disk == "aws_s3") {
                                                        $category_image = fetch_cloud_front_url('categories', $request['category_image']); // Construct the CloudFront URL
                                                    } else {
                                                        $category_image = $request['category_image'];
                                                    }
                                                    ?>

                                                    <div class="clickable-section align-items-center d-flex justify-content-between clickableSection job-card-footer" id="clickableSection" data-id="<?= $request['id']; ?>"
                                                        data-title="<?= $request['service_title']; ?>"
                                                        data-min-price="<?= $request['min_price']; ?>"
                                                        data-max-price="<?= $request['max_price']; ?>"
                                                        data-username="<?= $request['username']; ?>"
                                                        data-desc="<?= $request['service_short_description']; ?>"
                                                        data-category_name="<?= $request['category_name']; ?>"
                                                        data-counter_price="<?= $request['counter_price']; ?>"
                                                        data-cover_note="<?= $request['note']; ?>"
                                                        data-duration="<?= $request['duration']; ?>"
                                                        data-tax_id="<?= $request['tax_id']; ?>"
                                                        data-tax_amount="<?= $request['tax_amount']; ?>"
                                                        data-tax_percentage="<?= $request['tax_percentage']; ?>"
                                                        data-category_image="<?= $category_image; ?>"
                                                        data-user_image="<?= base_url() . $request['image']; ?>"
                                                        data-created-at="<?= $request['created_at']; ?>"
                                                        data-expiresat="<?= $request['requested_end_date'] . ' ' . $request['requested_end_time']; ?>">

                                                        <div class="o-media o-media--middle">
                                                            <?php if ($request['has_image']): ?>
                                                                <img class="o-media__img avatar"
                                                                    src="<?= $request['image_url']; ?>"
                                                                    alt="">
                                                            <?php else: ?>
                                                                <div class="avatar-fallback"
                                                                    style="background-color:<?= $request['fallback']['bgColor']; ?>;"
                                                                    title="<?= htmlspecialchars($request['fallback']['username']); ?>">
                                                                    <?= $request['fallback']['initial']; ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <div class="o-media__body">
                                                                <div class="provider_name_table"><?= $request['username'] ?></div>
                                                                <div class="provider_email_table"><?= $request['time_ago'] ?? ''; ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="budget mt-2">
                                                            <div><?= labels('budget', 'Budget') ?></div>
                                                            <div class="align-items-center d-flex">
                                                                <div class="mr-2 text-dark"><?= $currency . $request['min_price']; ?></div>
                                                                <div class="mr-2">-</div>
                                                                <div class="mr-2 text-dark"><?= $currency . $request['max_price']; ?></div>
                                                                <div class="mr-2 text-dark">
                                                                    <!-- Flip arrow in RTL so range reads naturally. -->
                                                                    <i class="fas fa-angle-<?= ($is_rtl === 1) ? 'left' : 'right'; ?>"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
</div>
</section>
</div>
<!-- Applied Job Modal -->
<div class="modal fade" id="appliedJobsModal" tabindex="-1" aria-labelledby="appliedJobsModalLable" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-dark"><?= labels('your_bid', 'Your Bid') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <hr>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div id="job-title-view" class="font-weight-bold text-dark"></div>
                    </div>
                    <div class="col-md-12">
                        <div id="job-desc-view">
                            <span class="job-desc-short-view"></span>
                            <span class="job-desc-full-view d-none"></span>
                            <a href="#" class="read-more-less-view text-primary"></a>
                        </div>
                    </div>
                </div>
                <div class="row d-flex p-3 gap-4">
                    <div class="align-content-center">
                        <?= labels('category', 'Category') ?>
                    </div>
                    <div class=" job_request_category_name d-flex">
                        <img class="job_request_category_image" id="job-category-image-view" src="" alt="" srcset="">
                        <div class="category" id="job-category-view"></div>
                    </div>
                </div>
                <hr class="mt-0">
                <div class="row w-100">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <img class="avatar" id="job-user-image-view" src="" alt="">
                            <div class="ml-3">
                                <div id="job-user-profile" class="text-muted"><?= labels('customer', 'Customer') ?></div>
                                <div id="job-username-view" class="font-weight-bold"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-left">
                            <div class="text-muted"><?= labels('budget', 'Budget') ?></div>
                            <div id="job-budget-view" class="font-weight-bold"></div>
                        </div>
                    </div>
                </div>
                <div class="row w-100 mt-3">
                    <div class="col-md-6 ">
                        <div class="">
                            <div class="text-muted"><?= labels('posted_at', 'Posted At') ?></div>
                            <div id="job-created-at-view" class="font-weight-bold"></div>
                        </div>
                    </div>
                    <div class="col-md-6 ">
                        <div>
                            <div class="text-muted"><?= labels('expires_on', 'Expires On') ?></div>
                            <div id="job-expires-on-view" class="font-weight-bold"></div>
                        </div>
                    </div>
                </div>
                <h6 class="mt-4 text-dark"><?= labels('your_bid', 'Your Bid') ?></h6>
                <div class="d-flex justify-content-end">
                    <div class="col-md-6 p-0">
                        <label for="counter_price" class="text-muted"><?= labels('counter_price', 'Counter Price') ?></label>
                    </div>
                    <div class="col-md-6 p-0">
                        <div id="counter_price_view" class="text-dark"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-end tax_info">
                    <div class="col-md-6 p-0">
                        <label for="tax_percentage_view" class="text-muted"><?= labels('tax_percentage', 'Tax Percentage') ?></label>
                    </div>
                    <div class="col-md-6 p-0">
                        <div id="tax_percentage_view" class="text-dark"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-end tax_info">
                    <div class="col-md-6 p-0 ">
                        <label for="tax_amount" class="text-muted"><?= labels('tax_amount', 'Tax Amount') ?></label>
                    </div>
                    <div class="col-md-6 p-0 ">
                        <div id="tax_amount_view" class="text-dark"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <div class="col-md-6 p-0 ">
                        <label for="final_total_view" class="text-muted"><?= labels('final_total', 'Final Total') ?></label>
                    </div>
                    <div class="col-md-6 p-0 ">
                        <div id="final_total_view" class="text-dark"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <div class="col-md-6 p-0">
                        <label for="duration_view" class="text-muted"><?= labels('duration', 'Duration') ?></label>
                    </div>
                    <div class="col-md-6 p-0">
                        <div id="duration_view" class="text-dark"></div>
                    </div>
                </div>
                <!-- <div class="">
                    <label for="cover_note_view" class="text-dark"><?= labels('cover_note', 'Cover Note') ?></label>
                    <div id="cover_note_view" class="text-muted"></div>
                </div> -->
                <div>
                    <label for="cover_note_view" class="text-dark"><?= labels('cover_note', 'Cover Note') ?></label>
                    <div id="cover_note_view" class="text-muted">
                        <span class="cover-note-short"></span>
                        <span class="cover-note-full d-none"></span>
                        <a href="#" class="cover-read-more-less text-primary"></a>
                    </div>
                </div>
                <div class="modal-footer px-0">
                    <button type="submit" class="btn bg-new-primary submit_btn" disabled><?= labels('submitted', 'Submitted') ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Apply Now Modal -->
<div class="modal fade" id="applyNowModal" tabindex="-1" aria-labelledby="applyNowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-dark"><?= labels('submit_your_bid', 'Submit Your Bid') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <hr>
            <div class="modal-body">
                <?= form_open('partner/make_bid', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data"]); ?>
                <input type="hidden" name="id" id="job-id">
                <div class="row">
                    <div class="col-md-12">
                        <div id="job-title" class="font-weight-bold text-dark"></div>
                    </div>
                    <div class="col-md-12">
                        <!-- <div id="job-desc"></div>


                        <a href="#" class="read-more-less text-primary"></a> -->

                        <div id="job-desc-view">
                            <span class="job-desc-short"></span>
                            <span class="job-desc-full d-none"></span>
                            <a href="#" class="read-more-less text-primary"></a>
                        </div>
                    </div>
                </div>
                <div class="row d-flex p-3 gap-4">
                    <div class="align-content-center">
                        <?= labels('category', 'Category') ?>
                    </div>
                    <div class=" job_request_category_name d-flex">
                        <img class="job_request_category_image" id="job-category-image" src="" alt="" srcset="">
                        <div class="category" id="job-category"></div>
                    </div>
                </div>
                <hr class="mt-0">
                <div class="row w-100">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <img class="avatar" id="job-user-image" src="" alt="">
                            <div class="ml-3">
                                <div id="job-user-profile" class="text-muted"><?= labels('customer', 'Customer') ?></div>
                                <div id="job-username" class="font-weight-bold"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-left">
                            <div class="text-muted"><?= labels('budget', 'Budget') ?></div>
                            <div id="job-budget" class="font-weight-bold"></div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row w-100 mt-3">
                    <div class="col-md-6">
                        <div class="">
                            <div class="text-muted"><?= labels('posted_at', 'Posted At') ?></div>
                            <div id="job-created-at" class="font-weight-bold"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div>
                            <div class="text-muted"><?= labels('expires_on', 'Expires On') ?></div>
                            <div id="job-expires-on" class="font-weight-bold"></div>
                        </div>
                    </div>
                </div>
                <hr>
                <h6 class="mt-4 text-dark"><?= labels('apply_bid', 'Apply Bid') ?></h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="counter_price"><?= labels('select_tax', 'Select Tax') ?></label>
                            <select id="tax" name="tax_id" class="form-control w-100" name="tax">
                                <option value=""><?= labels('select_tax', 'Select Tax') ?></option>
                                <?php foreach ($tax_data as $pn) : ?>
                                    <option value="<?= $pn['id'] ?>"><?= $pn['title'] ?>(<?= $pn['percentage'] ?>%)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="counter_price" class="required"><?= labels('counter_price', 'Counter Price') ?></label>
                            <input type="number" min=0 class="form-control" id="counter_price" name="counter_price">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="cover_note" class="required"><?= labels('write_cover_note', 'Write Cover Note') ?></label>
                            <textarea class="form-control" name="cover_note" id="cover_note" placeholder="<?= labels('write_cover_note', 'Write Cover Note') ?>"></textarea>
                        </div>
                    </div>
                </div>
                <label for="duration"><?= labels('how_much_time_you_need_to_perform_this_service', 'How Much time you need to perform this service') ?></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <div class="input-group-text myDivClass" style="height: 42px;">
                            <span class="mySpanClass"><?= labels('minutes', 'Minutes') ?></span>
                        </div>
                    </div>
                    <input type="number" style="height: 42px;" class="form-control" name="duration" id="duration" min="0" placeholder="<?= labels('duration_to_perform_task', 'Duration to Perform service') ?>" value="">
                </div>
                <div class="modal-footer px-0">
                    <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('submit_bid', 'Submit Bid') ?></button>
                    <?= form_close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- manage categories modal -->
<div class="modal fade" id="manage_categories" tabindex="-1" aria-labelledby="manage_categoriesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="row pl-3 m-0 border_bottom_for_cards">
                <div class="col-auto">
                    <div class="toggleButttonPostition"><?= labels('manage_category_preference', 'Manage Category Preference') ?></div>
                </div>
            </div>
            <div class="modal-body">
                <?= form_open('partner/manage_category_preference', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data"]); ?>
                <div class="row px-2 justify-content-center">
                    <?php foreach ($categories_name as $d): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 p-2">
                            <div class="category_preference_card">
                                <input type="checkbox" name="category_id[]" <?= (in_array($d['id'], $custom_job_categories) == true)  ? 'checked' : "" ?> value="<?= $d['id'] ?>">
                                <img class="category_preference_image" src="<?= base_url('public/uploads/categories/' . $d['image']); ?>" alt="" onerror="this.onerror=null;this.src='<?= base_url('public/backend/assets/default.png') ?>';">
                                <p class="text-center"><?= $d['name'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer px-0">
                    <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('submit', 'Submit') ?></button>
                    <?= form_close(); ?> <!-- Move this line inside the modal-body -->
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Disable Custom Job modal -->
<div class="modal fade" id="manage_custom_job_setting" tabindex="-1" aria-labelledby="manage_custom_job_settingLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <?= form_open('partner/manage_accepting_custom_jobs', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data"]); ?>
                <!-- Center the image -->
                <div class="row d-flex justify-content-center">
                    <div class="col-auto">
                        <img src="<?= base_url('public/uploads/site/think.png'); ?>" alt="Think Image" class="img-fluid">
                    </div>
                </div>
                <p class="text-center text-dark font-weight-bold"><?= labels('are_you_sure', 'Are You Sure..?') ?></p>
                <?php if ($is_accepting_custom_jobs == 1) : ?>
                    <p class="text-center text-muted"><?= labels('disable_job_service_warning', 'You are going to disable open job service request’s from customers across the world. that’s means you are not eligible to provide customized service.') ?></p>
                    <input type="hidden" name="custom_job_value" value="0">
                <?php else : ?>
                    <input type="hidden" name="custom_job_value" value="1">
                    <p class="text-center text-muted"><?= labels('custom_service_eligibility', ' You are now eligible to provide customized service. Open job service requests from customers across the world are enabled.') ?>
                    </p>
                <?php endif; ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-secondary btn-lg  submit_btn w-100"><?= labels('continue', 'Continue') ?></button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn bg-new-primary btn-lg  w-100" data-dismiss="modal"><?= labels('not_yet', 'Not Yet..!') ?></button>
                    </div>
                </div>
                <?= form_close(); ?>
            </div>
        </div>
    </div>
</div>
<script>
    <?php
    // Fetch general settings once and expose currency/decimals to JS in a safe, global way.
    $settings = get_settings('general_settings', true);
    $default_logo = base_url("public/uploads/site/" . $settings['logo']);
    $system_currency = isset($settings['currency']) ? $settings['currency'] : '$';
    $decimal_point = isset($settings['decimal_point']) ? (int)$settings['decimal_point'] : 0;
    ?>

    // Global currency and decimal configuration from general_settings.
    var appCurrency = '<?= $system_currency; ?>';
    var decimalPoint = <?= $decimal_point; ?>;

    $(document).ready(function() {
        $('#category_status').siblings('.switchery').addClass('active-content').removeClass('deactive-content');
        $('.job_request_apply_now_btn').on('click', function() {

            var data = $(this).data();


            $('#job-id').val(data.id);
            $('#job-title').text(data.title);
            $('#job-desc').text(data.desc);
            // Show budget with system currency and proper decimal formatting
            $('#job-budget').text(appCurrency + formatAmount(data.minPrice) + ' - ' + appCurrency + formatAmount(data.maxPrice));
            $('#job-category').text(data.category_name);
            // $('#job-category-image').attr('src', data.category_image);


            // User image with error handling
            $('#job-category-image')
                .attr('src', data.category_image)
                .on('error', function() {
                    $(this).attr('src', '<?= $default_logo; ?>')
                        .attr('onerror', null) // Prevent infinite loop
                        .addClass('fallback-image');
                });




            $('#job-username').text(data.username);
            // $('#job-user-image').attr('src', data.user_image);
            // User image with error handling
            $('#job-user-image')
                .attr('src', data.user_image)
                .on('error', function() {
                    $(this).attr('src', '<?= $default_logo; ?>')
                        .attr('onerror', null) // Prevent infinite loop
                        .addClass('fallback-image');
                });



            // Display created_at and expires_on as full date + 12-hour time (e.g., "23/01/2026 - 10:30 am")
            // We parse the "YYYY-MM-DD HH:MM:SS" string manually to avoid timezone shifts.
            $('#job-created-at').text(formatDateTimeTo12Hour(data.createdAt));
            $('#job-expires-on').text(formatDateTimeTo12Hour(data.expiresat));

            // Handle job description
            var fullDesc = data.desc;
            var shortDesc = fullDesc.length > 20 ? fullDesc.substring(0, 20) + '...' : fullDesc;

            $('.job-desc-short').text(shortDesc);
            $('.job-desc-full').text(fullDesc);
            $('.read-more-less').text(fullDesc.length > 20 ? '<?= labels('read_more', 'Read more') ?>' : '').off('click').on('click', function(e) {
                e.preventDefault();
                var isExpanded = $('.job-desc-full').hasClass('d-none');
                if (isExpanded) {
                    $('.job-desc-short').addClass('d-none');
                    $('.job-desc-full').removeClass('d-none');
                    $(this).text('<?= labels('read_less', 'Read less') ?>');
                } else {
                    $('.job-desc-short').removeClass('d-none');
                    $('.job-desc-full').addClass('d-none');
                    $(this).text('<?= labels('read_more', 'Read more') ?>');
                }
            });

            // Track job request viewed event
            if (typeof trackJobRequestViewed === 'function') {
                trackJobRequestViewed(data.id);
            }
        });
    });
    // Helper function to format date as DD/MM/YYYY HH:MM
    function formatDate(date) {
        if (isNaN(date)) return "Invalid Date"; // Handle invalid dates gracefully

        const day = String(date.getDate()).padStart(2, '0'); // Ensure 2-digit day
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Ensure 2-digit month
        const year = date.getFullYear();
        const time = date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });

        return `${day}/${month}/${year} - ${time}`;
    }

    // Helper to format "YYYY-MM-DD HH:MM:SS" (or "HH:MM:SS") into 12-hour time like "10:30 am".
    // This does NOT apply any timezone conversions; it simply re-formats the string from backend.
    function formatTimeTo12Hour(dateTimeString) {
        if (!dateTimeString) return '';

        // Split into date + time parts; if only time is given, use that directly.
        var parts = String(dateTimeString).trim().split(' ');
        var timePart = parts.length > 1 ? parts[1] : parts[0];

        if (!timePart) return '';

        var timePieces = timePart.split(':');
        var hour = parseInt(timePieces[0], 10);
        var minute = timePieces.length > 1 ? timePieces[1] : '00';

        if (isNaN(hour)) return '';

        var ampm = hour >= 12 ? 'pm' : 'am';
        hour = hour % 12;
        if (hour === 0) hour = 12;

        return hour + ':' + String(minute).padStart(2, '0') + ' ' + ampm;
    }

    // Helper to format full backend datetime "YYYY-MM-DD HH:MM:SS" (stored as UTC)
    // into "DD/MM/YYYY - hh:mm am/pm" in the user's local timezone.
    function 
    formatDateTimeTo12Hour(dateTimeString) {
        if (!dateTimeString) return '';

        // Append 'Z' so the Date constructor treats it as UTC.
        var utcString = String(dateTimeString).trim().replace(' ', 'T');
        if (utcString.indexOf('Z') === -1 && utcString.indexOf('+') === -1) {
            utcString += 'Z';
        }
        var date = new Date(utcString);
        if (isNaN(date.getTime())) return dateTimeString; // fallback: show as-is

        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();

        var hour = date.getHours();
        var minute = String(date.getMinutes()).padStart(2, '0');
        var ampm = hour >= 12 ? 'pm' : 'am';
        hour = hour % 12;
        if (hour === 0) hour = 12;

        return day + '/' + month + '/' + year + ' - ' + hour + ':' + minute + ' ' + ampm;
    }

    // Helper to format a numeric amount with the configured decimal precision.
    // Accepts string or number and returns a string rounded according to decimalPoint.
    function formatAmount(value) {
        if (value === null || value === undefined || value === '') return '0';
        var num = parseFloat(value);
        if (isNaN(num)) return '0';
        var decimals = (typeof decimalPoint !== 'undefined') ? parseInt(decimalPoint, 10) : 0;
        if (isNaN(decimals) || decimals < 0) decimals = 0;
        return num.toFixed(decimals);
    }


    function handleSwitchChange(checkbox) {
        var switchery = checkbox.nextElementSibling;
        if (checkbox.checked) {
            switchery.classList.add('active-content');
            switchery.classList.remove('deactive-content');
        } else {
            switchery.classList.add('deactive-content');
            switchery.classList.remove('active-content');
        }
    }
    var category_status = document.querySelector('#category_status');
    category_status.addEventListener('change', function() {
        handleSwitchChange(category_status);
    });
</script>
<script>
    document.querySelectorAll('.job_request_categories_container_card').forEach(card => {
        card.addEventListener('click', () => {
            card.classList.toggle('selected');
        });
    });
</script>
<script>
    function toggleReadMore(containerId) {
        const container = document.getElementById(containerId);
        const btn = container.querySelector('.read-more-btn');
        const shortText = container.querySelector('.short-text');
        const fullText = container.querySelector('.full-text');
        if (shortText.style.display === 'none') {
            // Show short text (collapse)
            shortText.style.display = 'block';
            fullText.style.display = 'none';
            btn.textContent = '<?= labels('read_more', 'Read more') ?>';
        } else {
            // Show full text (expand)
            shortText.style.display = 'none';
            fullText.style.display = 'block';
            btn.textContent = '<?= labels('read_less', 'Read less') ?>';
        }
    }
</script>
<script>
    document.addEventListener('click', function(event) {




        if (event.target.closest('.clickableSection')) {



            <?php
            $settings = get_settings('general_settings', true);
            $default_logo = base_url("public/uploads/site/" . $settings['logo']);
            ?>

            const element = event.target.closest('.clickableSection');
            $('#appliedJobsModal').modal('show');
            const data = $(element).data();

            $('#job-title-view').text(data.title);
            $('#job-category-view').text(data.category_name);

            // Category image with error handling
            $('#job-category-image-view')
                .attr('src', data.category_image)
                .on('error', function() {
                    $(this).attr('src', '<?= $default_logo; ?>')
                        .attr('onerror', null) // Prevent infinite loop
                        .addClass('fallback-image');
                });

            $('#job-username-view').text(data.username);

            // User image with error handling
            $('#job-user-image-view')
                .attr('src', data.user_image)
                .on('error', function() {
                    $(this).attr('src', '<?= $default_logo; ?>')
                        .attr('onerror', null) // Prevent infinite loop
                        .addClass('fallback-image');
                });

            $('#job-budget-view').text(appCurrency + formatAmount(data.minPrice) + ' - ' + appCurrency + formatAmount(data.maxPrice));

            // Display created_at and expires_on as full date + 12-hour time (e.g., "23/01/2026 - 10:30 am")
            // Parsed without timezone so it matches backend time exactly.
            $('#job-created-at-view').text(formatDateTimeTo12Hour(data.createdAt));
            $('#job-expires-on-view').text(formatDateTimeTo12Hour(data.expiresat));

            // Counter price with currency and proper rounding
            $('#counter_price_view').text(appCurrency + formatAmount(data.counter_price));
            // Show duration with translated "minutes" label
            $('#duration_view').text(data.duration + ' <?= labels('minutes', 'Minutes') ?>');


            // Tax and final total calculation/formatting similar to get_custom_job_requests API
            var numericCounter = parseFloat(data.counter_price || 0);
            var numericTax = parseFloat(data.tax_amount || 0);

            if (data.tax_id == "0" || data.tax_id == "" || isNaN(numericTax) || numericTax === 0) {
                $('#tax_amount_view').text('-');
                $('#tax_percentage_view').text('-');
                // When no tax, final total is just counter price
                $('#final_total_view').text(appCurrency + formatAmount(numericCounter));
            } else {
                $('#tax_amount_view').text(appCurrency + formatAmount(numericTax));
                $('#tax_percentage_view').text(data.tax_percentage + '%');
                var finalTotal = numericCounter + numericTax;
                $('#final_total_view').text(appCurrency + formatAmount(finalTotal));
            }

            // Handle job description
            var fullDesc = data.desc;
            var shortDesc = fullDesc.length > 20 ? fullDesc.substring(0, 20) + '...' : fullDesc;

            $('.job-desc-short-view').text(shortDesc);
            $('.job-desc-full-view').text(fullDesc);
            $('.read-more-less-view')
                .text(fullDesc.length > 20 ? '<?= labels('read_more', 'Read more') ?>' : '')
                .off('click')
                .on('click', function(e) {
                    e.preventDefault();
                    var isExpanded = $('.job-desc-full-view').hasClass('d-none');
                    if (isExpanded) {
                        $('.job-desc-short-view').addClass('d-none');
                        $('.job-desc-full-view').removeClass('d-none');
                        $(this).text('<?= labels('read_less', 'Read less') ?>');
                    } else {
                        $('.job-desc-short-view').removeClass('d-none');
                        $('.job-desc-full-view').addClass('d-none');
                        $(this).text('<?= labels('read_more', 'Read more') ?>');
                    }
                });

            // Handle cover note
            var fullCoverNote = data.cover_note || '';
            var shortCoverNote = fullCoverNote.length > 20 ? fullCoverNote.substring(0, 20) + '...' : fullCoverNote;

            $('.cover-note-short').text(shortCoverNote);
            $('.cover-note-full').text(fullCoverNote);
            $('.cover-read-more-less')
                .text(fullCoverNote.length > 20 ? '<?= labels('read_more', 'Read more') ?>' : '')
                .off('click')
                .on('click', function(e) {
                    e.preventDefault();
                    var isExpanded = $('.cover-note-full').hasClass('d-none');
                    if (isExpanded) {
                        $('.cover-note-short').addClass('d-none');
                        $('.cover-note-full').removeClass('d-none');
                        $(this).text('<?= labels('read_less', 'Read less') ?>');
                    } else {
                        $('.cover-note-short').removeClass('d-none');
                        $('.cover-note-full').addClass('d-none');
                        $(this).text('<?= labels('read_more', 'Read more') ?>');
                    }
                });
        }
    });
</script>