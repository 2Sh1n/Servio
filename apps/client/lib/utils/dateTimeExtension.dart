extension DateTimeExtensions on DateTime {
  bool isSameAs(DateTime date) {
    return year == date.year && month == date.month && day == date.day;
  }

  bool isToday() {
    return isSameAs(DateTime.now());
  }

  bool isYesterday() {
    return isSameAs(DateTime.now().subtract(const Duration(days: 1)));
  }

  bool isCurrentYear() {
    return year == DateTime.now().year;
  }
}
