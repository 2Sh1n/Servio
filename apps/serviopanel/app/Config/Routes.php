<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();
// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (file_exists(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}
/**
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(true);
$routes->set404Override(
    function () {
        $data['title'] = "Page not found";
        $data['main_page'] = "error404";
        $data['meta_keywords'] = "On Demand, Services,On Demand Services, Service Provider";
        $data['meta_description'] = "";
        return view('/frontend/retro/template', $data);
    }
);
$routes->setAutoRoute(true);
if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
// Auth routes - these should be accessible without authentication
$routes->get('/admin/login', 'Auth::login');
$routes->get('/auth/login', 'Auth::login');
$routes->get('/auth/logout', 'Auth::logout');
$routes->add('unauthorised', 'Home::unauthorised');
/**
 *      for migrations
 */
$routes->add('migration/index', 'Migrate::index');
$routes->add('migration/createmigrations', 'Migrate::createmigrations');

// Quick migration route - equivalent to Laravel's Artisan::call('migrate')
// Protected: Only admins can access this route
$routes->get('/migrate', static function () {
    $migrate = \Config\Services::migrations();

    try {
        // Get all migrations that haven't run yet
        $history = $migrate->getHistory();

        $ranVersions = array_column($history, 'version');

        // CodeIgniter 4 migration runner doesn't expose `findCliMigrations()`.
        // We build the pending list from `findMigrations()` and filter out
        // versions already present in the migration history.
        $pending = [];
        foreach ($migrate->findMigrations() as $migration) {
            if (! in_array($migration->version, $ranVersions, true)) {
                $pending[$migration->version] = $migration; // keyed by version for the loop below
            }
        }

        $results  = [];
        $hadError = false;

        foreach ($pending as $version => $migration) {
            // Skip already ran migrations
            if (in_array($version, $ranVersions, true)) {
                $results[] = [
                    'class'   => $migration->name,
                    'status'  => 'skipped',
                    'message' => 'Already ran',
                ];
                continue;
            }

            try {
                $migrate->force($migration->path, $migration->namespace);
                $results[] = [
                    'class'   => $migration->name,
                    'status'  => 'success',
                    'message' => 'Ran successfully',
                ];
            } catch (\Throwable $e) {
                $hadError  = true;
                // Log full details so we can debug failures after the request ends.
                log_message(
                    'error',
                    '[MIGRATE] Migration failed: ' . ($migration->name ?? 'unknown') .
                    ' | version: ' . ($migration->version ?? 'unknown') .
                    ' | error: ' . $e->getMessage() .
                    ' | file: ' . $e->getFile() . ':' . $e->getLine() .
                    ' | trace: ' . $e->getTraceAsString()
                );
                $results[] = [
                    'class'   => $migration->name,
                    'status'  => 'failed',
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile() . ':' . $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ];

                // Stop on first failure so we don't cascade errors
                break;
            }
        }

        // Build a readable summary
        $summary = '';
        foreach ($results as $r) {
            $icon     = match($r['status']) {
                'success' => '✅',
                'skipped' => '⏭️',
                'failed'  => '❌',
                default   => '❓',
            };
            $summary .= "{$icon} [{$r['status']}] {$r['class']}: {$r['message']}";
            if ($r['status'] === 'failed') {
                $summary .= " | File: {$r['file']}";
            }
            $summary .= "\n";
        }

        if ($hadError) {
            return redirect()->back()->with('error', nl2br($summary));
        }

        return redirect()->back()->with('success', nl2br($summary ?: 'All migrations already up to date.'));

    } catch (\Throwable $e) {
        // Log full details for unexpected runner-level crashes too.
        log_message(
            'error',
            '[MIGRATE] Migration runner crashed: ' .
            $e->getMessage() .
            ' | file: ' . $e->getFile() . ':' . $e->getLine() .
            ' | trace: ' . $e->getTraceAsString()
        );
        return redirect()->back()->with('error',
            '💥 Migration runner crashed: ' . $e->getMessage() .
            ' | File: ' . $e->getFile() . ':' . $e->getLine()
        );
    }
});

// Quick rollback route - equivalent to Laravel's Artisan::call('migrate:rollback')
// Protected: Only admins can access this route
$routes->get('/rollback', static function () {
    // Check if user is logged in and is an admin
    // $ionAuth = new \App\Libraries\CustomIonAuth();

    // if (!$ionAuth->loggedIn() || !$ionAuth->isAdmin()) {
    //     return redirect()->back()->with('error', 'Unauthorized: Only administrators can rollback migrations.');
    // }

    // User is admin, proceed with rollback
    $migrate = \Config\Services::migrations();
    try {
        $lastBatch = $migrate->getLastBatch();
        if ($lastBatch > 0) {
            $migrate->regress($lastBatch - 1);
            return redirect()->back()->with('success', 'Last migration batch (Batch ' . $lastBatch . ') rolled back successfully!');
        }
        return redirect()->back()->with('error', 'No migrations found to rollback.');
    } catch (\Throwable $e) {
        return redirect()->back()->with('error', 'Rollback failed: ' . $e->getMessage());
    }
});
/*
 ======================================
 Customer Route Files
 ======================================
 */
include_once('Routes_admin.php'); //panel admin routes
include_once('Routes_partner.php'); //partner panel routes

include_once('Routes_customer_apis.php'); //customer api routes
//partner api routes
$routes->post('partner/api/v1', 'Apis\Provider\V1::index');
$routes->group('partner/api/v1', ['filter' => 'language'], function ($routes) {

    /** Auth API Routes - START */
    $routes->post('register', 'Apis\Provider\AuthApiController::register');
    $routes->post('verify_user', 'Apis\Provider\AuthApiController::verify_user');
    $routes->post('resend_otp', 'Apis\Provider\AuthApiController::resend_otp');
    $routes->post('verify_otp', 'Apis\Provider\AuthApiController::verify_otp');
    $routes->post('forgot-password', 'Apis\Provider\AuthApiController::forgot_password');
    $routes->post('change-password', 'Apis\Provider\AuthApiController::change_password');
    $routes->post('login', 'Apis\Provider\AuthApiController::login');
    $routes->post('update_fcm', 'Apis\Provider\AuthApiController::update_fcm');
    $routes->post('delete_provider_account', 'Apis\Provider\AuthApiController::delete_provider_account');
    $routes->post('get_user_info', 'Apis\Provider\AuthApiController::get_user_info');
    $routes->post('logout', 'Apis\Provider\AuthApiController::logout');
    $routes->post('get_statistics', 'Apis\Provider\AuthApiController::get_statistics');
    /** Auth API Routes - END */

    /** NOTIFICATION API ROUTES - START */
    $routes->post('get_notifications', 'Apis\Provider\NotificationApiController::get_notifications');
    /** NOTIFICATION API ROUTES - END */

    /** CATEGORY API ROUTES - START */
    $routes->post('get_categories', 'Apis\Provider\CategoryApiController::get_categories');
    $routes->post('get_sub_categories', 'Apis\Provider\CategoryApiController::get_sub_categories');
    $routes->post('get_all_categories', 'Apis\Provider\CategoryApiController::get_all_categories');
    $routes->post('get_categories_hierarchical', 'Apis\Provider\CategoryApiController::get_categories_hierarchical');
    /** CATEGORY API ROUTES - END */

    /** SETTINGS API ROUTES - START */
    $routes->post('get_settings', 'Apis\Provider\SettingsApiController::get_settings');
    /** SETTINGS API ROUTES - END */

    /** PLACES API ROUTES - START */
    $routes->get('get_places_for_app', 'Apis\PlacesApiController::get_places');
    $routes->get('get_place_details_for_app', 'Apis\PlacesApiController::get_place_details');
    /** PLACES API ROUTES - END */

    /** COUNTRY CODES API ROUTES - START */
    $routes->get('get_country_codes', 'Apis\CountryCodesApiController::get_country_codes');
    /** COUNTRY CODES API ROUTES - END */

    /** LANGUAGE API ROUTES - START */
    $routes->get('get_language_list', 'Apis\LanguageApiController::get_language_list');
    $routes->post('get_language_json_data', 'Apis\LanguageApiController::get_language_json_data');
    /** LANGUAGE API ROUTES - END */

    /** PAGE SETTINGS API ROUTES - START */
    $routes->post('get_page_setting', 'Apis\PageSettingsApiController::get_page_setting');
    $routes->post('get_custom_pages', 'Apis\PageSettingsApiController::get_custom_pages');
    /** PAGE SETTINGS API ROUTES - END */

    /** WITHDRAWAL REQUEST API ROUTES - START */
    $routes->post('send_withdrawal_request', 'Apis\Provider\WithdrawRequestsApiController::send_withdrawal_request');
    $routes->post('get_withdrawal_request', 'Apis\Provider\WithdrawRequestsApiController::get_withdrawal_request');
    $routes->post('delete_withdrawal_request', 'Apis\Provider\WithdrawRequestsApiController::delete_withdrawal_request');
    /** WITHDRAWAL REQUEST API ROUTES - END */

    /** TAX API ROUTES - START */
    $routes->post('get_taxes', 'Apis\Provider\TaxApiController::get_taxes');
    /** TAX API ROUTES - END */

    /** CONTACT US API ROUTES - START */
    $routes->post('contact_us_api', 'Apis\Provider\ContactUsApiController::contact_us_api');
    /** CONTACT US API ROUTES - END */  

    /** HOME SCREEN API ROUTES - START */
    $routes->get('get_home_data', 'Apis\Provider\HomeScreenApiController::get_home_data');
    /** HOME SCREEN API ROUTES - END */
    
    /** CHAT API ROUTES - START */
    $routes->post('send_chat_message', 'Apis\Provider\ChatApiController::send_chat_message');
    $routes->post('get_chat_history', 'Apis\Provider\ChatApiController::get_chat_history');
    $routes->post('mark_message_as_read', 'Apis\Provider\ChatApiController::mark_message_as_read');
    $routes->post('get_chat_customers_list', 'Apis\Provider\ChatApiController::get_chat_customers_list');
    $routes->post('delete_chat_user', 'Apis\Provider\ChatApiController::delete_chat_user');
    /** CHAT API ROUTES - END */

    /** CHAT QUESTIONS API ROUTES - START */
    $routes->post('get_chat_questions', 'Apis\ChatQuestionsApiController::get_chat_questions');
    /** CHAT QUESTIONS API ROUTES - END */

    /** REPORT AND BLOCK API ROUTES - START */
    $routes->get('get_report_reasons', 'Apis\Provider\ReportAndBlockApiController::get_report_reasons');
    $routes->post('block_user', 'Apis\Provider\ReportAndBlockApiController::block_user');
    $routes->post('unblock_user', 'Apis\Provider\ReportAndBlockApiController::unblock_user');
    $routes->get('get_blocked_users', 'Apis\Provider\ReportAndBlockApiController::get_blocked_users');
    /** REPORT AND BLOCK API ROUTES - END */

    /** RATINGS API ROUTES - START */
    $routes->post('get_service_ratings', 'Apis\Provider\RatingsApiController::get_service_ratings');
    /** RATINGS API ROUTES - END */
    
    /** CUSTOM JOB REQUESTS API ROUTES - START */
    $routes->post('apply_for_custom_job', 'Apis\Provider\CustomJobRequestsApiController::apply_for_custom_job');
    $routes->post('get_custom_job_requests', 'Apis\Provider\CustomJobRequestsApiController::get_custom_job_requests');
    $routes->post('manage_category_preference', 'Apis\Provider\CustomJobRequestsApiController::manage_category_preference');
    $routes->post('manage_custom_job_request_setting', 'Apis\Provider\CustomJobRequestsApiController::manage_custom_job_request_setting');
    /** CUSTOM JOB REQUESTS API ROUTES - END */

    /** PROMO CODES API ROUTES - START */
    $routes->post('get_promocodes', 'Apis\Provider\PromocodesApiController::get_promocodes');
    $routes->post('manage_promocode', 'Apis\Provider\PromocodesApiController::manage_promocode');
    $routes->post('delete_promocode', 'Apis\Provider\PromocodesApiController::delete_promocode');
    /** PROMO CODES API ROUTES - END */

    /** CUSTOM FIELDS API ROUTES - START */
    $routes->post('get_custom_fields', 'Apis\Provider\CustomFieldsApiController::get_custom_fields');
    /** CUSTOM FIELDS API ROUTES - END */

    /** ORDERS API ROUTES - START */
    $routes->post('get_orders', 'Apis\Provider\OrdersApiController::get_orders');
    $routes->post('delete_orders', 'Apis\Provider\OrdersApiController::delete_orders');
    $routes->post('update_order_status', 'Apis\Provider\OrdersApiController::update_order_status');
    $routes->post('verify_booking_otp', 'Apis\Provider\OrdersApiController::verify_booking_otp');
    $routes->post('download-invoice', 'Apis\Provider\OrdersApiController::invoice_download');
    $routes->post('get_available_slots', 'Apis\Provider\OrdersApiController::get_available_slots');
    /** ORDERS API ROUTES - END */
    
    /** SERVICES API ROUTES - START */
    $routes->post('get_services', 'Apis\Provider\ServicesApiController::get_services');
    $routes->post('manage_service', 'Apis\Provider\ServicesApiController::manage_service');
    $routes->post('delete_service', 'Apis\Provider\ServicesApiController::delete_service');
    /** SERVICES API ROUTES - END */

    /** SETTLEMENTS API ROUTES - START */
    $routes->post('get_cash_collection', 'Apis\Provider\SettlementsApiController::get_cash_collection');
    $routes->post('get_settlement_history', 'Apis\Provider\SettlementsApiController::get_settlement_history');
    $routes->post('get_booking_settle_manegement_history', 'Apis\Provider\SettlementsApiController::get_booking_settle_manegement_history');
    /** SETTLEMENTS API ROUTES - END */

    /** SUBSCRIPTION API ROUTES - START */
    $routes->post('get_subscription', 'Apis\Provider\SubscriptionApiController::get_subscription');
    $routes->post('buy_subscription', 'Apis\Provider\SubscriptionApiController::buy_subscription');
    $routes->post('add_transaction', 'Apis\Provider\SubscriptionApiController::add_transaction');
    $routes->post('razorpay_create_order', 'Apis\Provider\SubscriptionApiController::razorpay_create_order');
    $routes->post('cashfree_create_order', 'Apis\Provider\SubscriptionApiController::cashfree_create_order');
    $routes->post('get_cashfree_order_status', 'Apis\Provider\SubscriptionApiController::get_cashfree_order_status');
    $routes->post('create_stripe_payment_intent', 'Apis\Provider\SubscriptionApiController::create_stripe_payment_intent');
    $routes->post('get_stripe_payment_status', 'Apis\Provider\SubscriptionApiController::get_stripe_payment_status');
    $routes->post('get_subscription_history', 'Apis\Provider\SubscriptionApiController::get_subscription_history');
    $routes->get('paypal_transaction_webview', 'Apis\Provider\SubscriptionApiController::paypal_transaction_webview');
    $routes->post('app_payment_status', 'Apis\Provider\SubscriptionApiController::verify_transaction');
    $routes->get('paystack_transaction_webview', 'Apis\Provider\SubscriptionApiController::paystack_transaction_webview');
    $routes->get('app_paystack_payment_status', 'Apis\Provider\SubscriptionApiController::app_paystack_payment_status');
    $routes->get('flutterwave_webview', 'Apis\Provider\SubscriptionApiController::flutterwave_webview');
    $routes->get('flutterwave_payment_status', 'Apis\Provider\SubscriptionApiController::flutterwave_payment_status');
    /** SUBSCRIPTION API ROUTES - END */
});


/** WEBHOOK ROUTES - START */
$routes->add('/api/webhooks/stripe', 'Webhooks\StripeWebhook::index');
$routes->add('/api/webhooks/paystack', 'Webhooks\PaystackWebhook::index');
$routes->add('/api/webhooks/razorpay', 'Webhooks\RazorpayWebhook::index');
$routes->add('/api/webhooks/paypal', 'Webhooks\PaypalWebhook::index');
$routes->add('/api/webhooks/flutterwave', 'Webhooks\FlutterwaveWebhook::index');
$routes->add('/api/webhooks/xendit', 'Webhooks\XenditWebhook::index');
$routes->add('/api/webhooks/cashfree', 'Webhooks\CashfreeWebhook::index');
/** Webhook routes - END */

// Firebase service worker route - serves service worker dynamically from database
// This allows Firebase configuration to be managed through admin panel
$routes->get('firebase-messaging-sw.js', 'Frontend::firebaseServiceWorker');


//other panel routes 

$routes->add('admin/reason_for_report_and_block_chat', 'admin\ReasonsForReportAndBlockChat::index');
$routes->add('admin/reason_for_report_and_block_chat/list', 'admin\ReasonsForReportAndBlockChat::list');
$routes->add('admin/reason_for_report_and_block_chat/add', 'admin\ReasonsForReportAndBlockChat::add');
$routes->add('admin/reason_for_report_and_block_chat/edit', 'admin\ReasonsForReportAndBlockChat::edit');
$routes->add('admin/reason_for_report_and_block_chat/get_reason_data', 'admin\ReasonsForReportAndBlockChat::get_reason_data');
$routes->add('admin/remove-rejection-reasons', 'admin\ReasonsForReportAndBlockChat::remove');

// User Reports Routes
$routes->add('admin/user_reports', 'admin\UserReports::index');
$routes->add('admin/user_reports/list', 'admin\UserReports::list');
$routes->add('admin/user_reports/view/(:num)', 'admin\UserReports::view/$1');

$routes->get('partner/reported_users', 'partner\ReportedUsers::index');
$routes->get('partner/reported_users/list', 'partner\ReportedUsers::list');
$routes->get('partner/reported_users/view/(:num)', 'partner\ReportedUsers::view/$1');
// Report Routes 