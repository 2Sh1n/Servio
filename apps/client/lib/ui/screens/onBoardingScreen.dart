import 'dart:ui';

import 'package:carousel_slider/carousel_slider.dart';
import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/core/analytics/google_analytics_service.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class OnBoardingScreen extends StatefulWidget {
  const OnBoardingScreen({
    Key? key,
  }) : super(key: key);

  @override
  State<OnBoardingScreen> createState() => _OnBoardingScreenState();

  static Route route(final RouteSettings routeSettings) => CupertinoPageRoute(
        builder: (final _) => const OnBoardingScreen(),
      );
}

class _OnBoardingScreenState extends State<OnBoardingScreen> {
  int currentSliderImage = 0;

  @override
  void initState() {
    super.initState();

    _logSlideSeen(currentSliderImage);
    // /// loading country codes before we load login screen
    // context.read<CountryCodeCubit>().loadAllCountryCode(context);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      body: AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          systemNavigationBarColor: Colors.transparent,
          systemNavigationBarIconBrightness:
              context.watch<AppThemeCubit>().state.appTheme == AppTheme.dark
                  ? Brightness.light
                  : Brightness.dark,
          systemNavigationBarDividerColor: Colors.transparent,
          statusBarIconBrightness:
              context.watch<AppThemeCubit>().state.appTheme == AppTheme.dark
                  ? Brightness.light
                  : Brightness.dark,
        ),
        child: ResponsiveHelper.isTabletDevice(context)
            ? Column(
                children: [
                  const Expanded(
                    child: SingleChildScrollView(
                      child: ImageSliderWithBlurEffectAndIndicators(),
                    ),
                  ),
                  const CustomSizedBox(
                    height: 20,
                  ),
                  Padding(
                    padding: EdgeInsets.symmetric(horizontal: 100.rw(context))
                        .copyWith(bottom: 20),
                    child: CustomRoundedButton(
                        buttonTitle: "getStarted".translate(context: context),
                        showBorder: false,
                        widthPercentage: 1,
                        backgroundColor: context.colorScheme.accentColor,
                        onTap: () {
                          GoogleAnalyticsService.logEvent(
                            'onboarding_get_started_tap',
                          );
                          Navigator.of(context).pushNamedAndRemoveUntil(
                            loginRoute,
                            (final route) => false,
                            arguments: {"source": "introSliderScreen"},
                          );
                        }),
                  ),
                ],
              )
            : Column(
                children: [
                  const ImageSliderWithBlurEffectAndIndicators(),
                  CustomSizedBox(
                    height: context.screenHeight * 0.05,
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: CustomRoundedButton(
                        buttonTitle: "getStarted".translate(context: context),
                        showBorder: false,
                        widthPercentage: 1,
                        backgroundColor: context.colorScheme.accentColor,
                        onTap: () {
                          GoogleAnalyticsService.logEvent(
                            'onboarding_get_started_tap',
                          );
                          Navigator.of(context).pushNamedAndRemoveUntil(
                            loginRoute,
                            (final route) => false,
                            arguments: {"source": "introSliderScreen"},
                          );
                        }),
                  ),
                ],
              ),
      ),
    );
  }
}

void _logSlideSeen(int index) {
  GoogleAnalyticsService.logEvent(
    'onboarding_slide_viewed',
    parameters: {
      'step_index': index,
    },
  );
}

class ImageSliderWithBlurEffectAndIndicators extends StatefulWidget {
  const ImageSliderWithBlurEffectAndIndicators({super.key});

  @override
  State<ImageSliderWithBlurEffectAndIndicators> createState() =>
      _ImageSliderWithBlurEffectAndIndicatorsState();
}

class _ImageSliderWithBlurEffectAndIndicatorsState
    extends State<ImageSliderWithBlurEffectAndIndicators> {
  int currentSliderImage = 0;

  final ScrollController _scrollController = ScrollController();
  var totalScroll;
  var currentScroll;

  @override
  void initState() {
    super.initState();
  }

  @override
  void dispose() {
    _scrollController.dispose();

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final double sliderHeight = ResponsiveHelper.isTabletDevice(context)
        ? context.screenHeight * 0.75
        : context.screenHeight * 0.85.rh(context);

    return LayoutBuilder(
      builder: (context, constraints) {
        // Use available height if it's constrained (in Expanded), otherwise use calculated height
        final double finalHeight =
            constraints.maxHeight.isFinite && constraints.maxHeight > 0
                ? constraints.maxHeight
                : sliderHeight;

        return CustomSizedBox(
          height: finalHeight,
          child: Stack(
            fit: StackFit.expand,
            children: [
              CarouselSlider(
                  options: CarouselOptions(
                    height: finalHeight,
                    viewportFraction: 1,
                    initialPage: 0,
                    enableInfiniteScroll: true,
                    reverse: false,
                    autoPlay: true,
                    autoPlayInterval: Duration(
                        milliseconds: sliderAnimationDurationSettings[
                            "sliderAnimationDuration"]),
                    autoPlayAnimationDuration: Duration(
                        milliseconds: sliderAnimationDurationSettings[
                            "changeSliderAnimationDuration"]),
                    autoPlayCurve: Curves.fastOutSlowIn,
                    enlargeCenterPage: false,
                    pauseAutoPlayOnTouch: false,
                    enlargeFactor: 1,
                    aspectRatio: 1,
                    scrollDirection: Axis.horizontal,
                    onPageChanged: (index, reason) {
                      setState(() {
                        currentSliderImage = index;
                      });
                      _logSlideSeen(index);
                    },
                  ),
                  items: List.generate(
                    introScreenList.length,
                    (index) => SingleChildScrollView(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Stack(
                            clipBehavior: Clip.none,
                            children: [
                              CustomContainer(
                                alignment: Alignment.topCenter,
                                child: CustomContainer(
                                  borderRadiusStyle: const BorderRadius.only(
                                    bottomRight: Radius.circular(16),
                                    bottomLeft: Radius.circular(16),
                                  ),
                                  image: DecorationImage(
                                    image: ExactAssetImage(
                                        introScreenList[index].imagePath),
                                    fit: BoxFit.cover,
                                  ),
                                  width: context.screenWidth,
                                  height:
                                      ResponsiveHelper.isTabletDevice(context)
                                          ? (context.screenHeight * 0.5)
                                          : (context.screenHeight * 0.6 - 14)
                                              .rh(context),
                                ),
                              ),
                              Align(
                                alignment: Alignment.topCenter,
                                child: BackdropFilter(
                                  filter: ImageFilter.blur(
                                      sigmaX: 4.0,
                                      sigmaY: 4.0,
                                      tileMode: TileMode.decal),
                                  child: CustomContainer(
                                    borderRadiusStyle: const BorderRadius.only(
                                      bottomRight: Radius.circular(16),
                                      bottomLeft: Radius.circular(16),
                                    ),
                                    image: DecorationImage(
                                      image: ExactAssetImage(
                                          introScreenList[index].imagePath),
                                      fit: BoxFit.cover,
                                    ),
                                    width: context.screenWidth,
                                    height:
                                        ResponsiveHelper.isTabletDevice(context)
                                            ? (context.screenHeight * 0.5)
                                            : (context.screenHeight * 0.6 - 15)
                                                .rh(context),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          CustomSizedBox(
                            height: ResponsiveHelper.isTabletDevice(context)
                                ? 20
                                : context.screenHeight * 0.05,
                          ),
                          Padding(
                            padding: EdgeInsets.symmetric(
                              horizontal:
                                  ResponsiveHelper.isTabletDevice(context)
                                      ? 50.rw(context)
                                      : 15,
                              vertical: ResponsiveHelper.isTabletDevice(context)
                                  ? 10
                                  : 0,
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                CustomText(
                                  introScreenList[index]
                                      .introScreenTitle
                                      .translate(context: context),
                                  color:
                                      Theme.of(context).colorScheme.blackColor,
                                  fontWeight: FontWeight.w600,
                                  fontStyle: FontStyle.normal,
                                  fontSize:
                                      ResponsiveHelper.isTabletDevice(context)
                                          ? 32.rf(context)
                                          : 26,
                                  textAlign: TextAlign.center,
                                ),
                                const CustomSizedBox(
                                  height: 8,
                                ),
                                CustomText(
                                  introScreenList[index]
                                      .introScreenSubTitle
                                      .translate(context: context),
                                  color: Theme.of(context)
                                      .colorScheme
                                      .lightGreyColor,
                                  fontWeight: FontWeight.w400,
                                  fontStyle: FontStyle.normal,
                                  fontSize:
                                      ResponsiveHelper.isTabletDevice(context)
                                          ? 18.rf(context)
                                          : 16,
                                  textAlign: TextAlign.center,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  )),
              //indicators
              Align(
                alignment: Alignment.topCenter,
                child: CustomSizedBox(
                    height: ResponsiveHelper.isTabletDevice(context)
                        ? (context.screenHeight * 0.5)
                        : (context.screenHeight * 0.6).rh(context),
                    child: Align(
                        alignment: Alignment.bottomCenter,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 20,
                            vertical: 30,
                          ),
                          child: CustomContainer(
                            height: 24,
                            child: LayoutBuilder(
                              builder: (p0, p1) {
                                return Center(
                                  child: SingleChildScrollView(
                                    controller: _scrollController,
                                    scrollDirection: Axis.horizontal,
                                    child: ClipRRect(
                                      borderRadius: BorderRadius.circular(50),
                                      child: BackdropFilter(
                                        filter: ImageFilter.blur(
                                            sigmaX: 13, sigmaY: 13),
                                        child: CustomContainer(
                                          padding: const EdgeInsets.symmetric(
                                              horizontal: 10, vertical: 5),
                                          child: Row(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.center,
                                            mainAxisAlignment:
                                                MainAxisAlignment.center,
                                            children: List.generate(
                                              introScreenList.length,
                                              (index) {
                                                return getIndicator(
                                                    index: index, p1: p1);
                                              },
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        ))),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget getIndicator({required int index, required BoxConstraints p1}) {
    return Stack(
      children: [
        AnimatedContainer(
          alignment: AlignmentDirectional.centerStart,
          curve: Curves.easeIn,
          duration: Duration(
              milliseconds: sliderAnimationDurationSettings[
                  "changeSliderAnimationDuration"]),
          margin: const EdgeInsets.symmetric(horizontal: 5),
          height: 8,
          width: currentSliderImage == index ? 30 : 8,
          decoration: BoxDecoration(
            color: context.colorScheme.secondaryColor,
            borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf50),
          ),
          child: currentSliderImage == index
              ? LayoutBuilder(
                  builder: (context, constraints) {
                    //we will get total available scroll with (padding between indicator + indicator size)
                    // + added 22, because active indicator extra width of 22
                    totalScroll = (introScreenList.length * 18) + 22;
                    currentScroll = (index * 18) + 22;
                    if (currentScroll >= p1.maxWidth) {
                      final currentScrolls = _scrollController.position.pixels;
                      _scrollController.animateTo(
                        currentScrolls + 5,
                        duration: const Duration(milliseconds: 10),
                        curve: Curves.easeInOut,
                      );
                    } else if (index == 0 &&
                        _scrollController.position.pixels != 0) {
                      _scrollController.animateTo(
                        0,
                        duration: const Duration(milliseconds: 10),
                        curve: Curves.easeInOut,
                      );
                    }
                    return const CustomSizedBox();
                  },
                )
              : const CustomSizedBox(),
        ),
        AnimatedContainer(
          duration: currentSliderImage == index
              ? Duration(
                  milliseconds: sliderAnimationDurationSettings[
                      "sliderAnimationDuration"])
              : Duration.zero,
          width: currentSliderImage == index ? 30 : 8,
          curve: Curves.easeIn,
          margin: const EdgeInsets.symmetric(horizontal: 5),
          height: 8,
          decoration: BoxDecoration(
            color: currentSliderImage == index
                ? context.colorScheme.accentColor
                : context.colorScheme.secondaryColor,
            borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf50),
          ),
        )
      ],
    );
  }
}
