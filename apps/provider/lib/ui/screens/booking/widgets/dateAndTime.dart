import 'package:flutter/material.dart';

import '../../../../app/generalImports.dart';

class DateAndTime extends StatelessWidget {
  final BookingsModel bookingModel;
  const DateAndTime({super.key, required this.bookingModel});

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        children: [
          CustomSvgPicture(
            svgImage: AppAssets.bCalender,
            height: 16,
            width: 16,
            color: context.colorScheme.accentColor,
          ),
          const SizedBox(width: 5),
          CustomText(
            bookingModel.dateOfService.toString().formatDate(),
            fontSize: 14,
            color: context.colorScheme.blackColor,
            fontWeight: FontWeight.w600,
          ),
          VerticalDivider(
            color: context.colorScheme.lightGreyColor,
            thickness: 0.2,
          ),
          CustomSvgPicture(
            svgImage: AppAssets.bClock,
            height: 16,
            width: 16,
            color: context.colorScheme.accentColor,
          ),
          const SizedBox(width: 5),
          CustomText(
            (bookingModel.startingTime ?? '').toString().formatTime(),
            fontSize: 14,
            color: context.colorScheme.blackColor,
            fontWeight: FontWeight.w600,
          ),
        ],
      ),
    );
  }
}
