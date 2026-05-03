class NotificationDataModel {
  String? id;
  String? title;
  String? message;
  String? type;
  String? typeId;
  String? image;
  String? orderId;
  String? isRead;
  DateTime dateSent;
  String? duration;
  String? url;
  String? providerName;
  String? translatedProviderName;
  String? categoryName;
  String? translatedCategoryName;
  String? notificationType;
  String? customJobRequestId;

  NotificationDataModel({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.typeId,
    required this.image,
    required this.orderId,
    required this.isRead,
    required this.dateSent,
    required this.duration,
    required this.url,
    required this.providerName,
    required this.translatedProviderName,
    required this.categoryName,
    required this.translatedCategoryName,
    required this.notificationType,
    required this.customJobRequestId,
  });

  factory NotificationDataModel.fromJson(final Map<String, dynamic> map) =>
      NotificationDataModel(
        id: map["id"]?.toString() ?? '',
        title: map["title"]?.toString() ?? '',
        message: map["message"]?.toString() ?? '',
        type: map["type"]?.toString() ?? '',
        typeId: map["type_id"]?.toString() ?? '',
        image: map["image"]?.toString() ?? '',
        orderId: map["order_id"]?.toString() ?? '',
        isRead: map["is_readed"]?.toString() ?? '',
        dateSent: DateTime.parse(map["date_sent"]?.toString() ?? ''),
        duration: map["duration"]?.toString() ?? '',
        url: map["url"]?.toString() ?? '',
        providerName: map["provider_name"]?.toString() ?? '',
        translatedProviderName:
            (map['translated_provider_name']?.toString() ?? '').isNotEmpty
                ? map['translated_provider_name']!.toString()
                : map["provider_name"]?.toString() ?? '',
        categoryName: map["category_name"]?.toString() ?? '',
        translatedCategoryName:
            map["translated_category_name"]?.toString() ?? '',
        notificationType: map["notification_type"]?.toString() ?? '',
        customJobRequestId: map["custom_job_request_id"]?.toString() ?? '',
      );
}
