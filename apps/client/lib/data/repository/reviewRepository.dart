import 'package:e_demand/app/generalImports.dart';

class ReviewRepository {
  ///This method is used to fetch available Reviews form database
  Future<Map<String, dynamic>> getReviews({
    required final bool isAuthTokenRequired,
    String? providerIdOrSlug,
    final ProviderDetailsParamType? type,
    String? serviceId,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        if (type == ProviderDetailsParamType.slug)
          ApiParam.providerSlug: providerIdOrSlug
        else
          ApiParam.partnerId: providerIdOrSlug,
        ApiParam.serviceId: serviceId,
        ApiParam.order: "DESC",
        ApiParam.sort: "created_at",
        ApiParam.offset: "0",
        ApiParam.limit: "100"
      };

      if (serviceId == null) {
        parameter.remove(ApiParam.serviceId);
      }
      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.getReview,
        useAuthToken: isAuthTokenRequired,
      );

      if (response.isEmpty) {
        return {"Reviews": [], "totalReviews": 0};
      }

      return {
        "Reviews": (response['data'] as List)
            .map((final e) => Reviews.fromJson(Map.from(e)))
            .toList(),
        "totalReviews": response['total']
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
