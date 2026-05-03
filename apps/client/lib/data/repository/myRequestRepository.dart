import 'package:e_demand/app/generalImports.dart';

class MyRequestRepository {
  Future<Map<String, dynamic>> submitCustomJobRequest({
    required Map<String, dynamic> parameters,
  }) async {
    try {
      final response = await ApiClient.post(
        parameter: parameters,
        url: ApiUrl.makeCustomJobRequest,
        useAuthToken: true,
      );
      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future fetchMyCustomJobRequests({
    required Map<String, dynamic> parameter,
  }) async {
    try {
      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.myCustomJobRequests,
        useAuthToken: true,
      );
      if (response['error'] == 'true') {
        return {"error": response['error'], "message": response['message']};
      }
      return {
        "data": (response['data'] as List)
            .map((final e) => MyRequestListModel.fromJson(Map.from(e)))
            .toList(),
        "total": response['total'] != null ? int.parse(response['total']) : 0
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> fetchMyCustomJobBidders(
      {required String customJobReqId}) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.customJobRequestId: customJobReqId,
      };
      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.customJobBidders,
        useAuthToken: true,
      );
      if (response['error'] == 'true') {
        return {"error": response['error'], "message": response['message']};
      }
      return {
        "bidders": (response['data']['bidders'] as List)
            .map((final e) => Bidder.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
        "customJob": CustomJob.fromJson(
            Map<String, dynamic>.from(response['data']['custom_job'])),
        "total": response['data']['total'] != null
            ? int.parse(response['data']['total'].toString())
            : 0
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future cancelMyBooking({required String customJobRequestId}) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.customJobRequestId: customJobRequestId,
      };
      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.cancelCustomJobRequest,
        useAuthToken: true,
      );
      if (response['error'] == 'true') {
        return {"error": response['error'], "message": response['message']};
      }
      return {
        "data": response['data'],
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Bidder> getUserBidderFromBidderId({
    required final bool useAuthToken,
    required final String customJobRequestId,
    required final String bidderId,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.customJobRequestId: customJobRequestId,
      };

      final response = await ApiClient.post(
        parameter: parameter,
        url: ApiUrl.customJobBidders,
        useAuthToken: true,
      );

      // Get the bidders list
      final biddersList =
          List<Map<String, dynamic>>.from(response['data']['bidders']);

      // If there's only one bidder, return it directly
      if (biddersList.length == 1) {
        return Bidder.fromJson(biddersList.first);
      }

      // Otherwise, search for the specific bidder
      for (var bidder in biddersList) {
        if (bidder['id'].toString() == bidderId.toString()) {
          return Bidder.fromJson(bidder);
        }
      }

      throw ApiException('Bidder not found');
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
