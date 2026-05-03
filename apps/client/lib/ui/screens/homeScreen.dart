import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/screens/bookingsDetails/widgets/previousOrderCardContainer.dart';
import 'package:e_demand/ui/widgets/categoryContainer.dart';
import 'package:e_demand/ui/widgets/scheduledMaintainanceContainer.dart';
import 'package:flutter/material.dart'; // ignore_for_file: use_build_context_synchronously

import '../widgets/sliderImageWidget.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({required this.scrollController, final Key? key})
      : super(key: key);

  final ScrollController scrollController;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with TickerProviderStateMixin {
  late ValueNotifier<int> currentSearchValueIndex = ValueNotifier(0);
  FocusNode searchBarFocusNode = FocusNode();
  List<String> searchValues = [
    "searchProviders",
    "searchServices",
    "searchElectronics",
    "searchHairCutting",
    "searchFanRepair"
  ];

  //this is used to show shadow under searchbar while scrolling
  ValueNotifier<bool> showShadowBelowSearchBar = ValueNotifier(false);

  late final AnimationController _animationController = AnimationController(
      vsync: this, duration: const Duration(milliseconds: 2500));

  late final Animation<double> _bottomToCenterTextAnimation =
      Tween<double>(begin: 1, end: 0).animate(CurvedAnimation(
          parent: _animationController, curve: const Interval(0.0, 0.25)));

  late final Animation<double> _centerToTopTextAnimation =
      Tween<double>(begin: 0, end: 1).animate(CurvedAnimation(
          parent: _animationController, curve: const Interval(0.75, 1.0)));

  late Timer? _timer;

  @override
  void dispose() {
    showShadowBelowSearchBar.dispose();
    searchBarFocusNode.dispose();
    currentSearchValueIndex.dispose();
    _animationController.dispose();
    _timer?.cancel();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _initialiseAnimation();
    AppQuickActions.initAppQuickActions();
    AppQuickActions.createAppQuickActions();

    checkLocationPermission();

    LocalAwesomeNotification.init(context);

    widget.scrollController.addListener(_scrollListener);
  }

  Future<void> fetchHomeScreenData() async {
    await context.read<SystemSettingCubit>().getSystemSettings();
    final futureAPIs = <Future>[
      context.read<HomeScreenCubit>().fetchHomeScreenData(),
      if (HiveRepository.getUserToken != '') ...[
        context.read<CartCubit>().getCartDetails(isReorderCart: false),
        context.read<BookmarkCubit>().fetchBookmark(type: 'list')
      ]
    ];

    await Future.wait(futureAPIs);
  }

  Future<void> checkLocationPermission() async {
    final LocationPermission permission = await Geolocator.checkPermission();

    if ((permission == LocationPermission.denied ||
            permission == LocationPermission.deniedForever) &&
        ((HiveRepository.getLatitude == "0.0" ||
                HiveRepository.getLatitude == '') &&
            (HiveRepository.getLongitude == "0.0" ||
                HiveRepository.getLongitude == ''))) {
      Future.delayed(Duration.zero, () {
        Navigator.pushNamed(context, allowLocationScreenRoute);
      });
    }
    if (permission == LocationPermission.always ||
        permission == LocationPermission.whileInUse) {
      Position position;

      if (HiveRepository.getLatitude != null &&
          HiveRepository.getLongitude != null &&
          HiveRepository.getLatitude != '' &&
          HiveRepository.getLongitude != '') {
        final latitude = HiveRepository.getLatitude ?? "0.0";
        final longitude = HiveRepository.getLongitude ?? "0.0";

        await GeocodingPlatform.instance!.placemarkFromCoordinates(
          double.parse(latitude.toString()),
          double.parse(longitude.toString()),
        );
      } else {
        position = await Geolocator.getCurrentPosition();
        await GeocodingPlatform.instance!
            .placemarkFromCoordinates(position.latitude, position.longitude);
      }

      setState(() {});
    }
  }

  void _scrollListener() {
    if (widget.scrollController.position.pixels > 7 &&
        !showShadowBelowSearchBar.value) {
      showShadowBelowSearchBar.value = true;
    } else if (widget.scrollController.position.pixels < 7 &&
        showShadowBelowSearchBar.value) {
      showShadowBelowSearchBar.value = false;
    }
  }

  Widget _getSearchBarContainer() {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      borderRadiusStyle: const BorderRadius.only(
        bottomLeft: Radius.circular(12),
        bottomRight: Radius.circular(12),
      ),
      child: CustomInkWellContainer(
        onTap: () async {
          ClarityService.logAction(
            ClarityActions.searchScreenOpened,
            {
              'source': 'home_top_search',
            },
          );
          await Navigator.pushNamed(context, searchScreen);
        },
        child: CustomContainer(
          margin: const EdgeInsets.all(15),
          padding: const EdgeInsets.symmetric(horizontal: 15),
          color: context.colorScheme.primaryColor,
          borderRadius: UiUtils.borderRadiusOf10,
          child: Row(
            children: [
              CustomContainer(
                width: 30,
                height: 30,
                margin: const EdgeInsetsDirectional.only(end: 10),
                padding: const EdgeInsets.all(5),
                child: CustomSvgPicture(
                  svgImage: AppAssets.search,
                  color: context.colorScheme.accentColor,
                ),
              ),
              Expanded(
                flex: 8,
                child: ValueListenableBuilder(
                    valueListenable: currentSearchValueIndex,
                    builder: (context, int searchValueIndex, _) {
                      return AnimatedBuilder(
                          animation: _bottomToCenterTextAnimation,
                          builder: (context, child) {
                            final dy = _bottomToCenterTextAnimation.value -
                                _centerToTopTextAnimation.value;

                            final opacity = 1 -
                                _bottomToCenterTextAnimation.value -
                                _centerToTopTextAnimation.value;

                            return CustomContainer(
                              height: 50,
                              alignment: Alignment(-1, dy),
                              child: Opacity(
                                  opacity: opacity,
                                  child: CustomText(
                                    searchValues[searchValueIndex]
                                        .translate(context: context),
                                    color: context.colorScheme.lightGreyColor,
                                    fontWeight: FontWeight.w400,
                                    fontStyle: FontStyle.normal,
                                    fontSize: 14,
                                    maxLines: 1,
                                  )),
                            );
                          });
                    }),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _getCategoryTextContainer() => Padding(
        padding:
            EdgeInsetsDirectional.only(start: 15, end: 15, top: 10.rh(context)),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            CustomText(
              'services'.translate(context: context),
              color: context.colorScheme.blackColor,
              fontWeight: FontWeight.w700,
              fontStyle: FontStyle.normal,
              fontSize: 18,
              textAlign: TextAlign.left,
            ),
          ],
        ),
      );

  Widget _getCategoryListContainer(
          {required final List<CategoryModel> categoryList}) =>
      categoryList.isEmpty
          ? const CustomSizedBox()
          : CustomContainer(
              margin: EdgeInsetsDirectional.only(top: 5.rw(context)),
              child: Column(
                children: [
                  _getCategoryTextContainer(),
                  _getCategoryItemsContainer(categoryList),
                ],
              ),
            );

  Widget _getTitleShimmerEffect({
    required final double height,
    required final double width,
    required final double borderRadius,
  }) =>
      CustomShimmerLoadingContainer(
        width: width,
        height: height,
        borderRadius: borderRadius,
      );

  Widget _getCategoryShimmerEffect() => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding:
                const EdgeInsetsDirectional.only(start: 15, end: 15, top: 15),
            child: _getTitleShimmerEffect(
              width: 350,
              height: 20,
              borderRadius: UiUtils.borderRadiusOf10,
            ),
          ),
          Padding(
            padding: EdgeInsetsDirectional.only(top: 15.rs(context)),
            child: SingleChildScrollView(
              padding:
                  EdgeInsetsDirectional.symmetric(horizontal: 5.rw(context)),
              scrollDirection: Axis.horizontal,
              child: Row(
                children: List.generate(
                  UiUtils.numberOfShimmerContainer,
                  (final int index) => const Column(
                    children: [
                      CustomShimmerLoadingContainer(
                        width: 210,
                        height: 60,
                        borderRadius: UiUtils.borderRadiusOf10,
                        margin: EdgeInsets.symmetric(horizontal: 10),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      );

  Widget _getSingleSectionShimmerEffect() => Padding(
        padding: EdgeInsetsDirectional.only(top: 15.rs(context)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.start,
          children: [
            Padding(
              padding:
                  EdgeInsetsDirectional.symmetric(horizontal: 15.rw(context)),
              child: _getTitleShimmerEffect(
                width: 350,
                height: 20,
                borderRadius: UiUtils.borderRadiusOf10,
              ),
            ),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: List.generate(
                  ResponsiveHelper.isTabletDevice(context) ? 9 : 4,
                  (final int index) => Padding(
                    padding: EdgeInsetsDirectional.only(
                        top: 10.rw(context),
                        end: 5.rw(context),
                        start: 5.rw(context)),
                    child: const CustomShimmerLoadingContainer(
                      width: 120,
                      height: 140,
                      borderRadius: UiUtils.borderRadiusOf10,
                      margin: EdgeInsets.symmetric(horizontal: 10),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      );

  void locationOnTap() {
    if (HiveRepository.getLocationName == null ||
        HiveRepository.getLocationName == '') {
      UiUtils.showBottomSheet(
        enableDrag: true,
        isScrollControlled: true,
        useSafeArea: true,
        child: CustomContainer(
          padding: EdgeInsetsDirectional.only(
              bottom: MediaQuery.of(context).viewInsets.bottom),
          child: const LocationBottomSheet(),
        ),
        context: context,
      ).then((final value) {
        if (value != null) {
          if (value['navigateToMap']) {
            Navigator.pushNamed(
              context,
              googleMapRoute,
              arguments: {
                "defaultLatitude": value['latitude'].toString(),
                "defaultLongitude": value['longitude'].toString(),
                'showAddressForm': false,
                'screenType': GoogleMapScreenType.selectLocationOnMap,
              },
            );
          }
        }
      });
    } else {
      Navigator.pushNamed(
        context,
        googleMapRoute,
        arguments: {
          "defaultLatitude": HiveRepository.getLatitude ?? "0.0",
          "defaultLongitude": HiveRepository.getLongitude ?? "0.0",
          'showAddressForm': false,
          'screenType': GoogleMapScreenType.providerOnMap,
        },
      );
    }
  }

  AppBar _getAppBar() => AppBar(
        elevation: 0.5,
        surfaceTintColor: context.colorScheme.secondaryColor,
        backgroundColor: context.colorScheme.secondaryColor,
        leadingWidth: 0,
        leading: const CustomSizedBox(),
        title: CustomInkWellContainer(
          onTap: () {
            locationOnTap();
          },
          child: Row(
            children: [
              CustomContainer(
                width: 44,
                height: 44,
                color: context.colorScheme.accentColor.withAlpha(15),
                borderRadius: UiUtils.borderRadiusOf10,
                padding: const EdgeInsets.all(10),
                child: CustomSvgPicture(
                  height: 24,
                  width: 24,
                  svgImage: AppAssets.currentLocation,
                  color: context.colorScheme.accentColor,
                ),
              ),
              const CustomSizedBox(
                width: 12,
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CustomText(
                      'your_location'.translate(context: context),
                      color: context.colorScheme.lightGreyColor,
                      fontWeight: FontWeight.w400,
                      fontStyle: FontStyle.normal,
                      fontSize: 12,
                      textAlign: TextAlign.start,
                    ),
                    const CustomSizedBox(
                      height: 3,
                    ),
                    Stack(
                      children: [
                        MarqueeWidget(
                          direction: Axis.horizontal,
                          child: ValueListenableBuilder(
                            valueListenable:
                                Hive.box(HiveRepository.userDetailBoxKey)
                                    .listenable(),
                            builder: (BuildContext context, Box box, _) =>
                                CustomText(
                              " ${HiveRepository.getLocationName ?? "selectYourLocation".translate(context: context)} ",
                              color: context.colorScheme.blackColor,
                              fontWeight: FontWeight.w500,
                              fontStyle: FontStyle.normal,
                              fontSize: 14,
                              maxLines: 1,
                            ),
                          ),
                        ),
                        PositionedDirectional(
                          start: 0,
                          child: CustomContainer(
                            width: 5,
                            height: 70,
                            gradient: LinearGradient(
                              colors: [
                                context.colorScheme.secondaryColor,
                                context.colorScheme.secondaryColor
                                    .withValues(alpha: 0.0005),
                              ],
                              stops: const [0.1, 1],
                              begin: AlignmentDirectional.centerStart,
                              end: AlignmentDirectional.centerEnd,
                            ),
                          ),
                        ),
                        PositionedDirectional(
                          end: 0,
                          child: CustomContainer(
                            width: 5,
                            height: 70,
                            gradient: LinearGradient(
                              colors: [
                                context.colorScheme.secondaryColor
                                    .withValues(alpha: 0.0005),
                                context.colorScheme.secondaryColor,
                              ],
                              stops: const [0.1, 1],
                              end: AlignmentDirectional.centerEnd,
                              begin: AlignmentDirectional.centerStart,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        actions: [
          CustomInkWellContainer(
            onTap: () {
              final authStatus = context.read<AuthenticationCubit>().state;
              if (authStatus is UnAuthenticatedState) {
                UiUtils.showAnimatedDialog(
                    context: context, child: const LogInAccountDialog());

                return;
              }
              ClarityService.logAction(
                ClarityActions.notificationOpened,
                {
                  'previous_route': Routes.currentRoute,
                },
              );
              Navigator.pushNamed(context, notificationRoute);
            },
            child: CustomContainer(
              width: 44,
              height: 44,
              margin: const EdgeInsetsDirectional.only(end: 15, start: 12),
              color: context.colorScheme.accentColor.withAlpha(15),
              borderRadius: UiUtils.borderRadiusOf10,
              padding: const EdgeInsets.all(10),
              child: CustomSvgPicture(
                height: 24,
                width: 24,
                svgImage: AppAssets.notification,
                color: context.colorScheme.accentColor,
              ),
            ),
          ),
        ],
      );

  Widget _getSections({required final List<Sections> sectionsList}) =>
      ListView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: sectionsList.length,
        itemBuilder: (final BuildContext context, int index) {
          return sectionsList[index].partners.isEmpty &&
                  sectionsList[index].subCategories.isEmpty &&
                  sectionsList[index].onGoingBookings.isEmpty &&
                  sectionsList[index].previousBookings.isEmpty &&
                  sectionsList[index].sliderImage == null
              ? const CustomSizedBox()
              : _getSingleSectionContainer(sectionsList[index]);
        },
      );

  Widget _getSingleSectionContainer(final Sections sectionData) =>
      SingleChildScrollView(
        child: BlocBuilder<UserDetailsCubit, UserDetailsState>(
          builder: (context, state) {
            final token = HiveRepository.getUserToken;
            if ((sectionData.sectionType == "previous_order" && token == '') ||
                (sectionData.sectionType == "ongoing_order" && token == ''))
              return const CustomSizedBox();

            if (sectionData.sectionType == "banner") {
              if (sectionData.sliderImage != null) {
                return CustomContainer(
                    avoideResponsive: true,
                    margin: const EdgeInsetsDirectional.only(top: 10),
                    height: 190.rs(context),
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: SingleSliderImageWidget(
                        sliderImage: sectionData.sliderImage!));
              }
              return const SizedBox.shrink();
            }

            return CustomContainer(
              margin: const EdgeInsetsDirectional.only(top: 10),
              color: context.colorScheme.secondaryColor,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _getSingleSectionTitle(sectionData),
                  _getSingleSectionData(sectionData),
                ],
              ),
            );
          },
        ),
      );

  Widget _getSingleSectionTitle(final Sections sectionData) => Padding(
        padding: const EdgeInsetsDirectional.only(start: 15, end: 15, top: 10),
        child: CustomText(
          sectionData.translatedTitle!,
          color: context.colorScheme.blackColor,
          fontWeight: FontWeight.w600,
          fontSize: 18,
          maxLines: 1,
          textAlign: TextAlign.left,
        ),
      );

  Widget _getSingleSectionData(final Sections sectionData) {
    return CustomSizedBox(
      width: context.screenWidth,
      child: SingleChildScrollView(
        padding: const EdgeInsetsDirectional.only(end: 15, start: 5),
        physics: const AlwaysScrollableScrollPhysics(),
        scrollDirection: Axis.horizontal,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: List.generate(
            sectionData.subCategories.isNotEmpty
                ? sectionData.subCategories.length
                : sectionData.partners.isNotEmpty
                    ? sectionData.partners.length
                    : sectionData.onGoingBookings.isNotEmpty
                        ? sectionData.onGoingBookings.length
                        : sectionData.previousBookings.isNotEmpty
                            ? sectionData.previousBookings.length
                            : 0,
            (index) {
              if (sectionData.subCategories.isNotEmpty) {
                return SectionCardForCategoryContainer(
                  title: sectionData.subCategories[index].translatedName!,
                  image: sectionData.subCategories[index].image!,
                  discount: "0",
                  cardHeight: 200,
                  imageHeight: 135,
                  imageWidth: 135,
                  providerCounter:
                      sectionData.subCategories[index].totalProviders!,
                  onTap: () async {
                    final subCategory = sectionData.subCategories[index];
                    ClarityService.logAction(
                      ClarityActions.homeCategoryShortcutTapped,
                      {
                        'category_id': subCategory.id ?? '',
                        'category_name': subCategory.translatedName ?? '',
                        'provider_count': subCategory.totalProviders ?? '',
                        'section': sectionData.sectionType ?? '',
                      },
                    );
                    await Navigator.pushNamed(
                      context,
                      subCategoryRoute,
                      arguments: {
                        "categoryId": subCategory.id,
                        "appBarTitle": subCategory.translatedName,
                        "type": CategoryType.category,
                      },
                    );
                  },
                );
              } else if (sectionData.partners.isNotEmpty) {
                return SectionCardForProviderContainer(
                  title: sectionData.partners[index].translatedCompanyName!,
                  image: sectionData.partners[index].image!,
                  discount: sectionData.partners[index].discount!,
                  bannerImage: sectionData.partners[index].bannerImage!,
                  numberOfRating: sectionData.partners[index].numberOfRating!,
                  averageRating: sectionData.partners[index].averageRating!,
                  distance: sectionData.partners[index].discount!,
                  services: sectionData.partners[index].totalServices!,
                  cardHeight: 180,
                  cardWidth: 290,
                  imageHeight: 135,
                  imageWidth: 120,
                  onTap: () {
                    final partner = sectionData.partners[index];
                    ClarityService.logAction(
                      ClarityActions.homePopularServiceTapped,
                      {
                        'provider_id': partner.id ?? '',
                        'provider_name': partner.translatedCompanyName ??
                            partner.companyName,
                        'services_count': partner.totalServices ?? '',
                        'section': sectionData.sectionType ?? '',
                      },
                    );
                    Navigator.pushNamed(
                      context,
                      providerRoute,
                      arguments: {"providerId": sectionData.partners[index].id},
                    ).then(
                      (final Object? value) {
                        //we are changing the route name
                        //to use CartSubDetailsContainer widget to navigate to provider details screen
                        Routes.previousRoute = Routes.currentRoute;
                        Routes.currentRoute = navigationRoute;
                      },
                    );
                  },
                );
              } else if (sectionData.onGoingBookings.isNotEmpty) {
                final Booking bookingData = sectionData.onGoingBookings[index];

                return _getBookingDetailsCard(bookingDetailsData: bookingData);
              } else if (sectionData.previousBookings.isNotEmpty) {
                final Booking bookingData = sectionData.previousBookings[index];
                if (sectionData.sectionType == "previous_order") {
                  return PreviousOrderCardContainer(
                      bookingDetailsData: bookingData);
                } else {
                  return _getBookingDetailsCard(
                      bookingDetailsData: bookingData);
                }
              }
              return const CustomSizedBox();
            },
          ),
        ),
      ),
    );
  }

  Widget _getBookingDetailsCard({required Booking bookingDetailsData}) {
    return CustomContainer(
      margin: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      border: Border.all(color: context.colorScheme.lightGreyColor, width: 0.5),
      borderRadius: UiUtils.borderRadiusOf10,
      width: 350,
      height: ResponsiveHelper.isTabletDevice(context) ? 211 : 241,
      child: CustomInkWellContainer(
        borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
        onTap: () {
          Navigator.pushNamed(
            context,
            bookingDetails,
            arguments: {"bookingDetails": bookingDetailsData},
          );
        },
        child: BookingCardContainer(
          bookingDetailsData: bookingDetailsData,
          bookingScreenName: "homeScreen",
        ),
      ),
    );
  }

  Widget _getCategoryItemsContainer(final List<CategoryModel> categoryList) =>
      CustomContainer(
        height: 70,
        child: ListView.separated(
          padding: const EdgeInsets.symmetric(horizontal: 15),
          scrollDirection: Axis.horizontal,
          separatorBuilder: (context, index) => const CustomSizedBox(
            avoideResponsive: false,
            width: 10,
          ),
          itemCount: categoryList.length,
          itemBuilder: (context, final int index) {
            return CustomContainer(
              // color: context.colorScheme.secondaryColor,
              borderRadius: UiUtils.borderRadiusOf10,
              child: Center(
                child: CategoryContainer(
                  backgroundColor: context.colorScheme.secondaryColor,
                  imageURL: categoryList[index].categoryImage!,
                  title: categoryList[index].translatedName!,
                  providers: categoryList[index].totalProviders!,
                  cardWidth: 110,
                  maxLines: 1,
                  imageRadius: UiUtils.borderRadiusOf8,
                  fontWeight: FontWeight.w500,
                  darkModeBackgroundColor:
                      categoryList[index].backgroundDarkColor,
                  lightModeBackgroundColor:
                      categoryList[index].backgroundLightColor,
                  onTap: () {
                    Navigator.pushNamed(
                      context,
                      subCategoryRoute,
                      arguments: {
                        'categoryId': categoryList[index].id,
                        'appBarTitle': categoryList[index].translatedName,
                        'type': CategoryType.category,
                      },
                    );
                  },
                ),
              ),
            );
          },
        ),
      );

  Widget _homeScreenShimmerLoading() => SingleChildScrollView(
        padding: EdgeInsetsDirectional.only(
            bottom: UiUtils.getScrollViewBottomPadding(context)),
        child: Column(
          children: [
            _getSliderImageShimmerEffect(),
            const CustomSizedBox(
              height: 15,
            ),
            _getCategoryShimmerEffect(),
            Column(
              children: List.generate(
                UiUtils.numberOfShimmerContainer,
                (final int index) => _getSingleSectionShimmerEffect(),
              ),
            )
          ],
        ),
      );

  Widget _getSliderImageShimmerEffect() => Padding(
        padding: EdgeInsetsDirectional.only(
            start: 15.rw(context), end: 15.rw(context), top: 15.rh(context)),
        child: CustomShimmerLoadingContainer(
          width: context.screenWidth,
          height: 200.rh(context),
          borderRadius: UiUtils.borderRadiusOf10,
        ),
      );

  void _initialiseAnimation() {
    _timer = Timer.periodic(const Duration(milliseconds: 2500), (timer) {
      if (currentSearchValueIndex.value != searchValues.length - 1) {
        currentSearchValueIndex.value += 1;
      } else {
        currentSearchValueIndex.value = 0;
      }
      _animationController.forward(from: 0.0);
    });

    _animationController.forward();
  }

  @override
  Widget build(final BuildContext context) =>
      AnnotatedRegion<SystemUiOverlayStyle>(
        value: UiUtils.getSystemUiOverlayStyle(context: context),
        child: SafeArea(
          top: false,
          child: Scaffold(
            appBar: _getAppBar(),
            body: Stack(
              children: [
                Column(
                  children: [
                    const CustomSizedBox(
                      avoideResponsive: false,
                      height: 70,
                    ),
                    Expanded(
                      child:
                          BlocBuilder<SystemSettingCubit, SystemSettingState>(
                        builder:
                            (context, SystemSettingState systemSettingState) {
                          // Show loading shimmer if system settings are not loaded yet
                          if (systemSettingState
                              is! SystemSettingFetchSuccess) {
                            if (systemSettingState
                                is SystemSettingFetchFailure) {
                              return ErrorContainer(
                                errorMessage: systemSettingState.errorMessage
                                    .toString()
                                    .translate(context: context),
                                onTapRetry: () {
                                  fetchHomeScreenData();
                                },
                              );
                            }

                            return _homeScreenShimmerLoading();
                          }

                          return BlocBuilder<CartCubit, CartState>(
                            // we have added Cart cubit
                            // because we want to calculate bottom padding of scroll

                            builder: (final BuildContext context,
                                    final CartState state) =>
                                BlocBuilder<HomeScreenCubit, HomeScreenState>(
                              builder:
                                  (context, HomeScreenState homeScreenState) {
                                if (homeScreenState
                                    is HomeScreenDataFetchSuccess) {
                                  final cartButtonHeight = context
                                              .read<CartCubit>()
                                              .getProviderIDFromCartData() ==
                                          '0'
                                      ? 0
                                      : kBottomNavigationBarHeight.rh(context) +
                                          10.rf(context);
                                  if (homeScreenState.homeScreenData.category!.isEmpty &&
                                      homeScreenState
                                          .homeScreenData.sections!.isEmpty &&
                                      homeScreenState
                                          .homeScreenData.sliders!.isEmpty) {
                                    return SingleChildScrollView(
                                      padding: EdgeInsets.symmetric(
                                        horizontal: 15.rw(context),
                                        vertical: 20.rh(context),
                                      ),
                                      child: Center(
                                        child: NoDataFoundWidget(
                                          titleKey: 'weAreNotAvailableHere'
                                              .translate(context: context),
                                          subtitleKey:
                                              'weAreNotAvailableHereSubTitle'
                                                  .translate(context: context),
                                          buttonName: "changeLocation"
                                              .translate(context: context),
                                          onTapRetry: locationOnTap,
                                          showRetryButton: true,
                                        ),
                                      ),
                                    );
                                  }
                                  return CustomRefreshIndicator(
                                    onRefreshCallback: fetchHomeScreenData,
                                    displacment: 12,
                                    child: SingleChildScrollView(
                                      controller: widget.scrollController,
                                      physics:
                                          const AlwaysScrollableScrollPhysics(),
                                      padding: EdgeInsetsDirectional.only(
                                        bottom:
                                            UiUtils.getScrollViewBottomPadding(
                                                    context) +
                                                cartButtonHeight,
                                      ),
                                      child: Column(
                                        children: [
                                          const CustomSizedBox(
                                            height: 25,
                                          ),
                                          if (systemSettingState
                                                  .systemSettingDetails
                                                  .appSetting !=
                                              null) ...[
                                            ScheduledMaintenanceContainer(
                                              appSettings: systemSettingState
                                                  .systemSettingDetails
                                                  .appSetting!,
                                            ),
                                          ],
                                          if (homeScreenState.homeScreenData
                                              .sliders!.isNotEmpty) ...[
                                            SliderImageWidget(
                                              sliderImages: homeScreenState
                                                  .homeScreenData.sliders!,
                                            ),
                                          ],
                                          _getCategoryListContainer(
                                            categoryList: homeScreenState
                                                .homeScreenData.category!,
                                          ),
                                          _getSections(
                                            sectionsList: homeScreenState
                                                .homeScreenData.sections!,
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                } else if (homeScreenState
                                    is HomeScreenDataFetchFailure) {
                                  return ErrorContainer(
                                    errorMessage: homeScreenState.errorMessage
                                        .translate(context: context),
                                    onTapRetry: () {
                                      fetchHomeScreenData();
                                    },
                                  );
                                }
                                return _homeScreenShimmerLoading();
                              },
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
                BlocBuilder<HomeScreenCubit, HomeScreenState>(
                  builder: (context, state) {
                    final shouldHideSearchBar =
                        state is HomeScreenDataFetchSuccess &&
                            state.homeScreenData.category!.isEmpty &&
                            state.homeScreenData.sections!.isEmpty &&
                            state.homeScreenData.sliders!.isEmpty;

                    if (shouldHideSearchBar) {
                      return const CustomSizedBox();
                    }

                    return ValueListenableBuilder(
                      builder: (final BuildContext context, final Object? value,
                              final Widget? child) =>
                          CustomContainer(
                        color: context.colorScheme.primaryColor,
                        borderRadius: 12,
                        boxShadow: showShadowBelowSearchBar.value
                            ? [
                                BoxShadow(
                                  offset: const Offset(0, 0.75),
                                  spreadRadius: 1,
                                  blurRadius: 5,
                                  color: context.colorScheme.lightGreyColor
                                      .withAlpha(80),
                                )
                              ]
                            : [],
                        child: _getSearchBarContainer(),
                      ),
                      valueListenable: showShadowBelowSearchBar,
                    );
                  },
                ),
                Padding(
                  padding: EdgeInsetsDirectional.only(
                    bottom: kBottomNavigationBarHeight.rh(context),
                  ),
                  child: const Align(
                      alignment: Alignment.bottomCenter,
                      child: CartSubDetailsContainer()),
                ),
              ],
            ),
          ),
        ),
      );
}
