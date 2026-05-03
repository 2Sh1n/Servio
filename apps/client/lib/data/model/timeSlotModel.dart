class AllSlots {
  AllSlots({this.time, this.isAvailable, this.message});

  AllSlots.fromJson(final Map<String, dynamic> json) {
    time = json["time"]?.toString() ?? '';
    isAvailable = json["is_available"]?.toInt() ?? 0;
    message = json["message"]?.toString() ?? '';
  }
  String? time;
  String? message;
  int? isAvailable;
}
