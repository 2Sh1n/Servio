import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/model/blogs/blogCategoryModel.dart';
import 'package:e_demand/data/model/blogs/blogModel.dart';

class BlogsRepository {
  Future<Map<String, dynamic>> getBlogs(
      {final String? offset,
      final String? limit,
      final String? categoryId,
      final String? id,
      final String? tagSlug}) async {
    final parameter = <String, dynamic>{
      "offset": offset,
      "limit": limit,
      "category_id": categoryId,
      "id": id,
      "tag": tagSlug,
    };
    parameter
        .removeWhere((final key, final value) => value == null || value == "");

    try {
      final response = await ApiClient.get(
        url: ApiUrl.getBlogs,
        useAuthToken: true,
        queryParameters: parameter,
      );
      if (response["data"].isEmpty) {
        return {
          "data": <BlogModel>[],
          "total": 0,
          "error": response['error'],
          "message": response['message'],
        };
      }

      return {
        "data": response["data"],
        "total": response['total'],
        "error": response['error'],
        "message": response['message'],
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> getBlogCategories() async {
    try {
      final response = await ApiClient.get(
        url: ApiUrl.getBlogCategories,
        useAuthToken: true,
      );

      if (response["data"] == null || (response["data"] as List).isEmpty) {
        return {
          "blogCategories": <BlogCategoryModel>[],
          "error": response['error'],
          "message": response['message'],
        };
      }

      return {
        "blogCategories": (response["data"] as List)
            .map((final value) => BlogCategoryModel.fromJson(Map.from(value)))
            .toList(),
        "error": response['error'],
        "message": response['message'],
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> getBlogDetails(String blogId) async {
    final parameter = <String, dynamic>{
      "id": blogId,
    };

    try {
      final response = await ApiClient.post(
        url: ApiUrl.getBlogDetails,
        useAuthToken: true,
        parameter: parameter,
      );

      if (response["data"] == null || response["data"]["blog"] == null) {
        return {
          "blogDetails": null,
          "relevantBlogs": <BlogModel>[],
          "error": response['error'],
          "message": response['message'],
        };
      }

      return {
        "blogDetails": response["data"]["blog"],
        "relevantBlogs": (response["data"]["related_blogs"] as List?)
                ?.map((final value) => BlogModel.fromJson(Map.from(value)))
                .toList() ??
            <BlogModel>[],
        "error": response['error'],
        "message": response['message'],
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
