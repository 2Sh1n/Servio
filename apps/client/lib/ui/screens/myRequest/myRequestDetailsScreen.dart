import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class ServiceDetailsScreen extends StatefulWidget {
  const ServiceDetailsScreen({
    final Key? key,
    required this.customJobRequestId,
    required this.status,
    required this.translatedStatus,
  }) : super(key: key);
  final String customJobRequestId;
  final String status;
  final String translatedStatus;

  static Route route(final RouteSettings settings) {
    final arguments = settings.arguments as Map;
    return CupertinoPageRoute(
      builder: (final context) => ServiceDetailsScreen(
        customJobRequestId: arguments['customJobRequestId'],
        status: arguments['status'],
        translatedStatus: arguments['translatedStatus'],
      ),
    );
  }

  @override
  State<ServiceDetailsScreen> createState() => _ServiceDetailsScreenState();
}

class _ServiceDetailsScreenState extends State<ServiceDetailsScreen> {
  ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    fetchServiceDetails();
  }

  @override
  void dispose() {
    super.dispose();
  }

  void fetchServiceDetails() {
    context
        .read<MyRequestDetailsCubit>()
        .fetchRequestDetails(customJobRequestId: widget.customJobRequestId);
  }

  @override
  Widget build(final BuildContext context) =>
      AnnotatedRegion<SystemUiOverlayStyle>(
        value: UiUtils.getSystemUiOverlayStyle(context: context),
        child: Scaffold(
          backgroundColor: context.colorScheme.primaryColor,
          appBar: UiUtils.getSimpleAppBar(
              context: context,
              title: 'serviceDetails'.translate(context: context),
              isLeadingIconEnable: true,
              elevation: 0.5,
              actions: [
                _buildCustomStatusContainer(
                    status: widget.status,
                    context: context,
                    translatedStatus: widget.translatedStatus),
              ]),
          body: BlocBuilder<MyRequestDetailsCubit, MyRequestDetailsState>(
            builder: (context, state) {
              if (state is MyRequestDetailsInProgress) {
                return _buildServiceDetailsShimmer(context: context);
              }
              if (state is MyRequestDetailsFailure) {
                return ErrorContainer(
                  errorMessage: state.errorMessage.translate(context: context),
                  onTapRetry: fetchServiceDetails,
                  showRetryButton: true,
                );
              }
              if (state is MyRequestDetailsSuccess) {
                return Stack(
                  children: [
                    SingleChildScrollView(
                      controller: _scrollController,
                      child: Column(
                        children: [
                          _buildServiceContainer(
                              context: context,
                              customJob: state.requestCustomJob),
                          const CustomSizedBox(
                            height: 12,
                          ),
                          _buildServiceDurationContainer(
                              context: context,
                              customJob: state.requestCustomJob),
                          const CustomSizedBox(
                            height: 18,
                          ),
                          _buildAvailableProviders(
                              context: context, bidders: state.requestBidders),
                          const CustomSizedBox(
                            height: 10,
                          ),
                        ],
                      ),
                    ),
                    if (state.requestCustomJob.status != 'cancelled' &&
                        state.requestCustomJob.status != 'booked')
                      Align(
                        alignment: Alignment.bottomCenter,
                        child: Padding(
                          padding: const EdgeInsetsDirectional.only(bottom: 10),
                          child: CustomRoundedButton(
                            height: kBottomNavigationBarHeight,
                            onTap: () async {
                              UiUtils.showBottomSheet(
                                context: context,
                                child: CancelBookingBottomSheet(
                                  customJobRequestId:
                                      state.requestCustomJob.id!,
                                ),
                              );
                            },
                            backgroundColor: context.colorScheme.accentColor,
                            buttonTitle:
                                'cancelRequest'.translate(context: context),
                            showBorder: false,
                            widthPercentage: 0.9,
                            fontWeight: FontWeight.w500,
                            textSize: 16,
                          ),
                        ),
                      )
                  ],
                );
              }
              return const CustomSizedBox();
            },
          ),
        ),
      );

  Widget _buildServiceContainer(
          {required BuildContext context, required CustomJob customJob}) =>
      CustomContainer(
        padding: const EdgeInsets.all(15),
        color: context.colorScheme.secondaryColor,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CustomText(
              customJob.serviceTitle ?? '',
              color: context.colorScheme.blackColor,
              fontSize: 18,
              fontWeight: FontWeight.w600,
            ),
            CustomReadMoreTextContainer(
                text: customJob.serviceShortDescription ?? '',
                textStyle: TextStyle(
                  color: context.colorScheme.lightGreyColor,
                  fontSize: 16,
                  fontWeight: FontWeight.w400,
                )),
            CustomDivider(
              color: context.colorScheme.lightGreyColor.withValues(alpha: 0.2),
              thickness: 0.5,
              height: 20,
            ),
            Row(
              children: [
                CustomText(
                  'serviceLBL'.translate(context: context),
                  color: context.colorScheme.blackColor,
                  fontSize: 14,
                  fontWeight: FontWeight.w400,
                ),
                const CustomSizedBox(
                  width: 10,
                ),
                Flexible(
                  child: _buildCategoryContainer(
                      context: context,
                      categoryName: customJob.categoryName ?? '',
                      categoryImage: customJob.categoryImage ?? ''),
                ),
              ],
            ),
            CustomDivider(
              color: context.colorScheme.lightGreyColor.withValues(alpha: 0.2),
              thickness: 0.5,
              height: 20,
            ),
            CustomText(
              'budget'.translate(context: context),
              color: Theme.of(context).colorScheme.lightGreyColor,
              fontWeight: FontWeight.w400,
              fontSize: 14,
            ),
            _buildBudgetContainer(
                minBudget: customJob.minPrice ?? '',
                maxBudget: customJob.maxPrice ?? ''),
          ],
        ),
      );

  Widget _buildServiceDurationContainer(
          {required BuildContext context, required CustomJob customJob}) =>
      CustomContainer(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
        color: context.colorScheme.secondaryColor,
        child: Row(
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomText(
                  'postedAt'.translate(context: context),
                  color: context.colorScheme.blackColor,
                  fontSize: 16,
                  fontWeight: FontWeight.w500,
                ),
                CustomText(
                  '${customJob.requestedStartDate?.formatDate() ?? ''} ${customJob.requestedStartTime?.formatTime() ?? ''}',
                  color: context.colorScheme.lightGreyColor,
                  fontSize: 14,
                  fontWeight: FontWeight.w400,
                ),
              ],
            ),
            const CustomSizedBox(width: 5),
            CustomSizedBox(
              height: MediaQuery.of(context).size.height * 0.05,
              child: VerticalDivider(
                color: context.colorScheme.lightGreyColor,
                thickness: 0.7,
              ),
            ),
            const CustomSizedBox(
              width: 5,
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomText(
                  'expiresOn'.translate(context: context),
                  color: context.colorScheme.blackColor,
                  fontSize: 16,
                  fontWeight: FontWeight.w500,
                ),
                CustomText(
                  '${customJob.requestedEndDate?.formatDate() ?? ''} ${customJob.requestedEndTime?.formatTime() ?? ''}',
                  color: context.colorScheme.lightGreyColor,
                  fontSize: 14,
                  fontWeight: FontWeight.w400,
                ),
              ],
            ),
          ],
        ),
      );

  Widget _buildAvailableProviders(
          {required BuildContext context, required List<Bidder> bidders}) =>
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (bidders.isEmpty) ...[
            NoDataFoundWidget(
              titleKey: 'noOneHasBidYet'.translate(context: context),
            ),
          ] else ...[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              child: Row(
                children: [
                  CustomText('availableProviders'.translate(context: context),
                      color: context.colorScheme.blackColor,
                      fontSize: 18,
                      fontWeight: FontWeight.w500),
                  const Spacer(),
                  CustomText(
                      '${bidders.length} ${'bids'.translate(context: context)}'),
                ],
              ),
            ),
            const CustomSizedBox(
              height: 10,
            ),
            ListView.separated(
              separatorBuilder: (context, index) => CustomDivider(
                height: 0,
                color:
                    context.colorScheme.lightGreyColor.withValues(alpha: 0.3),
              ),
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: bidders.length,
              padding: const EdgeInsetsDirectional.only(bottom: 10),
              itemBuilder: (context, index) {
                final bidder = bidders[index];
                return CustomContainer(
                  color: context.colorScheme.secondaryColor,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          CustomImageContainer(
                            borderRadius: UiUtils.borderRadiusOf5,
                            imageURL: bidder.providerImage ?? '',
                            height: 56,
                            width: 56,
                            boxFit: BoxFit.cover,
                          ),
                          const CustomSizedBox(
                            width: 10,
                          ),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.start,
                              mainAxisSize: MainAxisSize.max,
                              children: [
                                CustomText(
                                  bidder.companyName ?? '',
                                  color: context.colorScheme.blackColor,
                                  maxLines: 2,
                                  fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                ),
                                CustomReadMoreTextContainer(
                                  text: bidder.note ?? '',
                                  textStyle: TextStyle(
                                    color: context.colorScheme.lightGreyColor,
                                    fontSize: 14,
                                    fontWeight: FontWeight.w400,
                                  ),
                                ),
                              ],
                            ),
                          )
                        ],
                      ),
                      CustomDivider(
                        height: 20,
                        color: context.colorScheme.lightGreyColor
                            .withValues(alpha: 0.2),
                        thickness: 0.5,
                      ),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CustomText(
                            '${bidder.finalTotal?.priceFormat(context)}',
                            fontWeight: FontWeight.w700,
                            fontSize: 16,
                          ),
                          const CustomSizedBox(
                            width: 10,
                          ),
                          CustomSizedBox(
                            height: 25,
                            child: VerticalDivider(
                              color: context.colorScheme.lightGreyColor,
                            ),
                          ),
                          const CustomSizedBox(
                            width: 10,
                          ),
                          const CustomSvgPicture(
                            avoideResponsive: true,
                            svgImage: AppAssets.icClock,
                            height: 20,
                            width: 20,
                          ),
                          const CustomSizedBox(
                            width: 5,
                          ),
                          CustomText(
                            '${bidder.duration ?? ''} ${'min'.translate(context: context)}',
                            fontWeight: FontWeight.w400,
                            fontSize: 14,
                          ),
                          const Spacer(),
                          if (widget.status != 'cancelled' &&
                              widget.status != 'booked')
                            CustomContainer(
                              padding: const EdgeInsets.symmetric(
                                  vertical: 3, horizontal: 12),
                              color: context.colorScheme.accentColor
                                  .withValues(alpha: 0.1),
                              borderRadius: 4,
                              child: CustomFlatButton(
                                text: 'bookNow'.translate(context: context),
                                fontColor: context.colorScheme.accentColor,
                                onPressed: () {
                                  Navigator.pushNamed(
                                    context,
                                    scheduleScreenRoute,
                                    arguments: {
                                      "isFrom": "customJobRequest",
                                      "providerName":
                                          bidder.translatedProviderName,
                                      "providerId": bidder.partnerId,
                                      "companyName": bidder.companyName,
                                      "providerAdvanceBookingDays":
                                          bidder.advanceBookingDays,
                                      "customJobRequestId":
                                          bidder.customJobRequestId,
                                      "bidder": bidder
                                    },
                                  );
                                },
                                backgroundColor: Colors.transparent,
                                showBorder: false,
                                innerPadding: 0,
                                radius: 0,
                                fontSize: 14,
                              ),
                            ),
                        ],
                      ),
                      if (widget.status == 'cancelled' ||
                          widget.status == 'booked')
                        const CustomSizedBox(
                          height: 10,
                        ),
                    ],
                  ),
                );
              },
            ),
          ],
          //TODO: check this for responsive
          const CustomSizedBox(height: kBottomNavigationBarHeight),
        ],
      );

  Widget _buildCategoryContainer(
      {required BuildContext context,
      required String categoryName,
      required String categoryImage}) {
    return CustomContainer(
      color: Theme.of(context).colorScheme.accentColor.withValues(alpha: 0.15),
      borderRadius: UiUtils.borderRadiusOf5,
      padding: const EdgeInsetsDirectional.all(4),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Expanded(
            flex: ResponsiveHelper.isTabletDevice(context) ? 0 : 1,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf5),
              child: CustomCachedNetworkImage(
                height: 18,
                width: 18,
                networkImageUrl: categoryImage,
                fit: BoxFit.fill,
              ),
            ),
          ),
          const CustomSizedBox(
            width: 4,
          ),
          Flexible(
            flex: 12,
            child: CustomText(
              categoryName,
              color: context.colorScheme.accentColor,
              fontSize: 14,
              fontWeight: FontWeight.w400,
              maxLines: 1,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildServiceDetailsShimmer({required BuildContext context}) =>
      SingleChildScrollView(
        child: Column(
          children: [
            CustomShimmerLoadingContainer(
              borderRadius: 0,
              height: context.screenHeight * 0.25,
            ),
            CustomShimmerLoadingContainer(
              borderRadius: 0,
              height: context.screenHeight * 0.15,
              margin: const EdgeInsets.symmetric(vertical: 15),
            ),
            const CustomSizedBox(
              height: 30,
            ),
            CustomContainer(
              color: Colors.transparent,
              child: ListView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: 10,
                itemBuilder: (context, index) => CustomContainer(
                  color: Colors.transparent,
                  child: CustomShimmerLoadingContainer(
                    borderRadius: 0,
                    height: context.screenHeight * 0.1,
                    margin: const EdgeInsets.symmetric(vertical: 0.5),
                  ),
                ),
              ),
            ),
          ],
        ),
      );

  String dateFormat(final DateTime date) {
    final DateFormat formatter = DateFormat('dd-MM-yyyy - HH:MM');
    return formatter.format(date);
  }

  Widget _buildCustomStatusContainer({
    required final BuildContext context,
    required final String status,
    required final String translatedStatus,
  }) =>
      CustomContainer(
        margin: const EdgeInsetsDirectional.only(end: 15),
        padding: const EdgeInsets.symmetric(vertical: 3, horizontal: 12),
        borderRadius: 5,
        color:
            UiUtils.getBookingStatusColor(context: context, statusVal: status)
                .withValues(alpha: 0.1),
        child: CustomText(
          status == 'pending'
              ? 'requested'.translate(context: context)
              : translatedStatus,
          fontSize: 14,
          color: UiUtils.getBookingStatusColor(
              context: context, statusVal: status),
          maxLines: 1,
          fontWeight: FontWeight.w500,
        ),
      );

  Widget _buildBudgetContainer(
          {required String minBudget, required String maxBudget}) =>
      Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          CustomText(
            minBudget.priceFormat(context),
            color: context.colorScheme.blackColor,
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
          CustomText(
            "  ${'to'.translate(context: context)}  ",
            color: context.colorScheme.lightGreyColor,
            fontWeight: FontWeight.normal,
            fontSize: 14,
          ),
          CustomText(
            maxBudget.priceFormat(context),
            color: context.colorScheme.blackColor,
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
        ],
      );
}
