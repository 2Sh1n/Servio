import 'package:e_demand/app/generalImports.dart';

class CategoryRepository {
  ///This method is used to fetch categories
  Future fetchCategory({final int? limitOfAPIData, final int? offset}) async {
    try {
      final Map<String, dynamic> parameters = <String, dynamic>{
        ApiParam.longitude: HiveRepository.getLongitude,
        // HiveRepository.getLongitude,
        ApiParam.latitude: HiveRepository.getLatitude
      };
      final result = await ApiClient.post(
          parameter: parameters,
          url: ApiUrl.getCategories,
          useAuthToken: false);

      return (result['data'] as List)
          .map((final e) => CategoryModel.fromJson(Map.from(e)))
          .toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future fetchAllCategory() async {
    try {
      final Map<String, dynamic> parameters = <String, dynamic>{};
      final result = await ApiClient.post(
          parameter: parameters,
          url: ApiUrl.getCategoryHierarchical,
          useAuthToken: true);

      return (result['data'] as List)
          .map((final e) => AllCategoryModel.fromJson(Map.from(e)))
          .toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
