
import 'package:e_demand/app/generalImports.dart';

abstract class PlaceOrderState {}

class PlaceOrderInitial extends PlaceOrderState {}

class PlaceOrderInProgress extends PlaceOrderState {}

class PlaceOrderSuccess extends PlaceOrderState {
  PlaceOrderSuccess({
    required this.orderId,
    required this.razorpayOrderId,
    required this.isError,
    required this.message,
    required this.paypalLink,
    required this.paystackLink,
    required this.flutterwaveLink,
    required this.xenditLink,
    this.customJobRequestId,
    this.bidderId,
  });

  final bool isError;
  final String message;
  final String orderId;
  final String? customJobRequestId;
  final String? bidderId;
  final String razorpayOrderId;
  final String paypalLink;
  final String paystackLink;
  final String flutterwaveLink;
  final String xenditLink;
}

class PlaceOrderFailure extends PlaceOrderState {
  PlaceOrderFailure({required this.errorMessage});

  final String errorMessage;
}

class PlaceOrderCubit extends Cubit<PlaceOrderState> {
  PlaceOrderCubit({required this.cartRepository}) : super(PlaceOrderInitial());
  CartRepository cartRepository;

  //This method is used to place an order
  Future<void> placeOrder({
    final String? promoCodeId,
    required final String paymentMethod,
    final String? selectedAddressID,
    final String? orderId,
    final String? customJobRequestId,
    final String? bidderId,
    required final String isAtStoreOptionSelected,
    required final String status,
    required final String orderNote,
    required final String startingTime,
    required final String dateOfService,
  }) async {
    try {
      emit(PlaceOrderInProgress());

      final bookingContext = <String, Object?>{
        'order_id': orderId ?? '',
        'payment_method': paymentMethod,
        'date_of_service': dateOfService,
        'starting_time': startingTime,
        'promo_code_id': promoCodeId ?? '',
        'store_option': isAtStoreOptionSelected,
        'custom_job_request_id': customJobRequestId ?? '',
        'bidder_id': bidderId ?? '',
        'status': status,
      };
      if (orderNote.isNotEmpty) {
        bookingContext['order_note'] = orderNote;
      }
      ClarityService.logAction(
        ClarityActions.bookingRequested,
        bookingContext,
      );

      final orderData = await cartRepository.placeOrder(
        dateOfService: dateOfService,
        orderNote: orderNote,
        paymentMethod: paymentMethod,
        promoCodeId: promoCodeId,
        selectedAddressID: selectedAddressID,
        status: status,
        isAtStoreOptionSelected: isAtStoreOptionSelected,
        startingTime: startingTime,
        orderID: orderId,
        customJobRequestId: customJobRequestId,
        bidderId: bidderId,
      );

      String razorpayOrderId = '';
      if (paymentMethod == 'razorpay') {
        razorpayOrderId = await cartRepository.createRazorpayOrderId(
            orderId: orderData['orderId'].toString(), isAdditionalCharge: '');
      }
      emit(
        PlaceOrderSuccess(
          razorpayOrderId: razorpayOrderId,
          orderId: orderData['orderId'].toString(),
          isError: orderData['error'],
          message: orderData['message'],
          paypalLink: orderData['paypalLink'],
          paystackLink: orderData['paystackLink'],
          flutterwaveLink: orderData['flutterwaveLink'],
          xenditLink: orderData['xenditLink'],
        ),
      );
      final successPayload = {
        'order_id': orderData['orderId']?.toString() ?? '',
        'payment_method': paymentMethod,
        'promo_code_id': promoCodeId ?? '',
        'total_amount': orderData['totalAmount']?.toString() ??
            orderData['finalAmount']?.toString() ??
            '',
        'message': orderData['message']?.toString() ?? '',
        'custom_job_request_id': customJobRequestId ?? '',
        'store_option': isAtStoreOptionSelected,
        'razorpay_order_id': razorpayOrderId,
      };

      if (orderData['error'] == true) {
        ClarityService.logAction(
          ClarityActions.paymentFailed,
          {
            ...successPayload,
            'result': 'api_error',
          },
        );
      } else {
        ClarityService.logAction(
          ClarityActions.bookingConfirmed,
          successPayload,
        );
      }
    } catch (error) {
      ClarityService.logAction(
        ClarityActions.paymentFailed,
        {
          'order_id': orderId ?? '',
          'payment_method': paymentMethod,
          'custom_job_request_id': customJobRequestId ?? '',
          'message': error.toString(),
        },
      );
      emit(PlaceOrderFailure(errorMessage: error.toString()));
    }
  }
}
