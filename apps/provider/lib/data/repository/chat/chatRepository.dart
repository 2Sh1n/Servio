import 'package:edemand_partner/data/model/chat/blockUserList.dart';
import 'package:edemand_partner/data/model/chat/reportReasonModel.dart';
import 'package:path/path.dart' as p;

import '../../../app/generalImports.dart';

class ChatRepository {
  Future<Map<String, dynamic>> fetchChatUsers({
    required int offset,
    String? searchString,
  }) async {
    try {
      final response = await ApiClient.post(
        url: ApiUrl.getChatUsers,
        useAuthToken: true,
        parameter: {
          ApiParam.offset: offset.toString(),
          ApiParam.limit: UiUtils.chatLimit,
          if (searchString != null) ApiParam.search: searchString.trim(),
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
      throw ApiException(error.toString());
    }
  }

  Future<Map<String, dynamic>> fetchChatMessages({
    required int offset,
    required String bookingId,
    required String type,
    required String customerId,
  }) async {
    try {
      final Map<String, dynamic> parameter = {
        ApiParam.offset: offset.toString(),
        ApiParam.limit: UiUtils.chatLimit,
        ApiParam.bookingId: bookingId,
        ApiParam.type: type,
        ApiParam.customerId: customerId,
      };

      parameter.removeWhere(
        (key, value) =>
            value == null ||
            value == "null" ||
            (key == "booking_id" && (value == "0" || value == "-1")) ||
            value == "-",
      );

      final response = await ApiClient.post(
        url: ApiUrl.getChatMessages,
        useAuthToken: true,
        parameter: parameter,
      );

      final List<ChatMessage> chatMessage = [];

      for (int i = 0; i < response['data'].length; i++) {
        chatMessage.add(ChatMessage.fromJsonAPI(response['data'][i]));
      }

      return {
        "chatMessages": chatMessage,
        "totalItems": int.tryParse((response['total'] ?? "0").toString()),
        "isBlockedByUser": response['is_block_by_user'].toString() == "1"
            ? true
            : false,
        "isBlockedByProvider":
            response['is_block_by_provider'].toString() == "1" ? true : false,
      };
    } catch (error) {
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
        ApiParam.bookingId: bookingId,
      };
      if (bookingId == "0" || bookingId == "-1") {
        parameter.remove(ApiParam.bookingId);
      }
      if (receiverId == "-") {
        parameter.remove(ApiParam.receiverId);
      }
      if (message.isEmpty) {
        parameter.remove(ApiParam.message);
      }
      if (filePaths.isNotEmpty) {
        for (int i = 0; i < filePaths.length; i++) {
          final imagePart = await MultipartFile.fromFile(
            filePaths[i],
            filename: p.basename(filePaths[i]),
          );
          parameter["attachment[$i]"] = imagePart;
        }
      }

      final response = await ApiClient.post(
        url: ApiUrl.sendChatMessage,
        parameter: parameter,
        useAuthToken: true,
      );

      return ChatMessage.fromJsonAPI(response['data']);
    } catch (e) {
      throw ApiException('somethingWentWrongTitle');
    }
  }

  Future<String> blockUserWitReport({
    required String reasonId,
    required String additionalInfo,
    required String userId,
  }) async {
    final Map<String, dynamic> parameter = {
      ApiParam.userId: userId,
      ApiParam.reasonId: reasonId,
      ApiParam.additionalInfo: additionalInfo,
    };
    // Don't remove additionalInfo even if empty, as API requires it
    parameter.removeWhere(
      (key, value) =>
          (value == null || value == "null") && key != ApiParam.additionalInfo,
    );
    try {
      final response = await ApiClient.post(
        url: ApiUrl.blockUser,
        parameter: parameter,
        useAuthToken: true,
      );

      // Handle both string and map responses
      if (response['message'] is String) {
        return response['message'] as String;
      } else if (response['message'] is Map) {
        // If message is a map, it contains validation errors
        final Map<String, dynamic> errors =
            response['message'] as Map<String, dynamic>;
        // Extract the first error message
        final String firstError = errors.values.first.toString();
        throw ApiException(firstError);
      } else {
        return response['message'].toString();
      }
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<List<BlockedUserModel>> getBlockedUsers() async {
    try {
      final response = await ApiClient.get(
        url: ApiUrl.getBlockedUsers,
        useAuthToken: true,
      );
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

  Future<List<ReportReasonModel>> getReportReasons() async {
    try {
      final response = await ApiClient.get(
        url: ApiUrl.getReportReasons,
        useAuthToken: true,
      );
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

  Future<Map<String, dynamic>> unblockUser({required String userId}) async {
    try {
      final response = await ApiClient.post(
        url: ApiUrl.unblockUser,
        parameter: {ApiParam.userId: userId},
        useAuthToken: true,
      );
      return {"message": response['message'], "userId": userId};
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<String> deleteChat({
    required String userId,
    required String bookingId,
  }) async {
    try {
      final Map<String, dynamic> parameter = {
        ApiParam.userId: userId,
        ApiParam.bookingId: bookingId,
      };
      parameter.removeWhere(
        (key, value) => value == null || value == "null" || value == "0",
      );
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
}
