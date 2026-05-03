import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class StatusInvoiceWidget extends StatelessWidget {
  final String translatedStatus;
  final String status;
  final String? invoiceNumber;

  const StatusInvoiceWidget({
    super.key,
    required this.status,
    required this.invoiceNumber,
    required this.translatedStatus,
  });

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      padding: const EdgeInsets.all(15),
      border: Border.symmetric(
        horizontal: BorderSide(
          color: UiUtils.getBookingStatusColor(
              context: context, statusVal: translatedStatus),
          width: 0.5,
        ),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          _buildStatusSection(context, translatedStatus),
          _buildInvoiceSection(context),
        ],
      ),
    );
  }

  Widget _buildStatusSection(BuildContext context, String statusTranslated) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.start,
      children: [
        CustomText(
          "${"status".translate(context: context)}: ",
          maxLines: 1,
          color: context.colorScheme.blackColor,
          textAlign: TextAlign.start,
          fontSize: 14,
          fontWeight: FontWeight.w400,
        ),
        CustomText(
          statusTranslated.capitalize(),
          color: UiUtils.getBookingStatusColor(
              context: context, statusVal: status.toLowerCase()),
          maxLines: 1,
          fontSize: 14,
          fontWeight: FontWeight.w700,
        ),
      ],
    );
  }

  Widget _buildInvoiceSection(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.start,
      children: [
        CustomText(
          "${"invoiceNumber".translate(context: context)}: ",
          maxLines: 1,
          color: context.colorScheme.blackColor,
          textAlign: TextAlign.start,
          fontSize: 14,
          fontWeight: FontWeight.w400,
        ),
        CustomText(
          invoiceNumber ?? "0",
          color: context.colorScheme.accentColor,
          maxLines: 1,
          fontSize: 14,
          fontWeight: FontWeight.w400,
        ),
      ],
    );
  }
}
