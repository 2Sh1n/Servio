import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class PaymentGatewayManager {
  final Razorpay _razorpay = Razorpay();
  late final PaymentGatewaysSettings? paymentGatewaySetting;
  late final BuildContext context;
  String placedOrderId;

  PaymentGatewayManager({
    required this.context,
    required this.paymentGatewaySetting,
    required this.placedOrderId,
  }) {
    _initializeRazorpay();
  }

  void _initializeRazorpay() {
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handleRazorPayPaymentSuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handleRazorPayPaymentError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleRazorPayExternalWallet);
  }

  void _handleRazorPayPaymentSuccess(PaymentSuccessResponse response) {
    _navigateToOrderConfirmation(
      isSuccess: true,
      paymentGatewayName: "razorpay",
    );
  }

  void _handleRazorPayPaymentError(PaymentFailureResponse response) {}

  void _handleRazorPayExternalWallet(ExternalWalletResponse response) {}

  Future<void> processPayment({
    required String paymentMethod,
    required double amount,
    required String orderId,
    String? paymentUrl,
  }) async {
    placedOrderId = orderId;
    switch (paymentMethod) {
      case "cod":
        await _handleCodPayment(amount: amount);
      case "stripe":
        await _handleStripePayment(amount: amount);
        break;
      case "razorpay":
        await _handleRazorpayPayment(
          amount: amount,
          placedOrderId: placedOrderId,
        );
        break;
      case "paystack":
        await _handlePaystackPayment(paymentUrl: paymentUrl ?? '');
        break;
      case "paypal":
        await _handlePaypalPayment(paymentUrl: paymentUrl ?? '');
        break;
      case "flutterwave":
        await _handleFlutterwavePayment(paymentUrl: paymentUrl ?? '');
      case 'xendit':
        await _handleXenditPayment(paymentUrl: paymentUrl ?? '');
        break;
    }
  }

  Future<void> _handleCodPayment({required double amount}) async {
    _navigateToOrderConfirmation(isSuccess: true, paymentGatewayName: "cod");
  }

  Future<void> _handleStripePayment({required double amount}) async {
    try {
      await context.read<AddTransactionCubit>().addTransaction(
            status: "pending",
            orderID: placedOrderId,
            isAdditionalCharge: '1',
            paymentGatewayName: "stripe",
          );
      final String transactionId =
          context.read<AddTransactionCubit>().getTransactionId() ?? '';
      StripeService.init(
        paymentGatewaySetting!.stripePublishableKey,
        paymentGatewaySetting!.stripeMode,
      );

      final response = await StripeService.payWithPaymentSheet(
        orderId: placedOrderId,
        transactionId: transactionId,
      );
      if (response.status == 'succeeded') {
        _navigateToOrderConfirmation(
          isSuccess: true,
          paymentGatewayName: "stripe",
        );
      } else {
        UiUtils.showMessage(
            context,
            'paymentFailure'.translate(context: context),
            ToastificationType.error);

        context
            .read<AddTransactionCubit>()
            .addTransaction(status: "cancelled", orderID: placedOrderId);

        context.read<BookingCubit>().fetchBookingDetails(status: '');
        return;
      }
    } catch (_) {
      _navigateToOrderConfirmation(
        isSuccess: false,
        paymentGatewayName: "stripe",
      );
    }
  }

  Future<void> _handleRazorpayPayment({
    required final double amount,
    required final String placedOrderId,
  }) async {
    await context.read<AddTransactionCubit>().addTransaction(
          status: "pending",
          orderID: placedOrderId,
          isAdditionalCharge: '1',
          paymentGatewayName: "razorpay",
        );
    final _cartRepository = CartRepository();
    final String razorpayOrderId = await _cartRepository.createRazorpayOrderId(
        orderId: placedOrderId, isAdditionalCharge: '1');
    final transactionId =
        context.read<AddTransactionCubit>().getTransactionId();
    final options = {
      'key': paymentGatewaySetting!.razorpayKey,
      'amount': (amount * 100).toInt(),
      'name': appName,
      'description': 'Additional Charges Payment',
      'currency': paymentGatewaySetting!.razorpayCurrency,
      'notes': {
        'order_id': placedOrderId,
        'additional_charges_transaction_id': transactionId
      },
      'order_id': razorpayOrderId,
    };

    _razorpay.open(options);
  }

  Future<void> _handlePaystackPayment({required String paymentUrl}) async {
    _openWebviewPaymentGateway(
        webviewLink: paymentUrl, paymentGatewayName: "paystack");
  }

  Future<void> _handlePaypalPayment({required String paymentUrl}) async {
    _openWebviewPaymentGateway(
        webviewLink: paymentUrl, paymentGatewayName: "paypal");
  }

  Future<void> _handleFlutterwavePayment({required String paymentUrl}) async {
    _openWebviewPaymentGateway(
        webviewLink: paymentUrl, paymentGatewayName: "flutterwave");
  }

  Future<void> _handleXenditPayment({required String paymentUrl}) async {
    _openWebviewPaymentGateway(
        webviewLink: paymentUrl, paymentGatewayName: 'xendit');
  }

  void _openWebviewPaymentGateway(
      {required String webviewLink,
      required final String? paymentGatewayName}) {
    Navigator.pushNamed(
      context,
      webviewPaymentScreen,
      arguments: {'paymentURL': webviewLink},
    ).then((final Object? value) {
      final parameter = value as Map;
      if (parameter['paymentStatus'] == 'Completed') {
        _navigateToOrderConfirmation(
          isSuccess: true,
          paymentGatewayName: paymentGatewayName,
        );
      } else if (parameter['paymentStatus'] == 'Failed') {
        _navigateToOrderConfirmation(
          isSuccess: false,
          paymentGatewayName: paymentGatewayName,
        );
      }
    });
  }

  void _navigateToOrderConfirmation({
    required final bool isSuccess,
    required final String? paymentGatewayName,
  }) {
    if (!isSuccess) {
      UiUtils.showMessage(context, 'paymentFailed'.translate(context: context),
          ToastificationType.error);

      context
          .read<AddTransactionCubit>()
          .addTransaction(status: "cancelled", orderID: placedOrderId);

      context.read<BookingCubit>().fetchBookingDetails(status: '');
      return;
    }
    final transactionId =
        context.read<AddTransactionCubit>().getTransactionId();
    Navigator.pushNamed(
      context,
      orderConfirmation,
      arguments: {
        'isSuccess': isSuccess,
        'orderId': placedOrderId,
        'isFromAdditionalCharge': true,
        'paymentGatewayName': paymentGatewayName,
        'transactionId': transactionId ?? '',
      },
    );
  }

  void dispose() {
    _razorpay.clear();
  }
}
