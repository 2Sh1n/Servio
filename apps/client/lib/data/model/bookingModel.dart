import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/foundation.dart';

class Booking {
  String? id;
  String? customer;
  String? customerId;
  String? customerNo;
  String? customerEmail;
  String? paymentMethod;
  String? paymentStatus;
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
  String? status;
  String? translatedStatus;
  String? providerAdvanceBookingDays;
  String? otp;
  List<dynamic>? workStartedProof;
  List<dynamic>? workCompletedProof;
  String? providerAddress;
  String? providerLatitude;
  String? providerLongitude;
  String? providerNumber;
  String? remarks;
  String? createdAt;
  String? companyName;
  String? translatedCompanyName;
  String? visitingCharges;
  List<BookedService>? services;
  String? invoiceNo;
  String? isCancelable;
  String? newStartTimeWithDate;
  String? newEndTimeWithDate;
  String? isReorderAllowed;
  List<MultipleDayBookingData>? multipleDaysBooking;
  String? enquiryId;
  Key? key = UniqueKey();
  String? isPostBookingChatAllowed;
  List<AdditionalCharges>? additionalCharges;
  String? paymentStatusOfAdditionalCharge;
  String? paymentMethodOfAdditionalCharge;
  String? totalAdditionalCharge;
  int? isPayLaterAllowed;
  int? isOnlinePaymentAllowed;
  String? customJobRequestId;

  Booking({
    this.id,
    this.key,
    this.customer,
    this.customerId,
    this.customerNo,
    this.customerEmail,
    this.paymentMethod,
    this.paymentStatus,
    this.partner,
    this.profileImage,
    this.userId,
    this.partnerId,
    this.cityId,
    this.isCancelable,
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
    this.status,
    this.translatedStatus,
    this.otp,
    this.remarks,
    this.createdAt,
    this.companyName,
    this.translatedCompanyName,
    this.visitingCharges,
    this.services,
    this.invoiceNo,
    this.workCompletedProof,
    this.workStartedProof,
    this.multipleDaysBooking,
    this.newEndTimeWithDate,
    this.newStartTimeWithDate,
    this.providerNumber,
    this.providerAddress,
    this.providerLatitude,
    this.providerAdvanceBookingDays,
    this.providerLongitude,
    this.isReorderAllowed,
    this.enquiryId,
    this.isPostBookingChatAllowed,
    this.additionalCharges,
    this.paymentStatusOfAdditionalCharge,
    this.paymentMethodOfAdditionalCharge,
    this.totalAdditionalCharge,
    this.isPayLaterAllowed,
    this.isOnlinePaymentAllowed,
    this.customJobRequestId,
  });

  bool get isCustomJobRequest =>
      customJobRequestId != null &&
      customJobRequestId != '0' &&
      customJobRequestId != '';

  Booking.fromJson(final Map<String, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    customer = json["customer"]?.toString() ?? '';
    customerId = json["customer_id"]?.toString() ?? '';
    customerNo = json["customer_no"]?.toString() ?? '';
    customerEmail = json["customer_email"]?.toString() ?? '';
    paymentMethod = json["payment_method"]?.toString() ?? '';
    paymentStatus = json["payment_status"]?.toString() ?? '';
    partner = json["partner"]?.toString() ?? '';
    profileImage = json["profile_image"]?.toString() ?? '';
    userId = json["user_id"]?.toString() ?? '';
    partnerId = json["partner_id"]?.toString() ?? '';
    cityId = json["city_id"]?.toString() ?? '';
    total = json["total"]?.toString() ?? '';
    taxPercentage = json["tax_percentage"]?.toString() ?? '';
    taxAmount = json["tax_amount"]?.toString() ?? '';
    promoCode = json["promo_code"]?.toString() ?? '';
    promoDiscount = json["promo_discount"]?.toString() ?? '';
    finalTotal = json["final_total"]?.toString() ?? '';
    adminEarnings = json["admin_earnings"]?.toString() ?? '';
    partnerEarnings = json["partner_earnings"]?.toString() ?? '';
    addressId = json["address_id"]?.toString() ?? '';
    address = json["address"]?.toString() ?? '';
    dateOfService = json["date_of_service"]?.toString() ?? '';
    startingTime = json["starting_time"]?.toString() ?? '';
    endingTime = json["ending_time"]?.toString() ?? '';
    duration = json["duration"]?.toString() ?? '';
    status = json["status"]?.toString() ?? '';
    translatedStatus = (json["translated_status"]?.toString() ?? '').isNotEmpty
        ? json["translated_status"]!.toString()
        : (json["status"]?.toString() ?? '');
    otp = json["otp"] != '' ? json["otp"] : '--';
    remarks = json["remarks"]?.toString() ?? '';
    createdAt = json["created_at"]?.toString() ?? '';
    companyName = json["company_name"]?.toString() ?? '';

    translatedCompanyName =
        (json["translated_company_name"]?.toString() ?? '').isNotEmpty
            ? json["translated_company_name"]!.toString()
            : (json["company_name"]?.toString() ?? '');

    visitingCharges = json["visiting_charges"]?.toString() ?? '';
    workStartedProof = json["work_started_proof"];
    workCompletedProof = json["work_completed_proof"];
    isReorderAllowed = json["is_reorder_allowed"]?.toString() ?? '';
    providerAdvanceBookingDays = json["advance_booking_days"]?.toString() ?? '';
    invoiceNo = json["invoice_no"]?.toString() ?? '';
    isCancelable = json["is_cancelable"]?.toString() ?? '';
    providerAddress = json["partner_address"]?.toString() ?? '';
    providerLatitude =
        json["partner_latitude"] != null && json["partner_latitude"] != ''
            ? json["partner_latitude"]?.toString() ?? ''
            : "0.0";
    providerLongitude =
        json["partner_longitude"] != null && json["partner_longitude"] != ''
            ? json["partner_longitude"]?.toString() ?? ''
            : "0.0";
    providerNumber = json["partner_no"]?.toString() ?? '';
    newStartTimeWithDate = json['new_start_time_with_date'] ?? '';
    newEndTimeWithDate = json['new_end_time_with_date'] ?? '';
    enquiryId = json['e_id']?.toString() ?? '';
    isPostBookingChatAllowed = json['post_booking_chat']?.toString() ?? '';
    paymentStatusOfAdditionalCharge =
        json['payment_status_of_additional_charge']?.toString() ?? '';
    paymentMethodOfAdditionalCharge =
        json['payment_method_of_additional_charge']?.toString() ?? '';
    totalAdditionalCharge = json['total_additional_charge']?.toString() ?? '';
    isPayLaterAllowed =
        int.tryParse(json['is_pay_later_allowed'].toString()) ?? 0;
    isOnlinePaymentAllowed =
        int.tryParse(json['is_online_payment_allowed'].toString()) ?? 0;
    customJobRequestId = json['custom_job_request_id']?.toString() ?? '';

    if (json["services"] != null) {
      services = <BookedService>[];
      json["services"].forEach((final v) {
        services!.add(BookedService.fromJson(v));
      });
    }
    if (json["multiple_days_booking"] != null) {
      multipleDaysBooking = <MultipleDayBookingData>[];
      json["multiple_days_booking"].forEach((final v) {
        multipleDaysBooking!.add(MultipleDayBookingData.fromJson(v));
      });
    }
    if (json['additional_charges'] != null) {
      additionalCharges = <AdditionalCharges>[];
      json['additional_charges'].forEach((v) {
        additionalCharges!.add(AdditionalCharges.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = id;
    data['customer'] = customer;
    data['customer_id'] = customerId;
    data['partner_latitude'] = providerLatitude;
    data['partner_longitude'] = providerLongitude;
    data['advance_booking_days'] = providerAdvanceBookingDays;
    data['customer_no'] = customerNo;
    data['customer_email'] = customerEmail;
    data['payment_method'] = paymentMethod;
    data['payment_status'] = paymentStatus;
    data['partner'] = partner;
    data['profile_image'] = profileImage;
    data['user_id'] = userId;
    data['partner_id'] = partnerId;
    data['city_id'] = cityId;
    data['total'] = total;
    data['tax_amount'] = taxAmount;
    data['promo_code'] = promoCode;
    data['promo_discount'] = promoDiscount;
    data['final_total'] = finalTotal;
    data['admin_earnings'] = adminEarnings;
    data['partner_earnings'] = partnerEarnings;
    data['address_id'] = addressId;
    data['custom_job_request_id'] = customJobRequestId;
    data['address'] = address;
    if (additionalCharges != null) {
      data['additional_charges'] =
          additionalCharges!.map((v) => v.toJson()).toList();
    }
    data['payment_status_of_additional_charge'] =
        paymentStatusOfAdditionalCharge;
    data['payment_method_of_additional_charge'] =
        paymentMethodOfAdditionalCharge;
    data['total_additional_charge'] = totalAdditionalCharge;
    data['date_of_service'] = dateOfService;
    data['starting_time'] = startingTime;
    data['ending_time'] = endingTime;
    data['duration'] = duration;
    data['partner_address'] = providerAddress;
    data['partner_no'] = providerNumber;
    data['otp'] = otp;
    if (workStartedProof != null) {
      data['work_started_proof'] =
          workStartedProof!.map((v) => v.toJson()).toList();
    }
    if (workCompletedProof != null) {
      data['work_completed_proof'] =
          workCompletedProof!.map((v) => v.toJson()).toList();
    }
    data['is_reorder_allowed'] = isReorderAllowed;
    data['status'] = status;
    data['translated_status'] = translatedStatus;
    data['remarks'] = remarks;
    data['created_at'] = createdAt;
    data['company_name'] = companyName;
    data['translated_company_name'] = translatedCompanyName;
    data['visiting_charges'] = visitingCharges;
    if (services != null) {
      data['services'] = services!.map((v) => v.toJson()).toList();
    }
    data['post_booking_chat'] = isPostBookingChatAllowed;
    data['is_cancelable'] = isCancelable;
    data['new_start_time_with_date'] = newStartTimeWithDate;
    data['new_end_time_with_date'] = newEndTimeWithDate;
    if (multipleDaysBooking != null) {
      data['multiple_days_booking'] =
          multipleDaysBooking!.map((v) => v.toJson()).toList();
    }
    data['invoice_no'] = invoiceNo;
    data['e_id'] = enquiryId;
    data['is_pay_later_allowed'] = isPayLaterAllowed;
    data['is_online_payment_allowed'] = isOnlinePaymentAllowed;
    return data;
  }
}

class MultipleDayBookingData {
  MultipleDayBookingData({
    this.multipleDayDateOfService,
    this.multipleDayStartingTime,
    this.multipleEndingTime,
  });

  MultipleDayBookingData.fromJson(final Map<String, dynamic> json) {
    multipleDayDateOfService = json['multiple_day_date_of_service'] ?? '';
    multipleDayStartingTime = json['multiple_day_starting_time'] ?? '';
    multipleEndingTime = json['multiple_ending_time'] ?? '';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['multiple_day_date_of_service'] = multipleDayDateOfService;
    data['multiple_day_starting_time'] = multipleDayStartingTime;
    data['multiple_ending_time'] = multipleEndingTime;
    return data;
  }

  String? multipleDayDateOfService;
  String? multipleDayStartingTime;
  String? multipleEndingTime;
}

class BookedService {
  BookedService({
    this.id,
    this.orderId,
    this.serviceId,
    this.serviceTitle,
    this.translatedTitle,
    this.taxPercentage,
    this.taxAmount,
    this.price,
    this.originalPriceWithTax,
    this.discountPrice,
    this.priceWithTax,
    this.quantity,
    this.subTotal,
    this.status,
    this.translatedStatus,
    this.tags,
    this.translatedTags,
    this.duration,
    this.categoryId,
    this.rating,
    this.comment,
    this.image,
    this.reviewImages,
    this.customJobRequestId,
    this.customJobServiceNote,
    this.customJobServiceProviderNote,
  });

  BookedService copyWith({
    String? id,
    String? orderId,
    String? serviceId,
    String? serviceTitle,
    String? taxPercentage,
    String? discountPrice,
    String? taxAmount,
    String? price,
    String? quantity,
    String? subTotal,
    String? status,
    String? translatedStatus,
    String? tags,
    String? translatedTags,
    String? duration,
    String? categoryId,
    String? isCancelable,
    String? cancelableTill,
    String? title,
    String? translatedTitle,
    String? taxType,
    String? taxId,
    String? image,
    String? rating,
    String? comment,
    String? images,
    String? priceWithTax,
    String? taxValue,
    String? originalPriceWithTax,
    List<String>? reviewImages,
    String? customJobRequestId,
    String? customJobServiceNote,
    String? customJobServiceProviderNote,
  }) =>
      BookedService(
        id: id ?? this.id,
        orderId: orderId ?? this.orderId,
        serviceId: serviceId ?? this.serviceId,
        serviceTitle: serviceTitle ?? this.serviceTitle,
        translatedTitle: translatedTitle ?? this.translatedTitle,
        taxPercentage: taxPercentage ?? this.taxPercentage,
        discountPrice: discountPrice ?? this.discountPrice,
        taxAmount: taxAmount ?? this.taxAmount,
        price: price ?? this.price,
        quantity: quantity ?? this.quantity,
        subTotal: subTotal ?? this.subTotal,
        status: status ?? this.status,
        translatedStatus: translatedStatus ?? this.translatedStatus,
        tags: tags ?? this.tags,
        translatedTags: translatedTags ?? this.translatedTags,
        duration: duration ?? this.duration,
        categoryId: categoryId ?? this.categoryId,
        rating: rating ?? this.rating,
        comment: comment ?? this.comment,
        image: images ?? this.image,
        priceWithTax: priceWithTax ?? this.priceWithTax,
        originalPriceWithTax: originalPriceWithTax ?? this.originalPriceWithTax,
        reviewImages: reviewImages ?? this.reviewImages,
        customJobRequestId: customJobRequestId ?? this.customJobRequestId,
        customJobServiceNote: customJobServiceNote ?? this.customJobServiceNote,
        customJobServiceProviderNote:
            customJobServiceProviderNote ?? this.customJobServiceProviderNote,
      );

  BookedService.fromJson(final Map<String, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    orderId = json["order_id"]?.toString() ?? '';
    serviceId = json["service_id"]?.toString() ?? '';
    serviceTitle = json["service_title"]?.toString() ?? '';
    translatedTitle = (json["translated_title"]?.toString() ?? '').isNotEmpty
        ? json["translated_title"]!.toString()
        : (json["service_title"]?.toString() ?? '');
    taxPercentage = json["tax_percentage"]?.toString() ?? '';
    taxAmount = json["tax_amount"]?.toString() ?? '';
    price = json["price"]?.toString() ?? '';
    discountPrice = json["discount_price"]?.toString() ?? '';
    priceWithTax = json["price_with_tax"]?.toString() ?? '';
    originalPriceWithTax = json["original_price_with_tax"]?.toString() ?? '';
    quantity = json["quantity"]?.toString() ?? '';
    subTotal = json["sub_total"]?.toString() ?? '';
    status = json["status"]?.toString() ?? '';
    translatedStatus = (json["translated_status"]?.toString() ?? '').isNotEmpty
        ? json["translated_status"]!.toString()
        : (json["status"]?.toString() ?? '');
    tags = json["tags"]?.toString() ?? '';
    translatedTags = (json["translated_tags"]?.toString() ?? '').isNotEmpty
        ? json["translated_tags"]!.toString()
        : (json["tags"]?.toString() ?? '');
    duration = json["duration"]?.toString() ?? '';
    categoryId = json["category_id"]?.toString() ?? '';
    rating = json["rating"]?.toString() ?? '';
    comment = json["comment"]?.toString() ?? '';
    image = json["image"]?.toString() ?? '';
    reviewImages = (json["images"]?.isNotEmpty ?? false)
        ? (json["images"] as List).cast<String>()
        : [];
    customJobRequestId = json["custom_job_request_id"]?.toString() ?? '';
    customJobServiceNote = json["note"]?.toString() ?? '';
    customJobServiceProviderNote =
        json["service_short_description"]?.toString() ?? '';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = id;
    data['order_id'] = orderId;
    data['service_id'] = serviceId;
    data['service_title'] = serviceTitle;
    data['translated_title'] = translatedTitle;
    data['tax_percentage'] = taxPercentage;
    data['tax_amount'] = taxAmount;
    data['discount_price'] = discountPrice;
    data['price'] = price;
    data['original_price_with_tax'] = originalPriceWithTax;
    data['price_with_tax'] = priceWithTax;
    data['quantity'] = quantity;
    data['sub_total'] = subTotal;
    data['status'] = status;
    data['translated_status'] = translatedStatus;
    data['tags'] = tags;
    data['translated_tags'] = translatedTags;
    data['duration'] = duration;
    data['category_id'] = categoryId;
    data['rating'] = rating;
    data['comment'] = comment;
    data['image'] = image;
    data['review_images'] = reviewImages;
    data['custom_job_request_id'] = customJobRequestId;
    data['note'] = customJobServiceNote;
    data['service_short_description'] = customJobServiceProviderNote;

    return data;
  }

  String? id;
  String? orderId;
  String? serviceId;
  String? serviceTitle;
  String? translatedTitle;
  String? taxPercentage;
  String? taxAmount;
  String? discountPrice;
  String? price;
  String? originalPriceWithTax;
  String? priceWithTax;
  String? quantity;
  String? subTotal;
  String? status;
  String? translatedStatus;
  String? tags;
  String? translatedTags;
  String? duration;
  String? categoryId;
  String? rating;
  String? comment;
  String? image;
  List<String>? reviewImages;
  String? customJobRequestId;
  String? customJobServiceNote;
  String? customJobServiceProviderNote;
}

class AdditionalCharges {
  String? name;
  String? charge;

  AdditionalCharges({this.name, this.charge});

  AdditionalCharges.fromJson(Map<String, dynamic> json) {
    name = json['name'];
    charge = json['charge'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['name'] = name;
    data['charge'] = charge;
    return data;
  }
}
