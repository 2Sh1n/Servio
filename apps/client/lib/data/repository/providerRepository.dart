import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/model/providerMapModel.dart';

class ProviderRepository {
  /// This method is used to fetch Provider List
  Future<Map<String, dynamic>> fetchProviderList(
      {required final bool isAuthTokenRequired,
      final String? categoryId,
      final String? providerIdOrSlug,
      final String? subCategoryId,
      final String? filter,
      final String? promocode,
      final ProviderDetailsParamType? type}) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.latitude: HiveRepository.getLatitude,
        ApiParam.longitude: HiveRepository.getLongitude
      };

      if (categoryId != '' && categoryId != null) {
        parameter[ApiParam.categoryId] = categoryId;
      }
      if (providerIdOrSlug != '' && providerIdOrSlug != null) {
        if (type == ProviderDetailsParamType.slug) {
          parameter[ApiParam.slug] = providerIdOrSlug;
        } else {
          parameter[ApiParam.partnerId] = providerIdOrSlug;
        }
      }
      if (subCategoryId != '' && subCategoryId != null) {
        parameter[ApiParam.subCategoryId] = subCategoryId;
      }
      if (filter != '' && filter != null) {
        parameter[ApiParam.filter] = filter;
        // parameter[Api.order] = "desc";
      }
      if (promocode != '' && promocode != null) {
        parameter[ApiParam.promocode] = promocode;
      }

      final providers = await ApiClient.post(
          url: ApiUrl.getProviders, parameter: parameter, useAuthToken: false);
      if (providers['data'] == null || providers['data'] == []) {
        return {'totalProviders': "0", 'providerList': []};
      } else {
        return {
          'totalProviders': providers['total'].toString(),
          'providerList': (providers['data'] as List).map((providerData) {
            return Providers.fromJson(providerData);
          }).toList()
        };
      }
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  /// This method is used to search Provider
  Future<Map<String, dynamic>> searchProvider({
    required final String searchKeyword,
    required final String offset,
    required final String limit,
  }) async {
    try {
      final parameter = <String, dynamic>{
        ApiParam.latitude: HiveRepository.getLatitude,
        ApiParam.longitude: HiveRepository.getLongitude,
        ApiParam.search: searchKeyword.trim(),
        ApiParam.limit: limit,
        ApiParam.offset: offset,
        ApiParam.type: "provider",
      };

      final providers = await ApiClient.post(
          url: ApiUrl.searchServicesAndProvider,
          parameter: parameter,
          useAuthToken: false);

      return {
        'totalProviders': providers["data"]['total'].toString(),
        'providerList':
            (providers['data']["providers"] as List).map((providerData) {
          return Providers.fromJson(providerData);
        }).toList()
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  /// This method is used to check providers are available at selected latitude longitude
  Future<Map<String, dynamic>> checkProviderAvailability({
    required final String latitude,
    required final String longitude,
    required final String checkingAtCheckOut,
    final String? orderId,
    final String? customJobRequestId,
    final String? bidderId,
    required final bool isAuthTokenRequired,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.latitude: latitude,
        ApiParam.longitude: longitude,
        ApiParam.isCheckoutProcess: checkingAtCheckOut,
        ApiParam.orderId: orderId,
        ApiParam.customJobRequestId: customJobRequestId,
        ApiParam.bidderId: bidderId,
      };
      parameter.removeWhere(
        (key, value) => value == null || value == "null" || value == '',
      );

      final response = await ApiClient.post(
        url: ApiUrl.checkProviderAvailability,
        parameter: parameter,
        useAuthToken: isAuthTokenRequired,
      );
      return {
        'error': response['error'],
        'message': response['message'].toString(),
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  /// This method is to fetch the location-based provider list for map
  Future<List<ProviderMapModel>> getMapProviderList({
    required String latitude,
    required String longitude,
  }) async {
    try {
      final parameter = <String, dynamic>{
        ApiParam.latitude: latitude,
        ApiParam.longitude: longitude,
      };

      final providers = await ApiClient.post(
        url: ApiUrl.getProvidersOnMap,
        parameter: parameter,
        useAuthToken: false,
      );

      return (providers['data'] as List).map((providerData) {
        return ProviderMapModel.fromJson(Map.from(providerData));
      }).toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
