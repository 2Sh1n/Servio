class Bidder {
  String? id;
  String? partnerId;
  String? counterPrice;
  String? note;
  String? duration;
  String? customJobRequestId;
  String? status;
  String? taxId;
  String? taxAmount;
  String? taxPercentage;
  String? taxValue;
  String? companyName;
  String? providerName;
  String? translatedProviderName;
  String? advanceBookingDays;
  String? providerImage;
  String? atStore;
  String? atDoorstep;
  String? payableCommission;
  String? rating;
  String? totalOrders;
  int? isOnlinePaymentAllowed;
  int? isPayLaterAllowed;
  String? finalTotal;
  String? visitingCharges;

  Bidder(
      {this.id,
      this.partnerId,
      this.counterPrice,
      this.note,
      this.duration,
      this.customJobRequestId,
      this.status,
      this.companyName,
      this.providerName,
      this.translatedProviderName,
      this.advanceBookingDays,
      this.providerImage,
      this.atStore,
      this.atDoorstep,
      this.rating,
      this.totalOrders,
      this.isOnlinePaymentAllowed,
      this.isPayLaterAllowed,
      this.finalTotal,
      this.payableCommission,
      this.taxId,
      this.taxAmount,
      this.taxPercentage,
      this.visitingCharges});

  Bidder.fromJson(Map<String, dynamic> json) {
    id = json['id']?.toString() ?? '0';
    partnerId = json['partner_id']?.toString() ?? '0';
    counterPrice = json['counter_price']?.toString() ?? '0';
    note = json['note'] ?? '';
    duration = json['duration']?.toString() ?? '0';
    customJobRequestId = json['custom_job_request_id']?.toString() ?? '0';
    status = json['status'] ?? '';
    companyName = json['company_name'] ?? '';
    providerName = json['provider_name'] ?? '';
    translatedProviderName =
        (json['translated_provider_name']?.toString() ?? '').isNotEmpty
            ? json['translated_provider_name']!.toString()
            : providerName;
    advanceBookingDays = json['advance_booking_days']?.toString() ?? '0';
    providerImage = json['provider_image'] ?? '';
    atStore = json['at_store']?.toString() ?? '0';
    atDoorstep = json['at_doorstep']?.toString() ?? '0';
    rating = json['rating']?.toString() ?? '0';
    totalOrders = json['total_orders']?.toString() ?? '0';
    isOnlinePaymentAllowed =
        int.parse("${json['is_online_payment_allowed'] ?? 0}".toString());
    isPayLaterAllowed =
        int.parse("${json['is_pay_later_allowed'] ?? 0}".toString());
    taxId = json['tax_id'] ?? '';
    taxAmount = json['tax_amount']?.toString() ?? '0';
    taxPercentage = json['tax_percentage']?.toString() ?? '0';
    payableCommission = json['payable_commision']?.toString() ?? '0';
    finalTotal = json['final_total']?.toString() ?? '0';
    visitingCharges = json['visiting_charges']?.toString() ?? '0';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = id;
    data['partner_id'] = partnerId;
    data['counter_price'] = counterPrice;
    data['note'] = note;
    data['duration'] = duration;
    data['custom_job_request_id'] = customJobRequestId;
    data['status'] = status;
    data['company_name'] = companyName;
    data['provider_name'] = providerName;
    data['translated_provider_name'] = translatedProviderName;
    data['advance_booking_days'] = advanceBookingDays;
    data['provider_image'] = providerImage;
    data['at_store'] = atStore;
    data['at_doorstep'] = atDoorstep;
    data['rating'] = rating;
    data['total_orders'] = totalOrders;
    data['is_online_payment_allowed'] = isOnlinePaymentAllowed;
    data['is_pay_later_allowed'] = isPayLaterAllowed;
    data['tax_id'] = taxId;
    data['tax_amount'] = taxAmount;
    data['tax_percentage'] = taxPercentage;
    data['payable_commision'] = payableCommission;
    data['final_total'] = finalTotal;
    data['visiting_charges'] = visitingCharges;
    return data;
  }
}

class CustomJob {
  String? id;
  String? userid;
  String? categoryId;
  String? serviceTitle;
  String? serviceShortDescription;
  String? minPrice;
  String? maxPrice;
  String? requestedStartDate;
  String? requestedStartTime;
  String? requestedEndDate;
  String? requestedEndTime;
  String? createdAt;
  String? updatedAt;
  String? status;
  String? categoryName;
  String? categoryImage;

  CustomJob(
      {this.id,
      this.userid,
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
      this.categoryName,
      this.categoryImage});

  CustomJob.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userid = json['user_id'];
    categoryId = json['category_id'];
    serviceTitle = json['service_title'];
    serviceShortDescription = json['service_short_description'];
    minPrice = json['min_price'];
    maxPrice = json['max_price'];
    requestedStartDate = json['requested_start_date'];
    requestedStartTime = json['requested_start_time'];
    requestedEndDate = json['requested_end_date'];
    requestedEndTime = json['requested_end_time'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    status = json['status'];
    categoryName = json['category_name'];
    categoryImage = json['category_image'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = id;
    data['user_id'] = userid;
    data['category_id'] = categoryId;
    data['service_title'] = serviceTitle;
    data['service_short_description'] = serviceShortDescription;
    data['min_price'] = minPrice;
    data['max_price'] = maxPrice;
    data['requested_start_date'] = requestedStartDate;
    data['requested_start_time'] = requestedStartTime;
    data['requested_end_date'] = requestedEndDate;
    data['requested_end_time'] = requestedEndTime;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['status'] = status;
    data['category_name'] = categoryName;
    data['category_image'] = categoryImage;
    return data;
  }
}
