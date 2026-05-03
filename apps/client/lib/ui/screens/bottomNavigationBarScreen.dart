import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class CustomNavigationBar extends StatefulWidget {
  const CustomNavigationBar({final Key? key}) : super(key: key);

  @override
  State<CustomNavigationBar> createState() => CustomNavigationBarState();

  static Route route(final RouteSettings routeSettings) => CupertinoPageRoute(
        builder: (final BuildContext context) => CustomNavigationBar(
          key: UiUtils.bottomNavigationBarGlobalKey,
        ),
      );
}

class CustomNavigationBarState extends State<CustomNavigationBar>
    with WidgetsBindingObserver {
  String? currentVersion;
  ValueNotifier<int> selectedIndexOfBottomNavigationBar = ValueNotifier(0);
  List<ScrollController> scrollControllerList = [];

  @override
  Future<void> didChangeAppLifecycleState(AppLifecycleState state) async {
    if (state == AppLifecycleState.resumed) {
      final backgroundChatMessages = await ChatNotificationsRepository()
          .getBackgroundChatNotificationData();

      if (backgroundChatMessages.isNotEmpty) {
        //empty any old data and stream new once
        ChatNotificationsRepository()
            .setBackgroundChatNotificationData(data: []);
        for (int i = 0; i < backgroundChatMessages.length; i++) {
          ChatNotificationsUtils.addChatStreamValue(
              chatData: backgroundChatMessages[i]);
        }
      }
    }
  }

  @override
  void initState() {
    super.initState();

    MobileAds.instance.initialize();

    WidgetsBinding.instance.addObserver(this);

    for (int i = 0; i < 5; i++) {
      scrollControllerList.add(ScrollController());
    }
    Future.delayed(Duration.zero).then((final value) {
      context.read<VerifyOtpCubit>().setInitialState();
      context.read<SendVerificationCodeCubit>().setInitialState();
    });
    fetchCurrentVersion();

    if (Routes.globelProviderSlugForDeeplink != '') {
      Navigator.pushNamed(
        context,
        providerRoute,
        arguments: {
          "providerId": Routes.globelProviderSlugForDeeplink,
          'type': ProviderDetailsParamType.slug
        },
      );
    }
  }

  Future<void> fetchCurrentVersion() async {
    try {
      currentVersion =
          await PackageInfo.fromPlatform().then((final value) => value.version);
    } catch (_) {}
  }

  void _updateStatusBarForIOS(ThemeState themeState) {
    if (Platform.isIOS) {
      SystemChrome.setSystemUIOverlayStyle(
        SystemUiOverlayStyle(
          statusBarColor: themeState.appTheme == AppTheme.dark
              ? const Color(0xff212121)
              : const Color(0xffFFFFFF),
          systemNavigationBarColor: themeState.appTheme == AppTheme.dark
              ? const Color(0xff212121)
              : const Color(0xffFFFFFF),
          systemNavigationBarIconBrightness:
              themeState.appTheme == AppTheme.dark
                  ? Brightness.light
                  : Brightness.dark,
          systemNavigationBarDividerColor: Colors.transparent,
          statusBarIconBrightness: themeState.appTheme == AppTheme.dark
              ? Brightness.light
              : Brightness.dark,
        ),
      );
    }
  }

  @override
  void dispose() {
    selectedIndexOfBottomNavigationBar.dispose();

    // Use a more robust disposal approach
    for (int i = 0; i < scrollControllerList.length; i++) {
      final controller = scrollControllerList[i];
      try {
        // Check if controller is still valid before disposing
        if (controller.hasClients) {
          controller.dispose();
        }
      } catch (e) {
        // Log the error but don't crash the app
        debugPrint('Error disposing ScrollController $i: $e');
      }
    }

    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(final BuildContext context) =>
      BlocBuilder<AppThemeCubit, ThemeState>(
        builder: (context, themeState) {
          _updateStatusBarForIOS(themeState);
          return AnnotatedRegion<SystemUiOverlayStyle>(
            value: UiUtils.getSystemUiOverlayStyle(context: context),
            child: Scaffold(
              body: ValueListenableBuilder(
                valueListenable: selectedIndexOfBottomNavigationBar,
                builder:
                    (final context, final Object? value, final Widget? child) =>
                        PopScope(
                  onPopInvokedWithResult: (didPop, _) {
                    if (!didPop) {
                      selectedIndexOfBottomNavigationBar.value = 0;
                      return;
                    }
                  },
                  canPop: selectedIndexOfBottomNavigationBar.value == 0,
                  child: Stack(
                    children: [
                      IndexedStack(
                        sizing: StackFit.passthrough,
                        index: selectedIndexOfBottomNavigationBar.value,
                        children: [
                          HomeScreen(scrollController: scrollControllerList[0]),
                          BookingsScreen(
                            key: UiUtils.bookingScreenGlobalKey,
                            scrollController: scrollControllerList[1],
                          ),
                          CategoryScreen(
                              scrollController: scrollControllerList[2]),
                          MyRequestListScreen(
                              scrollController: scrollControllerList[3]),
                          ProfileScreen(
                            scrollController: scrollControllerList[4],
                            currentVersion: '1.0.0',
                          )
                        ],
                      ),
                      Align(
                        alignment: Alignment.bottomCenter,
                        child: BottomNavigationBar(
                          backgroundColor: context.colorScheme.secondaryColor,
                          selectedItemColor: context.colorScheme.accentColor,
                          unselectedItemColor:
                              context.colorScheme.lightGreyColor,
                          selectedFontSize: 12,
                          currentIndex:
                              selectedIndexOfBottomNavigationBar.value,
                          showUnselectedLabels: true,
                          showSelectedLabels: true,
                          type: BottomNavigationBarType.fixed,
                          onTap: (final int selectedIndex) {
                            final previousSelectedIndex =
                                selectedIndexOfBottomNavigationBar.value;
                            selectedIndexOfBottomNavigationBar.value =
                                selectedIndex;
                            //animate scroll to top when pressing the item twice
                            if (previousSelectedIndex == selectedIndex &&
                                scrollControllerList[selectedIndex]
                                    .hasClients &&
                                scrollControllerList[selectedIndex]
                                    .positions
                                    .isNotEmpty) {
                              try {
                                scrollControllerList[selectedIndex].animateTo(
                                  0,
                                  duration: const Duration(milliseconds: 500),
                                  curve: Curves.easeInOut,
                                );
                              } catch (e) {
                                debugPrint('Error animating scroll: $e');
                              }
                            }

                            if (HiveRepository.getUserToken == '' &&
                                selectedIndex == 1 &&
                                previousSelectedIndex != 1) {
                              UiUtils.showAnimatedDialog(
                                  context: context,
                                  child: const LogInAccountDialog());
                            }
                            if (HiveRepository.getUserToken == '' &&
                                selectedIndex == 3 &&
                                previousSelectedIndex != 3) {
                              UiUtils.showAnimatedDialog(
                                  context: context,
                                  child: const LogInAccountDialog());
                            }
                          },
                          items: [
                            _getBottomNavigationBarItem(
                              activeImage: AppAssets.activeExplore,
                              deActiveImage: AppAssets.deactiveExplore,
                              title: 'explore'.translate(context: context),
                              index: 0,
                              currentIndex:
                                  selectedIndexOfBottomNavigationBar.value,
                            ),
                            _getBottomNavigationBarItem(
                              activeImage: AppAssets.activeBooking,
                              deActiveImage: AppAssets.deactiveBooking,
                              title: 'booking'.translate(context: context),
                              index: 1,
                              currentIndex:
                                  selectedIndexOfBottomNavigationBar.value,
                            ),
                            _getBottomNavigationBarItem(
                              activeImage: AppAssets.activeCategory,
                              deActiveImage: AppAssets.deactiveCategory,
                              title: 'services'.translate(context: context),
                              index: 2,
                              currentIndex:
                                  selectedIndexOfBottomNavigationBar.value,
                            ),
                            _getBottomNavigationBarItem(
                              activeImage: AppAssets.activeMyReq,
                              deActiveImage: AppAssets.inactiveMyReq,
                              title: 'myRequests'.translate(context: context),
                              index: 3,
                              currentIndex:
                                  selectedIndexOfBottomNavigationBar.value,
                            ),
                            _getBottomNavigationBarItem(
                              activeImage: AppAssets.activeProfile,
                              deActiveImage: AppAssets.deactiveProfile,
                              title: 'profile'.translate(context: context),
                              index: 4,
                              currentIndex:
                                  selectedIndexOfBottomNavigationBar.value,
                            ),
                          ],
                        ),
                      )
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      );

  BottomNavigationBarItem _getBottomNavigationBarItem({
    required final String activeImage,
    required final String deActiveImage,
    required final String title,
    required final int index,
    required final int currentIndex,
  }) =>
      BottomNavigationBarItem(
        icon: currentIndex == index
            ? CustomSvgPicture(
                svgImage: activeImage,
                color: context.colorScheme.accentColor,
              )
            : CustomSvgPicture(
                svgImage: deActiveImage,
              ),
        label: title,
      );
}
