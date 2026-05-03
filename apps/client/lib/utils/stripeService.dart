import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:flutter_stripe/flutter_stripe.dart';

class StripeService {
  static void init(final String? stripeId, final String? stripeMode) {
    Stripe.publishableKey = stripeId ?? '';
  }

  static Future<StripeTransactionResponse> payWithPaymentSheet({
    required final String orderId,
    final String? transactionId,
  }) async {
    try {
      //create Payment intent via backend
      final Map<String, dynamic>? paymentIntent =
          await StripeService.createPaymentIntent(
        orderId: orderId,
        transactionId: transactionId,
      );

      if (paymentIntent == null) {
        return StripeTransactionResponse(
          message: 'Failed to create payment intent',
          success: false,
          status: 'fail',
        );
      }

      //setting up Payment Sheet
      await Stripe.instance.initPaymentSheet(
        paymentSheetParameters: SetupPaymentSheetParameters(
          paymentIntentClientSecret: paymentIntent['client_secret'],
          allowsDelayedPaymentMethods: true,
          style: ThemeMode.light,
          merchantDisplayName: appName,
          billingDetailsCollectionConfiguration:
              const BillingDetailsCollectionConfiguration(
            address: AddressCollectionMode.full,
            email: CollectionMode.always,
            name: CollectionMode.always,
            phone: CollectionMode.always,
          ),
        ),
      );

      //open payment sheet
      await Stripe.instance.presentPaymentSheet();

      //confirm payment via backend
      final statusOfTransaction = await StripeService.getPaymentStatus(
        paymentIntentId: paymentIntent['id'],
      );

      if (statusOfTransaction == 'succeeded') {
        return StripeTransactionResponse(
          message: 'Transaction successful',
          success: true,
          status: statusOfTransaction,
        );
      } else if (statusOfTransaction == 'pending' ||
          statusOfTransaction == 'captured') {
        return StripeTransactionResponse(
          message: 'Transaction pending',
          success: true,
          status: statusOfTransaction,
        );
      } else {
        return StripeTransactionResponse(
          message: 'Transaction failed',
          success: false,
          status: statusOfTransaction,
        );
      }
    } on PlatformException catch (err) {
      return StripeService.getPlatformExceptionErrorResult(err);
    } catch (error, st) {
      return StripeTransactionResponse(
        message: 'Transaction failed: $error $st',
        success: false,
        status: 'fail',
      );
    }
  }

  static StripeTransactionResponse getPlatformExceptionErrorResult(final err) {
    String message = 'Something went wrong';
    if (err.code == 'cancelled') {
      message = 'Transaction cancelled';
    }

    return StripeTransactionResponse(
        message: message, success: false, status: 'cancelled');
  }

  static Future<Map<String, dynamic>?> createPaymentIntent({
    required final String orderId,
    final String? transactionId,
  }) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.orderId: orderId,
      };

      if (transactionId != null && transactionId.isNotEmpty) {
        parameter[ApiParam.transactionId] = transactionId;
      }

      final response = await ApiClient.post(
        url: ApiUrl.createStripePaymentIntent,
        parameter: parameter,
        useAuthToken: true,
      );

      if (response['error'] == false && response['data'] != null) {
        return Map<String, dynamic>.from(response['data']);
      }
      return null;
    } catch (_) {}
    return null;
  }

  static Future<String?> getPaymentStatus({
    required final String paymentIntentId,
  }) async {
    try {
      final response = await ApiClient.post(
        url: ApiUrl.getStripePaymentStatus,
        parameter: {ApiParam.paymentIntentId: paymentIntentId},
        useAuthToken: true,
      );

      if (response['error'] == false && response['data'] != null) {
        final data = response['data'];
        return data['status'];
      }
      return 'fail';
    } catch (_) {
      return 'fail';
    }
  }
}

class StripeTransactionResponse {
  StripeTransactionResponse({this.message, this.success, this.status});

  final String? message, status;
  bool? success;
}
