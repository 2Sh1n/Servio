import 'dart:convert';

import 'package:edemand_partner/app/generalImports.dart';

enum ChatMessageType { textMessage, imageMessage, fileMessage }

class MessageDocument {
  final String fileName;
  final int fileSize;
  final String fileType;
  final String fileUrl;

  MessageDocument({
    required this.fileName,
    required this.fileSize,
    required this.fileType,
    required this.fileUrl,
  });

  String get getFileSizeString {
    const suffixes = ["B", "KB", "MB", "GB", "TB"];
    if (fileSize == 0) return '0 ${suffixes[0]}';
    final i = (log(fileSize) / log(1024)).floor();
    return "${(fileSize / pow(1024, i)).toStringAsFixed(0)} ${suffixes[i]}";
  }

  factory MessageDocument.fromJson(Map<String, dynamic> json) {
    return MessageDocument(
      fileName: json['file_name'] ?? '',
      fileSize: json['file_size'] ?? 0,
      fileType: json['file_type'] ?? '',
      fileUrl: json['file'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'file_name': fileName,
      'file_size': fileSize,
      'file_type': fileType,
      'file': fileUrl,
    };
  }

  @override
  String toString() {
    return 'Document(fileName: $fileName, fileSize: $fileSize, fileType: $fileType, fileUrl: $fileUrl)';
  }
}

class SenderDetails {
  final int id;
  final String name;
  final String profile;
  final String role;

  SenderDetails({
    required this.id,
    required this.name,
    required this.profile,
    required this.role,
  });

  factory SenderDetails.fromJson(Map<String, dynamic> json) {
    return SenderDetails(
      id: int.parse((json['id'] ?? "0").toString()),
      name: json['username'] ?? '',
      profile: json['image'] ?? '',
      role: json['role'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {'id': id, 'name': name, 'profile': profile, 'role': role};
  }

  @override
  String toString() {
    return 'SenderDetails(id: $id, name: $name, profile: $profile, role: $role)';
  }
}

class ChatMessage {
  String id;
  DateTime sendOrReceiveDateTime;
  String senderId;
  String? receiverId;
  bool isLocallyStored;
  String message;
  ChatMessageType messageType;
  List<MessageDocument> files;
  SenderDetails senderDetails;

  ChatMessage({
    required this.id,
    required this.sendOrReceiveDateTime,
    required this.senderId,
    required this.isLocallyStored,
    required this.message,
    required this.files,
    required this.senderDetails,
    this.receiverId,
    this.messageType = ChatMessageType.textMessage,
  });

  //this constructor will return proper message type according to the data provided
  factory ChatMessage.fromJsonAPI(Map<String, dynamic> json) {
    ChatMessageType messageType = ChatMessageType.textMessage;

    final List<MessageDocument> documents = [];
    if (((json["file"] ?? []) as List).isNotEmpty) {
      documents.addAll(
        (json["file"] as List).map((e) => MessageDocument.fromJson(e)).toList(),
      );
      bool isThereAnyFile = false;
      for (int i = 0; i < documents.length; i++) {
        if (!documents[i].fileUrl.isImage()) {
          isThereAnyFile = true;
        }
      }
      if (isThereAnyFile) {
        messageType = ChatMessageType.fileMessage;
      } else {
        messageType = ChatMessageType.imageMessage;
      }
    }

    return ChatMessage(
      files: documents,
      id: json['id']?.toString() ?? "0",
      message: json['message']?.toString() ?? '',
      messageType: messageType,
      sendOrReceiveDateTime:
          DateTime.tryParse(json['created_at']?.toString() ?? '')?.toLocal() ??
          DateTime.now(),
      senderId: json['sender_id']?.toString() ?? "0",
      isLocallyStored: false,
      receiverId: json['receiver_id']?.toString(),
      senderDetails: SenderDetails.fromJson(json['sender_details'] ?? {}),
    );
  }

  factory ChatMessage.fromNotificationData(Map json) {
    ChatMessageType messageType = ChatMessageType.textMessage;

    final List<MessageDocument> documents = [];

    // Safely decode file JSON, defaulting to empty list if null/empty/invalid
    List? file;
    try {
      final fileValue = json["file"];
      if (fileValue != null && fileValue.toString().trim().isNotEmpty) {
        file = jsonDecode(fileValue.toString());
      }
    } catch (e) {
      // If JSON decode fails, file remains null
      file = null;
    }

    if (file != null &&
        file.isNotEmpty &&
        (file[0] as List?)?.isNotEmpty == true) {
      documents.addAll(
        (file[0] as List).map((e) => MessageDocument.fromJson(e)).toList(),
      );
      bool isThereAnyFile = false;
      for (int i = 0; i < documents.length; i++) {
        if (!documents[i].fileUrl.isImage()) {
          isThereAnyFile = true;
        }
      }
      if (isThereAnyFile) {
        messageType = ChatMessageType.fileMessage;
      } else {
        messageType = ChatMessageType.imageMessage;
      }
    }

    // Safely decode sender_details JSON, defaulting to null if null/empty/invalid
    List? senderDetails;
    try {
      final senderDetailsValue = json["sender_details"];
      if (senderDetailsValue != null &&
          senderDetailsValue.toString().trim().isNotEmpty) {
        senderDetails = jsonDecode(senderDetailsValue.toString());
      }
    } catch (e) {
      // If JSON decode fails, senderDetails remains null
      senderDetails = null;
    }

    return ChatMessage(
      files: documents,
      id: (json['id'] ?? "0").toString(),
      message: json['message'] ?? '',
      messageType: messageType,
      sendOrReceiveDateTime:
          DateTime.tryParse(json['last_message_date'].toString())?.toLocal() ??
          DateTime.now(),
      senderId: json['sender_id']?.toString() ?? "0",
      isLocallyStored: false,
      receiverId: json['receiver_id'],
      senderDetails: SenderDetails.fromJson(senderDetails?[0] ?? {}),
    );
  }

  ChatMessage copyWith({
    String? id,
    DateTime? sendOrReceiveDateTime,
    String? senderId,
    bool? isLocallyStored,
    String? message,
    List<MessageDocument>? files,
  }) {
    return ChatMessage(
      id: id ?? this.id,
      sendOrReceiveDateTime:
          sendOrReceiveDateTime ?? this.sendOrReceiveDateTime,
      senderId: senderId ?? this.senderId,
      isLocallyStored: isLocallyStored ?? this.isLocallyStored,
      message: message ?? this.message,
      files: files ?? this.files,
      senderDetails: senderDetails,
    );
  }

  Map<String, dynamic> toMap() {
    return <String, dynamic>{
      'id': id,
      'sendOrReceiveDateTime': sendOrReceiveDateTime.millisecondsSinceEpoch,
      'senderId': senderId,
      'receiverId': receiverId,
      'isLocallyStored': isLocallyStored,
      'message': message,
      'files': files.map((e) => e.toJson()).toList(),
      'senderDetails': senderDetails.toJson(),
      'messageType': messageType.toString(),
    };
  }

  factory ChatMessage.fromMap(Map<String, dynamic> map) {
    return ChatMessage(
      id: map['id']?.toString() ?? '0',
      sendOrReceiveDateTime: DateTime.fromMillisecondsSinceEpoch(
        map['sendOrReceiveDateTime']?.toInt() ?? 0,
      ),
      senderId: map['senderId']?.toString() ?? '0',
      isLocallyStored: map['isLocallyStored'] as bool,
      message: map['message']?.toString() ?? '',
      receiverId: map['receiverId']?.toString() ?? '0',
      senderDetails: SenderDetails.fromJson(map['senderDetails']),
      files:
          (map['files'] as List?)
              ?.map((e) => MessageDocument.fromJson(e))
              .toList() ??
          [],
      messageType: ChatMessageType.values.firstWhere(
        (element) => element.toString() == map['messageType'],
        orElse: () => ChatMessageType.textMessage,
      ),
    );
  }

  String toJson() => json.encode(toMap());

  factory ChatMessage.fromJson(String source) =>
      ChatMessage.fromMap(json.decode(source) as Map<String, dynamic>);

  @override
  String toString() {
    return 'ChatMessage(id: $id, sendOrReceiveDateTime: $sendOrReceiveDateTime, senderId: $senderId, isLocallyStored: $isLocallyStored, message: $message, files: $files)';
  }
}
