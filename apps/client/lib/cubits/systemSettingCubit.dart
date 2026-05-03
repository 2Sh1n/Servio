import 'package:e_demand/app/generalImports.dart';

abstract class SystemSettingState {}

class SystemSettingFetchInitial extends SystemSettingState {}

class SystemSettingFetchInProgress extends SystemSettingState {}

class SystemSettingFetchSuccess extends SystemSettingState {
  SystemSettingFetchSuccess({required this.systemSettingDetails});

  final SystemSettingsModel systemSettingDetails;
}

class SystemSettingFetchFailure extends SystemSettingState {
  SystemSettingFetchFailure({required this.errorMessage});

  final dynamic errorMessage;
}

class SystemSettingCubit extends Cubit<SystemSettingState> {
  SystemSettingCubit({required this.settingRepository})
    : super(SystemSettingFetchInitial());
  SettingRepository settingRepository = SettingRepository();

  Future<void> getSystemSettings() async {
    emit(SystemSettingFetchInProgress());
    settingRepository
        .getSystemSetting()
        .then((final SystemSettingsModel value) {
          emit(SystemSettingFetchSuccess(systemSettingDetails: value));
        })
        .catchError((final error) {
          emit(SystemSettingFetchFailure(errorMessage: error.toString()));
        });
  }

  AppSetting? get appSetting {
    if (state is SystemSettingFetchSuccess) {
      return (state as SystemSettingFetchSuccess)
          .systemSettingDetails
          .appSetting;
    }
    return null;
  }

  String get systemCurrency => generalSettings?.currency ?? '';

  String get systemCurrencyCountryCode =>
      generalSettings?.countryCurrencyCode ?? '';

  String get systemDecimalPoints => generalSettings?.decimalPoint ?? '0';

  GeneralSettings? get generalSettings {
    if (state is SystemSettingFetchSuccess) {
      return (state as SystemSettingFetchSuccess)
          .systemSettingDetails
          .generalSettings;
    }
    return null;
  }

  LoginSettings? get loginSettings {
    if (state is SystemSettingFetchSuccess) {
      return (state as SystemSettingFetchSuccess)
          .systemSettingDetails
          .loginSettings;
    }
    return null;
  }

  String get systemDistanceUnit => generalSettings?.distanceUnit ?? '';

  String get androidVersion =>
      appSetting?.customerCurrentVersionAndroidApp ?? '1.0.0';

  String get iOSVersion => appSetting?.customerCurrentVersionIosApp ?? '1.0.0';

  PaymentGatewaysSettings? get paymentGatewaysSettings {
    if (state is SystemSettingFetchSuccess) {
      return (state as SystemSettingFetchSuccess)
          .systemSettingDetails
          .paymentGatewaysSettings;
    }
    return null;
  }

  SystemTaxSettings? get systemTaxSettings {
    if (state is SystemSettingFetchSuccess) {
      return (state as SystemSettingFetchSuccess)
          .systemSettingDetails
          .systemTaxSettings;
    }
    return null;
  }

  SystemSettingsModel get systemSettings =>
      (state as SystemSettingFetchSuccess).systemSettingDetails;

  String get privacyPolicy =>
      systemSettings.translatedCustomerPrivacyPolicy ?? '';

  String get termCondition =>
      systemSettings.translatedCustomerTermsConditions ?? '';

  String get aboutUs => systemSettings.translatedAboutUs ?? '';

  String get contactUs => systemSettings.contactUs?.contactUS ?? '';

  String get messageForCustomerApplication {
    final AppSetting? appSetting = this.appSetting;
    if (appSetting?.translatedMessageForCustomerApplication != null &&
        appSetting!.translatedMessageForCustomerApplication!.isNotEmpty) {
      return appSetting.translatedMessageForCustomerApplication!;
    }
    return appSetting?.messageForCustomerApplication ?? '';
  }

  bool get isOTPSystemEnable => generalSettings?.isOTPSystemEnable == "1";

  bool get showProviderAddress => generalSettings?.showProviderAddress == "1";

  String get getCustomerAppURL => Platform.isAndroid
      ? appSetting!.customerAppPlayStoreURL ?? ''
      : appSetting!.customerAppAppStoreURL ?? '';

  String get getProviderAppURL => Platform.isAndroid
      ? appSetting!.providerAppPlayStoreURL ?? ''
      : appSetting!.providerAppAppStoreURL ?? '';

  Map<String, dynamic> get getSupportDetails => {
    "email": generalSettings?.supportEmail ?? '',
    "mobile": generalSettings?.phone ?? '',
    "address": generalSettings?.translatedAddress ?? '',
    "supportHours": generalSettings?.supportHours ?? '',
  };

  List<SocialMediaURL>? get socialMediaLinks => systemSettings.socialMediaURLs;

  bool get isDemoModeEnabled => generalSettings?.isDemoModeEnabled ?? false;

  bool get isAdEnabled =>
      appSetting?.isAndroidAdEnabled == "1" ||
      appSetting?.isIosAdEnabled == "1";

  bool get isChatImageUploadEnabled =>
      generalSettings?.enableChatImageUpload == "1";

  bool get isChatFileUploadEnabled =>
      generalSettings?.enableChatFileUpload == "1";

  bool get isTaxEnabled => systemTaxSettings?.taxStatus == "on";

  String get taxName => systemTaxSettings?.taxName ?? '';

  String get taxPercentage => systemTaxSettings?.tax ?? '0';

  bool get showTaxOnCheckout => systemTaxSettings?.showOnCheckout == "1";

  String get BannerAdId => Platform.isAndroid
      ? appSetting?.androidBannerId ?? ''
      : appSetting?.iosBannerId ?? '';

  String get InterstitialAdId => Platform.isAndroid
      ? appSetting?.androidInterstitialId ?? ''
      : appSetting?.iosInterstitialId ?? '';

  bool isMoreThanOnePaymentGatewayEnabled() {
    final bool paystackEnabled =
        paymentGatewaysSettings?.paystackStatus == "enable";
    final bool razorpayEnabled =
        paymentGatewaysSettings?.razorpayApiStatus == "enable";
    final bool paypalEnabled =
        paymentGatewaysSettings?.paypalStatus == "enable";
    final bool stripeEnabled =
        paymentGatewaysSettings?.stripeStatus == "enable";
    final bool flutterwaveEnabled =
        paymentGatewaysSettings?.isFlutterwaveEnable == "enable";
    final bool isXenditEnabled =
        paymentGatewaysSettings?.isXenditEnabled ?? false;

    int paymentGatewayCount = 0;
    if (paystackEnabled) {
      paymentGatewayCount++;
    }
    if (razorpayEnabled) {
      paymentGatewayCount++;
    }
    if (paypalEnabled) {
      paymentGatewayCount++;
    }
    if (stripeEnabled) {
      paymentGatewayCount++;
    }
    if (flutterwaveEnabled) {
      paymentGatewayCount++;
    }
    if (isXenditEnabled) {
      paymentGatewayCount++;
    }

    return paymentGatewayCount > 1;
  }

  List<Map<String, dynamic>> getEnabledPaymentMethods({
    required bool isPayLaterAllowed,
    bool? isOnlinePaymentAllowed,
  }) {
    if (state is SystemSettingFetchSuccess) {
      final PaymentGatewaysSettings? paymentGatewaySetting =
          (state as SystemSettingFetchSuccess)
              .systemSettingDetails
              .paymentGatewaysSettings;

      final List<Map<String, dynamic>> paymentMethods = [
        {
          "title": 'payNow'.translate(
            context: UiUtils.rootNavigatorKey.currentContext!,
          ),
          "description": 'payOnlineNow',
          "optionDescription": "payOnlineNow",
          "image": AppAssets.card,
          "isEnabled":
              (isOnlinePaymentAllowed ?? false) &&
              !isMoreThanOnePaymentGatewayEnabled(),
          "paymentType": getSingleEnabledPaymentMethod(),
        },
        {
          "title": 'Paypal',
          "description": 'payOnlineNowUsingPaypal',
          "optionDescription": "payOnlineNowUsingPaypal",
          "image": AppAssets.paypal,
          "isEnabled":
              paymentGatewaySetting?.paypalStatus == "enable" &&
              isMoreThanOnePaymentGatewayEnabled(),
          "paymentType": "paypal",
        },
        {
          "title": 'Razorpay',
          "description": 'payOnlineNowUsingRazorpay',
          "optionDescription": "payOnlineNowUsingRazorpay",
          "image": AppAssets.razorpay,
          "isEnabled":
              paymentGatewaySetting?.razorpayApiStatus == "enable" &&
              isMoreThanOnePaymentGatewayEnabled(),
          "paymentType": "razorpay",
        },
        {
          "title": 'Paystack',
          "description": 'payOnlineNowUsingPaystack',
          "optionDescription": "payOnlineNowUsingPaystack",
          "image": AppAssets.paystack,
          "isEnabled":
              paymentGatewaySetting?.paystackStatus == "enable" &&
              isMoreThanOnePaymentGatewayEnabled(),
          "paymentType": "paystack",
        },
        {
          "title": 'Stripe',
          "description": 'payOnlineNowUsingStripe',
          "optionDescription": "payOnlineNowUsingStripe",
          "image": AppAssets.stripe,
          "isEnabled":
              paymentGatewaySetting?.stripeStatus == "enable" &&
              isMoreThanOnePaymentGatewayEnabled(),
          "paymentType": "stripe",
        },
        {
          "title": 'Flutterwave',
          "description": 'payOnlineNowUsingFlutterwave',
          "optionDescription": "payOnlineNowUsingFlutterwave",
          "image": AppAssets.flutterwave,
          "isEnabled":
              paymentGatewaySetting?.isFlutterwaveEnable == "enable" &&
              isMoreThanOnePaymentGatewayEnabled(),
          "paymentType": "flutterwave",
        },
        {
          "title": 'Xendit',
          "description": 'payOnlineNowUsingXendit',
          "optionDescription": "payOnlineNowUsingXendit",
          "image": AppAssets.xendit,
          "isEnabled":
              (paymentGatewaySetting?.isXenditEnabled ?? false) &&
              isMoreThanOnePaymentGatewayEnabled(),
          "paymentType": "xendit",
        },
        {
          "title": 'payOnService'.translate(
            context: UiUtils.rootNavigatorKey.currentContext!,
          ),
          "description": 'payWithServiceAtYourHome',
          "optionDescription": "payWithServiceAtStore",
          "image": AppAssets.cod,
          'isEnabled': isPayLaterAllowed,
          "paymentType": "cod",
        },
      ];

      paymentMethods.removeWhere((element) => !element["isEnabled"]);
      return paymentMethods;
    }
    return [];
  }

  String getSingleEnabledPaymentMethod() {
    String selectedPaymentMethod = '';
    if (state is SystemSettingFetchSuccess) {
      final PaymentGatewaysSettings? paymentGatewaySetting =
          (state as SystemSettingFetchSuccess)
              .systemSettingDetails
              .paymentGatewaysSettings;
      if (paymentGatewaySetting?.stripeStatus == "enable") {
        selectedPaymentMethod = "stripe";
      } else if (paymentGatewaySetting?.razorpayApiStatus == "enable") {
        selectedPaymentMethod = "razorpay";
      } else if (paymentGatewaySetting?.paystackStatus == "enable") {
        selectedPaymentMethod = "paystack";
      } else if (paymentGatewaySetting?.paypalStatus == "enable") {
        selectedPaymentMethod = "paypal";
      } else if (paymentGatewaySetting?.isFlutterwaveEnable == "enable") {
        selectedPaymentMethod = "flutterwave";
      } else if (paymentGatewaySetting?.isXenditEnabled ?? false) {
        selectedPaymentMethod = "xendit";
      }
    }
    return selectedPaymentMethod;
  }
}

enum TypesOfAppURLs {
  customerAppPlaystoreURL,
  customerAppAppstoreURL,
  providerAppPlaystoreURL,
  providerAppAppstoreURL,
}
