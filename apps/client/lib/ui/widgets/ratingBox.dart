import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class RatingBox extends StatelessWidget {
  final String averageRating;
  final String totalNumberOfRatings;
  final String totalNumberOfFiveStarRating;
  final String totalNumberOfFourStarRating;
  final String totalNumberOfThreeStarRating;
  final String totalNumberOfTwoStarRating;
  final String totalNumberOfOneStarRating;
  final double progressReviewWidth;

  const RatingBox({
    final Key? key,
    required this.averageRating,
    required this.totalNumberOfRatings,
    required this.totalNumberOfFiveStarRating,
    required this.totalNumberOfFourStarRating,
    required this.totalNumberOfThreeStarRating,
    required this.totalNumberOfTwoStarRating,
    required this.totalNumberOfOneStarRating,
    required this.progressReviewWidth,
  }) : super(key: key);

  @override
  Widget build(final BuildContext context) => CustomContainer(
        height: 100,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              flex: 8,
              child: CustomContainer(
                color: context.colorScheme.primaryColor,
                borderRadius: UiUtils.borderRadiusOf10,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CustomText(
                      double.parse(averageRating).toStringAsFixed(1),
                      color: context.colorScheme.accentColor,
                      fontWeight: FontWeight.w600,
                    ),
                    const CustomSizedBox(height: 5),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(5, (final int index) {
                        final double starRating = double.parse(averageRating);
                        if (index < starRating) {
                          return const Icon(
                            Icons.star,
                            color: AppColors.ratingStarColor,
                          );
                        }
                        return Icon(Icons.star,
                            color: context.colorScheme.lightGreyColor);
                      }),
                    ),
                  ],
                ),
              ),
            ),
            const CustomSizedBox(
              width: 5,
            ),
            Expanded(
              flex: 9,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  ratingProgressBar(
                    context,
                    '5',
                    totalNumberOfRatings != '0'
                        ? int.parse(totalNumberOfFiveStarRating) /
                            int.parse(totalNumberOfRatings)
                        : 0,
                  ),
                  ratingProgressBar(
                    context,
                    '4',
                    totalNumberOfRatings != '0'
                        ? int.parse(totalNumberOfFourStarRating) /
                            int.parse(totalNumberOfRatings)
                        : 0,
                  ),
                  ratingProgressBar(
                    context,
                    '3',
                    totalNumberOfRatings != '0'
                        ? int.parse(totalNumberOfThreeStarRating) /
                            int.parse(totalNumberOfRatings)
                        : 0,
                  ),
                  ratingProgressBar(
                    context,
                    '2',
                    totalNumberOfRatings != '0'
                        ? int.parse(totalNumberOfTwoStarRating) /
                            int.parse(totalNumberOfRatings)
                        : 0,
                  ),
                  ratingProgressBar(
                    context,
                    '1',
                    totalNumberOfRatings != '0'
                        ? int.parse(totalNumberOfOneStarRating) /
                            int.parse(totalNumberOfRatings)
                        : 0,
                  ),
                ],
              ),
            ),
          ],
        ),
      );

  Widget ratingProgressBar(
    final BuildContext context,
    final String ratingName,
    final double rating,
  ) =>
      Row(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          CustomText(
            ratingName,
            fontSize: 12,
            fontWeight: FontWeight.bold,
            color: context.colorScheme.blackColor,
          ),
          const CustomSizedBox(
            width: 10,
          ),
          CustomContainer(
            width: progressReviewWidth,
            clipBehavior: Clip.antiAlias,
            borderRadius: UiUtils.borderRadiusOf5,
            child: CustomTweenAnimation(
              beginValue: 0,
              endValue: rating.isNaN ? 0 : rating,
              curve: Curves.fastLinearToSlowEaseIn,
              durationInSeconds: 1,
              builder: (final BuildContext context, final double value,
                      final Widget? child) =>
                  LinearProgressIndicator(
                color: context.colorScheme.accentColor,
                backgroundColor: context.colorScheme.lightGreyColor,
                minHeight: 6,
                value: rating,
              ),
            ),
          ),
          const CustomSizedBox(
            width: 10,
          ),
          Expanded(
            child: CustomText(
              "${(rating * 100).round()} %",
              fontSize: 12,
              textAlign: TextAlign.end,
              fontWeight: FontWeight.bold,
              color: context.colorScheme.blackColor,
            ),
          ),
        ],
      );
}
