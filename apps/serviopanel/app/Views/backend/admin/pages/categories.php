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
            <h1><?= labels('categories', "Categories") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"> <?= labels('category', 'Categories') ?></a></div>
            </div>
        </div>
        <div class="row">
            <?php
            if ($permissions['create']['categories'] == 1 && $permissions['create']['seo_settings'] == 1) { ?>
                <div class="col-md-6 ">
                    <?= helper('form'); ?>
                    <?= form_open('/admin/category/add_category', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data"]); ?>
                    <div class="card">
                        <div class="row m-0">
                            <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('category', 'Category') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        // Sort languages so default language appears first for better UI
                                        $sorted_languages = sort_languages_with_default_first($languages);
                                        foreach ($sorted_languages as $index => $language) {
                                            if ($language['is_default'] == 1) {
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
                            <div class="row mt-3">
                                <?php
                                // Use sorted languages for content divs as well
                                foreach ($sorted_languages as $index => $language) {
                                ?>
                                    <div class="col-md-6" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <div class="form-group">
                                            <label for="category_name" class="required"><?= labels('name', 'Name') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <input id="category_name" class="form-control" type="text" <?= $language['is_default'] ? 'data-slug-source data-slug-target="#category_slug"' : '' ?> name="name[<?= $language['code'] ?>]" placeholder="<?= labels('enter_name_of_category', 'Enter the name of the Category here') ?>">
                                        </div>
                                    </div>
                                <?php } ?>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                        <input id="category_slug" class="form-control" type="text" name="category_slug" placeholder="<?= labels('enter_category_slug_here', 'Enter the slug of the Category here') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="make_parent" class="required"><?= labels('type', 'Type') ?></label><br>
                                        <select name="make_parent" id="make_parent" class="form-control">
                                            <option value="0"><?= labels('category', 'Category') ?></option>
                                            <option value="1"><?= labels('sub_category', 'Sub Category') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="parent">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category_ids" class="required"> <?= labels('select_parent_category', 'Select Parent Category') ?></label><br>
                                        <select name="parent_id" id="category_ids" class="form-control">
                                            <option value=""><?= labels('select_parent_category', 'Select Parent Category') ?></option>
                                            <?php foreach ($categories as $category) : ?>
                                                <option value="<?= $category['id'] ?>"><?= $category['name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group"> <label for="image" class="required"><?= labels('image', 'Image') ?></label> <small id="image-recommendation">(<?= labels('category_image_recommended_size', 'We recommend 60x60 pixels') ?>)</small><br>
                                        <input type="file" class="filepond" name="image" id="image" accept="image/*">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="color" class="required"><?= labels('dark_theme_color', 'Dark Theme Color') ?></label>
                                        <br>
                                        <input type="color" name="dark_theme_color" id="dark_theme_color" title="<?= labels('choose_color', 'Choose Color') ?>" value="#000000" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="color" class="required"><?= labels('light_theme_color', 'Light Theme Color') ?></label>
                                        <br>
                                        <input type="color" name="light_theme_color" id="light_theme_color" title="<?= labels('choose_color', 'Choose Color') ?>" value="#FFFFFF" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="row m-0">
                            <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('seo_settings', 'SEO Settings') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                        <?php
                                        // Get default language for SEO section
                                        $default_language_seo = '';
                                        foreach ($sorted_languages as $lang) {
                                            if ($lang['is_default'] == 1) {
                                                $default_language_seo = $lang['code'];
                                                break;
                                            }
                                        }
                                        foreach ($sorted_languages as $index => $language) {
                                        ?>
                                            <div class="language-seo-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                id="language-seo-<?= $language['code'] ?>"
                                                data-language="<?= $language['code'] ?>"
                                                style="cursor: pointer; padding: 0.5rem 0;">
                                                <span class="language-seo-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                    <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                                </span>
                                                <div class="language-seo-underline"
                                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <!-- SEO content for each language -->
                            <?php foreach ($sorted_languages as $index => $language) { ?>
                                <div id="translationDivSeo-<?= $language['code'] ?>" <?= $language['code'] == $default_language_seo ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                                <input id="meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255">
                                                <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                                <textarea id="meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"></textarea>
                                                <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                                <input id="meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                                <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                                    data-toggle="popover"
                                                    class="fa fa-question-circle"
                                                    data-original-title=""
                                                    title=""></i>
                                                <textarea id="schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <!-- Meta Image (shared across all languages) -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                        <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                        <input type="file" class="filepond" name="meta_image" id="meta_image" accept="image/*">
                                        <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md d-flex justify-content-end">
                            <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('add_category', 'Add Category') ?></button>
                        </div>
                    </div>
                    <?= form_close(); ?>
                </div>
            <?php }
            ?>
            <?php
            if ($permissions['read']['categories'] == 1 && $permissions['read']['seo_settings'] == 1) { ?>
                <div class="col-md-6 ">
                    <div class="card ">

                        <div class="row m-0">
                            <div class="col mb-3 " style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('category_list', 'Category List') ?></div>
                            </div>
                        </div>
                        <div class="row pb-3 pl-3">
                            <div class="col-12">
                                <div class="row mb-3 mt-3">
                                    <div class="col-md-4 col-sm-2 mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" id="customSearchBtn" type="button">
                                                    <i class="fa fa-search d-inline"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="btn btn-secondary  ml-2 filter_button" id="filterButton">
                                        <span class="material-symbols-outlined mt-1">
                                            filter_alt
                                        </span>
                                    </button>
                                    <div class="dropdown d-inline ml-2">
                                        <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <?= labels('download', 'Download') ?>
                                        </button>
                                        <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                            <a class="dropdown-item" onclick="custome_export('pdf','Category list','category_list');"> <?= labels('pdf', 'PDF') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('excel','Category list','category_list');"> <?= labels('excel', 'Excel') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('csv','Category list','category_list')"> <?= labels('csv', 'CSV') ?></a>
                                        </div>
                                    </div>
                                </div>
                                <table class="table " data-fixed-columns="true" id="category_list" data-pagination-successively-size="2"
                                    data-detail-formatter="category_formater" data-query-params="category_query_params" data-auto-refresh="true"
                                    data-toggle="table" data-url="<?= base_url("admin/categories/list") ?>" data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true"
                                    data-show-refresh="false" data-sort-name="id" data-sort-order="desc">
                                    <thead>
                                        <tr>
                                            <th data-field="id" data-visible="true" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="category_image" class="text-center"><?= labels('image', 'Image') ?></th>
                                            <th data-field="parent_id" data-visible="false" class="text-center" data-sortable="true"><?= labels('parent_Id', 'Parent Id') ?></th>
                                            <th data-field="parent_category_name" class="text-center" data-visible="false"><?= labels('parent_category_name', 'Parent Category Name') ?></th>
                                            <th data-field="name" class="text-center"><?= labels('name', 'Name') ?></th>
                                            <th data-field="slug" class="text-center"><?= labels('slug', 'Slug') ?></th>

                                            <th data-field="dark_color_format" class="text-center" data-visible="false"><?= labels('dark_theme_color', 'Dark Color') ?></th>
                                            <th data-field="light_color_format" class="text-center" data-visible="false"><?= labels('light_theme_color', 'Light Color') ?></th>
                                            <th data-field="created_at" data-visible="false" class="text-center" data-sortable="true"><?= labels('created_at', 'Created At') ?></th>
                                            <th data-field="operations" class="text-center" data-events="Category_events"><?= labels('operations', 'Operations') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

    <div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header m-0 p-0">
                    <div class="row pl-3 w-100">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('update_category', 'Update Category') ?></div>
                        </div>
                        <div class="col d-flex justify-content-end  mt-4 border_bottom_for_cards">
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <?= form_open('admin/category/update_category', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_Category', 'enctype' => "multipart/form-data"]); ?>
                    <div class="row font-weight-bold">
                        <div class="col-md-12" style="font-size: 16px; color:rgba(0, 0, 0, 0.60); margin-bottom: 10px;">
                            <?= labels('category_details', 'Category Details') ?>
                            <hr>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                foreach ($languages as $index => $language) {
                                    if ($language['is_default'] == 1) {
                                        $current_modal_language = $language['code'];
                                    }
                                ?>
                                    <div class="language-modal-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-modal-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span class="language-modal-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-modal-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_make_parent"><?= labels('type', 'Type') ?></label><br>
                                <select name="edit_make_parent" id="edit_make_parent" class="form-control">
                                    <option value="0"><?= labels('category', 'Category') ?></option>
                                    <option value="1"><?= labels('sub_category', 'Sub Category') ?></option>
                                </select>
                            </div>
                        </div>

                        <?php
                        foreach ($languages as $index => $language) {
                        ?>
                            <div class="col-md-6" id="translationModalDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_modal_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="form-group">
                                    <label for="edit_name_modal<?= $language['code'] ?>"><?= labels('name', 'Name') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                    <input id="edit_name_modal<?= $language['code'] ?>" class="form-control" type="text" name="name[<?= $language['code'] ?>]" placeholder="Enter the name of the Category here" autocomplete="off">
                                </div>
                            </div>
                        <?php } ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category_slug" class="required"><?= labels('slug', 'Slug') ?></label>
                                <input id="edit_category_slug" class="form-control" type="text" name="category_slug" placeholder="<?= labels('enter_category_slug_here', 'Enter the slug of the Category here') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row" id="edit_parent">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category_ids"><?= labels('select_parent_category', 'Select Parent Category') ?></label><br>
                                <select name="edit_parent_id" id="edit_category_ids" class="form-control">
                                    <option value=""><?= labels('select_parent_category', 'Select Parent Category') ?></option>
                                    <?php foreach ($categories as $category) : ?>
                                        <option value="<?= $category['id'] ?>"><?= $category['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="id" id="id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="mb-3">
                                    <?= labels('image', "Image") ?>
                                    <input type="file" name="image" class="filepond" id="formFile" accept="image/*">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edit_dark_theme_color"><?= labels('dark_theme_color', 'Dark Theme Color') ?></label>
                                <input type="color" name="edit_dark_theme_color" id="edit_dark_theme_color" class="form-control" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="edit_light_theme_color"><?= labels('light_theme_color', 'Light Theme Color') ?></label>
                                <input type="color" name="edit_light_theme_color" id="edit_light_theme_color" class="form-control" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="edit_categoryImage" style="width: 200px; height: 150px; border: 1px solid ;border-color: #e4e6fc;border-radius: 0.35rem;margin-bottom:25px ">
                                <img src="" alt="old_image" style="display: block;margin-left: auto;margin-top: 25px;margin-right: auto;width: 80%;" width="50%" height="100px" id="category_image" id="update_service_image" onerror="this.onerror=null;this.src='<?= base_url('public/backend/assets/default.png') ?>';">
                            </div>
                        </div>
                    </div>
                    <div class="row font-weight-bold mt-3">
                        <div class="col-md-12" style="font-size: 16px; color:rgba(0, 0, 0, 0.60); margin-bottom: 10px;">
                            <?= labels('seo_settings', 'SEO Settings') ?>
                            <hr>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap align-items-center gap-4">
                                <?php
                                // Get default language for SEO section in modal
                                $default_language_seo_modal = '';
                                foreach ($languages as $lang) {
                                    if ($lang['is_default'] == 1) {
                                        $default_language_seo_modal = $lang['code'];
                                        break;
                                    }
                                }
                                foreach ($languages as $index => $language) {
                                ?>
                                    <div class="language-seo-modal-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                        id="language-seo-modal-<?= $language['code'] ?>"
                                        data-language="<?= $language['code'] ?>"
                                        style="cursor: pointer; padding: 0.5rem 0;">
                                        <span class="language-seo-modal-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                            <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                        </span>
                                        <div class="language-seo-modal-underline"
                                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- SEO content for each language in edit modal -->
                    <?php foreach ($languages as $index => $language) { ?>
                        <div id="translationSeoDiv-<?= $language['code'] ?>" <?= $language['code'] == $default_language_seo_modal ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_meta_title<?= $language['code'] ?>"><?= labels('meta_title', "Meta Title") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <i data-content="<?= labels('data_content_meta_title', 'Meta title should not exceed 60 characters for optimal SEO performance.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <input id="edit_meta_title<?= $language['code'] ?>" class="form-control" type="text" name="meta_title[<?= $language['code'] ?>]" placeholder="<?= labels('enter_title_here', 'Enter the title here') ?>" maxlength="255">
                                        <small class="form-text text-muted"><?= labels('max_255_characters', 'Maximum 255 characters') ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_meta_description<?= $language['code'] ?>"><?= labels('meta_description', 'Meta Description') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <i data-content="<?= labels('data_content_meta_description', 'Meta description should be between 150-160 characters for optimal SEO ranking.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <textarea id="edit_meta_description<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="meta_description[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('meta_description', 'Meta Description') ?> <?= labels('here', ' Here ') ?>" maxlength="500"></textarea>
                                        <small class="form-text text-muted"><?= labels('max_500_characters', 'Maximum 500 characters') ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_meta_keywords<?= $language['code'] ?>"><?= labels('meta_keywords', 'Meta Keywords') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <i data-content="<?= labels('data_content_meta_keywords', 'For optimal SEO performance, it is recommended to use up to 10 well-targeted keywords.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i>
                                        <input id="edit_meta_keywords<?= $language['code'] ?>" style="border-radius: 0.25rem" class="w-100" type="text" name="meta_keywords[<?= $language['code'] ?>][]" placeholder="<?= labels('press_enter_to_add_keyword', 'Press enter to add keyword') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_schema_markup<?= $language['code'] ?>"><?= labels('schema_markup', 'Schema Markup') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <i data-content='<?= labels("data_content_schema_markup", "Schema markup helps search engines understand your content. Generate markup using this") . " <a href=\"https://www.rankranger.com/schema-markup-generator\" target=\"_blank\">" . labels("tool", "tool") . "</a>" ?>'
                                            data-toggle="popover"
                                            class="fa fa-question-circle"
                                            data-original-title=""
                                            title=""></i>
                                        <textarea id="edit_schema_markup<?= $language['code'] ?>" style="min-height:60px" class="form-control" type="text" name="schema_markup[<?= $language['code'] ?>]" rowspan="10" placeholder="<?= labels('enter', 'Enter') ?> <?= labels('schema_markup', 'Schema Markup') ?> <?= labels('here', ' Here ') ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <!-- Meta Image (shared across all languages) -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="meta_image"><?= labels('meta_image', 'Meta Image') ?> </label>
                                <i data-content="<?= labels('data_content_meta_image', 'Upload a high-quality image (1200x630px recommended) for social media sharing.') ?>" class="fa fa-question-circle" data-original-title="" title="" data-toggle="popover"></i> <small>(<?= labels('seo_image_recommended_size', 'We recommend 1200 x 630 pixels') ?>)</small><br>
                                <input type="file" class="filepond" name="meta_image" id="edit_meta_image" accept="image/*">
                                <small class="form-text text-muted"><?= labels('upload_image_formats', 'Supported formats: JPEG, JPG, PNG, GIF') ?></small>
                                <div id="edit_categoryMetaImage" style="width: 200px; height: 150px; border: 1px solid ;border-color: #e4e6fc;border-radius: 0.35rem;margin-bottom:25px; display: none; position: relative;">
                                    <img src="" alt="old_image" style="display: block;margin-left: auto;margin-top: 25px;margin-right: auto;width: 80%;" width="50%" height="100px" id="edit_meta_image_preview">
                                    <button type="button" class="btn btn-sm btn-danger remove-category-seo-image"
                                        style="position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; padding: 0; border-radius: 50%; font-size: 10px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('update_category', 'Update Category') ?></button>
                    <?= form_close() ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', "Close") ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="filterBackdrop"></div>
<div class="drawer" id="filterDrawer">
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="bg-new-primary" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <div class="bg-white m-3 text-new-primary" style="box-shadow: 0px 8px 26px #00b9f02e; display: inline-block; padding: 10px; height: 45px; width: 45px; border-radius: 15px;">
                            <span class="material-symbols-outlined">
                                filter_alt
                            </span>
                        </div>
                        <h3 class="mb-0" style="display: inline-block; font-size: 16px; margin-left: 10px;"><?= labels('filters', "Filters") ?></h3>
                    </div>
                    <div id="cancelButton" style="cursor: pointer;">
                        <span class="material-symbols-outlined mr-2">
                            cancel
                        </span>
                    </div>
                </div>
                <div class="row mt-4 mx-2">
                    <div class="col-md-12">
                        <div class="form-group ">
                            <label for="table_filters"><?= labels('table_filters', 'Table filters') ?></label>
                            <div id="columnToggleContainer">
                            </div>
                            <button class="btn btn-primary d-block mt-3" id="apply_filter"><?= labels('apply', 'Apply') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Global variables for AJAX requests
    var base_url = '<?= base_url() ?>';
    var csrf_token_name = '<?= csrf_token() ?>';
    var csrf_token_value = '<?= csrf_hash() ?>';

    var picker1 = document.getElementById('dark_theme_color');
    var box1 = document.getElementById('categoryImage');
    picker1.addEventListener('change', function() {
        box1.style.backgroundColor = this.value;
    })
    var picker2 = document.getElementById('light_theme_color');
    var box2 = document.getElementById('categoryImage');
    picker2.addEventListener('change', function() {
        box2.style.backgroundColor = this.value;
    })
    var picker3 = document.getElementById('edit_light_theme_color');
    var box3 = document.getElementById('edit_categoryImage');
    picker3.addEventListener('change', function() {
        box3.style.backgroundColor = this.value;
    })
    var picker4 = document.getElementById('edit_dark_theme_color');
    var box4 = document.getElementById('edit_categoryImage');
    picker4.addEventListener('change', function() {
        box4.style.backgroundColor = this.value;
    })
</script>

<script>
    $(document).ready(function() {
        for_drawer("#filterButton", "#filterDrawer", "#filterBackdrop", "#cancelButton");
        var dynamicColumns = fetchColumns('category_list');
        setupColumnToggle('category_list', dynamicColumns, 'columnToggleContainer');

        // Initialize Tagify for meta keywords fields (all language-specific inputs)
        $(document).ready(function() {
            // Target all keyword inputs: meta_keywords[lang] and edit_meta_keywords[lang]
            var metaKeywordsInputs = document.querySelectorAll('input[id^="meta_keywords"], input[id^="edit_meta_keywords"]');
            if (metaKeywordsInputs != null && metaKeywordsInputs.length > 0) {
                metaKeywordsInputs.forEach(input => {
                    if (input && !input.tagify) {
                        new Tagify(input);
                    }
                });
            }
        });

        // SEO language tab switching for Add Category form
        let default_language_seo = '<?= isset($default_language_seo) ? $default_language_seo : $current_language ?>';
        $(document).on('click', '.language-seo-option', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-seo-underline').css('width', '0%');
            $('#language-seo-' + language).find('.language-seo-underline').css('width', '100%');

            // Update text styling
            $('.language-seo-text').removeClass('text-primary fw-medium');
            $('.language-seo-text').addClass('text-muted');
            $('#language-seo-' + language).find('.language-seo-text').removeClass('text-muted');
            $('#language-seo-' + language).find('.language-seo-text').addClass('text-primary fw-medium');

            // Show/hide translation divs
            if (language != default_language_seo) {
                $('#translationDivSeo-' + language).show();
                $('#translationDivSeo-' + default_language_seo).hide();
            }

            default_language_seo = language;
        });

        // SEO language tab switching for Edit Category modal
        let default_language_seo_modal = '<?= isset($default_language_seo_modal) ? $default_language_seo_modal : $current_modal_language ?>';
        $(document).on('click', '.language-seo-modal-option', function() {
            const language = $(this).data('language');

            // Update underline animation
            $('.language-seo-modal-underline').css('width', '0%');
            $('#language-seo-modal-' + language).find('.language-seo-modal-underline').css('width', '100%');

            // Update text styling
            $('.language-seo-modal-text').removeClass('text-primary fw-medium');
            $('.language-seo-modal-text').addClass('text-muted');
            $('#language-seo-modal-' + language).find('.language-seo-modal-text').removeClass('text-muted');
            $('#language-seo-modal-' + language).find('.language-seo-modal-text').addClass('text-primary fw-medium');

            // Show/hide translation divs
            if (language != default_language_seo_modal) {
                $('#translationSeoDiv-' + language).show();
                $('#translationSeoDiv-' + default_language_seo_modal).hide();
            }

            default_language_seo_modal = language;
        });

        // Image recommendation text switch (Category / Subcategory)
        const $typeSelect = $('#make_parent');
        const $imageHint = $('#image-recommendation');

        if ($typeSelect.length && $imageHint.length) {
            const imageHintText = {
                category: "<?= labels('category_image_recommended_size', 'We recommend 60x60 pixels') ?>",
                subcategory: "<?= labels('subcategory_image_recommended_size', 'We recommend 260 x 345 pixels') ?>"
            };

            const updateImageHint = () => {
                $imageHint.text(
                    $typeSelect.val() === '1' ?
                    `(${imageHintText.subcategory})` :
                    `(${imageHintText.category})`
                );
            };

            updateImageHint(); // initial state
            $typeSelect.on('change', updateImageHint);
        }
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

            // Hide other popovers
            if (currentPopover && currentPopover[0] !== $this[0]) {
                currentPopover.popover('hide');
            }

            currentPopover = $this;
            $this.popover('show');

        }).on('mouseleave', function() {
            isOverTrigger = false;
            startHideTimer();
        });

        // Handle popover content hover
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


    // Search button click handler - triggers table refresh when search button is clicked
    $("#customSearchBtn").on('click', function() {
        $('#category_list').bootstrapTable('refresh');
    });

    // Allow Enter key to trigger search button click
    $("#customSearch").on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#customSearchBtn').click();
        }
    });

    // Handle category SEO image removal
    $(document).on('click', '.remove-category-seo-image', function() {
        const button = $(this);
        const categoryId = $('#id').val(); // Get category ID from hidden input

        if (confirm('<?= labels('are_you_sure_to_remove_seo_image', 'Are you sure you want to remove this SEO image?') ?>')) {
            // Show loading state
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            // Make AJAX request to remove SEO image
            $.ajax({
                url: '<?= base_url('admin/categories/remove_seo_image') ?>',
                type: 'POST',
                data: {
                    category_id: categoryId,
                    <?= csrf_token() ?>: '<?= csrf_hash() ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error === false) {
                        // Hide the image container
                        $('#edit_categoryMetaImage').hide();
                        // Clear the image preview
                        $('#edit_meta_image_preview').attr('src', '');
                        // Show success message
                        alert(response.message || 'SEO image removed successfully');
                        // Reset button state (even on success, since the button will be hidden)
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    } else {
                        // Show error message
                        alert(response.message || 'Failed to remove SEO image');
                        // Reset button
                        button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    alert('An error occurred while removing the SEO image');
                    // Reset button
                    button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                }
            });
        }
    });

    // Handle file input change to reset button state when new image is uploaded
    $(document).on('change', '#edit_meta_image', function() {
        // Reset any existing remove button to original state
        $('.remove-category-seo-image').prop('disabled', false).html('<i class="fas fa-times"></i>');
    });
</script>

<script>
    // select default language
    $(document).ready(function() {
        let default_language = '<?= $current_language ?>';
        let current_modal_language = '<?= $current_modal_language ?>';

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

            default_language = language;
        });

        $(document).on('click', '.language-modal-option', function() {
            const language = $(this).data('language');

            $('.language-modal-underline').css('width', '0%');
            $('#language-modal-' + language).find('.language-modal-underline').css('width', '100%');

            $('.language-modal-text').removeClass('text-primary fw-medium');
            $('.language-text-faqs').addClass('text-muted');
            $('#language-modal-' + language).find('.language-modal-text').removeClass('text-muted');
            $('#language-modal-' + language).find('.language-modal-text').addClass('text-primary');

            if (language != current_modal_language) {
                $('#translationModalDiv-' + language).show();
                $('#translationModalDiv-' + current_modal_language).hide();
            }

            current_modal_language = language;
        });

        // Handle parent category dropdown visibility in modal
        $(document).on('change', '#edit_make_parent', function() {
            if ($(this).val() == "1") {
                $("#edit_parent").show();
            } else {
                $("#edit_parent").hide();
            }
        });

        // Clear modal data when modal is hidden
        $('#update_modal').on('hidden.bs.modal', function() {
            // Clear form fields
            $('#update_modal input[type="text"], #update_modal textarea').val('');
            $('#update_modal select').prop('selectedIndex', 0);

            // Clear all multilanguage Tagify keyword inputs
            var tagifyInputs = document.querySelectorAll('#update_modal input[id^="edit_meta_keywords"]');
            if (tagifyInputs && tagifyInputs.length > 0) {
                tagifyInputs.forEach(function(input) {
                    if (input && input.tagify) {
                        input.tagify.removeAllTags();
                    }
                });
            }

            // Hide images
            $('#edit_categoryMetaImage').hide();
            $('#category_image').attr('src', '');

            // Reset parent dropdown visibility and restore all options
            $('#edit_parent').hide();
            $('#edit_make_parent').val('0');
            // Restore all parent category options that might have been hidden
            $('#edit_category_ids option').show();
        });
    });
</script>