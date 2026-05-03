import 'dart:convert';

import 'package:edemand_partner/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:flutter_stripe/flutter_stripe.dart';

class StripeService {
  static Future<void> init(
    final String? stripeId,
    final String? stripeMode,
  ) async {
    Stripe.publishableKey = stripeId ?? '';
    await Stripe.instance.applySettings();
  }

  static Future<StripeTransactionResponse> payWithPaymentSheet({
    required final String transactionID,
  }) async {
    try {
      //create Payment intent via backend
      final Map<String, dynamic>? paymentIntent =
          await StripeService.createPaymentIntent(
            transactionID: transactionID,
          );
      //setting up Payment Sheet
      await Stripe.instance.initPaymentSheet(
        paymentSheetParameters: SetupPaymentSheetParameters(
          paymentIntentClientSecret: paymentIntent!['client_secret'],
          allowsDelayedPaymentMethods: true,
          style: ThemeMode.light,
          merchantDisplayName: appName,
        ),
      );

      //open payment sheet
      await Stripe.instance.presentPaymentSheet();

      //confirm payment via backend
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getStripePaymentStatus,
        parameter: {
          ApiParam.paymentIntentId: paymentIntent['id'],
        },
        useAuthToken: true,
      );

      if (response['error'] == true) {
        throw ApiException(response['message'] ?? 'Payment confirmation failed');
      }

      final Map<String, dynamic> stripeData = response['data'] is String
          ? Map<String, dynamic>.from(jsonDecode(response['data']))
          : Map<String, dynamic>.from(response['data']);

      final statusOfTransaction = stripeData['status'];

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
    } catch (error) {
      return StripeTransactionResponse(
        message: 'Transaction failed: $error',
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
      message: message,
      success: false,
      status: 'cancelled',
    );
  }

  static Future<Map<String, dynamic>?> createPaymentIntent({
    required final String transactionID,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.createStripePaymentIntent,
        parameter: {
          ApiParam.transactionID: transactionID,
        },
        useAuthToken: true,
      );

      if (response['error'] == true) {
        throw ApiException(response['message'] ?? 'Failed to create payment intent');
      }

      // Backend returns Stripe's response in 'data' field
      final Map<String, dynamic> stripeData = response['data'] is String
          ? Map<String, dynamic>.from(jsonDecode(response['data']))
          : Map<String, dynamic>.from(response['data']);

      return stripeData;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}

class StripeTransactionResponse {
  StripeTransactionResponse({this.message, this.success, this.status});

  final String? message, status;
  bool? success;
}
