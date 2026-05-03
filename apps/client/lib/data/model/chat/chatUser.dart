import 'package:e_demand/app/generalImports.dart';

class ChatUser {
  final String id;
  final String name;
  final String translatedName;
  final String? profile;
  final ChatMessage? lastMessage;
  int unReadChats;
  final bool isBlockedByUser;
  final bool isBlockedByProvider;

  ///0 : Admin 1: Provider 2: customer
  final String receiverType;
  final String? senderType;
  final String? bookingId;
  final String? bookingStatus;
  final String? translatedOrderStatus;
  final String? providerId;
  final String senderId;

  ChatUser({
    required this.id,
    required this.name,
    required this.translatedName,
    required this.receiverType,
    this.senderType,
    required this.senderId,
    this.bookingId,
    this.bookingStatus,
    this.translatedOrderStatus,
    this.providerId,
    this.profile,
    this.lastMessage,
    required this.unReadChats,
    this.isBlockedByUser = false,
    this.isBlockedByProvider = false,
  });

  //getters for the UI
  int get unreadNotificationsCount => unReadChats;

  bool get hasUnreadMessages => unreadNotificationsCount != 0;

  String get userName =>
      translatedName.toString().isNotEmpty ? translatedName : name;

  String get avatar => profile ?? '';

  factory ChatUser.fromJson(Map<String, dynamic> json) {
    return ChatUser(
        id: (json['id'] ?? "0").toString(),
        name: json['name'] ?? json["partner_name"] ?? '',
        translatedName:
            (json["translated_partner_name"]?.toString() ?? '').isNotEmpty
                ? json["translated_partner_name"].toString()
                : (json["translated_name"]?.toString() ?? '').isNotEmpty
                    ? json["translated_name"].toString()
                    : json['name'] ?? json["partner_name"] ?? '',
        profile: json['profile'] ?? json["image"],
        lastMessage: json['last_message'] != null
            ? ChatMessage.fromJsonAPI(json['last_message'])
            : null,
        unReadChats: json['un_read_chats'] ?? 0,
        receiverType: json['receiver_type'] ?? '',
        senderType: json['sender_type'] ?? '',
        bookingId: json["booking_id"] ?? "0",
        bookingStatus: json["order_status"] ?? '',
        translatedOrderStatus:
            (json["translated_order_status"]?.toString() ?? '').isNotEmpty
                ? json["translated_order_status"]!.toString()
                : (json["order_status"]?.toString() ?? ''),
        providerId: json["partner_id"] ?? "0",
        senderId: json["sender_id"] ?? "0",
        isBlockedByUser: json['is_block_by_user'].toString() == "1",
        isBlockedByProvider: json['is_block_by_provider'].toString() == "1");
  }

  factory ChatUser.fromNotificationData(Map<String, dynamic> json) {
    // Determine sender type - check if explicitly provided or infer from receiver_type
    final String? senderType = json['sender_type']?.toString();

    // Get name - support both username/companyName fields
    String name = '';
    if (senderType == "0") {
      name = "customerSupport";
    } else {
      name = (json['username'] ??
              json['companyName'] ??
              json['company_name'] ??
              '')
          .toString();
    }

    // Get translated name - support both translated_username/translatedName fields
    String translatedName = '';
    if (senderType == "0") {
      translatedName = "customerSupport";
    } else {
      final translatedUsername = json['translated_username']?.toString() ?? '';
      final translatedNameField = json['translatedName']?.toString() ??
          json['translated_name']?.toString() ??
          '';

      translatedName = translatedUsername.isNotEmpty
          ? translatedUsername
          : translatedNameField.isNotEmpty
              ? translatedNameField
              : name;
    }

    return ChatUser(
      id: (json['sender_id'] ?? json['user_id'] ?? "0").toString(),
      name: name,
      translatedName: translatedName,
      profile: (json['profile_image'] ?? json['profile'] ?? '').toString(),
      lastMessage: null,
      unReadChats: (json['un_read_chats'] ?? json['unread_chats'] ?? 0).toInt(),
      receiverType:
          (json['receiver_type'] ?? json['receiverType'] ?? '').toString(),
      senderType: senderType ?? '',
      bookingId:
          (json["booking_id"] ?? json["order_id"] ?? json["bookingId"] ?? "0")
              .toString(),
      bookingStatus: (json["booking_status"] ??
              json["order_status"] ??
              json["bookingStatus"] ??
              '')
          .toString(),
      translatedOrderStatus:
          (json['translated_order_status']?.toString() ?? '').isNotEmpty
              ? json['translated_order_status']!.toString()
              : (json['booking_status']?.toString() ??
                  json['order_status']?.toString() ??
                  json['bookingStatus']?.toString() ??
                  ''),
      providerId: (json["provider_id"] ??
              json["partner_id"] ??
              json["providerId"] ??
              "0")
          .toString(),
      senderId: (json["receiver_id"] ?? json["receiverId"] ?? "0").toString(),
      isBlockedByUser:
          (json['is_block_by_user'] ?? json['isBlockedByUser'] ?? "0")
                  .toString() ==
              "1",
      isBlockedByProvider:
          (json['is_block_by_provider'] ?? json['isBlockedByProvider'] ?? "0")
                  .toString() ==
              "1",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': translatedName.toString().isNotEmpty ? translatedName : name,
      'partner_name':
          translatedName.toString().isNotEmpty ? translatedName : name,
      'profile': profile,
      'image': profile,
      'last_message': lastMessage?.toMap(),
      'un_read_chats': unReadChats,
      'receiver_type': receiverType,
      'sender_type': senderType,
      "booking_id": bookingId,
      "partner_id": providerId,
      "order_status": bookingStatus,
      'translated_order_status': translatedOrderStatus,
      "sender_id": senderId,
      "is_block_by_user": isBlockedByUser ? "1" : "0",
      "is_block_by_provider": isBlockedByProvider ? "1" : "0",
    };
  }

  ChatUser copyWith({
    String? id,
    String? name,
    String? translatedName,
    ChatMessage? lastMessage,
    int? unReadChats,
    String? receiverType,
    String? senderType,
    String? enquiryId,
    String? bookingId,
    String? bookingStatus,
    String? translatedOrderStatus,
    String? providerId,
    String? senderId,
    String? profile,
    bool? isBlockedByUser,
    bool? isBlockedByProvider,
  }) {
    return ChatUser(
      id: id ?? this.id,
      name: name ?? this.name,
      translatedName: translatedName ?? this.translatedName,
      profile: profile ?? this.profile,
      lastMessage: lastMessage ?? this.lastMessage,
      unReadChats: unReadChats ?? this.unReadChats,
      receiverType: receiverType ?? this.receiverType,
      senderType: senderType ?? this.senderType,
      bookingId: bookingId ?? this.bookingId,
      bookingStatus: bookingStatus ?? this.bookingStatus,
      translatedOrderStatus:
          translatedOrderStatus ?? this.translatedOrderStatus,
      providerId: providerId ?? this.providerId,
      senderId: senderId ?? this.senderId,
      isBlockedByUser: isBlockedByUser ?? this.isBlockedByUser,
      isBlockedByProvider: isBlockedByProvider ?? this.isBlockedByProvider,
    );
  }

  @override
  bool operator ==(covariant ChatUser other) {
    if (bookingId == "0" && other.bookingId == "0") {
      return other.providerId == providerId;
    }
    return other.bookingId == bookingId;
  }

  @override
  int get hashCode {
    if (bookingId == "0" && senderType != "0") {
      return providerId.hashCode;
    }
    if (senderType == "0") {
      return senderType.hashCode;
    }
    return bookingId.hashCode;
  }
}
