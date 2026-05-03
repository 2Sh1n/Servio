class BookingsModel {
  BookingsModel({
    this.id,
    this.customer,
    this.customerId,
    this.customerNo,
    this.customerEmail,
    this.userWallet,
    this.paymentMethod,
    this.partner,
    this.profileImage,
    this.userId,
    this.partnerId,
    this.cityId,
    this.total,
    this.taxPercentage,
    this.taxAmount,
    this.promoCode,
    this.promoDiscount,
    this.finalTotal,
    this.adminEarnings,
    this.partnerEarnings,
    this.addressId,
    this.address,
    this.dateOfService,
    this.startingTime,
    this.endingTime,
    this.duration,
    this.isCancelable,
    this.status,
    this.translatedStatus,
    this.otp,
    this.remarks,
    this.createdAt,
    this.companyName,
    this.visitingCharges,
    this.services,
    this.latitude,
    this.longitude,
    this.advanceBookingDays,
    this.invoiceNo,
    this.additionalCharges,
    this.totalAdditionalCharges,
    this.workCompletedProof,
    this.workStartedProof,
    this.multipleDaysBooking,
    this.newEndTimeWithDate,
    this.paymentStatus,
    this.newStartTimeWithDate,
    this.customJobRequestId,
  });

  BookingsModel.fromJson(Map<String, dynamic> json) {
    latitude = json['latitude']?.toString() ?? '';
    longitude = json['longitude']?.toString() ?? '';
    id = json['id']?.toString() ?? '';
    customer = json['customer']?.toString() ?? '';
    customerId = json['customer_id']?.toString() ?? '';
    customerNo = json['customer_no']?.toString() ?? '';
    customerEmail = json['customer_email']?.toString() ?? '';
    userWallet = json['user_wallet']?.toString() ?? '';
    paymentMethod = json['payment_method']?.toString() ?? '';
    paymentStatus = json['payment_status']?.toString() ?? '';
    partner = json['partner']?.toString() ?? '';
    profileImage = json['profile_image']?.toString() ?? '';
    userId = json['user_id']?.toString() ?? '';
    partnerId = json['partner_id']?.toString() ?? '';
    cityId = json['city_id']?.toString() ?? '';
    total = json['total']?.toString() ?? '';
    taxPercentage = json['tax_percentage']?.toString() ?? '';
    taxAmount = json['tax_amount']?.toString() ?? '';
    promoCode = json['promo_code']?.toString() ?? '';
    promoDiscount = json['promo_discount']?.toString() ?? '';
    finalTotal = json['final_total']?.toString() ?? '';
    adminEarnings = json['admin_earnings']?.toString() ?? '';
    partnerEarnings = json['partner_earnings']?.toString() ?? '';
    addressId = json['address_id']?.toString() ?? '';
    address = json['address']?.toString() ?? '';
    dateOfService = json['date_of_service']?.toString() ?? '';
    startingTime = json['starting_time']?.toString() ?? '';
    endingTime = json['ending_time']?.toString() ?? '';
    duration = json['duration']?.toString() ?? '';
    isCancelable = json['is_cancelable']?.toInt() ?? 0;
    status = json['status']?.toString() ?? '';
    translatedStatus = (json['translated_status']?.toString() ?? '').isNotEmpty
        ? json['translated_status'].toString()
        : (json['status']?.toString() ?? '');
    otp = json['otp']?.toString() ?? '';
    remarks = json['remarks']?.toString() ?? '';
    createdAt = json['created_at']?.toString() ?? '';
    companyName = json['company_name']?.toString() ?? '';
    advanceBookingDays = json['advance_booking_days']?.toString() ?? '';
    workStartedProof = json['work_started_proof'] ?? [];
    workCompletedProof = json['work_completed_proof'] ?? [];
    additionalCharges = json['additional_charges'] ?? [];
    totalAdditionalCharges = json['total_additional_charge']?.toString() ?? '0';
    invoiceNo = json['invoice_no']?.toString() ?? '';
    visitingCharges = json['visiting_charges']?.toString() ?? '';
    customJobRequestId = json['custom_job_request_id']?.toString() ?? '';
    if (json['services'] != null) {
      services = <Services>[];
      json['services'].forEach((v) {
        services!.add(Services.fromJson(v));
      });
    }
    newStartTimeWithDate = json['new_start_time_with_date']?.toString() ?? '';
    newEndTimeWithDate = json['new_end_time_with_date']?.toString() ?? '';
    if (json['multiple_days_booking'] != null) {
      multipleDaysBooking = <MultipleDayBookingData>[];
      json['multiple_days_booking'].forEach((v) {
        multipleDaysBooking!.add(MultipleDayBookingData.fromJson(v));
      });
    }
  }

  String? id;
  String? customer;
  String? latitude;
  String? longitude;
  String? customerId;
  String? customerNo;
  String? customerEmail;
  String? userWallet;
  String? paymentMethod;
  String? partner;
  String? profileImage;
  String? userId;
  String? partnerId;
  String? cityId;
  String? total;
  String? taxPercentage;
  String? taxAmount;
  String? promoCode;
  String? promoDiscount;
  String? finalTotal;
  String? adminEarnings;
  String? partnerEarnings;
  String? addressId;
  String? address;
  String? dateOfService;
  String? startingTime;
  String? endingTime;
  String? duration;
  int? isCancelable;
  String? status;
  String? otp;
  String? remarks;
  String? createdAt;
  String? companyName;
  String? visitingCharges;
  List<Services>? services;
  List<dynamic>? workStartedProof;
  List<dynamic>? workCompletedProof;
  List<dynamic>? additionalCharges;
  String? totalAdditionalCharges;
  String? invoiceNo;
  String? advanceBookingDays;
  String? newStartTimeWithDate;
  String? newEndTimeWithDate;
  List<MultipleDayBookingData>? multipleDaysBooking;
  String? customJobRequestId;
  String? paymentStatus;
  String? translatedStatus;

  double? get totalAdditionalChargeDouble {
    return double.tryParse(
      double.parse(
        totalAdditionalCharges?.toString().replaceAll(",", '') ?? '',
      ).toString(),
    );
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['customer'] = customer;
    data['customer_id'] = customerId;
    data['customer_no'] = customerNo;
    data['customer_email'] = customerEmail;
    data['user_wallet'] = userWallet;
    data['payment_method'] = paymentMethod;
    data['partner'] = partner;
    data['profile_image'] = profileImage;
    data['user_id'] = userId;
    data['partner_id'] = partnerId;
    data['city_id'] = cityId;
    data['total'] = total;
    data['tax_percentage'] = taxPercentage ?? '';
    data['tax_amount'] = taxAmount;
    data['promo_code'] = promoCode;
    data['promo_discount'] = promoDiscount;
    data['final_total'] = finalTotal;
    data['admin_earnings'] = adminEarnings;
    data['partner_earnings'] = partnerEarnings;
    data['address_id'] = addressId;
    data['address'] = address;
    data['date_of_service'] = dateOfService;
    data['starting_time'] = startingTime;
    data['ending_time'] = endingTime;
    data['duration'] = duration;
    data['is_cancelable'] = isCancelable;
    data['status'] = status;
    data['translated_status'] = translatedStatus;
    data['otp'] = otp;
    data['remarks'] = remarks;
    data['created_at'] = createdAt;
    data['company_name'] = companyName;
    data['visiting_charges'] = visitingCharges;
    data['latitude'] = latitude;
    data['longitude'] = longitude;
    data['advance_booking_days'] = advanceBookingDays;
    data['work_started_proof'] = workStartedProof;
    data['work_completed_proof'] = workCompletedProof;
    data['additional_charges'] = additionalCharges;
    data['total_additional_charge'] = totalAdditionalCharges;
    data['custom_job_request_id'] = customJobRequestId;
    data['payment_status'] = paymentStatus;

    if (services != null) {
      data['services'] = services!.map((Services v) => v.toJson()).toList();
    }
    data['invoice_no'] = invoiceNo;
    return data;
  }
}

class MultipleDayBookingData {
  MultipleDayBookingData({
    this.multipleDayDateOfService,
    this.multipleDayStartingTime,
    this.multipleEndingTime,
  });

  MultipleDayBookingData.fromJson(Map<String, dynamic> json) {
    multipleDayDateOfService =
        json['multiple_day_date_of_service']?.toString() ?? '';
    multipleDayStartingTime =
        json['multiple_day_starting_time']?.toString() ?? '';
    multipleEndingTime = json['multiple_ending_time']?.toString() ?? '';
  }

  String? multipleDayDateOfService;
  String? multipleDayStartingTime;
  String? multipleEndingTime;

  Map<String, dynamic> toJson() => {
    'multiple_day_date_of_service': multipleDayDateOfService,
    'multiple_day_starting_time': multipleDayStartingTime,
    'multiple_ending_time': multipleEndingTime,
  };
}

class Services {
  String? id;
  String? orderId;
  String? serviceId;
  String? serviceTitle;
  String? taxPercentage;
  String? taxAmount;
  String? price;
  String? taxValue;
  String? priceWithTax;
  String? discountPrice;
  String? originalPriceWithTax;
  String? quantity;
  String? subTotal;
  String? status;
  String? translatedStatus;
  String? tags;
  String? duration;
  String? categoryId;
  String? isCancelable;
  String? cancelableTill;
  String? rating;
  String? comment;
  String? advanceBookingDays;

  Services({
    this.id,
    this.orderId,
    this.serviceId,
    this.serviceTitle,
    this.taxPercentage,
    this.taxAmount,
    this.price,
    this.originalPriceWithTax,
    this.discountPrice,
    this.priceWithTax,
    this.taxValue,
    this.quantity,
    this.subTotal,
    this.status,
    this.translatedStatus,
    this.tags,
    this.duration,
    this.categoryId,
    this.isCancelable,
    this.cancelableTill,
    this.rating,
    this.comment,
    this.advanceBookingDays,
  });

  Services.fromJson(Map<String, dynamic> json) {
    id = json['id']?.toString() ?? '';
    orderId = json['order_id']?.toString() ?? '';
    serviceId = json['service_id']?.toString() ?? '';
    serviceTitle = json['service_title']?.toString() ?? '';
    taxPercentage = json['tax_percentage']?.toString() ?? '';
    taxAmount = json['tax_amount']?.toString() ?? '';
    price = json['price']?.toString() ?? '';
    priceWithTax = json['price_with_tax']?.toString() ?? '';
    discountPrice = json['discount_price']?.toString() ?? '0';
    originalPriceWithTax = json['original_price_with_tax']?.toString() ?? '';
    taxValue = json['tax_value']?.toString() ?? '';
    quantity = json['quantity']?.toString() ?? '';
    subTotal = json['sub_total']?.toString() ?? '';
    status = json['status']?.toString() ?? '';
    translatedStatus = (json['translated_status']?.toString() ?? '').isNotEmpty
        ? json['translated_status'].toString()
        : (json['status']?.toString() ?? '');

    tags = json['tags']?.toString() ?? '';
    duration = json['duration']?.toString() ?? '';
    categoryId = json['category_id']?.toString() ?? '';
    isCancelable = json['is_cancelable']?.toString() ?? '';
    cancelableTill = json['cancelable_till']?.toString() ?? '';
    rating = json['rating']?.toString() ?? '';
    comment = json['comment']?.toString() ?? '';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['order_id'] = orderId;
    data['service_id'] = serviceId;
    data['service_title'] = serviceTitle;
    data['tax_percentage'] = taxPercentage;
    data['tax_amount'] = taxAmount;
    data['price'] = price;
    data['quantity'] = quantity;
    data['sub_total'] = subTotal;
    data['status'] = status;
    data['translated_status'] = translatedStatus;
    data['tags'] = tags;
    data['duration'] = duration;
    data['category_id'] = categoryId;
    data['is_cancelable'] = isCancelable;
    data['cancelable_till'] = cancelableTill;
    data['rating'] = rating;
    data['comment'] = comment;
    return data;
  }
}
