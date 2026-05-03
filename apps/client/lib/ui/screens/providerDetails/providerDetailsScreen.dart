import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/SliderPromoCode.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

enum ProviderDetailsParamType {
  slug,
  id;
}

class ProviderDetailsScreen extends StatefulWidget {
  const ProviderDetailsScreen(
      {required this.providerIDOrSlug, final Key? key, required this.type})
      : super(key: key);
  final String providerIDOrSlug;
  final ProviderDetailsParamType type;

  @override
  State<ProviderDetailsScreen> createState() => _ProviderDetailsScreenState();

  static Route route(final RouteSettings routeSettings) {
    final Map arguments = routeSettings.arguments as Map;
    return CupertinoPageRoute(
      builder: (final context) => BlocProvider(
        create: (context) => ProviderDetailsAndServiceCubit(
            ServiceRepository(), ProviderRepository()),
        child: ProviderDetailsScreen(
          providerIDOrSlug: arguments["providerId"],
          type: arguments['type'] ?? ProviderDetailsParamType.id,
        ),
      ),
    );
  }
}

class _ProviderDetailsScreenState extends State<ProviderDetailsScreen>
    with SingleTickerProviderStateMixin, AutomaticKeepAliveClientMixin {
  late TabController _tabController = TabController(length: 2, vsync: this);

  List<String> tabLabels = ['services', 'about'];

  int selectedIndex = 0;

  ScrollController _serviceListScrollController = ScrollController();

  double? calculatedWidth;

  late final bool isTablet = ResponsiveHelper.isTablet(context);

  @override
  void initState() {
    super.initState();

    fetchProviderDetailsAndServices();

    _tabController.addListener(() {
      if (_tabController.indexIsChanging ||
          _tabController.index != selectedIndex) {
        setState(() {
          selectedIndex = _tabController.index;
        });
      }
    });

    //_serviceListScrollController.addListener(serviceListScrollController);
  }

  void serviceListScrollController() {
    if (mounted &&
        !context.read<ProviderDetailsAndServiceCubit>().hasMoreServices()) {
      return;
    }
// nextPageTrigger will have a value equivalent to 70% of the list size.
    final nextPageTrigger =
        0.7 * _serviceListScrollController.position.maxScrollExtent;

// _scrollController fetches the next paginated data when the current position of the user on the screen has surpassed
    if (_serviceListScrollController.position.pixels > nextPageTrigger) {
      if (mounted) {
        context.read<ProviderDetailsAndServiceCubit>().fetchMoreServices(
            providerId: widget.providerIDOrSlug, type: widget.type);
      }
    }
  }

  void fetchProviderDetailsAndServices() {
    context
        .read<ProviderDetailsAndServiceCubit>()
        .fetchProviderDetailsAndServices(
            providerId: widget.providerIDOrSlug,
            promocode: "1",
            type: widget.type);
    context
        .read<ReviewCubit>()
        .fetchReview(providerId: widget.providerIDOrSlug, type: widget.type);
  }

  Widget providerDetailsScreenShimmerLoading() => SingleChildScrollView(
        child: Column(
          children: [
            CustomShimmerLoadingContainer(
              height: context.screenHeight * 0.4,
              width: context.screenWidth,
            ),
            const CustomShimmerLoadingContainer(
              borderRadius: 0,
              margin: EdgeInsets.symmetric(vertical: 5, horizontal: 5),
              height: 50,
            ),
            Column(
              children: List.generate(
                UiUtils.numberOfShimmerContainer,
                (final int index) => const CustomShimmerLoadingContainer(
                  height: 150,
                  margin: EdgeInsets.symmetric(vertical: 5, horizontal: 5),
                ),
              ),
            )
          ],
        ),
      );

  Widget profileView(ProviderDetailsAndServiceFetchSuccess state,
      double providerContainerHight) {
    //TODO: check this for responsive
    return CustomSizedBox(
      width: calculatedWidth,
      height: providerContainerHight,
      child: CustomContainer(
        padding: const EdgeInsetsDirectional.all(8),
        color: context.colorScheme.secondaryColor,
        borderRadius: UiUtils.borderRadiusOf8,
        clipBehavior: Clip.antiAlias,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.start,
          children: [
            Expanded(
                flex: 1,
                child: CustomImageContainer(
                  borderRadius: UiUtils.borderRadiusOf8,
                  imageURL: state.providerDetails.image!,
                  height: 80.rh(context),
                  width: 80.rh(context),
                  boxFit: BoxFit.fill,
                  boxShadow: [],
                )),
            const CustomSizedBox(
              width: 10,
            ),
            Expanded(
              flex: 3,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.max,
                    children: [
                      Expanded(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.start,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CustomText(
                              state.providerDetails.translatedCompanyName!,
                              fontSize: 16,
                              maxLines: 1,
                              color: context.colorScheme.blackColor,
                              fontWeight: FontWeight.w600,
                            ),
                            CustomText(
                              '${state.providerDetails.totalServices!} ${state.providerDetails.totalServices!.convertToInt() < 1 ? 'service'.translate(context: context) : 'services'.translate(context: context)}',
                              fontSize: 14,
                              maxLines: 1,
                              color: context.colorScheme.blackColor,
                              fontWeight: FontWeight.w200,
                            ),
                          ],
                        ),
                      ),
                      BookMarkIcon(
                        providerData: state.providerDetails,
                      )
                    ],
                  ),
                  IntrinsicHeight(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            CustomSvgPicture(
                              svgImage: AppAssets.currentLocation,
                              height: 16,
                              width: 16,
                              color: context.colorScheme.accentColor,
                            ),
                            const CustomSizedBox(width: 5),
                            CustomText(
                              "${double.parse(state.providerDetails.distance!).ceil()} ${context.read<SystemSettingCubit>().systemDistanceUnit}",
                              fontSize: 14,
                              color: context.colorScheme.blackColor,
                              fontWeight: FontWeight.w600,
                            ),
                            if (state.providerDetails.ratings! != '0.0')
                              VerticalDivider(
                                color: context.colorScheme.lightGreyColor,
                                thickness: 0.2,
                              ),
                            if (state.providerDetails.ratings! != '0.0')
                              GestureDetector(
                                onTap: () {
                                  Navigator.pushNamed(
                                    context,
                                    reviewScreen,
                                    arguments: {
                                      'providerDetails': state.providerDetails,
                                    },
                                  );
                                },
                                child: Row(
                                  children: [
                                    const CustomSvgPicture(
                                      avoideResponsive: true,
                                      svgImage: AppAssets.icStar,
                                      height: 16,
                                      width: 16,
                                      color: AppColors.ratingStarColor,
                                    ),
                                    const CustomSizedBox(width: 5),
                                    CustomText(
                                      double.parse(
                                              state.providerDetails.ratings!)
                                          .toString(),
                                      fontSize: 14,
                                      color: context.colorScheme.blackColor,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ],
                                ),
                              )
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _serviceListScrollController.dispose();
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(final BuildContext context) {
    // calculatedWidth = context.screenWidth.rw(context) -
    //     2.rw(context) *
    //         ((context.screenWidth.rw(context) * 0.15.rw(context)) -
    //             45.rw(context));

    calculatedWidth = context.screenWidth - 16;

    super.build(context);
    return AnnotatedSafeArea(
      isAnnotated: true,
      child: Scaffold(
        bottomNavigationBar: const BannerAdWidget(),
        body: BlocBuilder<SystemSettingCubit, SystemSettingState>(
            builder: (context, SystemSettingState systemSettingState) {
          if (systemSettingState is! SystemSettingFetchSuccess) {
            return providerDetailsScreenShimmerLoading();
          }
          return BlocBuilder<CartCubit, CartState>(
            builder: (final BuildContext context, final CartState state) =>
                BlocBuilder<ProviderDetailsAndServiceCubit,
                    ProviderDetailsAndServiceState>(
              builder: (final context, final state) {
                if (state is ProviderDetailsAndServiceFetchFailure) {
                  return Center(
                    child: ErrorContainer(
                      showRetryButton: true,
                      onTapRetry: () {
                        fetchProviderDetailsAndServices();
                      },
                      errorMessage:
                          state.errorMessage.translate(context: context),
                    ),
                  );
                } else if (state is ProviderDetailsAndServiceFetchSuccess) {
                  if (state.providerDetails.isAvailableAtLocation == "0") {
                    return Center(
                      child: CustomText(
                        "providerNotAvailableAtLocation"
                            .translate(context: context),
                        color: context.colorScheme.blackColor,
                      ),
                    );
                  }
                  final double appbarimageContainerHight =
                      context.screenHeight.rh(context) * 0.27;
                  final double providerContainerHight = 90.rh(context);
                  final double promocodeHight =
                      state.providerDetails.promocode!.length == 1
                          ? 100.rh(context)
                          : state.providerDetails.promocode!.isNotEmpty
                              ? 120.rh(context)
                              : 10.rh(context);
                  final bool hasPromoCode =
                      state.providerDetails.promocode?.isNotEmpty ?? false;
                  final double scrollThreshold =
                      hasPromoCode ? 250.rs(context) : 150.rs(context);

                  return Stack(
                    children: [
                      NestedScrollView(
                        physics: const ClampingScrollPhysics(),
                        controller: _serviceListScrollController,
                        headerSliverBuilder: (final BuildContext context,
                                final bool innerBoxIsScrolled) =>
                            <Widget>[
                          SliverLayoutBuilder(
                            builder: (context, constraints) {
                              return SliverAppBar(
                                title: constraints.scrollOffset >=
                                        scrollThreshold
                                    ? CustomText(
                                        state.providerDetails
                                            .translatedCompanyName!,
                                        color: context.colorScheme.blackColor,
                                      )
                                    : const SizedBox.shrink(),
                                leading: CustomContainer(
                                  margin: const EdgeInsetsDirectional.only(
                                    end: 10,
                                    top: 10,
                                    bottom: 10,
                                    start: 10,
                                  ),
                                  color: context.colorScheme.secondaryColor
                                      .withValues(alpha: 0.5),
                                  borderRadius: UiUtils.borderRadiusOf8,
                                  child: CustomInkWellContainer(
                                    onTap: () {
                                      Navigator.pop(context);
                                    },
                                    borderRadius: BorderRadius.circular(
                                        UiUtils.borderRadiusOf8),
                                    child: Padding(
                                      padding:
                                          const EdgeInsetsDirectional.symmetric(
                                              horizontal: 5),
                                      child: CustomSvgPicture(
                                        svgImage: Directionality.of(context)
                                                .toString()
                                                .contains(TextDirection
                                                    .RTL.value
                                                    .toLowerCase())
                                            ? AppAssets.backArrowLtr
                                            : AppAssets.backArrow,
                                        color: context.colorScheme.accentColor,
                                      ),
                                    ),
                                  ),
                                ),
                                bottom: PreferredSize(
                                  preferredSize:
                                      Size(double.infinity, 50.rh(context)),
                                  child: Container(
                                    decoration: BoxDecoration(
                                      color: context.colorScheme.secondaryColor,
                                      borderRadius: BorderRadius.only(
                                        topLeft: Radius.circular(
                                            constraints.scrollOffset >= 150
                                                ? 0
                                                : UiUtils.borderRadiusOf10),
                                        topRight: Radius.circular(
                                            constraints.scrollOffset >= 150
                                                ? 0
                                                : UiUtils.borderRadiusOf10),
                                      ),
                                    ),
                                    width: double.infinity,
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 10),
                                    child: Row(
                                      children: [
                                        Expanded(
                                          child: TabBar(
                                            onTap: (value) {
                                              setState(() {
                                                selectedIndex = value;
                                              });
                                            },
                                            controller: _tabController,
                                            isScrollable: false,
                                            dividerColor: Colors.transparent,
                                            padding: const EdgeInsetsDirectional
                                                .only(
                                              end: 5,
                                            ),
                                            labelPadding:
                                                const EdgeInsets.all(2),
                                            indicatorColor: Colors.transparent,
                                            labelStyle: const TextStyle(
                                                fontWeight: FontWeight.bold),
                                            unselectedLabelStyle:
                                                const TextStyle(
                                                    fontWeight:
                                                        FontWeight.normal),
                                            tabs: tabLabels.map((e) {
                                              final int index = tabLabels.indexOf(
                                                  e); // Get the index of the current tab
                                              return Tab(
                                                child: CustomContainer(
                                                  height: 40,
                                                  borderRadius:
                                                      UiUtils.borderRadiusOf8,
                                                  color: selectedIndex == index
                                                      ? context.colorScheme
                                                          .accentColor
                                                      : context.colorScheme
                                                          .primaryColor,
                                                  alignment: Alignment.center,
                                                  padding: const EdgeInsets
                                                      .symmetric(
                                                      horizontal: 20,
                                                      vertical: 2),
                                                  child: CustomText(
                                                    e.translate(
                                                        context: context),
                                                    color: selectedIndex ==
                                                            index
                                                        ? AppColors.whiteColors
                                                        : context.colorScheme
                                                            .blackColor,
                                                  ),
                                                ),
                                              );
                                            }).toList(),
                                          ),
                                        ),
                                        BlocBuilder<UserDetailsCubit,
                                                UserDetailsState>(
                                            builder: (context, userDetails) {
                                          final token =
                                              HiveRepository.getUserToken;
                                          return Row(
                                            mainAxisSize: MainAxisSize.min,
                                            children: [
                                              CustomToolTip(
                                                toolTipMessage: "share"
                                                    .translate(
                                                        context: context),
                                                child: CustomContainer(
                                                  height: 40,
                                                  width: 40,
                                                  margin:
                                                      const EdgeInsetsDirectional
                                                          .only(
                                                    top: 10,
                                                    bottom: 10,
                                                  ),
                                                  color: context
                                                      .colorScheme.accentColor
                                                      .withAlpha(20),
                                                  borderRadius:
                                                      UiUtils.borderRadiusOf5,
                                                  child: CustomInkWellContainer(
                                                    borderRadius: BorderRadius
                                                        .circular(UiUtils
                                                            .borderRadiusOf5),
                                                    onTap: () =>
                                                        _handleShareButtonClick(
                                                      providerDetails:
                                                          state.providerDetails,
                                                    ),
                                                    child: Padding(
                                                      padding:
                                                          const EdgeInsetsDirectional
                                                              .all(10),
                                                      child: CustomSvgPicture(
                                                        svgImage:
                                                            AppAssets.shareSp,
                                                        color: context
                                                            .colorScheme
                                                            .accentColor,
                                                      ),
                                                    ),
                                                  ),
                                                ),
                                              ),
                                              const CustomSizedBox(
                                                width: 10,
                                              ),
                                              if (state.providerDetails
                                                          .isPreBookingChatAllowed ==
                                                      "1" &&
                                                  token != '') ...[
                                                CustomInkWellContainer(
                                                  borderRadius:
                                                      BorderRadius.circular(
                                                          UiUtils
                                                              .borderRadiusOf5),
                                                  onTap: () {
                                                    Navigator.pushNamed(
                                                        context, chatMessages,
                                                        arguments: {
                                                          "chatUser": ChatUser(
                                                            id: state
                                                                    .providerDetails
                                                                    .providerId ??
                                                                "-",
                                                            name: state
                                                                .providerDetails
                                                                .translatedCompanyName
                                                                .toString(),
                                                            translatedName: state
                                                                .providerDetails
                                                                .translatedCompanyName
                                                                .toString(),
                                                            receiverType: "1",
                                                            bookingId: "0",
                                                            providerId: state
                                                                .providerDetails
                                                                .providerId,
                                                            unReadChats: 0,
                                                            profile: state
                                                                .providerDetails
                                                                .image,
                                                            senderId: context
                                                                    .read<
                                                                        UserDetailsCubit>()
                                                                    .getUserDetails()
                                                                    .id ??
                                                                "0",
                                                          ),
                                                        });
                                                  },
                                                  child: CustomContainer(
                                                    height: 40,
                                                    width: 40,
                                                    margin:
                                                        const EdgeInsetsDirectional
                                                            .only(
                                                      top: 10,
                                                      bottom: 10,
                                                    ),
                                                    padding:
                                                        const EdgeInsetsDirectional
                                                            .symmetric(
                                                            horizontal: 10,
                                                            vertical: 10),
                                                    color: context
                                                        .colorScheme.accentColor
                                                        .withAlpha(20),
                                                    borderRadius:
                                                        UiUtils.borderRadiusOf5,
                                                    child: CustomSvgPicture(
                                                      svgImage:
                                                          AppAssets.drChat,
                                                      color: context.colorScheme
                                                          .accentColor,
                                                    ),
                                                  ),
                                                ),
                                              ]
                                            ],
                                          );
                                        }),
                                      ],
                                    ),
                                  ),
                                ),
                                systemOverlayStyle: const SystemUiOverlayStyle(
                                    statusBarColor: Colors.transparent),
                                pinned: true,
                                elevation: 0,
                                surfaceTintColor:
                                    context.colorScheme.secondaryColor,
                                expandedHeight: appbarimageContainerHight +
                                    (providerContainerHight / 2) +
                                    promocodeHight +
                                    (30.rh(context)),
                                backgroundColor:
                                    constraints.scrollOffset >= scrollThreshold
                                        ? context.colorScheme.secondaryColor
                                        : context.colorScheme.primaryColor,
                                actions: [
                                  if (constraints.scrollOffset >=
                                      scrollThreshold)
                                    CustomContainer(
                                      height: 40,
                                      width: 40,
                                      padding:
                                          const EdgeInsetsDirectional.symmetric(
                                              horizontal: 10, vertical: 10),
                                      color: context.colorScheme.secondaryColor,
                                      borderRadius: UiUtils.borderRadiusOf5,
                                      child: BookMarkIcon(
                                        providerData: state.providerDetails,
                                      ),
                                    )
                                ],
                                flexibleSpace: FlexibleSpaceBar(
                                  collapseMode: CollapseMode.pin,
                                  stretchModes: const [
                                    StretchMode.zoomBackground
                                  ],
                                  background: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      //TODO: check this for responsive
                                      CustomSizedBox(
                                        height: appbarimageContainerHight +
                                            (providerContainerHight / 2),
                                        width: double.maxFinite,
                                        child: Stack(
                                          alignment: Alignment.topCenter,
                                          children: [
                                            CustomSizedBox(
                                              width: context.screenWidth,
                                              height: appbarimageContainerHight,
                                              child: CustomCachedNetworkImage(
                                                networkImageUrl: state
                                                    .providerDetails
                                                    .bannerImage!,
                                                fit: BoxFit.cover,
                                              ),
                                            ),
                                            Align(
                                              alignment: Alignment.bottomCenter,
                                              child: profileView(state,
                                                  providerContainerHight),
                                            ),
                                          ],
                                        ),
                                      ),
                                      if (state.providerDetails.promocode!
                                          .isNotEmpty)

                                        //TODO: check this for responsive
                                        CustomSizedBox(
                                          width: calculatedWidth,
                                          child: Padding(
                                            padding: const EdgeInsets.symmetric(
                                                vertical: 10),
                                            child: SliderPromocode(
                                                height: 80.rh(context),
                                                promocode: state
                                                    .providerDetails.promocode!,
                                                calculatedWidth:
                                                    calculatedWidth!),
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                        ],
                        body: TabBarView(
                          controller: _tabController,
                          physics: const BouncingScrollPhysics(),
                          children: [
                            ProviderServicesContainer(
                              isProviderAvailableAtLocation:
                                  state.providerDetails.isAvailableAtLocation!,
                              servicesList: state.serviceList,
                              providerID:
                                  state.providerDetails.providerId ?? "0",
                              isLoadingMoreData: state.isLoadingMoreServices,
                            ),
                            AboutProviderContainer(
                                providerDetails: state.providerDetails)
                          ],
                        ),
                      ),
                      if ((context.watch<CartCubit>().state
                              is CartFetchSuccess) &&
                          (context.watch<CartCubit>().state as CartFetchSuccess)
                                  .cartData
                                  .providerId ==
                              widget.providerIDOrSlug)
                        PositionedDirectional(
                            start: 0,
                            end: 0,
                            bottom: MediaQuery.of(context).viewPadding.bottom,
                            child: const AddToCartComtainer()),
                      if (context.watch<CartCubit>().state
                              is CartFetchSuccess &&
                          ((context.watch<CartCubit>().state
                                          as CartFetchSuccess)
                                      .cartData
                                      .cartDetails ??
                                  [])
                              .isNotEmpty &&
                          ((context.watch<CartCubit>().state
                                      as CartFetchSuccess)
                                  .cartData
                                  .providerId !=
                              widget.providerIDOrSlug)) ...[
                        PositionedDirectional(
                          start: 0,
                          end: 0,
                          bottom: MediaQuery.of(context).viewPadding.bottom,
                          child: CartSubDetailsContainer(
                            providerID: state.providerDetails.providerId,
                          ),
                        ),
                      ],
                    ],
                  );
                }
                return providerDetailsScreenShimmerLoading();
              },
            ),
          );
        }),
      ),
    );
  }

  @override
  bool get wantKeepAlive => true;

  //to prevent multi-taps
  bool isSharing = false;

  Future<void> _handleShareButtonClick(
      {required Providers providerDetails}) async {
    if (isSharing) {
      return;
    }
    isSharing = true;
    ClarityService.logAction(
      ClarityActions.serviceDetailShareTapped,
      {
        'provider_id': providerDetails.id ?? '',
        'provider_name': providerDetails.translatedCompanyName ??
            providerDetails.companyName ??
            '',
      },
    );
    final String shareURL =
        '$deepLinkDomain/provider-details/${providerDetails.slug}?lang=${HiveRepository.getCurrentLanguage()!.languageCode}&lat=${HiveRepository.getLatitude}&lng=${HiveRepository.getLongitude}&share=true';
    await UiUtils.share(
      context: context,
      shareURL,
      subject: "Share Provider",
    );
    isSharing = false;
  }
}

class SliverAppBarDelegate extends SliverPersistentHeaderDelegate {
  final double minHeight;
  final double maxHeight;
  final Widget child;

  SliverAppBarDelegate({
    required this.minHeight,
    required this.maxHeight,
    required this.child,
  });

  @override
  double get minExtent => minHeight;

  @override
  double get maxExtent => max(maxHeight, minHeight);

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return SizedBox.expand(child: child);
  }

  @override
  bool shouldRebuild(SliverAppBarDelegate oldDelegate) {
    return maxHeight != oldDelegate.maxHeight ||
        minHeight != oldDelegate.minHeight ||
        child != oldDelegate.child;
  }
}
