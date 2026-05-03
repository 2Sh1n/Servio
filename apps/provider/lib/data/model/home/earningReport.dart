class EarningReport {
  final String? adminCommission;
  final String? myIncome;
  final String? remainingIncome;
  final String? futureEarningFromBookings;

  EarningReport({
    this.adminCommission,
    this.myIncome,
    this.remainingIncome,
    this.futureEarningFromBookings,
  });

  EarningReport.fromJson(Map<String, dynamic> json)
    : adminCommission = json['admin_commission']?.toString() ?? '',
      myIncome = json['my_income']?.toString() ?? '',
      remainingIncome = json['remaining_income']?.toString() ?? '',
      futureEarningFromBookings =
          json['future_earning_from_bookings']?.toString() ?? '0';

  Map<String, dynamic> toJson() => {
    'admin_commission': adminCommission,
    'my_income': myIncome,
    'remaining_income': remainingIncome,
    'future_earning_from_bookings': futureEarningFromBookings,
  };
}
