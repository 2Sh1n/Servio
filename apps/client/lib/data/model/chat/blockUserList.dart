class BlockedUserModel {
  final String? id;
  final String? username;
  final String? translatedProviderName;
  final String? email;
  final String? phone;
  final String? image;
  final String? reason;
  final String? translatedReason;
  final String? additionalInfo;
  final String? blockedDate;

  BlockedUserModel({
    this.id,
    this.username,
    this.translatedProviderName,
    this.email,
    this.phone,
    this.image,
    this.reason,
    this.translatedReason,
    this.additionalInfo,
    this.blockedDate,
  });

  BlockedUserModel.fromJson(Map<String, dynamic> json)
      : id = json['id']?.toString() ?? '',
        username = json['username']?.toString() ?? '',
        translatedProviderName =
            (json['translated_provider_name']?.toString() ?? '').isNotEmpty
                ? json['translated_provider_name']!.toString()
                : (json['username']?.toString() ?? ''),
        email = json['email']?.toString() ?? '',
        phone = json['phone']?.toString() ?? '',
        image = json['image']?.toString() ?? '',
        reason = json['reason']?.toString() ?? '',
        translatedReason =
            (json['translated_reason']?.toString() ?? '').isNotEmpty
                ? json['translated_reason']!.toString()
                : (json['reason']?.toString() ?? ''),
        additionalInfo = json['additional_info']?.toString() ?? '',
        blockedDate = json['blocked_date']?.toString() ?? '';

  Map<String, dynamic> toJson() => {
        'id': id,
        'username': username,
        'translated_provider_name': translatedProviderName,
        'email': email,
        'phone': phone,
        'image': image,
        'reason': reason,
        'translated_reason': translatedReason,
        'additional_info': additionalInfo,
        'blocked_date': blockedDate
      };
}
