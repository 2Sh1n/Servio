import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class PreviousOrderCardContainer extends StatelessWidget {
  final Booking bookingDetailsData;

  const PreviousOrderCardContainer({
    Key? key,
    required this.bookingDetailsData,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final services = bookingDetailsData.services ?? [];
    final showMore = services.length > 1;
    final mainService = services.isNotEmpty ? services[0] : null;
    final moreCount = services.length - 1;
    return CustomContainer(
      margin: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      border: Border.all(color: context.colorScheme.lightGreyColor, width: 0.5),
      borderRadius: UiUtils.borderRadiusOf10,
      width: 312,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: CustomText(
                    bookingDetailsData.translatedCompanyName ?? '',
                    fontSize: 14,
                    fontWeight: FontWeight.w400,
                    color: context.colorScheme.lightGreyColor,
                    maxLines: 1,
                  ),
                ),
                CustomText(
                  bookingDetailsData.translatedStatus ?? '',
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: UiUtils.getBookingStatusColor(
                      context: context,
                      statusVal: bookingDetailsData.status ?? ''),
                  maxLines: 1,
                ),
              ],
            ),
            const CustomSizedBox(
              height: 12,
            ),
            Divider(
              height: 1,
              thickness: 0.5,
              color: context.colorScheme.lightGreyColor,
            ),
            const CustomSizedBox(
              height: 12,
            ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomContainer(
                  width: 50,
                  height: 50,
                  borderRadius: UiUtils.borderRadiusOf10,
                  child: ClipRRect(
                      borderRadius:
                          BorderRadius.circular(UiUtils.borderRadiusOf10),
                      child: CustomCachedNetworkImage(
                        fit: BoxFit.cover,
                        networkImageUrl: mainService?.image ?? '',
                      )),
                ),
                const CustomSizedBox(
                  width: 12,
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CustomText(
                        mainService?.translatedTitle ?? '',
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: context.colorScheme.blackColor,
                        maxLines: 1,
                      ),
                      const CustomSizedBox(
                        height: 8,
                      ),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          if (showMore) ...[
                            IntrinsicWidth(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  CustomText(
                                    '+$moreCount ${'more'.translate(context: context)}',
                                    fontSize: 12,
                                    fontWeight: FontWeight.w400,
                                    color: context.colorScheme.accentColor,
                                  ),
                                  const CustomSizedBox(
                                    height: 2,
                                  ),
                                  CustomContainer(
                                    height: 1.2,
                                    color: context.colorScheme.accentColor,
                                  ),
                                ],
                              ),
                            ),
                            const CustomSizedBox(
                              width: 12,
                            ), // Space between text and divider
                            Container(
                              width: 1.5,
                              height: 20,
                              color: context.colorScheme.lightGreyColor
                                  .withValues(alpha: 0.5),
                            ),
                            const CustomSizedBox(
                              width: 12,
                            ), // Space between divider and total
                          ],
                          CustomText(
                            '${'total'.translate(context: context)} - ',
                            fontSize: 14,
                            fontWeight: FontWeight.w400,
                            color: context.colorScheme.lightGreyColor,
                          ),
                          CustomText(
                            (bookingDetailsData.finalTotal ?? '0')
                                .priceFormat(context),
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: context.colorScheme.blackColor,
                          ),
                        ],
                      )
                    ],
                  ),
                ),
              ],
            ),
            const CustomSizedBox(height: 12),
            Row(
              children: [
                if (bookingDetailsData.status == "completed") ...[
                  Expanded(
                      child: CustomRoundedButton(
                    onTap: () {
                      Navigator.pushNamed(
                        context,
                        bookingDetails,
                        arguments: {"bookingDetails": bookingDetailsData},
                      );
                    },
                    backgroundColor:
                        context.colorScheme.lightGreyColor.withAlpha(40),
                    buttonTitle: 'viewService'.translate(context: context),
                    showBorder: false,
                    widthPercentage: 0.9,
                    fontWeight: FontWeight.w500,
                    radius: UiUtils.borderRadiusOf5,
                    titleColor: context.colorScheme.blackColor,
                    shadowColor:
                        context.colorScheme.lightGreyColor.withAlpha(20),
                    textSize: 14,
                  )),
                  const CustomSizedBox(
                    width: 10,
                  )
                ],
                if (bookingDetailsData.isReorderAllowed == "1") ...[
                  const CustomSizedBox(width: 12),
                  Expanded(
                    child: ReOrderButton(
                      bookingDetails: bookingDetailsData,
                      isReorderFrom: "bookingDetails",
                      bookingId: bookingDetailsData.id ?? "0",
                      textSize: 14,
                      cardFrom: 'home',
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
