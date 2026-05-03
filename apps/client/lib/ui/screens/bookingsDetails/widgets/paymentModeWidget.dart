import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

typedef PaymentGatewayDetails = ({String paymentType, String paymentImage});

class PaymentModeWidget extends StatelessWidget {
  final Booking bookingDetails;
  final bool isAwaitingOrConfirmed;
  final bool hasUnpaidAdditionalCharges;
  final bool isBookingCancelable;
  final Function(String) onReschedule;
  final VoidCallback onCancelBooking;
  final VoidCallback onPayAdditionalCharges;

  const PaymentModeWidget({
    super.key,
    required this.bookingDetails,
    required this.isAwaitingOrConfirmed,
    required this.hasUnpaidAdditionalCharges,
    required this.isBookingCancelable,
    required this.onReschedule,
    required this.onCancelBooking,
    required this.onPayAdditionalCharges,
  });

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      padding: const EdgeInsets.all(15),
      child: Column(
        children: [
          _buildMainPaymentSection(context),
          if (_hasAdditionalChargePayment()) ...[
            const CustomSizedBox(height: 10),
            _buildAdditionalChargePaymentSection(context),
          ],
          const CustomSizedBox(height: 20),
          _buildActionButtonsRow(context),
        ],
      ),
    );
  }

  bool _hasAdditionalChargePayment() {
    return bookingDetails.paymentStatusOfAdditionalCharge != '';
  }

  Widget _buildMainPaymentSection(BuildContext context) {
    final String paymentMethod = bookingDetails.paymentMethod ?? '';
    final PaymentGatewayDetails paymentDetails =
        _getPaymentGatewayDetails(paymentMethod: paymentMethod.toLowerCase());
    final String paymentStatus =
        _getFormattedPaymentStatus(context, bookingDetails.paymentStatus);

    return Row(
      children: [
        _buildPaymentIcon(context, paymentDetails.paymentImage),
        const CustomSizedBox(width: 12),
        _buildPaymentInfo(
          context,
          title: 'paymentMode'.translate(context: context),
          paymentType: paymentMethod == 'cod'
              ? paymentDetails.paymentType.capitalize()
              : paymentMethod.capitalize(),
          status: paymentStatus,
        ),
      ],
    );
  }

  Widget _buildAdditionalChargePaymentSection(BuildContext context) {
    final String paymentMethod =
        bookingDetails.paymentMethodOfAdditionalCharge ?? '';
    final PaymentGatewayDetails paymentDetails =
        _getPaymentGatewayDetails(paymentMethod: paymentMethod.toLowerCase());
    final String paymentStatus = _getAdditionalChargePaymentStatus(context);

    return Row(
      children: [
        _buildPaymentIcon(context, paymentDetails.paymentImage),
        const CustomSizedBox(width: 12),
        _buildPaymentInfo(
          context,
          title: 'additionalChargesPaymentMode'.translate(context: context),
          paymentType: paymentMethod == 'cod'
              ? paymentDetails.paymentType.capitalize()
              : paymentMethod.capitalize(),
          status: paymentStatus,
        ),
      ],
    );
  }

  Widget _buildPaymentIcon(BuildContext context, String iconPath) {
    return CustomContainer(
      height: 44,
      width: 44,
      borderRadius: UiUtils.borderRadiusOf5,
      child: CustomSvgPicture(svgImage: iconPath),
    );
  }

  Widget _buildPaymentInfo(
    BuildContext context, {
    required String title,
    required String paymentType,
    required String status,
  }) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          CustomText(
            title,
            fontSize: 12,
            fontWeight: FontWeight.w400,
            color: context.colorScheme.blackColor,
          ),
          const CustomSizedBox(height: 4),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Expanded(
                child: CustomText(
                  paymentType,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: context.colorScheme.accentColor,
                ),
              ),
              CustomText(
                status,
                color: UiUtils.getPaymentStatusColor(paymentStatus: status),
                fontWeight: FontWeight.w700,
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _getFormattedPaymentStatus(
      BuildContext context, String? paymentStatus) {
    final String status = paymentStatus?.toLowerCase().isEmpty ?? true
        ? "pending"
        : paymentStatus?.toLowerCase() ?? '';
    return status.translate(context: context);
  }

  String _getAdditionalChargePaymentStatus(BuildContext context) {
    final String statusCode =
        bookingDetails.paymentStatusOfAdditionalCharge ?? '';
    final String status = switch (statusCode) {
      '0' => "pending",
      '1' => "success",
      '2' => "failed",
      _ => '',
    };
    return status.translate(context: context);
  }

  Widget _buildActionButtonsRow(BuildContext context) {
    return Align(
      alignment: Alignment.bottomCenter,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (isAwaitingOrConfirmed) ...[
            Expanded(child: _buildRescheduleButton(context)),
            const CustomSizedBox(width: 10),
          ],
          if (hasUnpaidAdditionalCharges) ...[
            const CustomSizedBox(width: 10),
            Expanded(child: _buildPayAdditionalChargesButton(context)),
          ],
          if (isBookingCancelable) ...[
            Expanded(child: _buildCancelButton(context)),
          ],
        ],
      ),
    );
  }

  Widget _buildRescheduleButton(BuildContext context) {
    return CancelAndRescheduleButton(
      bookingId: bookingDetails.id ?? "0",
      buttonName: "reschedule",
      onButtonTap: () => onReschedule("reschedule"),
    );
  }

  Widget _buildPayAdditionalChargesButton(BuildContext context) {
    return Align(
      alignment: Alignment.bottomCenter,
      child: Padding(
        padding: const EdgeInsetsDirectional.only(bottom: 10),
        child: CustomRoundedButton(
          buttonTitle: 'payAdditionalCharges'.translate(context: context),
          showBorder: true,
          widthPercentage: 1,
          backgroundColor: context.colorScheme.accentColor,
          onTap: onPayAdditionalCharges,
        ),
      ),
    );
  }

  Widget _buildCancelButton(BuildContext context) {
    return CancelAndRescheduleButton(
      bookingId: bookingDetails.id ?? "0",
      buttonName: "cancelBooking",
      backgroundColor: AppColors.redColor.withAlpha(50),
      titleColor: AppColors.redColor,
      onButtonTap: onCancelBooking,
    );
  }

  PaymentGatewayDetails _getPaymentGatewayDetails(
      {required String paymentMethod}) {
    switch (paymentMethod) {
      case "cod":
        return (paymentType: "cod", paymentImage: AppAssets.cod);
      case "stripe":
        return (paymentType: "stripe", paymentImage: AppAssets.stripe);
      case "razorpay":
        return (paymentType: "razorpay", paymentImage: AppAssets.razorpay);
      case "paystack":
        return (paymentType: "paystack", paymentImage: AppAssets.paystack);
      case "paypal":
        return (paymentType: "paypal", paymentImage: AppAssets.paypal);
      case "flutterwave":
        return (
          paymentType: "flutterwave",
          paymentImage: AppAssets.flutterwave
        );
      case "xendit":
        return (paymentType: "Xendi", paymentImage: AppAssets.cod);
      default:
        return (paymentType: "cod", paymentImage: AppAssets.cod);
    }
  }
}
