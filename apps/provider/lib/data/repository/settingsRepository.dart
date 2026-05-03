import '../../app/generalImports.dart';

class SettingsRepository {
  Future getSystemSettings({required bool isAnonymous}) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getSettings,
        parameter: {},
        useAuthToken: isAnonymous ? false : true,
      );
      return response['data'];
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future updateFCM({
    required final String fcmId,
    required final String platform,
  }) async {
    await ApiClient.post(
      url: ApiUrl.updateFcm,
      parameter: {ApiParam.fcmId: fcmId, ApiParam.platform: platform},
      useAuthToken: true,
    );
  }

  Future<String> createRazorpayOrderId({
    required final String subscriptionID,
  }) async {
    try {
      final Map<String, dynamic> parameters = {
        ApiParam.subscriptionID: subscriptionID,
      };
      final result = await ApiClient.post(
        parameter: parameters,
        url: ApiUrl.createRazorpayOrder,
        useAuthToken: true,
      );

      return result['data']['id'];
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> sendQueryToAdmin({
    required final Map<String, dynamic> parameter,
  }) async {
    try {
      final response = await ApiClient.post(
        url: ApiUrl.sendQuery,
        parameter: parameter,
        useAuthToken: true,
      );

      return {"message": response["message"], "error": response['error']};
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<LanguageListModel> getLanguageList() async {
    try {
      final response = await ApiClient.get(
        url: ApiUrl.getLanguageList,
        useAuthToken: false,
      );

      return LanguageListModel.fromJson(response);
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to fetch language JSON data
  Future<Map<String, dynamic>> getLanguageJsonData(String languageCode) async {
    try {
      // Get FCM token
      String? fcmToken;
      try {
        fcmToken = await FirebaseMessaging.instance.getToken();
      } catch (_) {
        // If FCM token fails to get, continue without it
      }

      final Map<String, dynamic> parameters = {
        ApiParam.languageCode: languageCode,
        ApiParam.platform: 'provider_app',
      };

      // Add FCM token if available
      if (fcmToken != null && fcmToken.isNotEmpty) {
        parameters[ApiParam.fcmId] = fcmToken;
      }

      final response = await ApiClient.post(
        url: ApiUrl.getLanguageJsonData,
        parameter: parameters,
        useAuthToken: false,
      );

      return response['data'] ?? {};
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
