import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/review/reviewComment.dart';
import 'package:flutter/material.dart';

class ReviewsContainer extends StatelessWidget {
  final EdgeInsets padding;
  final String averageRating;
  final String totalNumberOfRatings;
  final String totalNumberOfFiveStarRating;
  final String totalNumberOfFourStarRating;
  final String totalNumberOfThreeStarRating;
  final String totalNumberOfTwoStarRating;
  final String totalNumberOfOneStarRating;
  final double progressReviewWidth;

  final List<Reviews> listOfReviews;

  const ReviewsContainer({
    super.key,
    required this.padding,
    required this.averageRating,
    required this.totalNumberOfRatings,
    required this.totalNumberOfFiveStarRating,
    required this.totalNumberOfFourStarRating,
    required this.totalNumberOfThreeStarRating,
    required this.totalNumberOfTwoStarRating,
    required this.totalNumberOfOneStarRating,
    required this.listOfReviews,
    required this.progressReviewWidth,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: padding,
          child: RatingBox(
            averageRating: averageRating,
            totalNumberOfRatings: totalNumberOfRatings,
            totalNumberOfOneStarRating: totalNumberOfOneStarRating,
            totalNumberOfTwoStarRating: totalNumberOfTwoStarRating,
            totalNumberOfThreeStarRating: totalNumberOfThreeStarRating,
            totalNumberOfFourStarRating: totalNumberOfFourStarRating,
            totalNumberOfFiveStarRating: totalNumberOfFiveStarRating,
            progressReviewWidth: progressReviewWidth,
          ),
        ),
        const CustomDivider(
          thickness: 0.5,
        ),
        ListView.builder(
          padding: EdgeInsets.zero,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: listOfReviews.length,
          itemBuilder: (final BuildContext context, final int index) {
            return Column(
              children: [
                ReviewDetails(reviews: listOfReviews[index]),
                if (index != listOfReviews.length - 1)
                  const CustomDivider(
                    thickness: 0.5,
                  ),
              ],
            );
          },
        ),
      ],
    );
  }
}
