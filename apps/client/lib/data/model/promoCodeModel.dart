class Promocode {
  Promocode({
    this.id,
    this.promoCode,
    this.message,
    this.translatedMessage,
    this.startDate,
    this.endDate,
    this.noOfUsers,
    this.minimumOrderAmount,
    this.discount,
    this.discountType,
    this.maxDiscountAmount,
    this.repeatUsage,
    this.noOfRepeatUsage,
    this.image,
  });

  Promocode.fromJson(final Map<String, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    promoCode = json["promo_code"]?.toString() ?? '';
    message = json["message"]?.toString() ?? '';
    translatedMessage =
        (json["translated_message"]?.toString() ?? '').isNotEmpty
            ? json["translated_message"]!.toString()
            : (json["message"]?.toString() ?? '');

    startDate = json['start_date']?.toString().split(" ").first ?? '';
    endDate = json['end_date']?.toString().split(" ").first ?? '';
    noOfUsers = json["no_of_users"]?.toString() ?? '';
    minimumOrderAmount = json["minimum_order_amount"]?.toString() ?? '';
    discount = json["discount"]?.toString() ?? '';
    discountType = json["discount_type"]?.toString() ?? '';
    maxDiscountAmount = json["max_discount_amount"]?.toString() ?? '';
    repeatUsage = json["repeat_usage"]?.toString() ?? '';
    noOfRepeatUsage = json["no_of_repeat_usage"]?.toString() ?? "1";
    image = json["image"]?.toString() ?? '';
    status = json["status"]?.toString() ?? '';
  }

  String? id;
  String? promoCode;
  String? message;
  String? translatedMessage;
  String? startDate;
  String? endDate;
  String? noOfUsers;
  String? minimumOrderAmount;
  String? discount;
  String? discountType;
  String? maxDiscountAmount;
  String? repeatUsage;
  String? noOfRepeatUsage;
  String? image;
  String? status;
}
