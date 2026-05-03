import 'package:e_demand/app/generalImports.dart';

class SubCategoryRepository {
  ///This method is used to fetch subCategories
  Future<Map<String, dynamic>> fetchSubCategory({
    required final bool isAuthTokenRequired,
    required final String categoryID,
  }) async {
    try {
      final Map<String, dynamic> parameters = <String, dynamic>{
        ApiParam.categoryId: categoryID,
        ApiParam.longitude: HiveRepository.getLongitude,
        ApiParam.latitude: HiveRepository.getLatitude,
      };

      final result = await ApiClient.post(
          url: ApiUrl.getSubCategories,
          parameter: parameters,
          useAuthToken: false);

      return {
        "subCategoryList": (result['data'] as List)
            .map((final subCategoryData) =>
                SubCategory.fromJson(Map.from(subCategoryData)))
            .toList(),
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
