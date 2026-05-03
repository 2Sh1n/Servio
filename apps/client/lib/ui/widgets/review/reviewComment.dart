import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class ReviewDetails extends StatelessWidget {
  const ReviewDetails({required this.reviews, final Key? key})
      : super(key: key);
  final Reviews reviews;

  @override
  Widget build(final BuildContext context) {
    // Parse date in DD-MM-YYYY HH:mm format and convert to format expected by convertToAgo

    String dateStringForAgo = reviews.ratedOn!;
    DateTime? parsedDate = DateTime.tryParse(dateStringForAgo);
    if (parsedDate == null) {
      try {
        // Try parsing DD-MM-YYYY HH:mm format
        parsedDate = DateFormat("dd-MM-yyyy HH:mm").parse(reviews.ratedOn!);
        // Convert to yyyy-MM-dd HH:mm:ss format for convertToAgo
        dateStringForAgo = DateFormat("yyyy-MM-dd HH:mm:ss").format(parsedDate);
      } catch (e) {
        // If parsing fails, try other common formats
        try {
          parsedDate =
              DateFormat("yyyy-MM-dd HH:mm:ss").parse(reviews.ratedOn!);
          dateStringForAgo = reviews.ratedOn!;
        } catch (e2) {
          // If all parsing fails, use the original string and let convertToAgo handle it
          dateStringForAgo = reviews.ratedOn!;
        }
      }
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          CustomText(
            reviews.translatedServiceName!,
            color: context.colorScheme.blackColor,
            fontWeight: FontWeight.w700,
            fontStyle: FontStyle.normal,
            fontSize: 14,
            textAlign: TextAlign.left,
          ),
          Padding(
            padding: const EdgeInsetsDirectional.only(top: 5),
            child: Row(
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf50),
                  child: CustomCachedNetworkImage(
                    networkImageUrl: reviews.profileImage!,
                    fit: BoxFit.fill,
                    width: 50,
                    height: 50,
                  ),
                ),
                const CustomSizedBox(
                  width: 8,
                ),
                Expanded(
                  flex: 6,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      CustomText(
                        reviews.userName!,
                        color: context.colorScheme.blackColor,
                        fontWeight: FontWeight.w700,
                        fontStyle: FontStyle.normal,
                        fontSize: 14,
                        textAlign: TextAlign.left,
                      ),
                      if (reviews.comment!.isNotEmpty) ...[
                        CustomReadMoreTextContainer(
                          text: reviews.comment!,
                          textStyle: TextStyle(
                            color: context.colorScheme.lightGreyColor,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                Expanded(
                  flex: 3,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: <Widget>[
                          const CustomSvgPicture(
                            avoideResponsive: true,
                            svgImage: AppAssets.icStar,
                            height: 16,
                            width: 16,
                            color: AppColors.ratingStarColor,
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 5),
                            child: CustomText(
                              double.parse(reviews.rating!).toStringAsFixed(1),
                              fontWeight: FontWeight.w700,
                              color: context.colorScheme.blackColor,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                      CustomText(
                        UiUtils.getTimeAgo(context,
                            date: DateTime.tryParse(dateStringForAgo)),
                        fontWeight: FontWeight.w500,
                        fontSize: 12,
                        color: context.colorScheme.lightGreyColor,
                        maxLines: 1,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (reviews.images!.isNotEmpty) ...[
            const CustomSizedBox(
              height: 10,
            ),
            SingleChildScrollView(
              // padding: const EdgeInsets.symmetric(horizontal: 15),
              scrollDirection: Axis.horizontal,
              child: Row(
                children: List.generate(
                  reviews.images!.length,
                  (final index) => CustomInkWellContainer(
                    onTap: () {
                      Navigator.pushNamed(
                        context,
                        imagePreview,
                        arguments: {
                          "startFrom": index,
                          "reviewDetails": reviews,
                          "isReviewType": true,
                          "dataURL": reviews.images
                        },
                      );
                    },
                    child: CustomContainer(
                      margin: const EdgeInsets.symmetric(horizontal: 5),
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      child: ClipRRect(
                        borderRadius:
                            BorderRadius.circular(UiUtils.borderRadiusOf10),
                        child: CustomCachedNetworkImage(
                          networkImageUrl: reviews.images![index],
                          width: 60,
                          height: 50,
                          fit: BoxFit.cover,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
