class ClarityActions {
  const ClarityActions._();

  // App-wide
  static const String appLaunch = 'app_launch';
  static const String appResume = 'app_resume';
  static const String appBackground = 'app_background';
  static const String maintenanceModeViewed = 'maintenance_mode_viewed';
  static const String forceUpdatePromptShown = 'force_update_prompt_shown';

  // Authentication
  static const String loginAttempt = 'login_attempt';
  static const String loginSuccess = 'login_success';
  static const String loginFailure = 'login_failure';
  static const String logout = 'logout';
  static const String otpSent = 'otp_sent';
  static const String otpVerified = 'otp_verified';
  static const String signupCompleted = 'signup_completed';

  // Discovery & browsing
  static const String homeBannerTapped = 'home_banner_tapped';
  static const String homeCategoryShortcutTapped =
      'home_category_shortcut_tapped';
  static const String homePopularServiceTapped = 'home_popular_service_tapped';
  static const String searchScreenOpened = 'search_screen_opened';
  static const String serviceSearchSubmitted = 'service_search_submitted';
  static const String serviceFilterApplied = 'service_filter_applied';
  static const String serviceSortChanged = 'service_sort_changed';
  static const String serviceListItemViewed = 'service_list_item_viewed';

  // Service details
  static const String serviceDetailViewed = 'service_detail_viewed';
  static const String serviceDetailBookNowTapped =
      'service_detail_book_now_tapped';
  static const String serviceDetailContactTapped =
      'service_detail_contact_tapped';
  static const String serviceDetailShareTapped = 'service_detail_share_tapped';
  static const String serviceReviewSubmitted = 'service_review_submitted';

  // Cart & wishlist
  static const String cartViewed = 'cart_viewed';
  static const String cartItemAdded = 'cart_item_added';
  static const String cartItemRemoved = 'cart_item_removed';
  static const String cartCleared = 'cart_cleared';
  static const String cartCheckoutTapped = 'cart_checkout_tapped';
  static const String bookmarkAdded = 'bookmark_added';
  static const String bookmarkRemoved = 'bookmark_removed';

  // Scheduling & bookings
  static const String timeslotPickerOpened = 'timeslot_picker_opened';
  static const String timeslotSlotSelected = 'timeslot_slot_selected';
  static const String timeslotCustomTimeEntered =
      'timeslot_custom_time_entered';
  static const String timeslotValidationFailed = 'timeslot_validation_failed';
  static const String bookingRequested = 'booking_requested';
  static const String bookingConfirmed = 'booking_confirmed';
  static const String bookingCancelled = 'booking_cancelled';
  static const String bookingCompleted = 'booking_completed';
  static const String bookingRescheduled = 'booking_rescheduled';
  static const String bookingAdditionalChargeApproved =
      'booking_additional_charge_approved';

  // Promo codes
  static const String promoCodeApplied = 'promo_code_applied';
  static const String promoCodeFailed = 'promo_code_failed';

  // Payments
  static const String paymentMethodSelected = 'payment_method_selected';
  static const String paymentStarted = 'payment_started';
  static const String paymentSucceeded = 'payment_succeeded';
  static const String paymentFailed = 'payment_failed';
  static const String paymentGatewayRedirected = 'payment_gateway_redirected';

  // Profile & settings
  static const String profileUpdated = 'profile_updated';
  static const String profileUpdateSaved = 'profile_update_saved';
  static const String settingsChanged = 'settings_changed';
  static const String languageChanged = 'language_changed';
  static const String themeChanged = 'theme_changed';
  static const String notificationOpened = 'notification_opened';
  static const String addressAdded = 'address_added';
  static const String addressDeleted = 'address_deleted';
  static const String deleteAccountConfirmed = 'delete_account_confirmed';

  // Custom job requests
  static const String customJobRequestCreated = 'custom_job_request_created';
  static const String customJobRequestCancelled =
      'custom_job_request_cancelled';
  static const String customJobBidSubmitted = 'custom_job_bid_submitted';

  // Subscriptions
  static const String subscriptionPlanViewed = 'subscription_plan_viewed';
  static const String subscriptionPurchaseStarted =
      'subscription_purchase_started';
  static const String subscriptionPurchaseSuccess =
      'subscription_purchase_success';
  static const String subscriptionPurchaseFailed =
      'subscription_purchase_failed';

  // Communication
  static const String chatMessageSent = 'chat_message_sent';
  static const String supportTicketCreated = 'support_ticket_created';
}
