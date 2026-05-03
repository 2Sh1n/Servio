class CashCollectionModel {
  const CashCollectionModel({
    this.id,
    this.message,
    this.commissionAmount,
    this.status,
    this.translatedStatus,
    this.date,
    this.orderID,
  });

  factory CashCollectionModel.fromJson(Map<String, dynamic> json) {
    return CashCollectionModel(
      id: json['id']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      commissionAmount: json['commison']?.toString() ?? '',
      status: json['status']?.toString() == 'admin_cash_recevied'
          ? 'paid'
          : 'received',
      translatedStatus: (json['translated_status']?.toString() ?? '').isNotEmpty
          ? json['translated_status'].toString()
          : (json['status']?.toString() == 'admin_cash_recevied'
                ? 'paid'
                : 'received'),
      orderID: json['order_id']?.toString() ?? '',
      date: json['date']?.toString() ?? '',
    );
  }

  final String? id;
  final String? message;
  final String? commissionAmount;
  final String? status;
  final String? translatedStatus;
  final String? date;
  final String? orderID;
}
