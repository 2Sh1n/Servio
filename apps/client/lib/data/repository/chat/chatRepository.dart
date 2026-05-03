import 'package:dio/dio.dart';
import 'package:e_demand/data/model/chat/blockUserList.dart';
import 'package:e_demand/data/model/chat/reportReasonModel.dart';

import 'package:path/path.dart' as p;

import '../../../app/generalImports.dart';

class ChatRepository {
  String getChatType(String value) {
    final Map<String, String> mapping = {
      'enquiryChats': ApiParam.preBookingChats,
      'bookingChats': ApiParam.bookingChats,
    };

    return mapping[value] ?? 'Unknown';
  }

  Future<Map<String, dynamic>> fetchChatUsers(
      {required int offset,
      String? searchString,
      required String filterType,
      required String orderStatus}) async {
    try {
      final response = await ApiClient.post(
        url: ApiUrl.getChatUsers,
        useAuthToken: true,
        parameter: {
          ApiParam.offset: offset.toString(),
          ApiParam.limit: UiUtils.chatLimit.toString(),
          if (filterType != "enquiryChats" && orderStatus != 'all')
            ApiParam.orderstatus: orderStatus,
          ApiParam.filterType: getChatType(filterType),
          if (searchString != null) ApiParam.search: searchString.trim()
        },
      );

      final List<ChatUser> chatUsers = [];

      for (int i = 0; i < response['data'].length; i++) {
        chatUsers.add(ChatUser.fromJson(response['data'][i]));
      }

      return {
        "chatUsers": chatUsers,
        "totalItems": int.tryParse((response['total'] ?? "1").toString()),
        "totalUnreadUsers": 0, //unused response['total_unread_users'] ??
      };
    } catch (error) {
      if (kDebugMode) {}
      throw ApiException(error.toString());
    }
  }

  Future<Map<String, dynamic>> fetchChatMessages({
    required int offset,
    required String bookingId,
    String? providerId,
    required String type,
  }) async {
    try {
      final Map<String, dynamic> parameter = {
        ApiParam.offset: offset.toString(),
        ApiParam.limit: UiUtils.chatLimit.toString(),
        ApiParam.bookingId: bookingId,
        ApiParam.type: type,
        ApiParam.providerId: providerId
      };

      parameter.removeWhere((key, value) =>
          value == "null" ||
          value == null ||
          value == '' ||
          (key == "booking_id" && (value == "0" || value == "-1")));

      final response = await ApiClient.post(
          url: ApiUrl.getChatMessages,
          useAuthToken: true,
          parameter: parameter);

      final List<ChatMessage> chatMessage = [];

      for (int i = 0; i < response['data'].length; i++) {
        chatMessage.add(ChatMessage.fromJsonAPI(response['data'][i]));
      }

      return {
        "chatMessages": chatMessage,
        "totalItems": int.tryParse((response['total'] ?? "0").toString()),
        "isBlockedByUser":
            response['is_block_by_user'].toString() == "1" ? true : false,
        "isBlockedByProvider":
            response['is_block_by_provider'].toString() == "1" ? true : false,
      };
    } catch (error) {
      if (kDebugMode) {}
      throw ApiException(error.toString());
    }
  }

  Future<ChatMessage> sendChatMessage({
    required String message,
    List<String> filePaths = const [],
    required String receiverId,

    ///0 : Admin 1: Provider
    required String sendMessageTo,
    String? bookingId,
  }) async {
    try {
      final Map<String, dynamic> parameter = {
        ApiParam.receiverId: receiverId,
        ApiParam.message: message,
        ApiParam.receiverType: sendMessageTo,
        ApiParam.type: sendMessageTo,
        ApiParam.bookingId: bookingId,
      };
      if (bookingId == "0" ||
          bookingId == "null" ||
          bookingId == null ||
          bookingId == "-1") {
        parameter.remove(ApiParam.bookingId);
      }
      if (receiverId == "-" || sendMessageTo == "0") {
        parameter.remove(ApiParam.receiverId);
      }
      if (message.isEmpty) {
        parameter.remove(ApiParam.message);
      }
      if (filePaths.isNotEmpty) {
        for (int i = 0; i < filePaths.length; i++) {
          final mimeType = lookupMimeType(filePaths[i]);
          final extension = mimeType!.split("/");

          final imagePart = await MultipartFile.fromFile(
            filePaths[i],
            filename: p.basename(filePaths[i]),
            contentType: MediaType(extension[0], extension[1]),
          );
          parameter["attachment[$i]"] = imagePart;
        }
      }

      final response = await ApiClient.post(
          url: ApiUrl.sendChatMessage,
          parameter: parameter,
          useAuthToken: true);

      return ChatMessage.fromJsonAPI(response['data']);
    } catch (e) {
      throw ApiException("somethingWentWrong");
    }
  }

  Future<String> blockUserWitReport({
    required String reasonId,
    required String additionalInfo,
    required String providerId,
  }) async {
    try {
      final Map<String, dynamic> parameter = {
        ApiParam.partnerId: providerId,
        ApiParam.reasonId: reasonId,
        ApiParam.additionalInfo: additionalInfo,
      };
      parameter.removeWhere(
          (key, value) => value == "null" || value == null || value == '');
      final response = await ApiClient.post(
          url: ApiUrl.blockUser, parameter: parameter, useAuthToken: true);

      return response['message'] as String;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<List<ReportReasonModel>> getReportReasons() async {
    try {
      final response =
          await ApiClient.get(url: ApiUrl.getReportReasons, useAuthToken: true);
      if (response['data'].isEmpty) {
        return [];
      }
      return (response['data'] as List<dynamic>)
          .map((e) => ReportReasonModel.fromJson(e))
          .toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<String> unblockUser({required String providerId}) async {
    try {
      final response = await ApiClient.post(
        url: ApiUrl.unblockUser,
        parameter: {ApiParam.partnerId: providerId},
        useAuthToken: true,
      );
      return response['message'];
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<String> deleteChat(
      {required String partnerId, required String bookingId}) async {
    try {
      final Map<String, dynamic> parameter = {
        ApiParam.partnerId: partnerId,
        ApiParam.bookingId: bookingId,
      };
      parameter.removeWhere((key, value) =>
          value == "null" || value == null || value == '' || value == "0");
      final response = await ApiClient.post(
        url: ApiUrl.deleteChat,
        parameter: parameter,
        useAuthToken: true,
      );
      return response['message'];
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<List<BlockedUserModel>> getBlockedProviders() async {
    try {
      final response = await ApiClient.get(
          url: ApiUrl.getBlockedProvider, useAuthToken: true);
      if (response['data'].isEmpty) {
        return [];
      }
      return (response['data'] as List<dynamic>)
          .map((e) => BlockedUserModel.fromJson(e))
          .toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
