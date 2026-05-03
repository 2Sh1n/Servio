import 'package:e_demand/data/model/serviceModel.dart';

class Cart {
  List<CartDetails>? cartDetails;
  String? carId;
  String? providerName;
  String? translatedProviderName;
  String? isAtDoorstepAvailable;
  String? isAtStoreAvailable;
  String? providerId;
  String? totalQuantity;
  String? subTotal;
  String? taxPercentage;
  String? taxAmount;
  String? taxValue;
  String? overallAmount;
  String? total;
  String? promoCodeDiscountAmount;
  String? appliedPromoCodeName;
  String? isPayLaterAllowed;
  String? isOnlinePaymentAllowed;
  String? visitingCharges;
  String? advanceBookingDays;
  String? companyName;
  String? translatedCompanyName;
  String? subTotalWithoutTax;

  Cart({
    this.carId,
    this.cartDetails,
    this.providerName,
    this.translatedProviderName,
    this.providerId,
    this.totalQuantity,
    this.subTotal,
    this.taxPercentage,
    this.taxAmount,
    this.taxValue,
    this.overallAmount,
    this.total,
    this.appliedPromoCodeName,
    this.promoCodeDiscountAmount,
    this.visitingCharges,
    this.advanceBookingDays,
    this.companyName,
    this.translatedCompanyName,
    this.isPayLaterAllowed,
    this.isAtDoorstepAvailable,
    this.isAtStoreAvailable,
    this.isOnlinePaymentAllowed,
    this.subTotalWithoutTax,
  });

  Cart.fromJson(final Map<String, dynamic> json) {
    if (json['data'] != null && json["data"] != '' && json['data'] is List) {
      cartDetails = <CartDetails>[];
      (json['data'] as List).forEach((final v) {
        cartDetails!.add(CartDetails.fromJson(v));
      });
    }
    carId = json["cart_id"]?.toString() ?? '';
    isAtDoorstepAvailable = json["at_doorstep"]?.toString() ?? '';
    isAtStoreAvailable = json["at_store"]?.toString() ?? '';
    providerName = json['provider_names']?.toString() ?? '';
    translatedProviderName =
        (json["translated_provider_names"]?.toString() ?? '').isNotEmpty
            ? json["translated_provider_names"]!.toString()
            : providerName;
    providerId = json['provider_id']?.toString() ?? '';
    totalQuantity = json['total_quantity']?.toString() ?? '';
    subTotal = json['sub_total']?.toString() ?? '';
    taxPercentage = json['tax_percentage']?.toString() ?? '';
    taxAmount = json['tax_amount']?.toString() ?? '';
    taxValue = json['tax_value']?.toString() ?? '0.00';
    overallAmount = json['overall_amount']?.toString() ?? '';
    total = json['total']?.toString() ?? '';
    visitingCharges = json['visiting_charges']?.toString() ?? '';
    isPayLaterAllowed = json['is_pay_later_allowed']?.toString() ?? '';
    advanceBookingDays = json['advance_booking_days']?.toString() ?? '';
    companyName = json['company_name']?.toString() ?? '';
    translatedCompanyName =
        (json["translated_company_name"]?.toString() ?? '').isNotEmpty
            ? json["translated_company_name"]!.toString()
            : companyName;
    isOnlinePaymentAllowed =
        json['is_online_payment_allowed']?.toString() ?? '';
    subTotalWithoutTax = json['sub_total_without_tax']?.toString() ?? '';
  }
}

class CartDetails {
  String? id;
  String? serviceId;
  String? isSavedForLater;
  String? qty;
  Services? serviceDetails;

  CartDetails({
    this.id,
    this.serviceId,
    this.isSavedForLater,
    this.qty,
    this.serviceDetails,
  });

  CartDetails.fromJson(final Map<String, dynamic> json) {
    id = json['id']?.toString() ?? '';
    serviceId = json['service_id']?.toString() ?? '';
    isSavedForLater = json['is_saved_for_later']?.toString() ?? '';
    qty = json['qty']?.toString() ?? '';
    serviceDetails = json['servic_details'] != null
        ? Services.fromJson(json['servic_details'] as Map<String, dynamic>)
        : null;
  }
}
