import 'package:intl/intl.dart';

class SystemSettingsModel {
  SystemSettingsModel({
    this.paymentGatewaysSettings,
    this.translatedAboutUs,
    this.contactUs,
    this.generalSettings,
    this.translatedCustomerTermsConditions,
    this.translatedCustomerPrivacyPolicy,
    this.socialMediaURLs,
    this.appSetting,
    this.systemTaxSettings,
    this.loginSettings,
  });

  SystemSettingsModel.fromJson(final Map<String, dynamic> json) {
    paymentGatewaysSettings = json["payment_gateways_settings"] != null
        ? PaymentGatewaysSettings.fromJson(json["payment_gateways_settings"])
        : null;
    translatedAboutUs = json["translated_about_us"] != null
        ? (json["translated_about_us"] is String
            ? json["translated_about_us"] as String
            : json["translated_about_us"].toString())
        : null;
    appSetting = json["app_settings"] != null
        ? AppSetting.fromJson(json["app_settings"])
        : null;
    translatedContactUs = json["translated_contact_us"] != null
        ? json["translated_contact_us"]
        : null;
    generalSettings = json["general_settings"] != null
        ? GeneralSettings.fromJson(json["general_settings"])
        : null;
    socialMediaURLs = json["web_settings"] != null &&
            json["web_settings"]["social_media"] != null &&
            (json["web_settings"]["social_media"] is List) &&
            (json["web_settings"]["social_media"] as List).isNotEmpty
        ? (json["web_settings"]["social_media"] as List)
            .map((e) => SocialMediaURL.fromJson(Map.from(e)))
            .toList()
        : [];

    translatedCustomerTermsConditions =
        json["translated_customer_terms_conditions"] != null
            ? (json["translated_customer_terms_conditions"] is String
                ? json["translated_customer_terms_conditions"] as String
                : json["translated_customer_terms_conditions"].toString())
            : null;
    translatedCustomerPrivacyPolicy =
        json["translated_customer_privacy_policy"] != null
            ? (json["translated_customer_privacy_policy"] is String
                ? json["translated_customer_privacy_policy"] as String
                : json["translated_customer_privacy_policy"].toString())
            : null;
    systemTaxSettings = json["system_tax_settings"] != null
        ? SystemTaxSettings.fromJson(json["system_tax_settings"])
        : null;

    loginSettings = json["login_settings"] != null
        ? LoginSettings.fromJson(json["login_settings"])
        : null;
  }

  List<SocialMediaURL>? socialMediaURLs;
  PaymentGatewaysSettings? paymentGatewaysSettings;

  ContactUs? contactUs;
  //AboutUs? aboutUs;
  String? translatedContactUs;
  String? translatedAboutUs;
  GeneralSettings? generalSettings;
  //CustomerTermsConditions? customerTermsConditions;
  //CustomerPrivacyPolicy? customerPrivacyPolicy;
  String? translatedCustomerTermsConditions;
  String? translatedCustomerPrivacyPolicy;
  AppSetting? appSetting;
  SystemTaxSettings? systemTaxSettings;
  LoginSettings? loginSettings;
}

class PaymentGatewaysSettings {
  PaymentGatewaysSettings({
    this.razorpayApiStatus,
    this.razorpayMode,
    this.razorpayCurrency,
    this.razorpayKey,
    this.paystackStatus,
    this.paystackMode,
    this.paystackCurrency,
    this.paystackKey,
    this.flutterPublicKey,
    this.stripeStatus,
    this.stripeMode,
    this.stripeCurrency,
    this.stripePublishableKey,
    this.paypalStatus,
    this.isOnlinePaymentEnable,
    this.isPayLaterEnable,
    this.isFlutterwaveEnable,
    this.flutterwaveWebsiteUrl,
    this.paypalWebsiteUrl,
    this.isXenditEnabled,
    this.xenditWebsiteUrl,
  });

  PaymentGatewaysSettings.fromJson(final Map<String, dynamic> json) {
    isPayLaterEnable = json["cod_setting"]?.toString() ?? "1";
    isOnlinePaymentEnable = json["payment_gateway_setting"]?.toString() ?? "0";
    razorpayApiStatus = json["razorpayApiStatus"];
    razorpayMode = json["razorpay_mode"];
    razorpayCurrency = json["razorpay_currency"];
    razorpayKey = json["razorpay_key"];
    paystackStatus = json["paystack_status"];
    paystackMode = json["paystack_mode"];
    paystackCurrency = json["paystack_currency"];
    paystackKey = json["paystack_key"];
    flutterPublicKey = json["flutter_public_key"];
    stripeStatus = json["stripe_status"];
    stripeMode = json["stripe_mode"];
    stripeCurrency = json["stripe_currency"];
    stripePublishableKey = json["stripe_publishable_key"];
    paypalStatus = json["paypal_status"];
    isFlutterwaveEnable = json["flutterwave_status"];
    flutterwaveWebsiteUrl = json["flutterwave_website_url"];
    paypalWebsiteUrl = json["paypal_website_url"];
    isXenditEnabled = (json['xendit_status']?.toString() ?? '') == 'enable';
    xenditWebsiteUrl = json['xendit_website_url'];
  }

  String? razorpayApiStatus;
  String? razorpayMode;
  String? razorpayCurrency;
  String? razorpayKey;
  String? paystackStatus;
  String? paystackMode;
  String? paystackCurrency;
  String? paystackKey;
  String? flutterPublicKey;
  String? stripeStatus;
  String? stripeMode;
  String? stripeCurrency;
  String? stripePublishableKey;
  String? paypalStatus;
  String? isPayLaterEnable;
  String? isOnlinePaymentEnable;
  String? isFlutterwaveEnable;
  String? flutterwaveWebsiteUrl;
  String? paypalWebsiteUrl;
  bool? isXenditEnabled;
  String? xenditWebsiteUrl;
}

class TermsConditions {
  TermsConditions({this.termsConditions});

  TermsConditions.fromJson(final Map<String, dynamic> json) {
    termsConditions = json["terms_conditions"];
  }

  String? termsConditions;
}

class PrivacyPolicy {
  PrivacyPolicy({this.privacyPolicy});

  PrivacyPolicy.fromJson(final Map<String, dynamic> json) {
    privacyPolicy = json["privacy_policy"];
  }

  String? privacyPolicy;
}

class AboutUs {
  AboutUs({this.aboutUs});

  AboutUs.fromJson(final Map<String, dynamic> json) {
    aboutUs = json["translated_about_us"];
  }

  String? aboutUs;
}

class ContactUs {
  ContactUs({this.contactUS});

  ContactUs.fromJson(final Map<String, dynamic> json) {
    contactUS = json["contact_us"];
  }

  String? contactUS;
}

class SocialMediaURL {
  SocialMediaURL({this.imageURL, this.url});

  SocialMediaURL.fromJson(final Map<String, dynamic> json) {
    imageURL = json["file"];
    url = json["url"];
  }

  String? imageURL;
  String? url;
}

class GeneralSettings {
  GeneralSettings({
    this.translatedCompanyTitle,
    this.supportName,
    this.supportEmail,
    this.phone,
    this.maxServiceableDistance,
    this.translatedAddress,
    this.translatedShortDescription,
    this.translatedCopyrightDetails,
    this.supportHours,
    this.isOTPSystemEnable,
    this.showProviderAddress,
    this.maxFilesOrImagesInOneMessage,
    this.maxFileSizeInMBCanBeSent,
    this.maxCharactersInATextMessage,
    required this.isDemoModeEnabled,
    this.distanceUnit,
    this.defaultCountryCode,
    this.enableChatImageUpload,
    this.enableChatFileUpload,
    this.countryCurrencyCode,
    this.currency,
    this.decimalPoint,
  });

  GeneralSettings.fromJson(final Map<String, dynamic> json) {
    translatedCompanyTitle =
        (json["translated_company_title"]?.toString() ?? '').isNotEmpty
            ? json["translated_company_title"]!.toString()
            : (json["company_title"]?.toString() ?? '');
    supportName = json["support_name"];
    supportEmail = json["support_email"];
    phone = json["phone"];
    maxServiceableDistance =
        json["max_serviceable_distance"]?.toString() ?? "0";

    translatedAddress =
        (json["translated_address"]?.toString() ?? '').isNotEmpty
            ? json["translated_address"]!.toString()
            : (json["address"]?.toString() ?? '');
    translatedShortDescription =
        (json["translated_short_description"]?.toString() ?? '').isNotEmpty
            ? json["translated_short_description"]!.toString()
            : (json["short_description"]?.toString() ?? '');
    translatedCopyrightDetails =
        (json["translated_copyright_details"]?.toString() ?? '').isNotEmpty
            ? json["translated_copyright_details"]!.toString()
            : (json["copyright_details"]?.toString() ?? '');
    supportHours = json["support_hours"];
    isOTPSystemEnable = json["otp_system"];
    showProviderAddress = json['provider_location_in_provider_details'] ?? "0";

    maxCharactersInATextMessage = json['maxCharactersInATextMessage'];
    maxFileSizeInMBCanBeSent = json['maxFileSizeInMBCanBeSent'];
    maxFilesOrImagesInOneMessage = json['maxFilesOrImagesInOneMessage'];
    defaultCountryCode = json["default_country_code"];
    isDemoModeEnabled = (json['demo_mode'] == "" || json["demo_mode"] == null
            ? 0
            : json["demo_mode"]) ==
        "1";
    distanceUnit = json["distance_unit"];
    enableChatImageUpload = json["enable_chat_image_upload"] ?? "0";
    enableChatFileUpload = json["enable_chat_file_upload"] ?? "0";
    countryCurrencyCode = json["country_currency_code"];
    currency = json["currency"];
    decimalPoint = json["decimal_point"] == "" ? "0" : json["decimal_point"];
  }

  String? companyTitle;
  String? translatedCompanyTitle;
  String? supportName;
  String? supportEmail;
  String? phone;

  String? maxServiceableDistance;

  String? address;
  String? translatedAddress;
  String? shortDescription;
  String? translatedShortDescription;
  String? copyrightDetails;
  String? translatedCopyrightDetails;
  String? supportHours;
  String? isOTPSystemEnable;
  String? showProviderAddress;

  String? maxFilesOrImagesInOneMessage;
  String? maxFileSizeInMBCanBeSent;
  String? maxCharactersInATextMessage;
  bool isDemoModeEnabled = false;
  String? distanceUnit;
  String? defaultCountryCode;
  String? enableChatImageUpload;
  String? enableChatFileUpload;
  String? countryCurrencyCode;
  String? currency;
  String? decimalPoint;

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['translated_company_title'] = translatedCompanyTitle;
    data['support_name'] = supportName;
    data['support_email'] = supportEmail;
    data['phone'] = phone;
    data['max_serviceable_distance'] = maxServiceableDistance;

    data['translated_address'] = translatedAddress;
    data['translated_short_description'] = translatedShortDescription;
    data['translated_copyright_details'] = translatedCopyrightDetails;
    data['support_hours'] = supportHours;
    data['otp_system'] = isOTPSystemEnable;

    data['demo_mode'] = isDemoModeEnabled ? "1" : "0";
    return data;
  }
}

class AppSetting {
  AppSetting({
    this.customerCurrentVersionAndroidApp,
    this.customerCurrentVersionIosApp,
    this.customerCompulsaryUpdateForceUpdate,
    this.providerCurrentVersionAndroidApp,
    this.providerCurrentVersionIosApp,
    this.providerCompulsaryUpdateForceUpdate,
    this.messageForCustomerApplication,
    this.translatedMessageForCustomerApplication,
    this.customerAppMaintenanceMode,
    this.providerAppAppStoreURL,
    this.providerAppPlayStoreURL,
    this.customerAppAppStoreURL,
    this.customerAppPlayStoreURL,
    this.androidBannerId,
    this.androidInterstitialId,
    this.isAndroidAdEnabled,
    this.iosBannerId,
    this.iosInterstitialId,
    this.isIosAdEnabled,
    this.customerAppScheduleMaintenanceMode,
    this.customerAppMaintenanceModeStartDateTime,
    this.customerAppMaintenanceModeEndDateTime,
  });

  AppSetting.fromJson(final Map<String, dynamic> json) {
    customerCurrentVersionAndroidApp =
        json["customer_current_version_android_app"];
    customerCurrentVersionIosApp = json["customer_current_version_ios_app"];
    customerCompulsaryUpdateForceUpdate =
        json["customer_compulsary_update_force_update"];
    providerCurrentVersionAndroidApp =
        json["provider_current_version_android_app"];
    providerCurrentVersionIosApp = json["provider_current_version_ios_app"];
    providerCompulsaryUpdateForceUpdate =
        json["provider_compulsary_update_force_update"];
    messageForCustomerApplication = json["message_for_customer_application"];
    translatedMessageForCustomerApplication =
        json["translated_message_for_customer_application"];
    customerAppMaintenanceMode = json["customer_app_maintenance_mode"];
    customerAppPlayStoreURL = json['customer_playstore_url'] ?? "";
    customerAppAppStoreURL = json['customer_appstore_url'] ?? "";
    providerAppPlayStoreURL = json['provider_playstore_url'] ?? "";
    providerAppAppStoreURL = json['provider_appstore_url'] ?? "";
    //
    isAndroidAdEnabled = json['customer_android_google_ads_status'] ?? "";
    androidInterstitialId =
        json['customer_android_google_interstitial_id'] ?? "";
    androidBannerId = json['customer_android_google_banner_id'] ?? "";
    isIosAdEnabled = json['customer_ios_google_ads_status'] ?? "";
    iosInterstitialId = json['customer_ios_google_interstitial_id'] ?? "";
    iosBannerId = json['customer_ios_google_banner_id'] ?? "";
    customerAppScheduleMaintenanceMode =
        json['customer_app_scheduled_maintenance_mode']?.toString() == '1';
    customerAppMaintenanceModeStartDateTime =
        DateFormat('yyyy-MM-dd HH:mm:ss').tryParse(
      json['customer_app_maintenance_mode_start_datetime']?.toString() ?? '',
      true,
    );
    customerAppMaintenanceModeEndDateTime =
        DateFormat('yyyy-MM-dd HH:mm:ss').tryParse(
      json['customer_app_maintenance_mode_end_datetime']?.toString() ?? '',
      true,
    );
  }

  String? customerCurrentVersionAndroidApp;
  String? customerCurrentVersionIosApp;
  String? customerCompulsaryUpdateForceUpdate;
  String? providerCurrentVersionAndroidApp;
  String? providerCurrentVersionIosApp;
  String? providerCompulsaryUpdateForceUpdate;
  String? messageForCustomerApplication;
  String? translatedMessageForCustomerApplication;
  String? customerAppMaintenanceMode;
  String? customerAppAppStoreURL;
  String? customerAppPlayStoreURL;
  String? providerAppAppStoreURL;
  String? providerAppPlayStoreURL;
  String? isAndroidAdEnabled;
  String? androidBannerId;
  String? androidInterstitialId;
  String? isIosAdEnabled;
  String? iosBannerId;
  String? iosInterstitialId;
  bool? customerAppScheduleMaintenanceMode;
  DateTime? customerAppMaintenanceModeStartDateTime;
  DateTime? customerAppMaintenanceModeEndDateTime;
}

class SystemTaxSettings {
  SystemTaxSettings({
    this.taxStatus,
    this.taxName,
    this.tax,
    this.showOnCheckout,
  });

  SystemTaxSettings.fromJson(final Map<String, dynamic> json) {
    taxStatus = json["tax_status"] ?? "off";
    taxName = json["tax_name"] ?? "";
    tax = json["tax"] ?? "0";
    showOnCheckout = json["show_on_checkout"] ?? "0";
  }

  String? taxStatus;
  String? taxName;
  String? tax;
  String? showOnCheckout;
}

class LoginSettings {
  bool? isPhoneAuthEnabled;
  bool? isEmailAuthEnabled;
  bool? isPasswordLoginEnabled;
  bool? isSocialLoginEnabled;

  LoginSettings(
      {this.isPhoneAuthEnabled,
      this.isEmailAuthEnabled,
      this.isPasswordLoginEnabled,
      this.isSocialLoginEnabled});

  LoginSettings.fromJson(final Map<String, dynamic> json) {
    final phoneValue = json["phone_authentication_enabled"]?.toString() ?? "1";
    final emailValue = json["email_authentication_enabled"]?.toString() ?? "1";
    final passwordValue =
        json["customer_password_login_enabled"]?.toString() ?? "1";
    final socialValue =
        json["social_authentication_enabled"]?.toString() ?? "0";

    isPhoneAuthEnabled = phoneValue == "1";
    isEmailAuthEnabled = emailValue == "1";
    isPasswordLoginEnabled = passwordValue == "1";
    isSocialLoginEnabled = socialValue == "1";
  }
}
