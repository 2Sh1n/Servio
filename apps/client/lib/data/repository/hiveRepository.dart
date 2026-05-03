import '../../app/generalImports.dart';

class HiveRepository {
  //box-keys
  static String authStatusBoxKey = "authStatus";
  static String userDetailBoxKey = "userDetailsBox";
  static String languageBoxKey = "language";
  static String themeBoxKey = 'theme';
  static String placesBoxKey = 'places';

  ///--------------------------------- authStatusBox Keys
  ///
  static String isAuthenticatedKey = "isAuthenticated";
  static String isUserFirstTimeKey = "isFirstTime";
  static String isUserSkippedLoginBeforeKey = "isUserSkippedLoginBefore";
  static String lastSkippedMaintenanceScheduleWarningKey =
      "lastSkippedMaintenanceScheduleWarning";

  ///--------------------------------- userDetailBox Keys
  ///
  static String userIdKey = "id";
  static String userNameKey = "username";
  static String phoneNumberKey = "phone";
  static String countryCodeKey = "country_code";
  static String emailIdKey = "email";
  static String fcmKey = "fcm_id";
  static String profileImageKey = "image";
  static String latitudeKey = "latitude";
  static String longitudeKey = "longitude";
  static String locationNameKey = "locationName";
  static String cityKey = "city";
  static String cityIdKey = "city_id";
  static String tokenIdKey = "token";
  static String postalCodeKey = "postalCodeKey";

  ///--------------------------------- languageBoxKey Keys
  ///
  static String currentLanguageKey = "currentLanguageKey";
  static String languageJsonDataKey = "languageJsonData";

  ///--------------------------------- themeBox Keys
  ///
  static String isDarkThemeEnableKey = 'currentTheme';
  static String resendTokenKey = 'resendToken';

  ///--------------------------------- placesBox Key
  ///
  static String getStoredPlacesKey = 'getStoredPlaces';

  ///--------------------------------- userDetailBox methods
  ///
  static String? get getUserEmailId =>
      Hive.box(userDetailBoxKey).get(emailIdKey);

  static set setUserEmailId(emailId) =>
      Hive.box(userDetailBoxKey).put(emailIdKey, emailId);

  static String? get getUserMobileCountryCode =>
      Hive.box(userDetailBoxKey).get(countryCodeKey);

  static set setUserMobileCountryCode(countryCode) =>
      Hive.box(userDetailBoxKey).put(countryCodeKey, countryCode);

  static String? get getUserMobileNumber =>
      Hive.box(userDetailBoxKey).get(phoneNumberKey);

  static set setUserMobileNumber(mobileNumber) =>
      Hive.box(userDetailBoxKey).put(phoneNumberKey, mobileNumber);

  static String? get getUsername => Hive.box(userDetailBoxKey).get(userNameKey);

  static set setUsername(username) =>
      Hive.box(userDetailBoxKey).put(userNameKey, username);

  static String? get getUserProfilePictureURL =>
      Hive.box(userDetailBoxKey).get(profileImageKey);

  static set setUserProfilePictureURL(profilePictureURL) =>
      Hive.box(userDetailBoxKey).put(profileImageKey, profilePictureURL);

  static String? get getLongitude =>
      (Hive.box(userDetailBoxKey).get(longitudeKey) ?? "0.0").toString();

  static set setLongitude(longitude) =>
      Hive.box(userDetailBoxKey).put(longitudeKey, longitude);

  static String? get getLatitude =>
      (Hive.box(userDetailBoxKey).get(latitudeKey) ?? "0.0").toString();

  static set setLatitude(latitude) =>
      Hive.box(userDetailBoxKey).put(latitudeKey, latitude);

  static String? get getLocationName =>
      Hive.box(userDetailBoxKey).get(locationNameKey);

  static set setLocationName(locationName) =>
      Hive.box(userDetailBoxKey).put(locationNameKey, locationName);

  static String get getUserToken =>
      Hive.box(userDetailBoxKey).get(tokenIdKey) ?? '';

  static set setUserToken(enable) =>
      Hive.box(userDetailBoxKey).put(tokenIdKey, enable);

  static dynamic get getResendToken =>
      Hive.box(userDetailBoxKey).get(resendTokenKey);

  static set setResendToken(enable) =>
      Hive.box(userDetailBoxKey).put(resendTokenKey, enable);

  static String? get getLastSkippedMaintenanceScheduleWarning =>
      Hive.box(userDetailBoxKey).get(lastSkippedMaintenanceScheduleWarningKey);

  static set setLastSkippedMaintenanceScheduleWarning(data) =>
      Hive.box(userDetailBoxKey)
          .put(lastSkippedMaintenanceScheduleWarningKey, data);

  ///--------------------------------- languageBox methods

  static Future<bool> storeLanguage({
    required dynamic data,
    required AppLanguage lang,
  }) async {
    final box = Hive.box(languageBoxKey);

    await box.put(languageJsonDataKey, data);
    await box.put(currentLanguageKey, lang.toJson()); // storing as JSON

    return true;
  }

  static dynamic getLanguageJsonData() {
    return Hive.box(languageBoxKey).get(languageJsonDataKey);
  }

  static AppLanguage? getCurrentLanguage() {
    final data = Hive.box(languageBoxKey).get(currentLanguageKey);

    if (data == null) {
      return null;
    }

    // Convert Map<dynamic, dynamic> → Map<String, dynamic>
    final language =
        AppLanguage.fromJson(Map<String, dynamic>.from(data as Map));
    return language;
  }

  ///--------------------------------- themeBox methods

  static bool get isDarkThemeUsing =>
      Hive.box(themeBoxKey).get(isDarkThemeEnableKey) ?? false;

  static set setDarkThemeEnable(enable) =>
      Hive.box(themeBoxKey).put(isDarkThemeEnableKey, enable);

  ///--------------------------------- authStatusBox methods

  static bool get isUserLoggedIn =>
      Hive.box(authStatusBoxKey).get(isAuthenticatedKey) ?? false;

  static set setUserLoggedIn(enable) =>
      Hive.box(authStatusBoxKey).put(isAuthenticatedKey, enable);

  static bool get isUserFirstTimeInApp =>
      Hive.box(authStatusBoxKey).get(isUserFirstTimeKey) ?? true;

  static set setUserFirstTimeInApp(enable) =>
      Hive.box(authStatusBoxKey).put(isUserFirstTimeKey, enable);

  static bool get isUserSkippedTheLoginBefore =>
      Hive.box(authStatusBoxKey).get(isUserSkippedLoginBeforeKey) ?? false;

  static set setUserSkippedTheLoginBefore(enable) =>
      Hive.box(authStatusBoxKey).put(isUserSkippedLoginBeforeKey, enable);

  ///--------------------------------- placeBox methods

  static List<Map> get getStoredPlaces {
    return ((Hive.box(placesBoxKey).get(getStoredPlacesKey) ?? []) as List)
        .map((e) => Map.from(e))
        .toList();
  }

  static set setPlaces(List<Map> enable) {
    Hive.box(placesBoxKey).put(getStoredPlacesKey, enable);
  }

  ///---------------------------------general methods
  ///
  static Future<void> init() async {
    await Hive.openBox(authStatusBoxKey);
    await Hive.openBox(themeBoxKey);
    await Hive.openBox(userDetailBoxKey);
    await Hive.openBox(languageBoxKey);
    await Hive.openBox(placesBoxKey);
  }

  static dynamic getAllValueOf({required String boxName}) {
    return Hive.box(boxName).toMap();
  }

  static Future<void> putAllValue(
      {required String boxName, required Map<dynamic, dynamic> values}) async {
    await Hive.box(boxName).putAll(values);
  }

  static Future<void> clearBoxValues({required String boxName}) async {
    await Hive.box(boxName).clear();
  }

  static Future<void> storePlaceInHive({
    required String placeId,
    required String placeName,
    required String latitude,
    required String longitude,
  }) async {
    final List<Map> lists = HiveRepository.getStoredPlaces;

    lists.removeWhere((element) => element["placeId"] == placeId);
    lists.insert(0, {
      "name": placeName,
      "cityName": placeName,
      "placeId": placeId,
      "latitude": latitude,
      "longitude": longitude,
    });

    HiveRepository.setPlaces = lists;
  }
}
