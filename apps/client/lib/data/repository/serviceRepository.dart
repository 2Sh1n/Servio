import 'package:e_demand/app/generalImports.dart';

class ServiceRepository {
  ///This method is used to fetch available services form database
  Future<Map<String, dynamic>> getServices({
    required final String offset,
    required final String limit,
    required final bool isAuthTokenRequired,
    required final ProviderDetailsParamType type,
    required final String providerIdOrSlug,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.latitude: HiveRepository.getLatitude,
        ApiParam.longitude: HiveRepository.getLongitude,
        if (type == ProviderDetailsParamType.slug)
          ApiParam.providerSlug: providerIdOrSlug
        else
          ApiParam.partnerId: providerIdOrSlug,
        ApiParam.limit: limit,
        ApiParam.offset: offset
      };

      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.getServices,
        useAuthToken: isAuthTokenRequired,
      );

      return {
        "services": (response['data'] as List)
            .map((final e) => Services.fromJson(Map.from(e)))
            .toList(),
        "totalServices": response['total']
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to search services
  Future<Map<String, dynamic>> searchServicesWithProviders({
    required final String offset,
    required final String limit,
    required final bool isAuthTokenRequired,
    required String searchText,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.latitude: HiveRepository.getLatitude,
        ApiParam.longitude: HiveRepository.getLongitude,
        ApiParam.search: searchText.trim(),
        ApiParam.type: "service",
        ApiParam.limit: limit,
        ApiParam.offset: offset
      };

      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.searchServicesAndProvider,
        useAuthToken: isAuthTokenRequired,
      );

      if (response.isEmpty) {
        return {"providersWithServices": [], "totalProvidersWithServices": 0};
      }
      return {
        "providersWithServices":
            (response['data']["Services"] as List).map((final e) {
          final Providers provider =
              Providers.fromJson(Map.from(e["provider"]));
          return provider;
        }).toList(),
        "totalProvidersWithServices": response["data"]['total']
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
