import 'package:dio/dio.dart';
import 'package:e_demand/app/generalImports.dart';
import 'package:path/path.dart' as p;

class UserRepository {
  Future<Map<String, dynamic>> updateUserDetails(
      final UpdateUserDetails model) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.username: model.username,
        ApiParam.mobile: model.phone,
        ApiParam.countryCode: model.countryCode,
        ApiParam.email: model.email,
      };

      if (model.image != '') {
        final mimeType = lookupMimeType(model.image!.path);
        final extension = mimeType!.split("/");

        parameter[ApiParam.image] = await MultipartFile.fromFile(
          model.image!.path,
          filename: p.basename(model.image!.path),
          contentType: MediaType('image', extension[1]),
        );
      }

      final Map<String, dynamic> response = await ApiClient.post(
          url: ApiUrl.updateUser, parameter: parameter, useAuthToken: true);

      if (response['error']) {
        throw ApiException(response['message'].toString());
      }

      HiveRepository.setUsername = response['data']["username"];
      HiveRepository.setUserMobileCountryCode =
          response['data']["country_code"];
      HiveRepository.setUserProfilePictureURL = response['data']["image"];
      HiveRepository.setUserMobileNumber = response['data']["phone"];
      HiveRepository.setUserEmailId = response['data']["email"];
      //await Hive.box(userDetailBoxKey).putAll();
      return {
        "data": response['data'],
        'message': response['message'].toString(),
        "error": response['error'],
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
