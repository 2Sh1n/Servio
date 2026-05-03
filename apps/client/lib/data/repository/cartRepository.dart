import 'package:e_demand/app/generalImports.dart';
import 'package:intl/intl.dart';

class CartRepository {
  //This method is used to get user cart details
  Future<Map<String, dynamic>> getUserCart(
      {required final bool useAuthToken, final String? reOrderId}) async {
    try {
      final cartData = await ApiClient.post(
          url: ApiUrl.getCart,
          parameter: reOrderId == null ? {} : {ApiParam.orderId: reOrderId},
          useAuthToken: useAuthToken);

      // Check if data is empty array or not a Map
      if (cartData["data"] is! Map ||
          (cartData["data"] is List && (cartData["data"] as List).isEmpty)) {
        return {
          "error": cartData["error"],
          "message": cartData["message"],
          "cartData": {},
          "reOrderCartData": {}
        };
      }

      // Check if both cart_data.data and reorder_data.data are empty
      final cartDataData = cartData["data"]["cart_data"]["data"];
      final reorderDataData = cartData["data"]["reorder_data"]["data"];

      final isCartDataEmpty = cartDataData == '' ||
          cartDataData == null ||
          (cartDataData is List && cartDataData.isEmpty);
      final isReorderDataEmpty = reorderDataData == '' ||
          reorderDataData == null ||
          (reorderDataData is List && reorderDataData.isEmpty);

      if (isCartDataEmpty && isReorderDataEmpty) {
        return {
          "error": cartData["error"],
          "message": cartData["message"],
          "cartData": {},
          "reOrderCartData": {}
        };
      }
      return {
        "error": cartData["error"],
        "message": cartData["message"],
        "cartData": cartData["data"]["cart_data"],
        "reOrderCartData": cartData["data"]["reorder_data"]
      };
    } catch (e) {
      throw ApiException("somethingWentWrong"
          .translate(context: UiUtils.rootNavigatorKey.currentContext!));
    }
  }

  ///This method is used to addService into Cart
  Future<Map<String, dynamic>> addServiceIntoCart({
    required final bool useAuthToken,
    required final int serviceId,
    required final int quantity,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.qty: quantity,
        ApiParam.serviceId: serviceId
      };
      final Map<String, dynamic> cartData = await ApiClient.post(
          url: ApiUrl.manageCart,
          parameter: parameter,
          useAuthToken: useAuthToken);

      //to store updated cart data
      Cart updatedCartData = Cart();
      if (cartData['data'] != []) {
        updatedCartData = Cart.fromJson(cartData);
      }
      return {
        'error': cartData['error'],
        'message': cartData['message'],
        'cartData': updatedCartData
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to remove service from Cart
  Future<Map<String, dynamic>> removeServiceFromCart({
    required final bool useAuthToken,
    required final int serviceId,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.serviceId: serviceId
      };
      final cartData = await ApiClient.post(
          url: ApiUrl.removeFromCart,
          parameter: parameter,
          useAuthToken: useAuthToken);

      //to store updated cart data
      Cart updatedCartData = Cart();
      if (cartData['data'] != []) {
        updatedCartData = Cart.fromJson(cartData);
      }

      return {
        'error': cartData['error'],
        'message': cartData['message'],
        'cartData': updatedCartData
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to remove Cart
  Future<Map<String, dynamic>> removeCart({
    required final bool useAuthToken,
    required final String providerId,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.providerId: providerId
      };
      final cartData = await ApiClient.post(
          url: ApiUrl.removeFromCart,
          parameter: parameter,
          useAuthToken: useAuthToken);

      //to store updated cart data
      Cart updatedCartData = Cart();
      if (cartData['data'] != []) {
        updatedCartData = Cart.fromJson(cartData);
      }

      return {
        'error': cartData['error'],
        'message': cartData['message'],
        'cartData': updatedCartData
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to fetch timeslot of specific date
  Future<Map<String, dynamic>> fetchTimeSlots({
    required final bool useAuthToken,
    required final int providerId,
    required final String selectedDate,
    final String? customJobRequestId,
  }) async {
    try {
      final parameter = <String, dynamic>{
        ApiParam.partnerId: providerId,
        ApiParam.date: selectedDate,
        ApiParam.customJobRequestId: customJobRequestId,
      };
      parameter.removeWhere(
        (key, value) => value == null || value == "null" || value == '',
      );
      final cartData = await ApiClient.post(
        url: ApiUrl.getAvailableSlots,
        parameter: parameter,
        useAuthToken: useAuthToken,
      );

      final Map<String, dynamic> response = <String, dynamic>{
        'error': cartData['error'],
        'message': cartData['message'],
        'slots': <AllSlots>[]
      };

      if (!cartData['error']) {
        response['slots'] = (cartData['data']['all_slots'] as List)
            .map((final e) => AllSlots.fromJson(Map.from(e)))
            .toList();
      }

      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///this method is used to place an order
  Future<Map<String, dynamic>> placeOrder({
    final String? promoCodeId,
    required final String paymentMethod,
    required final String? selectedAddressID,
    required final String isAtStoreOptionSelected,
    required final String status,
    required final String orderNote,
    required final String startingTime,
    required final String dateOfService,
    final String? orderID,
    final String? customJobRequestId,
    final String? bidderId,
  }) async {
    try {
      final parameter = <String, dynamic>{
        ApiParam.promocodeId: promoCodeId ?? '',
        ApiParam.paymentMethod: paymentMethod,
        ApiParam.addressId: selectedAddressID,
        ApiParam.status: status,
        ApiParam.orderNote: orderNote,
        ApiParam.dateOfService: DateFormat("yyyy-MM-dd")
            .format(DateTime.parse("$dateOfService 00:00:00.000Z")),
        ApiParam.startingTime: startingTime,
        ApiParam.atStore: isAtStoreOptionSelected,
        ApiParam.orderId: orderID,
        ApiParam.customJobRequestId: customJobRequestId,
        ApiParam.bidderId: bidderId,
      };

      parameter.removeWhere(
        (key, value) => value == null || value == "null" || value == '',
      );

      final orderData = await ApiClient.post(
          url: ApiUrl.placeOrder, parameter: parameter, useAuthToken: true);
      if (orderData['error'] == true) {
        final errorMessage = orderData['message']?.toString() ??
            'somethingWentWrong'
                .translate(context: UiUtils.rootNavigatorKey.currentContext!);
        throw ApiException(errorMessage);
      }
      return {
        'orderId': orderData['data']['order_id'],
        'error': orderData['error'],
        'message': orderData['message'] ?? '',
        'paypalLink': orderData['data']['paypal_link'] ?? '',
        'paystackLink': orderData['data']['paystack_link'] ?? '',
        'flutterwaveLink': orderData['data']['flutterwave'] ?? '',
        'xenditLink': orderData['data']['xendit'] ?? '',
      };
    } catch (e) {
      if (e is ApiException) {
        throw e;
      }
      // For other exceptions, convert to ApiException
      throw ApiException(e.toString());
    }
  }

  ///This method is used to create razorpay order Id
  Future<String> createRazorpayOrderId(
      {required final String orderId,
      required final String isAdditionalCharge}) async {
    try {
      final Map<String, dynamic> parameters = <String, dynamic>{
        ApiParam.orderId: orderId,
        ApiParam.isAdditionalCharge: isAdditionalCharge
      };
      final result = await ApiClient.post(
          parameter: parameters,
          url: ApiUrl.createRazorpayOrder,
          useAuthToken: true);

      return result['data']['id'];
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to fetch promocodes of partner
  Future fetchPromocodes(
      {required final String providerIdOrSlug,
      required final ProviderDetailsParamType type}) async {
    try {
      final Map<String, dynamic> parameters = <String, dynamic>{
        if (type == ProviderDetailsParamType.slug)
          ApiParam.providerSlug: providerIdOrSlug
        else
          ApiParam.partnerId: providerIdOrSlug,
      };
      final result = await ApiClient.post(
          parameter: parameters, url: ApiUrl.getPromocode, useAuthToken: false);

      if (result["data"] == null) {}
      return (result['data'] as List)
          .map((final e) => Promocode.fromJson(Map.from(e)))
          .toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to validate promocodes of partner
  Future<Map<String, dynamic>> validatePromocode({
    required final String promocodeId,
    required final String totalAmount,
  }) async {
    try {
      final Map<String, dynamic> parameters = <String, dynamic>{
        ApiParam.promocodeId: promocodeId,
        ApiParam.totalAmount: totalAmount
      };
      final result = await ApiClient.post(
          parameter: parameters,
          url: ApiUrl.validatePromocode,
          useAuthToken: true);

      final Map<String, dynamic> response = <String, dynamic>{
        'error': result['error'],
        'message': result['message'],
      };
      if (!result['error']) {
        response['discountAmount'] = result['data'][0]['final_discount'];
        response['finalOrderAmount'] = result['data'][0]['final_total'];
      }
      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  ///This method is used to validate custom time for booking
  Future<Map<String, dynamic>> validateCustomTime({
    required final String providerId,
    required final String selectedDate,
    required final String selectedTime,
    final String? orderId,
    final String? customJobRequestId,
  }) async {
    try {
      final Map<String, dynamic> parameters = <String, dynamic>{
        ApiParam.date: selectedDate,
        ApiParam.time: selectedTime,
        ApiParam.partnerId: providerId,
        ApiParam.orderId: orderId,
        ApiParam.customJobRequestId: customJobRequestId,
      };

      parameters.removeWhere(
        (key, value) => value == null || value == "null" || value == '',
      );
      final result = await ApiClient.post(
          parameter: parameters,
          url: ApiUrl.validateCustomTime,
          useAuthToken: true);

      final Map<String, dynamic> response = <String, dynamic>{
        'error': result['error'],
        'message': result['message'],
      };
      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
