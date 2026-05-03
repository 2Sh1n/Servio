import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class BookmarkRepository {
  ///This method is used to fetch available Bookmark from database
  Future<Map<String, dynamic>> getBookmark({
    required final bool isAuthTokenRequired,
    required final Map<String, dynamic> parameter,
  }) async {
    try {
      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.bookMark,
        useAuthToken: isAuthTokenRequired,
      );
      return {
        "bookmark": (response['data'] as List)
            .map((final e) => Providers.fromJson(Map.from(e)))
            .toList(),
        "totalBookmark": (response['total'] ?? "0").toString()
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future addBookmark({
    required final bool isAuthTokenRequired,
    required final String providerID,
    required final BuildContext context,
  }) async {
    final Map<String, dynamic> parameter = <String, dynamic>{
      ApiParam.partnerId: providerID,
      ApiParam.type: "add"
    };
    try {
      final response = await ApiClient.post(
          url: ApiUrl.bookMark, parameter: parameter, useAuthToken: true);
      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future removeBookmark({
    required final bool isAuthTokenRequired,
    required final String providerID,
    required final BuildContext context,
  }) async {
    final Map<String, dynamic> parameter = <String, dynamic>{
      ApiParam.partnerId: providerID,
      ApiParam.type: "remove",
    };
    try {
      final response = await ApiClient.post(
          url: ApiUrl.bookMark, parameter: parameter, useAuthToken: true);

      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
