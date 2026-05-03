<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\TranslatedSubscriptionModel;
use DateTime;

class HomeScreenApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    private  $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1"
    ];
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_home_data()
    {
        try {
            $partner_id = $this->user_details['id'];

            //-------------------------------SUBSCRIPTION INFORMATION------------------------------//
            $subscription = fetch_details('partner_subscriptions', ['partner_id' => $partner_id], [], 1, 0, 'id', 'DESC');

            // Get current language from request header for translations
            $currentLanguage = get_current_language_from_request();

            // Get default language from database
            $defaultLanguage = 'en';
            $languages = fetch_details('languages', ['is_default' => 1], ['code']);
            if (!empty($languages)) {
                $defaultLanguage = $languages[0]['code'];
            }

            // Initialize subscription translation model
            $subscriptionTranslationModel = new TranslatedSubscriptionModel();

            // Get subscription translations if subscription exists
            $translation = null;
            $defaultTranslation = null;
            if (!empty($subscription[0]['subscription_id'])) {
                $subscriptionId = $subscription[0]['subscription_id'];

                // Get translations for requested language and default language
                $translation = $subscriptionTranslationModel->getTranslation($subscriptionId, $currentLanguage);
                if (!$translation && $currentLanguage !== $defaultLanguage) {
                    $translation = $subscriptionTranslationModel->getTranslation($subscriptionId, $defaultLanguage);
                }
                $defaultTranslation = $subscriptionTranslationModel->getTranslation($subscriptionId, $defaultLanguage);
            }

            $subscriptionInformation = [
                'subscription_id' => $subscription[0]['subscription_id'] ?? "",
                'isSubscriptionActive' => $subscription[0]['status'] ?? "deactive",
                'created_at' => $subscription[0]['created_at'] ?? "",
                'updated_at' => $subscription[0]['updated_at'] ?? "",
                'is_payment' => $subscription[0]['is_payment'] ?? "",
                'id' => $subscription[0]['id'] ?? "",
                'partner_id' => $subscription[0]['partner_id'] ?? "",
                'purchase_date' => $subscription[0]['purchase_date'] ?? "",
                'expiry_date' => $subscription[0]['expiry_date'] ?? "",
                'name' => $subscription[0]['name'] ?? "",
                'description' => $subscription[0]['description'] ?? "",
                'duration' => $subscription[0]['duration'] ?? "",
                'price' => $subscription[0]['price'] ?? "",
                'discount_price' => $subscription[0]['discount_price'] ?? "",
                'order_type' => $subscription[0]['order_type'] ?? "",
                'max_order_limit' => $subscription[0]['max_order_limit'] ?? "",
                'is_commision' => $subscription[0]['is_commision'] ?? "",
                'commission_threshold' => $subscription[0]['commission_threshold'] ?? "",
                'commission_percentage' => $subscription[0]['commission_percentage'] ?? "",
                'publish' => $subscription[0]['publish'] ?? "",
                'tax_id' => $subscription[0]['tax_id'] ?? "",
                'tax_type' => $subscription[0]['tax_type'] ?? ""
            ];

            // Apply translation logic to name and description fields
            if (!empty($subscription[0]['subscription_id'])) {
                // Set main fields: use default language translations or fallback to main table
                $subscriptionInformation['name'] = $defaultTranslation['name'] ?? $subscriptionInformation['name'];
                $subscriptionInformation['description'] = $defaultTranslation['description'] ?? $subscriptionInformation['description'];

                // Set translated fields: use requested language, fallback to default language, then main table
                $subscriptionInformation['translated_name'] = $translation['name'] ?? $defaultTranslation['name'] ?? $subscriptionInformation['name'];
                $subscriptionInformation['translated_description'] = $translation['description'] ?? $defaultTranslation['description'] ?? $subscriptionInformation['description'];
            } else {
                // No subscription found, set translated fields to empty
                $subscriptionInformation['translated_name'] = "";
                $subscriptionInformation['translated_description'] = "";
            }

            if (!empty($subscription)) {
                $isCommissionBasedSubscription = ($subscription[0]['is_commision'] == "yes") ? 1 : 0;
            }

            if (!empty($subscription[0])) {
                $price = calculate_partner_subscription_price($subscription[0]['partner_id'], $subscription[0]['subscription_id'], $subscription[0]['id']);
            }
            $subscriptionInformation['tax_value'] = $price[0]['tax_value'] ?? "";
            $subscriptionInformation['price_with_tax'] = $price[0]['price_with_tax'] ?? "";
            $subscriptionInformation['original_price_with_tax'] = $price[0]['original_price_with_tax'] ?? "";
            $subscriptionInformation['tax_percentage'] = $price[0]['tax_percentage'] ?? "";

            if ($subscriptionInformation['isSubscriptionActive'] !== 'active') {
                $data['subscription_information'] = null;
            } else {
                $data['subscription_information'] = $subscriptionInformation;
            }


            //-------------------------------BOOKING INFORMATION------------------------------//
            $currentDate = (new DateTime())->format('Y-m-d');
            $tomorrowDate = (new DateTime('tomorrow'))->format('Y-m-d');

            $todayBookings = fetch_details('orders', [
                'partner_id' => $partner_id
            ]);


            $todayBooking = array_filter($todayBookings, function ($order) use ($currentDate) {
                return date('Y-m-d', strtotime($order['date_of_service'])) === $currentDate;
            });

            $tomorrowBookings = array_filter($todayBookings, function ($order) use ($tomorrowDate) {
                return date('Y-m-d', strtotime($order['date_of_service'])) === $tomorrowDate;
            });

            $upcomingBooking = fetch_details('orders', [
                'partner_id' => $partner_id,
                'created_at >=' => $currentDate
            ]);


            $bookings['today_bookings'] = count($todayBooking);
            $bookings['tommorrow_bookings'] = count($tomorrowBookings);
            $bookings['upcoming_bookings'] = count($upcomingBooking);

            $data['bookings'] = $bookings;

            //--------------------------------EARNING REPORT SECTION -------------------------------//

            $adminCommission = fetch_details('users', ['id' => $partner_id], ['admin_commission']);
            $data['earning_report']['admin_commission'] = $adminCommission[0]['admin_commission'];


            $total_balance = strval(unsettled_commision($partner_id));

            $data['earning_report']['my_income'] = $total_balance;

            $remainingIncome = fetch_details('users', ['id' => $partner_id], ['balance']);
            $data['earning_report']['remaining_income'] = $remainingIncome[0]['balance'];


            $amount = fetch_details('orders', ['partner_id' => $partner_id, 'is_commission_settled' => '0', 'status' => 'awaiting'], ['sum(final_total) as total']);
            if (isset($amount) && !empty($amount)) {
                $admin_commission_percentage = get_admin_commision($partner_id);
                $admin_commission_amount = intval($admin_commission_percentage) / 100;
                $total = $amount[0]['total'];
                $commision = intval($total) * $admin_commission_amount;
                $unsettled_amount = $total - $commision;
            } else {
                $unsettled_amount = 0.0;
            }
            // $unsettled_amount = $unsettled_amount;


            $data['earning_report']['future_earning_from_bookings'] = (float)$unsettled_amount;

            //-------------------------CUSTOM JOB SECTION ------------------------------------------//
            $db = \Config\Database::connect();

            // Fixed: Changed $this->userId to $partner_id to prevent "Cannot access offset of type string on string" error
            $custom_job_categories = fetch_details('partner_details', ['partner_id' => $partner_id], ['custom_job_categories', 'is_accepting_custom_jobs']);
            $partner_categoried_preference = !empty($custom_job_categories) &&
                isset($custom_job_categories[0]['custom_job_categories']) &&
                !empty($custom_job_categories[0]['custom_job_categories']) ?
                json_decode($custom_job_categories[0]['custom_job_categories']) : [];


            $builder = $db->table('custom_job_requests cj')
                ->select('cj.*, u.username, u.image, c.id as category_id, c.name as category_name, c.image as category_image')
                ->join('users u', 'u.id = cj.user_id')
                ->join('categories c', 'c.id = cj.category_id')
                ->where('cj.status', 'pending')
                ->where("(SELECT COUNT(1) FROM partner_bids pb WHERE pb.custom_job_request_id = cj.id AND pb.partner_id = $partner_id) = 0");
            if (!empty($partner_categoried_preference)) {
                $builder->whereIn('cj.category_id', $partner_categoried_preference);
            }
            $builder->orderBy('cj.id', 'DESC');
            $custom_job_requests = $builder->get()->getResultArray();
            $filteredJobs = [];
            foreach ($custom_job_requests as $row) {
                $did_partner_bid = fetch_details('partner_bids', [
                    'custom_job_request_id' => $row['id'],
                    'partner_id' => $partner_id,
                ]);
                if (empty($did_partner_bid)) {
                    $check = fetch_details('custom_job_provider', [
                        'partner_id' => $partner_id,
                        'custom_job_request_id' => $row['id'],
                    ]);
                    if (!empty($check)) {
                        $filteredJobs[] = $row;
                    }
                }
            }
            if (!empty($filteredJobs)) {
                foreach ($filteredJobs as &$job) {
                    if (!empty($job['image'])) {
                        $job['image'] = base_url('public/backend/assets/profiles/' . $job['image']);
                    } else {
                        $job['image'] = base_url('public/backend/assets/profiles/default.png');
                    }
                }
            }
            $data['custom_jobs']['total_open_jobs'] = count($filteredJobs);
            $filteredJobs = array_slice($filteredJobs, 0, 2);

            $data['custom_jobs']['open_jobs'] = $filteredJobs;

            //---------------------------SALES REPORT (CHARTS) --------------------------------//
            $last_monthly_sales = (isset($_POST['last_monthly_sales']) && !empty(trim($_POST['last_monthly_sales']))) ? $this->request->getPost("last_monthly_sales") : 12;


            $monthly_sales = $db->table('orders')
                ->select('YEAR(date_of_service) as year, MONTHNAME(date_of_service) as month, SUM(final_total) as total_amount')
                ->where('date_of_service >=', "DATE_SUB(CURDATE(), INTERVAL $last_monthly_sales MONTH)", false) // No binding needed
                ->where('date_of_service <=', date("Y-m-d"))
                ->where([
                    'partner_id' => $partner_id,
                    "status" => "completed"
                ])
                ->groupBy("YEAR(date_of_service), MONTH(date_of_service)")
                ->orderBy("YEAR(date_of_service), MONTH(date_of_service)")
                ->get()->getResultArray();

            foreach ($monthly_sales as &$sale) {
                $sale['month'] = labels(strtolower($sale['month']), $sale['month']);
            }




            $yearly_sales = $db->table('orders')
                ->select('YEAR(date_of_service) as year, SUM(final_total) as total_amount')
                ->where('date_of_service BETWEEN CURDATE() - INTERVAL 1 YEAR AND CURDATE()')
                ->where(['partner_id' => $partner_id, 'date_of_service < ' => date("Y-m-d H:i:s"), "status" => "completed"])
                ->groupBy("YEAR(date_of_service)")
                ->get()->getResultArray();

            $weekly_sales = $db->table('orders')
                ->select('WEEK(date_of_service) as week, SUM(final_total) as total_amount')
                ->where('date_of_service BETWEEN CURDATE() - INTERVAL 1 WEEK AND CURDATE()')
                ->where(['partner_id' => $partner_id, 'date_of_service < ' => date("Y-m-d H:i:s"), "status" => "completed"])
                ->groupBy("WEEK(date_of_service)")
                ->get()->getResultArray();

            $sales_data = [
                'monthly_sales' => $monthly_sales,
                'yearly_sales'  => $yearly_sales,
                'weekly_sales'  => $weekly_sales
            ];

            $data['sales_data'] = $sales_data;

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'data fetched successfully'),
                'data'  => $data ?? [],
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_POST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_home_data()'
            );
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }
}
