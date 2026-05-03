// ignore_for_file: file_names

import 'package:e_demand/app/generalImports.dart';

class GooglePlaceRepository {
  Future<PlacesModel> searchLocationsFromPlaceAPI(
    final String text,
  ) async {
    try {
      final Map<String, dynamic> queryParameters = <String, dynamic>{
        ApiParam.input: text,
      };

      final Map<String, dynamic> placesData = await ApiClient.get(
          url: ApiUrl.placeAPI,
          useAuthToken: false,
          queryParameters: queryParameters);

      return PlacesModel.fromJson(placesData['data']);
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future getPlaceDetailsFromPlaceId(final String placeId) async {
    try {
      final Map<String, dynamic> queryParameters = <String, dynamic>{
        ApiParam.placeid: placeId
      };
      final Map<String, dynamic> response = await ApiClient.get(
        url: ApiUrl.placeApiDetails,
        queryParameters: queryParameters,
        useAuthToken: false,
      );
      return response['data']['result']['geometry']['location'];
    } catch (e) {
      rethrow;
    }
  }
}
