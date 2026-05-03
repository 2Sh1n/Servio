// ignore_for_file: file_names

import 'package:e_demand/app/generalImports.dart';

class AddressRepository {
  Future<Map<String, dynamic>> addAddress(
      final AddressModel addressDataModel) async {
    try {
      final demo = addressDataModel.toMap();

      demo[ApiParam.lattitude] = addressDataModel.latitude;

      final response = await ApiClient.post(
          url: ApiUrl.addAddress, parameter: demo, useAuthToken: true);

      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> updateDefaultAddress(final String Id) async {
    try {
      final response = await ApiClient.post(
        url: ApiUrl.addAddress,
        parameter: {ApiParam.addressId: Id, ApiParam.isDefault: "1"},
        useAuthToken: true,
      );
      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<List<GetAddressModel>> fetchAddress() async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getAddress,
        parameter: {ApiParam.limit: 100, ApiParam.offset: 0},
        useAuthToken: true,
      );

      final List<GetAddressModel> mappedResponse =
          (response['data'] as List<dynamic>).map((final entry) {
        final getAddressModel = GetAddressModel.fromJson(entry);

        return getAddressModel;
      }).toList();

      return mappedResponse;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> deleteAddress(final String id) async {
    final Map<String, dynamic> parameter = <String, dynamic>{
      ApiParam.addressId: id
    };
    try {
      final Map<String, dynamic> response = await ApiClient.post(
          url: ApiUrl.deleteAddress, parameter: parameter, useAuthToken: true);

      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
