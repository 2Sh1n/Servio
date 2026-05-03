import 'package:edemand_partner/data/model/chat/chatMessage.dart';

class ChatUser {
  final String id;
  final String name;
  final String? profile;
  final ChatMessage? lastMessage;
  int unReadChats;

  ///0 : Admin 1: Provider 2: customer
  final String receiverType;
  final String? bookingId;
  final String? bookingStatus;
  final String? bookingTranslatedStatus;
  final String? providerId;
  final String? senderId;

  ChatUser({
    required this.id,
    required this.name,
    required this.receiverType,
    this.bookingId,
    this.bookingStatus,
    this.bookingTranslatedStatus,
    this.providerId,
    this.senderId,
    this.profile,
    this.lastMessage,
    required this.unReadChats,
  });

  int get unreadNotificationsCount => unReadChats;

  bool get hasUnreadMessages => unreadNotificationsCount != 0;

  String get userName => name;

  String get avatar => profile ?? '';

  factory ChatUser.fromJson(Map<String, dynamic> json) {
    return ChatUser(
      id: (json['id'] ?? json["customer_id"] ?? "0").toString(),
      name: json['name'] ?? json["customer_name"] ?? '',
      profile: json['profile'] ?? json["image"],
      lastMessage: json['last_message'] != null
          ? ChatMessage.fromJsonAPI(json['last_message'])
          : null,
      unReadChats: json['un_read_chats'] ?? 0,
      receiverType: json['receiver_type'] ?? '',
      bookingId: json["booking_id"] ?? "0",
      bookingStatus: json["order_status"] ?? json["booking_status"] ?? '',
      bookingTranslatedStatus: json["translated_booking_status"] ?? json["order_status"] ?? '',
      providerId: json["partner_id"] ?? "0",
      senderId: json["sender_id"] ?? "0",
    );
  }

  factory ChatUser.fromNotificationData(Map<String, dynamic> json) {
    return ChatUser(
      id: (json['sender_id'] ?? "0").toString(),
      //if admin send the message then name will be customer support
      name: json["sender_type"] == "0"
          ? "customerSupport"
          : json['username'] ?? '',
      profile: json['profile_image'] ?? '',
      lastMessage: null,
      unReadChats: json['un_read_chats'] ?? 0,
      receiverType: json['sender_type'] ?? '',
      bookingId: json["booking_id"] ?? "0",
      bookingStatus: json["order_status"] ?? '',
      bookingTranslatedStatus: json["translated_booking_status"] ?? json["order_status"] ?? '',
      providerId: json["partner_id"] ?? "0",
      senderId: json["receiver_id"] ?? "0",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'partner_name': name,
      'profile': profile,
      'image': profile,
      'last_message': lastMessage?.toMap(),
      'un_read_chats': unReadChats,
      'receiver_type': receiverType,
      "booking_id": bookingId,
      "partner_id": providerId,
      "sender_id": senderId,
      "order_status": bookingStatus,
      'translated_booking_status':bookingTranslatedStatus
    };
  }

  ChatUser copyWith({
    String? id,
    String? name,
    ChatMessage? lastMessage,
    int? unReadChats,
    String? receiverType,
    String? enquiryId,
    String? bookingId,
    String? bookingStatus,
    String? bookingTranslatedStatus,
    String? providerId,
    String? senderId,
    String? profile,
  }) {
    return ChatUser(
      id: id ?? this.id,
      name: name ?? this.name,
      profile: profile ?? profile,
      lastMessage: lastMessage ?? this.lastMessage,
      unReadChats: unReadChats ?? this.unReadChats,
      receiverType: receiverType ?? this.receiverType,
      bookingId: bookingId ?? this.bookingId,
      bookingStatus: bookingStatus ?? this.bookingStatus,
      bookingTranslatedStatus: bookingTranslatedStatus ?? this.bookingTranslatedStatus,
      providerId: providerId ?? this.providerId,
      senderId: senderId ?? this.senderId,
    );
  }

  @override
  bool operator ==(covariant ChatUser other) {
    if (bookingId == "0" && other.bookingId == "0") {
      return other.id == id;
    }
    return other.bookingId == bookingId;
  }

  @override
  int get hashCode {
    if (bookingId == "0") {
      return id.hashCode;
    }
    return bookingId.hashCode;
  }
}
