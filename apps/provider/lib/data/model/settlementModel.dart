class SettlementModel {
  SettlementModel({
    this.id,
    this.providerId,
    this.message,
    this.date,
    this.amount,
    this.status,
    this.translatedStatus
  });

  factory SettlementModel.fromJson(Map<String, dynamic> json) =>
      SettlementModel(
        id: json['id']?.toString() ?? '',
        providerId: json['provider_id']?.toString() ?? '',
        message: json['message']?.toString() ?? '',
        date: json['date']?.toString() ?? '',
        amount: json['amount']?.toString() ?? '',
        status: json['status']?.toString() ?? '',
        translatedStatus: json['translated_status']?.toString() ?? '',
      );

  final String? id;
  final String? providerId;
  final String? message;
  final String? date;
  final String? amount;
  final String? status;
  final String? translatedStatus;
}
