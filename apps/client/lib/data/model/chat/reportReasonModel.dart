class ReportReasonModel {
  final String? id;
  final String? reason;
  final String? needsAdditionalInfo;
  final String? translatedReason;
  final String? type;

  ReportReasonModel(
      {this.id,
      this.reason,
      this.needsAdditionalInfo,
      this.type,
      this.translatedReason});

  ReportReasonModel copyWith({
    String? id,
    String? reason,
    String? needsAdditionalInfo,
    String? type,
    String? translatedReason,
  }) {
    return ReportReasonModel(
      id: id ?? this.id,
      reason: reason ?? this.reason,
      needsAdditionalInfo: needsAdditionalInfo ?? this.needsAdditionalInfo,
      type: type ?? this.type,
      translatedReason: translatedReason ?? this.translatedReason,
    );
  }

  ReportReasonModel.fromJson(Map<String, dynamic> json)
      : id = json['id']?.toString() ?? '',
        reason = json['reason']?.toString() ?? '',
        needsAdditionalInfo = json['needs_additional_info']?.toString() ?? '',
        type = json['type']?.toString() ?? '',
        translatedReason =
            (json['translated_reason']?.toString() ?? '').isNotEmpty
                ? json['translated_reason']!.toString()
                : (json['type']?.toString() ?? '');

  Map<String, dynamic> toJson() => {
        'id': id,
        'reason': reason,
        'needs_additional_info': needsAdditionalInfo,
        'type': type,
        'translated_reason': translatedReason
      };
}
