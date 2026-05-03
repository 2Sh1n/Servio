import 'package:flutter/material.dart';

import '../../app/generalImports.dart';

class CashCollectionScreen extends StatefulWidget {
  const CashCollectionScreen({super.key});

  @override
  CashCollectionScreenState createState() => CashCollectionScreenState();

  static Route<CashCollectionScreen> route(RouteSettings routeSettings) {
    return CupertinoPageRoute(
      builder: (_) => MultiBlocProvider(
        providers: [
          BlocProvider<CashCollectionCubit>(
            create: (BuildContext context) => CashCollectionCubit(),
          ),
          BlocProvider<AdminCollectCashCollectionHistoryCubit>(
            create: (BuildContext context) =>
                AdminCollectCashCollectionHistoryCubit(),
          ),
        ],
        child: const CashCollectionScreen(),
      ),
    );
  }
}

class CashCollectionScreenState extends State<CashCollectionScreen>
    with SingleTickerProviderStateMixin {
  late final ScrollController _pageScrollController = ScrollController()
    ..addListener(_pageScrollListen);

  late TabController _tabController;
  String selectedFilter = 'paid';
  final List<String> filterOptions = ["paid", "collected"];
  ValueNotifier<bool> isScrolling = ValueNotifier(false);
  OverlayEntry? overlayEntry;

  @override
  void initState() {
    _tabController = TabController(length: filterOptions.length, vsync: this);
    loadData();
    super.initState();
  }

  @override
  void dispose() {
    isScrolling.dispose();
    overlayEntry?.remove();
    overlayEntry?.dispose();
    _tabController.dispose();

    super.dispose();
  }

  void loadData() {
    if (selectedFilter == 'paid') {
      context
          .read<AdminCollectCashCollectionHistoryCubit>()
          .fetchAdminCollectedCashCollection();
    } else if (selectedFilter == 'collected') {
      context.read<CashCollectionCubit>().fetchCashCollection();
    }
  }

  void _pageScrollListen() {
    if (_pageScrollController.position.pixels > 7 && !isScrolling.value) {
      isScrolling.value = true;
    } else if (_pageScrollController.position.pixels < 7 && isScrolling.value) {
      isScrolling.value = false;
    }
    if (_pageScrollController.isEndReached()) {
      if (selectedFilter == 'paid' &&
          context
              .read<AdminCollectCashCollectionHistoryCubit>()
              .hasMoreData()) {
        context
            .read<AdminCollectCashCollectionHistoryCubit>()
            .fetchAdminCollectedMoreCashCollection();
      } else if (selectedFilter == 'collected' &&
          context.read<CashCollectionCubit>().hasMoreData()) {
        context.read<CashCollectionCubit>().fetchMoreCashCollection();
      }
    }
  }

  Widget setTitleAndSubDetails({
    required String title,
    required String subTitle,
  }) {
    return Row(
      children: [
        Expanded(
          flex: 3,
          child: CustomText(
            title.translate(context: context),
            fontSize: 14,
            maxLines: 1,
            fontWeight: FontWeight.w400,
            color: Theme.of(context).colorScheme.blackColor,
          ),
        ),
        const Expanded(child: SizedBox(width: 5)),
        Expanded(
          flex: 6,
          child: CustomText(
            subTitle,
            fontSize: 11,
            maxLines: 3,
            fontWeight: FontWeight.w400,
            color: Theme.of(context).colorScheme.blackColor,
          ),
        ),
      ],
    );
  }

  Widget showCashCollectionList({
    required List<CashCollectionModel> cashCollectionData,
    required String payableCommissionAmount,
    required bool isLoadingMore,
    // required bool colle
  }) {
    return cashCollectionData.isEmpty
        ? Center(
            child: NoDataContainer(
              titleKey: 'noDataFound'.translate(context: context),
              subTitleKey: 'noDataFoundSubTitle'.translate(context: context),
            ),
          )
        : SingleChildScrollView(
            controller: _pageScrollController,
            clipBehavior: Clip.none,
            child: Column(
              children: [
                const SizedBox(height: 130),
                CustomDivider(
                  indent: 10,
                  endIndent: 10,
                  color: context.colorScheme.lightGreyColor.withAlpha(50),
                  thickness: 0.5,
                ),
                ListView.separated(
                  shrinkWrap: true,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 15,
                    vertical: 10,
                  ),
                  itemCount: cashCollectionData.length,
                  physics: const NeverScrollableScrollPhysics(),
                  itemBuilder: (BuildContext context, int index) {
                    return CustomContainer(
                      width: MediaQuery.sizeOf(context).width,
                      padding: const EdgeInsetsDirectional.all(10),
                      color: Theme.of(context).colorScheme.secondaryColor,
                      borderRadius: UiUtils.borderRadiusOf10,
                      boxShadow: [
                        BoxShadow(
                          color: context.colorScheme.lightGreyColor.withAlpha(
                            20,
                          ),
                          offset: const Offset(0, 1),
                          blurRadius: 2,
                        ),
                      ],
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              CustomText(
                                (cashCollectionData[index].commissionAmount ??
                                        "0")
                                    .replaceAll(',', '')
                                    .toString()
                                    .priceFormat(context),
                                color: context.colorScheme.blackColor,
                                fontSize: 16,
                                fontWeight: FontWeight.w500,
                              ),
                              CustomText(
                                cashCollectionData[index].date!
                                    .formatDateAndTimeOnlyDate(),
                                color: context.colorScheme.lightGreyColor,
                                fontSize: 12,
                                fontWeight: FontWeight.w400,
                              ),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: CustomText(
                                  cashCollectionData[index].message!,
                                  color: context.colorScheme.lightGreyColor,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w400,
                                ),
                              ),
                              cashCollectionData[index].orderID == ''
                                  ? CustomText(
                                      cashCollectionData[index].translatedStatus!
                                          .trim(),
                                      color:
                                          cashCollectionData[index].status!
                                                  .trim()
                                                  .capitalize() ==
                                              'Paid'
                                          ? AppColors.greenColor
                                          : AppColors.starRatingColor,
                                      fontSize: 14,
                                      fontWeight: FontWeight.w400,
                                    )
                                  : Row(
                                      mainAxisAlignment: MainAxisAlignment.end,
                                      children: [
                                        CustomText(
                                          'booking'.translate(context: context),
                                          color: context.colorScheme.blackColor,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w400,
                                        ),
                                        CustomText(
                                          " : ",
                                          color: context.colorScheme.blackColor,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w400,
                                        ),
                                        CustomText(
                                          '#${cashCollectionData[index].orderID!}',
                                          color:
                                              context.colorScheme.accentColor,
                                          fontSize: 14,
                                          fontWeight: FontWeight.w400,
                                        ),
                                      ],
                                    ),
                            ],
                          ),
                        ],
                      ),
                    );
                  },
                  separatorBuilder: (BuildContext context, int index) =>
                      const SizedBox(height: 10),
                ),
                if (isLoadingMore) ...[
                  CustomCircularProgressIndicator(
                    color: Theme.of(context).colorScheme.accentColor,
                  ),
                ],
              ],
            ),
          );
  }

  Widget showLoadingShimmerEffect() {
    return ListView.builder(
      shrinkWrap: true,
      padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
      itemCount: 8,
      physics: const AlwaysScrollableScrollPhysics(),
      clipBehavior: Clip.none,
      itemBuilder: (BuildContext context, int index) {
        return Padding(
          padding: const EdgeInsets.all(8.0),
          child: ShimmerLoadingContainer(
            child: CustomShimmerContainer(height: context.screenHeight * 0.18),
          ),
        );
      },
    );
  }

  Widget mainWidget() {
    return Stack(
      children: [
        CustomRefreshIndicator(
          onRefresh: () async {
            loadData();
          },
          child: (selectedFilter == 'paid')
              ? BlocConsumer<
                  AdminCollectCashCollectionHistoryCubit,
                  AdminCollectCashCollectionHistoryState
                >(
                  listener:
                      (
                        BuildContext context,
                        AdminCollectCashCollectionHistoryState state,
                      ) {
                        if (state
                            is AdminCollectCashCollectionHistoryFetchSuccess) {
                          context
                              .read<FetchSystemSettingsCubit>()
                              .updatePayebleCommision(
                                state.totalPayableCommission,
                              );
                        }
                      },
                  builder:
                      (
                        BuildContext context,
                        AdminCollectCashCollectionHistoryState state,
                      ) {
                        if (state
                            is AdminCollectCashCollectionHistoryStateFailure) {
                          return Center(
                            child: ErrorContainer(
                              onTapRetry: () {
                                context
                                    .read<
                                      AdminCollectCashCollectionHistoryCubit
                                    >()
                                    .fetchAdminCollectedCashCollection();
                              },
                              errorMessage: state.errorMessage,
                            ),
                          );
                        }
                        if (state
                            is AdminCollectCashCollectionHistoryFetchSuccess) {
                          return showCashCollectionList(
                            cashCollectionData: state.cashCollectionData,
                            payableCommissionAmount:
                                state.totalPayableCommission,
                            isLoadingMore: state.isLoadingMore,
                          );
                        }
                        return showLoadingShimmerEffect();
                      },
                )
              : BlocConsumer<CashCollectionCubit, CashCollectionState>(
                  listener: (BuildContext context, CashCollectionState state) {
                    if (state is CashCollectionFetchSuccess) {
                      context
                          .read<FetchSystemSettingsCubit>()
                          .updatePayebleCommision(state.totalPayableCommission);
                    }
                  },
                  builder: (BuildContext context, CashCollectionState state) {
                    if (state is CashCollectionFetchFailure) {
                      return Center(
                        child: ErrorContainer(
                          onTapRetry: () {
                            context
                                .read<CashCollectionCubit>()
                                .fetchCashCollection();
                          },
                          errorMessage: state.errorMessage,
                        ),
                      );
                    }
                    if (state is CashCollectionFetchSuccess) {
                      return showCashCollectionList(
                        payableCommissionAmount: state.totalPayableCommission,
                        cashCollectionData: state.cashCollectionData,
                        isLoadingMore: state.isLoadingMore,
                      );
                    }
                    return showLoadingShimmerEffect();
                  },
                ),
        ),
        Align(
          alignment: Alignment.topCenter,
          child: ValueListenableBuilder(
            valueListenable: isScrolling,
            builder: (BuildContext context, Object? value, Widget? child) =>
                CustomContainer(
                  color: context.colorScheme.primaryColor,
                  boxShadow: isScrolling.value
                      ? [
                          BoxShadow(
                            offset: const Offset(0, 0.75),
                            spreadRadius: 1,
                            blurRadius: 5,
                            color: Theme.of(
                              context,
                            ).colorScheme.blackColor.withValues(alpha: 0.2),
                          ),
                        ]
                      : [],
                  child: CustomContainer(
                    margin: const EdgeInsets.symmetric(
                      vertical: 5,
                      horizontal: 5,
                    ),
                    height: 125,
                    padding: const EdgeInsets.symmetric(
                      vertical: 5,
                      horizontal: 5,
                    ),
                    width: MediaQuery.sizeOf(context).width,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Text(
                          'amountPayable'.translate(context: context),
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w400,
                            color: context.colorScheme.blackColor,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          (context.watch<FetchSystemSettingsCubit>().state
                                  as FetchSystemSettingsSuccess)
                              .payableCommission
                              .toString()
                              .priceFormat(context),
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w600,
                            color: context.colorScheme.blackColor,
                          ),
                        ),
                        const SizedBox(height: 15),
                        TabBar(
                          indicatorWeight: 0.1,
                          dividerHeight: 0,
                          indicatorSize: TabBarIndicatorSize.tab,
                          indicatorColor: context.colorScheme.accentColor,
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          overlayColor: const WidgetStatePropertyAll(
                            Colors.transparent,
                          ),
                          controller: _tabController,
                          tabs: filterOptions.map((status) {
                            final bool isSelected = status == selectedFilter;
                            return Tab(
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 18.0,
                                  vertical: 0,
                                ),
                                child: CustomText(
                                  status.translate(context: context),
                                  textAlign: TextAlign.center,
                                  color: isSelected
                                      ? context.colorScheme.accentColor
                                      : context.colorScheme.blackColor,
                                  fontWeight: isSelected
                                      ? FontWeight.w500
                                      : FontWeight.w400,
                                ),
                              ),
                            );
                          }).toList(),
                          onTap: (index) {
                            setState(() {
                              selectedFilter = filterOptions[index];
                            });
                            loadData();
                          },
                          isScrollable: false,
                        ),
                      ],
                    ),
                  ),
                ),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return InterstitialAdWidget(
      child: Scaffold(
        backgroundColor: Theme.of(context).colorScheme.primaryColor,
        appBar: UiUtils.getSimpleAppBar(
          statusBarColor: context.colorScheme.secondaryColor,
          context: context,
          elevation: 1,
          title: 'cashCollection'.translate(context: context),
          actions: [
            Padding(
              padding: const EdgeInsetsDirectional.only(end: 10),
              child: IconButton(
                onPressed: () {
                  if (overlayEntry?.mounted ?? false) {
                    return;
                  }
                  overlayEntry = OverlayEntry(
                    builder: (BuildContext context) => Positioned.directional(
                      textDirection: Directionality.of(context),
                      end: 10,
                      top: context.screenHeight * .10,
                      child: CustomContainer(
                        width: MediaQuery.sizeOf(context).width * .9,
                        color: Theme.of(context).colorScheme.secondaryColor,
                        borderRadius: UiUtils.borderRadiusOf5,
                        boxShadow: [
                          BoxShadow(
                            blurRadius: 0.5,
                            spreadRadius: 0.5,
                            color: context.colorScheme.lightGreyColor,
                          ),
                        ],
                        child: Padding(
                          padding: const EdgeInsets.all(5.0),
                          child: Text(
                            'cashCollectionDescription'.translate(
                              context: context,
                            ),
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.blackColor,
                              fontSize: 12,
                              fontWeight: FontWeight.normal,
                              fontStyle: FontStyle.normal,
                              decoration: TextDecoration.none,
                            ),
                          ),
                        ),
                      ),
                    ),
                  );

                  Overlay.of(context).insert(overlayEntry!);
                  Timer(
                    const Duration(seconds: 5),
                    () => overlayEntry!.remove(),
                  );
                },
                icon: Icon(
                  Icons.help_outline_outlined,
                  color: Theme.of(context).colorScheme.blackColor,
                ),
              ),
            ),
          ],
        ),
        body: mainWidget(),
        floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
      ),
    );
  }
}
