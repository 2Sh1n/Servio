class JobRequestModel {
  String? id;
  String? userId;
  String? categoryId;
  String? serviceTitle;
  String? serviceShortDescription;
  String? minPrice;
  String? maxPrice;
  String? requestedStartDate;
  String? requestedStartTime;
  String? requestedEndDate;
  String? requestedEndTime;
  DateTime? createdAt;
  String? updatedAt;
  String? status;
  String? username;
  String? image;
  String? categoryName;
  String? categoryImage;
  String? partnerId;
  String? counterPrice;
  String? note;
  String? duration;
  String? customJobRequestId;
  String? customJobId;
  String? taxId;
  String? taxAmount;
  String? taxPercentage;
  String? finalTotal;
  String? translatedServiceTitle;
  String? translatedServiceShortDescription;
  String? translatedCategoryName;
  bool? bidApplied;

  JobRequestModel({
    this.id,
    this.userId,
    this.categoryId,
    this.serviceTitle,
    this.serviceShortDescription,
    this.minPrice,
    this.maxPrice,
    this.requestedStartDate,
    this.requestedStartTime,
    this.requestedEndDate,
    this.requestedEndTime,
    this.createdAt,
    this.updatedAt,
    this.status,
    this.username,
    this.image,
    this.categoryName,
    this.categoryImage,
    this.partnerId,
    this.counterPrice,
    this.note,
    this.duration,
    this.customJobRequestId,
    this.customJobId,
    this.taxId,
    this.taxAmount,
    this.taxPercentage,
    this.finalTotal,
    this.translatedServiceTitle,
    this.translatedServiceShortDescription,
    this.translatedCategoryName,
    this.bidApplied,
  });

  JobRequestModel.fromJson(Map<String, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    userId = json["user_id"]?.toString() ?? '';
    categoryId = json["category_id"]?.toString() ?? '';
    serviceTitle = json["service_title"]?.toString() ?? '';
    serviceShortDescription =
        json["service_short_description"]?.toString() ?? '';
    minPrice = json["min_price"]?.toString() ?? '';
    maxPrice = json["max_price"]?.toString() ?? '';
    requestedStartDate = json["requested_start_date"]?.toString() ?? '';
    requestedStartTime = json["requested_start_time"]?.toString() ?? '';
    requestedEndDate = json["requested_end_date"]?.toString() ?? '';
    requestedEndTime = json["requested_end_time"]?.toString() ?? '';
    createdAt = DateTime.tryParse(json["created_at"]?.toString() ?? '');
    updatedAt = json["updated_at"]?.toString() ?? '';
    status = json["status"]?.toString() ?? '';
    username = json["username"]?.toString() ?? '';
    image = json["image"]?.toString() ?? '';
    categoryName = json["category_name"]?.toString() ?? '';
    categoryImage = json["category_image"]?.toString() ?? '';
    partnerId = json["partner_id"]?.toString() ?? '';
    counterPrice = json["counter_price"]?.toString() ?? '';
    note = json["note"]?.toString() ?? '';
    duration = json["duration"]?.toString() ?? '';
    customJobRequestId = json["custom_job_request_id"]?.toString() ?? '';
    customJobId = json["custom_job_id"]?.toString() ?? '';
    taxId = json["tax_id"]?.toString() ?? '';
    taxAmount = json["tax_amount"]?.toString() ?? '';
    taxPercentage = json["tax_percentage"]?.toString() ?? '';
    finalTotal = json["final_total"]?.toString() ?? '';
    translatedServiceTitle =
        (json['translated_service_title']?.toString() ?? '').isNotEmpty
        ? json['translated_service_title'].toString()
        : (json['service_title']?.toString() ?? '');
    translatedServiceShortDescription =
        (json['translated_service_short_description']?.toString() ?? '')
            .isNotEmpty
        ? json['translated_service_short_description'].toString()
        : (json['service_short_description']?.toString() ?? '');
    translatedCategoryName =
        (json['translated_category_name']?.toString() ?? '').isNotEmpty
        ? json['translated_category_name'].toString()
        : (json['category_name']?.toString() ?? '');
    bidApplied = json["bid_applied"]?.toString() == '1';
  }

  Map<String, dynamic> toJson() => {
    "id": id,
    "user_id": userId,
    "category_id": categoryId,
    "service_title": serviceTitle,
    "service_short_description": serviceShortDescription,
    "min_price": minPrice,
    "max_price": maxPrice,
    "requested_start_date": requestedStartDate,
    "requested_start_time": requestedStartTime,
    "requested_end_date": requestedEndDate,
    "requested_end_time": requestedEndTime,
    "created_at": createdAt,
    "updated_at": updatedAt,
    "status": status,
    "username": username,
    "image": image,
    "category_name": categoryName,
    "category_image": categoryImage,
    "partner_id": partnerId,
    "counter_price": counterPrice,
    "note": note,
    "duration": duration,
    "custom_job_request_id": customJobRequestId,
    "custom_job_id": customJobId,
    "tax_id": taxId,
    "tax_amount": taxAmount,
    "tax_percentage": taxPercentage,
    "final_total": finalTotal,
    "translated_service_title": translatedServiceTitle,
    "translated_service_short_description": translatedServiceShortDescription,
    "translated_category_name": translatedCategoryName,
    "bid_applied": bidApplied == true ? '1' : '0',
  };
}
