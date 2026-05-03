<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('view_service', "View Service") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/services') ?>"><i class="	fas fa-tools text-warning"></i> <?= labels('service', 'Service') ?></a></div>
                <div class="breadcrumb-item"><?= labels('view_service', 'View Service') ?></a></div>

            </div>
        </div>

        <!-- Service Details card - full width row -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card d-flex flex-column">
                    <div class="row pl-3 border_bottom_for_cards m-0">
                        <div class="col-auto ">
                            <div class="toggleButttonPostition"><?= labels('service_details', 'Service Details') ?></div>
                        </div>

                        <div class="col d-flex justify-content-end mr-3 mt-4 py-2">
                            <?php
                            $label = ($service[0]['status'] == 1) ?
                                "<div class='tag border-0 rounded-md  bg-emerald-success text-emerald-success mx-2'>" . labels('active', 'Active') . "</div>" :
                                "<div class='tag border-0 rounded-md  bg-emerald-danger text-emerald-danger mx-2'>" . labels('deactive', 'Deactive') . "</div>";

                            echo $label;
                            ?>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row mb-3">
                            <!-- Provider field - responsive column sizing with proper spacing -->
                            <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fa-solid fa-user text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('provider', 'Provider') ?></span>
                                        <p class="m-0"><?= !empty($service[0]['provider_company_name']) ? $service[0]['provider_company_name'] : $service[0]['user_id'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Title field - responsive column sizing with proper spacing -->
                            <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-tools fa-lg text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('title', 'Title') ?></span>
                                        <p class="m-0"><?= $service[0]['title'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Slug field - responsive column sizing with proper spacing -->
                            <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fa-solid fa-info text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('slug', 'Slug') ?></span>
                                        <p class="m-0"><?= $service[0]['slug'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <!-- Category field - responsive column sizing with proper spacing -->
                            <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fa-solid fa-list text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('category', 'Category') ?></span>
                                        <p class="m-0"><?= !empty($service[0]['category_name']) ? $service[0]['category_name'] : $service[0]['category_id'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tax Type field - responsive column sizing with proper spacing -->
                            <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fa-solid fa-percent text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('tax_type', 'Tax Type') ?></span>
                                        <p class="m-0"><?= $service[0]['tax_type'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Members Required field - responsive column sizing with proper spacing -->
                            <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fa-solid fa-people-carry-box text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('members_required', 'Members required') ?></span>
                                        <p class="m-0"><?= $service[0]['number_of_members_required'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <!-- Max Quantity Allowed field - responsive column sizing with proper spacing -->
                            <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-calculator text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('max_quantity_allowed', 'Max quantity allowed') ?></span>
                                        <p class="m-0"><?= $service[0]['max_quantity_allowed'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Description field - responsive column sizing with proper spacing -->
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-book text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('description', 'Description') ?></span>
                                        <p class="m-0"><?= $service[0]['description'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Long Description field - on its own row for large content -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-quote-left text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('long_description', 'Long Description') ?></span>
                                        <p class="m-0"><?= $service[0]['long_description'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>

        <!-- Basic Details card - full width row -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card d-flex flex-column">
                    <div class="row pl-3 border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('basic_details', 'Basic Details') ?></div>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Image section - separated with proper spacing to avoid congestion -->
                        <div class="row mb-3">
                            <div class="col-12 mb-3">
                                <div class="col-xl-12 col-md-12">
                                    <span class="title"><?= labels('image', 'Image') ?></span>
                                </div>
                                <div class="col-xl-12 col-md-12">
                                    <img alt="no image found" class="mt-2" style="width: 100px; height: 100px; object-fit: cover;" id="image_preview" src="<?= isset($service[0]['image']) ? ($service[0]['image']) : "" ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Duration and Price fields - responsive column sizing with proper spacing -->
                        <div class="row mb-3">
                            <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-clock text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('duration', 'Duration') ?></span>
                                        <p class="m-0"><?= $service[0]['duration'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-coins text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('price', 'Price') ?></span>
                                        <p class="m-0"><?= $service[0]['price'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Discount Price and Is Cancelable fields - responsive column sizing with proper spacing -->
                        <div class="row mb-3">
                            <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-money-bill-wave text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('discount_price', 'Discount Price') ?></span>
                                        <p class="m-0"><?= $service[0]['discounted_price'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-info-circle text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('is_cancelable', 'Is Cancelable ') ?></span>
                                        <p class="m-0">
                                            <?php
                                            $is_cancellable_badge = ($service[0]['is_cancelable'] == 1) ?
                                                "<div class='text-emerald-success ml-3 mr-3 m-0'> " . labels('yes', 'Yes') . "</div>" :
                                                "<div class='text-emerald-danger ml-3 mr-3 m-0'> " . labels('no', 'No') . "</div>";

                                            echo $is_cancellable_badge;
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cancelable Till and At Store fields - responsive column sizing with proper spacing -->
                        <div class="row mb-3">
                            <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-info-circle text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('cancelable_till', 'Cancelable before') ?></span>
                                        <p class="m-0"><?= $service[0]['cancelable_till'] ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-info-circle text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('at_store', 'At Store') ?></span>
                                        <p class="m-0">
                                            <?php
                                            $is_cancellable_badge = ($service[0]['at_store'] == 1) ?
                                                "<div class='text-emerald-success ml-3 mr-3 m-0'>Yes</div>" :
                                                "<div class='text-emerald-danger ml-3 mr-3 m-0'>No</div>";

                                            echo $is_cancellable_badge;
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- At Doorstep field - responsive column sizing with proper spacing -->
                        <div class="row mb-3">
                            <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="icon_box">
                                        <i class="fas fa-info-circle text-white"></i>
                                    </div>
                                    <div class="service_info flex-grow-1">
                                        <span class="title"><?= labels('at_doorstep', 'At Doorstep') ?></span>
                                        <p class="m-0">
                                            <?php
                                            $is_cancellable_badge = ($service[0]['at_doorstep'] == 1) ?
                                                "<div class='text-emerald-success ml-3 mr-3 m-0'>Yes</div>" :
                                                "<div class='text-emerald-danger ml-3 mr-3 m-0'>No</div>";

                                            echo $is_cancellable_badge;
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 col-xl-8 col-sm-12">
                <div class="card  d-flex flex-column h-100">
                    <div class="row pl-3 border_bottom_for_cards m-0">
                        <div class="col-auto">
                            <div class="toggleButttonPostition"><?= labels('faqs', 'Faqs') ?></div>
                        </div>


                    </div>
                    <?php

                    if (!empty($service[0]['faqs'])) {

                        $faqs = json_decode($service[0]['faqs'], true); // Decode JSON into a PHP array

                        echo '<main>';
                        // Handle both array format [["question","answer"]] and object format {"1":["question","answer"]}
                        $index = 0;

                        foreach ($faqs as $key => $faq) {

                            // Case 1: ["question","answer"]
                            if (is_array($faq) && isset($faq[0], $faq[1])) {
                                $question = $faq[0];
                                $answer   = $faq[1];
                            }
                            // Case 2: {"question":"...","answer":"..."}
                            elseif (is_array($faq) && isset($faq['question'], $faq['answer'])) {
                                $question = $faq['question'];
                                $answer   = $faq['answer'];
                            }
                            // Unknown mutant format → skip
                            else {
                                continue;
                            }

                            echo '<div class="topic">';
                            echo '<div class="open1">';
                            echo '<h2 class="question">' . ($index + 1) . '. ' . htmlspecialchars($question) . '</h2>';
                            echo '<span class="faq-t"></span>';
                            echo '</div>';
                            echo '<p class="answer">' . htmlspecialchars($answer) . '</p>';
                            echo '</div>';

                            $index++;
                        }
                        echo '</main>';
                    } else {
                        echo '     <div class="col-md-12 d-flex justify-content-center">

                        <!-- <h5>No data found</h5> -->


                        <div class="empty-state" data-height="400" style="height: 400px;">
                            <div class="empty-state-icon bg-primary">
                                <i class="fas fa-question text-white "></i>
                            </div>
                            <h2>' . labels("we_couldnt_find_any_providers", "We couldn't find any providers") . '</h2>
                            <p class="lead">
                                ' . labels("sorry_we_cant_find_any_data", "Sorry we can't find any data, to get rid of this message, make at least 1 entry.") .
                            '</p>

                        </div>
                    </div>';
                    }
                    ?>


                </div>
            </div>

            <div class="col-md-12 col-xl-4 col-sm-12">
                <div class="card d-flex flex-column h-100 ">
                    <div class="row pl-3 border_bottom_for_cards m-0">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('other_images', 'Other Images') ?></div>
                        </div>

                    </div>

                    <div class="card-body">
                        <?php
                        // Check if other images exist and display them responsively
                        if (!empty($service[0]['other_images'])) {
                            $other_images = $service[0]['other_images'];
                        ?>
                            <div class="row">
                                <?php foreach ($other_images as $row) { ?>
                                    <!-- Responsive image columns - 2 per row on mobile, 3 on larger screens -->
                                    <div class="col-6 col-md-4 mb-3">
                                        <img alt="no image found" style="width: 100px; height: 100px; padding:5px; object-fit: cover;" src="<?= isset($row) ? ($row) : "" ?>">
                                    </div>
                            <?php }
                            } else {
                                // Empty state when no images are available
                                echo '     <div class="col-md-12 d-flex justify-content-center">
                                <div class="empty-state" data-height="400" style="height: 400px;">
                                    <div class="empty-state-icon bg-primary">
                                        <i class="fas fa-question text-white "></i>
                                    </div>
                                    <h2>We couldn\'t find any Providers</h2>
                                    <p class="lead">
                                        Sorry we can\'t find any data, to get rid of this message, make at least 1 entry.
                                    </p>
                                </div>
                            </div>';
                            }
                            ?>
                            </div>





                    </div>

                </div>


            </div>
        </div>


</div>


<div class="row">

</div>


</div>

</div>
</div>
</section>
</div>

<script>
    $(".open1").click(function() {
        var container = $(this).parents(".topic");
        var answer = container.find(".answer");
        var trigger = container.find(".faq-t");

        answer.slideToggle(200);

        if (trigger.hasClass("faq-o")) {
            trigger.removeClass("faq-o");
        } else {
            trigger.addClass("faq-o");
        }

        if (container.hasClass("expanded")) {
            container.removeClass("expanded");
        } else {
            container.addClass("expanded");
        }
    });
</script>
<style>
    /* Icon box styling - ensures perfectly square icons with consistent sizing */
    /* All icons will have the same width and height regardless of viewport size */
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
        flex-shrink: 0;
        /* Prevents icon from shrinking */
    }

    /* Icon font size consistency - ensures all icons are the same size */
    .icon_box i {
        font-size: 20px !important;
    }

    /* Service info styling - ensures text stays within card boundaries */
    .service_info {
        flex-grow: 1;
        /* Allows text area to take available space */
        min-width: 0;
        /* Allows text to wrap properly */
        word-wrap: break-word;
        /* Breaks long words to prevent overflow */
        overflow-wrap: break-word;
        /* Additional word breaking support */
    }

    /* Text content styling - prevents overflow and ensures proper wrapping */
    .service_info p {
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 100%;
        /* Ensures text never exceeds container width */
    }

    /* Responsive image styling - prevents overflow and maintains aspect ratio */
    .card img {
        max-width: 100%;
        height: auto;
        object-fit: cover;
        border-radius: 8px;
    }

    /* Ensure cards maintain proper spacing on all viewports */
    @media (max-width: 576px) {
        .service_info {
            margin-left: 0;
        }

        .icon_box {
            margin-right: 8px;
        }
    }
</style>