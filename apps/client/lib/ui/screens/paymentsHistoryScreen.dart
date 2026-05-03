import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({super.key});

  static Route route(final RouteSettings settings) => CupertinoPageRoute(
        builder: (final BuildContext context) => BlocProvider(
          create: (final context) =>
              FetchUserPaymentDetailsCubit(SystemRepository()),
          child: Builder(
            builder: (final context) => const PaymentsScreen(),
          ),
        ),
      );

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

class _PaymentsScreenState extends State<PaymentsScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final ScrollController _scrollController = ScrollController();
  final List<String> statuses = ['all', 'pending', 'success', 'failed'];
  String? selectedStatus = "all";
  Color statusColour = AppColors.greenColor;
  String statusIcon = AppAssets.icCheck;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(
      length: statuses.length,
      vsync: this,
    );
    fetchPaymentDetails();

    _scrollController.addListener(fetchMorePaymentDetails);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _tabController.dispose();
    super.dispose();
  }

  void fetchMorePaymentDetails() {
    if (!context.read<FetchUserPaymentDetailsCubit>().hasMoreTransactions()) {
      return;
    }

    final nextPageTrigger = 0.7 * _scrollController.position.maxScrollExtent;

    if (_scrollController.position.pixels > nextPageTrigger) {
      if (mounted) {
        context
            .read<FetchUserPaymentDetailsCubit>()
            .fetchUsersMorePaymentDetails(status: selectedStatus);
      }
    }
  }

  void fetchPaymentDetails() {
    context
        .read<FetchUserPaymentDetailsCubit>()
        .fetchUserPaymentDetails(status: selectedStatus);
  }

  SingleChildScrollView _buildPaymentDetailsShimmerLoading() =>
      SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
        child: Column(
          children: List.generate(
            UiUtils.numberOfShimmerContainer,
            (final int index) => CustomShimmerLoadingContainer(
              height: 100,
              width: context.screenWidth,
              borderRadius: UiUtils.borderRadiusOf10,
              margin: const EdgeInsets.symmetric(vertical: 10),
            ),
          ),
        ),
      );

  @override
  Widget build(final BuildContext context) => AnnotatedSafeArea(
        isAnnotated: true,
        child: Scaffold(
          backgroundColor: context.colorScheme.primaryColor,
          appBar: AppBar(
            centerTitle: false,
            title: CustomText('payment'.translate(context: context)),
            elevation: 1,
            // shadowColor: context.colorScheme.lightGreyColor,
            surfaceTintColor: context.colorScheme.secondaryColor,
            leading: GestureDetector(
              onTap: () => Navigator.pop(context),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 15),
                child: Center(
                  child: CustomSvgPicture(
                    width: 56,
                    svgImage: Directionality.of(context)
                            .toString()
                            .contains(TextDirection.RTL.value.toLowerCase())
                        ? AppAssets.backArrowLtr
                        : AppAssets.backArrow,
                  ),
                ),
              ),
            ),
            backgroundColor: context.colorScheme.secondaryColor,
            bottom: TabBar(
              physics: const NeverScrollableScrollPhysics(),
              indicatorColor: Colors.transparent,
              labelPadding:
                  const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              indicatorPadding: const EdgeInsets.all(10),
              indicatorSize: TabBarIndicatorSize.tab,
              overlayColor: const WidgetStatePropertyAll(Colors.transparent),
              indicator: BoxDecoration(
                  color: context.colorScheme.accentColor,
                  borderRadius: const BorderRadius.all(
                      Radius.circular(UiUtils.borderRadiusOf6))),
              // indicatorPadding: EdgeInsets.zero,
              controller: _tabController,
              labelColor: context.colorScheme.accentColor,
              unselectedLabelColor: context.colorScheme.blackColor,

              // labelStyle: TextStyle(color: context.colorScheme.blackColor),
              tabs: statuses.map((status) {
                final bool isSelected = status == selectedStatus;
                return Tab(
                  child: CustomText(
                    status.translate(context: context),
                    textAlign: TextAlign.center,
                    color: isSelected
                        ? context.colorScheme.secondaryColor
                        : context.colorScheme.blackColor,
                    // fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                  ),
                );
              }).toList(),
              onTap: (index) {
                setState(() {
                  selectedStatus = statuses[index];
                });
                fetchPaymentDetails();
              },
              isScrollable: false,
            ),
          ),
          body: TabBarView(
            physics: const NeverScrollableScrollPhysics(),
            controller: _tabController,
            children: statuses.map((status) {
              return BlocBuilder<FetchUserPaymentDetailsCubit,
                  FetchUserPaymentDetailsState>(
                builder: (final BuildContext context,
                    final FetchUserPaymentDetailsState state) {
                  if (state is FetchUserPaymentDetailsFailure) {
                    return ErrorContainer(
                      errorMessage: state.errorMessage,
                      onTapRetry: () {
                        fetchPaymentDetails();
                      },
                    );
                  }
                  if (state is FetchUserPaymentDetailsSuccess) {
                    if (state.paymentDetails.isEmpty) {
                      return NoDataFoundWidget(
                        titleKey:
                            'noPaymentDetailsFound'.translate(context: context),
                        subtitleKey: 'noPaymentDetailsFoundSubTitle'
                            .translate(context: context),
                      );
                    }
                    return SingleChildScrollView(
                      padding: const EdgeInsets.symmetric(
                          vertical: 10, horizontal: 10),
                      controller: _scrollController,
                      child: _buildPaymentHistoryCard(
                        isLoadingMoreError: state.isLoadingMoreError,
                        isLoadingMoreData: state.isLoadingMorePayments,
                        paymentDetails: state.paymentDetails,
                      ),
                    );
                  }
                  return _buildPaymentDetailsShimmerLoading();
                },
              );
            }).toList(),
          ),
        ),
      );

  Map<String, dynamic> getStatusColourAndIcons(Payment paymentDetails) {
    switch (paymentDetails.status) {
      case "pending":
        return {
          'color': AppColors.pendingPaymentStatusColor,
          'icon': AppAssets.icClock,
        };

      case "failed":
        return {
          'color': AppColors.failedPaymentStatusColor,
          'icon': AppAssets.icUncheck,
        };
      case "success":
        return {
          'color': AppColors.successPaymentStatusColor,
          'icon': AppAssets.icCheck,
        };
      default:
        return {
          'color': AppColors.successPaymentStatusColor,
          'icon': AppAssets.icCheck,
        };
    }
  }

  Widget _buildPaymentHistoryCard({
    required final bool isLoadingMoreData,
    required final bool isLoadingMoreError,
    required final List<Payment> paymentDetails,
  }) =>
      Column(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: List.generate(
          paymentDetails.length,
          (final int index) {
            statusColour =
                getStatusColourAndIcons(paymentDetails[index])['color'];
            statusIcon = getStatusColourAndIcons(paymentDetails[index])['icon'];

            if (index >=
                paymentDetails.length +
                    (isLoadingMoreData || isLoadingMoreError ? 1 : 0)) {
              return CustomLoadingMoreContainer(
                isError: isLoadingMoreError,
                onErrorButtonPressed: () {
                  fetchMorePaymentDetails();
                },
              );
            }

            return CustomContainer(
              color: context.colorScheme.secondaryColor,
              borderRadius: UiUtils.borderRadiusOf10,
              width: context.screenWidth,
              margin: const EdgeInsets.symmetric(vertical: 5),
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CustomText(
                    paymentDetails[index].translatedMessage!,
                    maxLines: 2,
                    fontSize: 14,
                    fontWeight: FontWeight.w400,
                    fontStyle: FontStyle.normal,
                  ),
                  const CustomSizedBox(
                    height: 10,
                  ),
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CustomText(
                              'bookingId'.translate(context: context),
                              maxLines: 1,
                              fontSize: 14,
                              fontWeight: FontWeight.w400,
                              fontStyle: FontStyle.normal,
                              color: context.colorScheme.lightGreyColor,
                            ),
                            CustomText(
                              paymentDetails[index].orderId!,
                              maxLines: 1,
                              fontSize: 14,
                              fontWeight: FontWeight.w400,
                              fontStyle: FontStyle.normal,
                            ),
                          ],
                        ),
                      ),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CustomText(
                              'tax'.translate(context: context),
                              maxLines: 1,
                              fontSize: 14,
                              fontWeight: FontWeight.w400,
                              fontStyle: FontStyle.normal,
                              color: context.colorScheme.lightGreyColor,
                            ),
                            CustomText(
                              paymentDetails[index].txnId!,
                              maxLines: 1,
                              fontSize: 14,
                              fontWeight: FontWeight.w400,
                              fontStyle: FontStyle.normal,
                            ),
                          ],
                        ),
                      )
                    ],
                  ),
                  const CustomSizedBox(
                    height: 10,
                  ),
                  CustomContainer(
                    color: statusColour.withAlpha(20),
                    borderRadius: UiUtils.borderRadiusOf10,
                    padding: const EdgeInsetsDirectional.symmetric(
                        vertical: 10, horizontal: 10),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        CustomText(
                          (paymentDetails[index].amount!).priceFormat(context),
                          color: statusColour,
                          fontSize: 18,
                          fontWeight: FontWeight.w600,
                          fontStyle: FontStyle.normal,
                        ),
                        Row(
                          children: [
                            CustomText(
                              paymentDetails[index]
                                  .translatedStatus
                                  .toString()
                                  .capitalize(),
                              color: statusColour,
                              fontSize: 14,
                              fontWeight: FontWeight.w400,
                              fontStyle: FontStyle.normal,
                            ),
                            const CustomSizedBox(
                              width: 10,
                            ),
                            CustomSvgPicture(
                              svgImage: statusIcon,
                              color: statusColour,
                              height: 18,
                              width: 18,
                            )
                          ],
                        )
                      ],
                    ),
                  )
                ],
              ),
            );
          },
        ),
      );
}
