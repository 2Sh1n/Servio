import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class ReviewScreen extends StatefulWidget {
  final Providers providerDetails;

  const ReviewScreen({super.key, required this.providerDetails});

  static Route route(final RouteSettings routeSettings) {
    final Map arguments = routeSettings.arguments as Map;

    return CupertinoPageRoute(
      builder: (final _) => BlocProvider<AddTransactionCubit>(
        create: (final BuildContext context) =>
            AddTransactionCubit(bookingRepository: BookingRepository()),
        child: ReviewScreen(
          providerDetails: arguments["providerDetails"] as Providers,
        ),
      ),
    );
  }

  @override
  State<ReviewScreen> createState() => _ReviewScreenState();
}

class _ReviewScreenState extends State<ReviewScreen> {
  @override
  Widget build(BuildContext context) {
    return AnnotatedSafeArea(
      isAnnotated: true,
      child: Scaffold(
        backgroundColor: context.colorScheme.secondaryColor,
        appBar: UiUtils.getSimpleAppBar(
          context: context,
          title: 'reviewers'.translate(context: context),
        ),
        body: BlocBuilder<ReviewCubit, ReviewState>(
          builder: (context, reviewState) {
            if (reviewState is ReviewFetchFailure) {
              return ErrorContainer(
                errorMessage:
                    reviewState.errorMessage.translate(context: context),
                onTapRetry: () {
                  context.read<ReviewCubit>().fetchReview(
                        providerId: widget.providerDetails.providerId ?? "0",
                      );
                },
              );
            } else if (reviewState is ReviewFetchSuccess) {
              if (reviewState.reviewList.isEmpty) {
                return Center(
                  child: NoDataFoundWidget(
                    titleKey: "noReviewsFound".translate(context: context),
                    subtitleKey:
                        'noReviewsFoundSubTitle'.translate(context: context),
                  ),
                );
              }
              return SingleChildScrollView(
                child: CustomContainer(
                  color: context.colorScheme.secondaryColor,
                  child: ReviewsContainer(
                    listOfReviews: reviewState.reviewList,
                    totalNumberOfRatings:
                        widget.providerDetails.numberOfRatings ?? "0",
                    averageRating: widget.providerDetails.ratings ?? "0",
                    totalNumberOfFiveStarRating:
                        widget.providerDetails.fiveStar ?? "0",
                    totalNumberOfFourStarRating:
                        widget.providerDetails.fourStar ?? "0",
                    totalNumberOfThreeStarRating:
                        widget.providerDetails.threeStar ?? "0",
                    totalNumberOfTwoStarRating:
                        widget.providerDetails.twoStar ?? "0",
                    totalNumberOfOneStarRating:
                        widget.providerDetails.oneStar ?? "0",
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                    progressReviewWidth: context.screenWidth * 0.3,
                  ),
                ),
              );
            }
            return CustomShimmerLoadingContainer(
              width: context.screenWidth * 0.9,
              height: 25,
              borderRadius: UiUtils.borderRadiusOf10,
            );
          },
        ),
      ),
    );
  }
}
