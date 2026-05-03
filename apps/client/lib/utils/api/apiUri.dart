import "../../app/generalImports.dart";

class ApiUrl {
  //headers

  ///API list
  static String getServices = "${baseUrl}get_services";
  static String getSubCategories = "${baseUrl}get_sub_categories";
  static String getReview = "${baseUrl}get_ratings";
  static String getCategories = "${baseUrl}get_categories";
  static String getNotifications = "${baseUrl}get_notifications";
  static String getSections = "${baseUrl}get_sections";
  static String getProviders = "${baseUrl}get_providers";
  static String getHomeScreenData = "${baseUrl}get_home_screen_data";
  static String getCart = "${baseUrl}get_cart";
  static String getAddress = "${baseUrl}get_address";
  static String addAddress = "${baseUrl}add_address";
  static String deleteAddress = "${baseUrl}delete_address";
  static String bookMark = "${baseUrl}book_mark";
  static String updateUser = "${baseUrl}update_user";

  // static String updateFCM = "${baseUrl}update_fcm";
  static String manageCart = "${baseUrl}manage_cart";
  static String manageNotification = "${baseUrl}manage_notification";
  static String manageUser = "${baseUrl}manage_user";
  static String getUserDetails = "${baseUrl}get_user_info";
  static String removeFromCart = "${baseUrl}remove_from_cart";
  static String getAvailableSlots = "${baseUrl}get_available_slots";
  static String placeOrder = "${baseUrl}place_order";
  static String getPromocode = "${baseUrl}get_promo_codes";
  static String validatePromocode = "${baseUrl}validate_promo_code";
  static String getSystemSettings = "${baseUrl}get_settings";
  static String getFAQs = "${baseUrl}get_faqs";
  static String createRazorpayOrder = "${baseUrl}razorpay_create_order";
  static String getOrderBooking = "${baseUrl}get_orders";
  static String addTransaction = "${baseUrl}add_transaction";
  static String addRating = "${baseUrl}add_rating";
  static String verifyUser = "${baseUrl}verify_user";
  static String verifyOTP = "${baseUrl}verify_otp";
  static String changeBookingStatus = "${baseUrl}update_order_status";
  static String validateCustomTime = "${baseUrl}check_available_slot";
  static String getTransactions = "${baseUrl}get_transactions";
  static String deleteUserAccount = "${baseUrl}delete_user_account";
  static String checkProviderAvailability =
      "${baseUrl}provider_check_availability";
  static String downloadInvoice = "${baseUrl}invoice-download";
  static String searchServicesAndProvider =
      "${baseUrl}search_services_providers";
  static String sendQuery = "${baseUrl}contact_us_api";
  static String resendOTP = "${baseUrl}resend_otp";
  static String getCategoryHierarchical =
      "${baseUrl}get_categories_hierarchical";
  static String getCountryCodes = "${baseUrl}get_country_codes";

  // Password authentication endpoints
  static String changePassword = "${baseUrl}change_password";

  static String getProvidersOnMap = "${baseUrl}get_providers_on_map";

  static String logout = "${baseUrl}logout";

  //my request APIs
  static String makeCustomJobRequest = "${baseUrl}make_custom_job_request";
  static String myCustomJobRequests = "${baseUrl}fetch_my_custom_job_requests";
  static String customJobBidders = "${baseUrl}fetch_custom_job_bidders";
  static String cancelCustomJobRequest = "${baseUrl}cancle_custom_job_request";

  //chat related APIs
  static const String sendChatMessage = "${baseUrl}send_chat_message";
  static const String getChatMessages = "${baseUrl}get_chat_history";
  static const String getChatUsers = "${baseUrl}get_chat_providers_list";
  static const String blockUser = "${baseUrl}block_user";
  static const String getBlockedProvider = "${baseUrl}get_blocked_providers";
  static const String getReportReasons = "${baseUrl}get_report_reasons";

  //new added
  static String unblockUser = '${baseUrl}unblock_user';
  static String deleteChat = '${baseUrl}delete_chat_user';

////////* Place API */////
  static String placeAPI = "${baseUrl}get_places_for_app";
  static String placeApiDetails = "${baseUrl}get_place_details_for_app";

  //blogs
  static String getBlogs = "${baseUrl}get_blogs";
  static String getBlogCategories = "${baseUrl}get_blog_categories";
  static String getBlogDetails = "${baseUrl}get_blog_details";

  //languages
  static String getLanguageList = "${baseUrl}get_language_list";
  static String getLanguageJsonData = "${baseUrl}get_language_json_data";

  //stripe payment
  static String createStripePaymentIntent =
      "${baseUrl}create_stripe_payment_intent";
  static String getStripePaymentStatus = "${baseUrl}get_stripe_payment_status";
}
