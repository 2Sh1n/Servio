// import 'package:edemand_partner/utils/errorFilter.dart';

import '../../app/generalImports.dart';

class ApiUrl {
  //Api list
  static String loginApi = '${baseUrl}login';
  static String getBookings = '${baseUrl}get_orders';
  static const String getHomeData = "${baseUrl}get_home_data";

  static String getCustomRequestJob = '${baseUrl}get_custom_job_requests';
  static String getServices = '${baseUrl}get_services';
  static String getCategoryHierarchical =
      "${baseUrl}get_categories_hierarchical";
  static String getCategories = '${baseUrl}get_categories';
  static String getPromocodes = '${baseUrl}get_promocodes';
  static String managePromocode = '${baseUrl}manage_promocode';
  static String updateBookingStatus = '${baseUrl}update_order_status';
  static String verifyBookingOTP = '${baseUrl}verify_booking_otp';
  static String downloadInvoice = '${baseUrl}download-invoice';
  static String manageService = '${baseUrl}manage_service';
  static String deleteService = '${baseUrl}delete_service';
  static String getServiceRatings = '${baseUrl}get_service_ratings';
  static String deletePromocode = '${baseUrl}delete_promocode';
  static String getAvailableSlots = '${baseUrl}get_available_slots';
  static String getSettings = '${baseUrl}get_settings';
  static String getWithdrawalRequest = '${baseUrl}get_withdrawal_request';
  static String sendWithdrawalRequest = '${baseUrl}send_withdrawal_request';
  static String getNotifications = '${baseUrl}get_notifications';
  static String updateFcm = '${baseUrl}update_fcm';
  static String deleteUserAccount = '${baseUrl}delete_provider_account';
  static String verifyUser = '${baseUrl}verify_user';
  static String registerProvider = '${baseUrl}register';
  static String changePassword = '${baseUrl}change-password';
  static String createNewPassword = '${baseUrl}forgot-password';
  static String getTaxes = '${baseUrl}get_taxes';
  static String getCashCollection = '${baseUrl}get_cash_collection';
  static String getSettlementHistory = '${baseUrl}get_settlement_history';
  static String createRazorpayOrder = "${baseUrl}razorpay_create_order";
  static String getSubscriptionsList = "${baseUrl}get_subscription";
  static String addSubscriptionTransaction = "${baseUrl}add_transaction";
  static String getPreviousSubscriptionsHistory =
      "${baseUrl}get_subscription_history";
  static String getBookingSettleManagementHistory =
      "${baseUrl}get_booking_settle_manegement_history";
  static String sendQuery = "${baseUrl}contact_us_api";
  static String getUserInfo = "${baseUrl}get_user_info";
  static String resendOTP = "${baseUrl}resend_otp";
  static String verifyOTP = "${baseUrl}verify_otp";
  static const String manageCustomJobRequest =
      "${baseUrl}manage_custom_job_request_setting";
  static const String applyForCustomJob = "${baseUrl}apply_for_custom_job";
  static const String manageCategoryPreference =
      "${baseUrl}manage_category_preference";
  static String manageNotification = "${baseUrl}manage_notification";
  static String logout = "${baseUrl}logout";

  //chat related APIs
  static const String sendChatMessage = "${baseUrl}send_chat_message";
  static const String getChatMessages = "${baseUrl}get_chat_history";
  static const String getChatUsers = "${baseUrl}get_chat_customers_list";
  static const String blockUser = "${baseUrl}block_user";
  static const String getReportReasons = "${baseUrl}get_report_reasons";
  static String unblockUser = '${baseUrl}unblock_user';
  static String deleteChat = '${baseUrl}delete_chat_user';
  static String getBlockedUsers = '${baseUrl}get_blocked_users';

  ////////* Place API */////

  static String placeApiKey = 'key';
  static String placeAPI = '${baseUrl}get_places_for_app';

  static const String input = 'input';
  static const String types = 'types';
  static const String placeid = 'placeid';

  static String placeApiDetails = '${baseUrl}get_place_details_for_app';

  static String getCountryCodes = "${baseUrl}get_country_codes";

  //languages
  static String getLanguageList = "${baseUrl}get_language_list";
  static String getLanguageJsonData = "${baseUrl}get_language_json_data";

  //Stripe payment APIs (backend proxy)
  static String createStripePaymentIntent =
      "${baseUrl}create_stripe_payment_intent";
  static String getStripePaymentStatus = "${baseUrl}get_stripe_payment_status";

  ///post method for API calling
}
