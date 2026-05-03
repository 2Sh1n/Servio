import 'dart:ui';
import 'package:edemand_partner/ui/widgets/customGridView.dart';
import 'package:flutter/material.dart';

import '../../../app/generalImports.dart';

class MainActivity extends StatefulWidget {
  const MainActivity({super.key});

  @override
  State<MainActivity> createState() => MainActivityState();

  static Route<MainActivity> route(RouteSettings routeSettings) {
    return CupertinoPageRoute(
      builder: (_) => MainActivity(key: UiUtils.bottomNavigationBarGlobalKey),
    );
  }
}

class MainActivityState extends State<MainActivity>
    with TickerProviderStateMixin, WidgetsBindingObserver {
  ValueNotifier<int> selectedIndexOfBottomNavigationBar = ValueNotifier(0);

  late PageController pageController;

  List<ScrollController> scrollControllerList = [];

  bool _showOverlay = false;
  PageController pagecontrollerforGridView = PageController();
  int _currentPage = 0;
  List<({String name, String icon})> gridItems = [
    (name: 'notifications', icon: AppAssets.notification),
    (name: 'promoCodeLbl', icon: AppAssets.drPromoCode),
    (name: 'cashCollection', icon: AppAssets.drCashCollection),
    (name: 'settlementHistory', icon: AppAssets.drSettlementHistory),
    (name: 'withdrawalRequest', icon: AppAssets.drWithdrawalRequest),
    (name: 'bookingPaymentManagement', icon: AppAssets.drBookingPayment),
    (name: 'reviewsTitleLbl', icon: AppAssets.reviews),
    (name: 'subscriptions', icon: AppAssets.drSubscription),
    (name: 'support', icon: AppAssets.support),
    (name: 'privacyPolicyLbl', icon: AppAssets.drPrivacyPolicy),
    (name: 'termsConditionLbl', icon: AppAssets.drTermsConditions),
    (name: 'changePassword', icon: AppAssets.drChangePass),
    (name: 'aboutUs', icon: AppAssets.drAboutUs),
    (name: 'contactUs', icon: AppAssets.drContactUs),
    (name: 'shareApp', icon: AppAssets.drShareApp),

    (name: 'languageLbl', icon: AppAssets.drLanguage),
    (name: 'darkThemeLbl', icon: AppAssets.drTheme),
    (name: 'logoutLbl', icon: AppAssets.drLogout),
    (name: 'deleteAccount', icon: AppAssets.drDeleteAccount),
    // (name: 'blockedUser', icon: AppAssets.drBlockedUsers),
  ];

  final itemsPerPage = 9;
  late final pagesCount = (gridItems.length / 9).ceil();

  bool isDragging = false;
  int draggingIndex = -1;
  OverlayEntry? draggingOverlay;
  bool _autoScrolling = false;
  double _draggedItemSize = 80.0;

  int? previousSelectedIndex;

  @override
  void initState() {
    super.initState();
    _loadSavedPositions();
    WidgetsBinding.instance.addObserver(this);

    for (int i = 0; i < 4; i++) {
      scrollControllerList.add(ScrollController());
    }

    AppQuickActions.initAppQuickActions();
    AppQuickActions.createAppQuickActions();

    Future.delayed(Duration.zero, () {
      LocalAwesomeNotification.init(context);
      try {
        context.read<GetProviderDetailsCubit>().getProviderDetails();
      } catch (_) {}
    });

    pageController = PageController();
  }

  Future<void> _loadSavedPositions() async {
    final newItems = await MainActivityRepository.loadPositions(gridItems);
    if (newItems.isNotEmpty) {
      gridItems = newItems;
      setState(() {});
    }
  }

  Future<void> _savePositions() async {
    await MainActivityRepository.savePositions(gridItems);
  }

  void _toggleOverlay() {
    setState(() {
      _showOverlay = !_showOverlay;
      if (!_showOverlay) _savePositions();
    });
  }

  void _handleAutoScroll(DragUpdateDetails details) {
    final double screenWidth = MediaQuery.of(context).size.width;
    final double position = details.globalPosition.dx;

    if (!_autoScrolling) {
      if (_currentPage == 0 && position > screenWidth * 0.9) {
        _autoScrolling = true;
        pagecontrollerforGridView
            .nextPage(
              duration: const Duration(milliseconds: 200),
              curve: Curves.easeInOut,
            )
            .then((_) {
              setState(() => _autoScrolling = false);
            });
      } else if (_currentPage == 1 && position < screenWidth * 0.1) {
        _autoScrolling = true;
        pagecontrollerforGridView
            .previousPage(
              duration: const Duration(milliseconds: 200),
              curve: Curves.easeInOut,
            )
            .then((_) {
              setState(() => _autoScrolling = false);
            });
      }
    }
  }

  @override
  Future<void> didChangeAppLifecycleState(AppLifecycleState state) async {
    if (state == AppLifecycleState.resumed) {
      final backgroundChatMessages = await ChatNotificationsRepository()
          .getBackgroundChatNotificationData();

      if (backgroundChatMessages.isNotEmpty) {
        //empty any old data and stream new once
        ChatNotificationsRepository().setBackgroundChatNotificationData(
          data: [],
        );
        for (int i = 0; i < backgroundChatMessages.length; i++) {
          ChatNotificationsUtils.addChatStreamValue(
            chatData: backgroundChatMessages[i],
          );
        }
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    pageController.dispose();

    super.dispose();
  }

  String _getUserName() {
    return context
            .watch<ProviderDetailsCubit>()
            .providerDetails
            .providerInformation
            ?.getTranslatedUsername(
              HiveRepository.getCurrentLanguage()?.languageCode ?? 'en',
            ) ??
        '';
  }

  String _getEmail() {
    return context.watch<ProviderDetailsCubit>().providerDetails.user?.email ??
        '';
  }

  dynamic getProfileImage() {
    return context.watch<ProviderDetailsCubit>().providerDetails.user?.image ??
        '';
  }

  bool doUserHasProfileImage() {
    return context.watch<ProviderDetailsCubit>().providerDetails.user?.image !=
            '' ||
        context.watch<ProviderDetailsCubit>().providerDetails.user?.image !=
            null;
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: UiUtils.getSystemUiOverlayStyle(context: context),
      child: PopScope(
        canPop: selectedIndexOfBottomNavigationBar.value == 0,
        onPopInvokedWithResult: (didPop, _) {
          if (didPop) {
            return;
          } else {
            if (selectedIndexOfBottomNavigationBar.value != 0) {
              selectedIndexOfBottomNavigationBar.value = 0;
              pageController.jumpToPage(
                selectedIndexOfBottomNavigationBar.value,
              );
            }
          }
        },
        child: BlocListener<GetProviderDetailsCubit, GetProviderDetailsState>(
          listener: (context, state) {
            if (state is GetProviderDetailsSuccessState) {
              HiveRepository.setUserData(state.providerDetails.toJsonData());
              context.read<ProviderDetailsCubit>().setUserInfo(
                state.providerDetails,
              );
            } else {
              //get the locally stored provider details and update the cubit
              context.read<ProviderDetailsCubit>().setUserInfo(
                HiveRepository.getProviderDetails(),
              );
            }
          },
          child: BlocBuilder<AppThemeCubit, ThemeState>(
            builder: (context, themeState) {
              return AnnotatedRegion(
                value: UiUtils.getSystemUiOverlayStyle(
                  context: context,
                  statusBarColor: Colors.transparent,
                  appTheme: themeState.appTheme,
                ),
                child: Scaffold(
                  resizeToAvoidBottomInset: false,
                  extendBodyBehindAppBar: true,
                  body: Stack(
                    children: [
                      ValueListenableBuilder(
                        valueListenable: selectedIndexOfBottomNavigationBar,
                        builder:
                            (
                              BuildContext context,
                              Object? value,
                              Widget? child,
                            ) {
                              return PageView(
                                physics: const NeverScrollableScrollPhysics(),
                                controller: pageController,
                                onPageChanged: onItemTapped,
                                children: [
                                  BlocProvider<FetchBookingsCubit>(
                                    create: (BuildContext context) =>
                                        FetchBookingsCubit(),
                                    child: HomeScreen(
                                      scrollController: scrollControllerList[0],
                                      navigateToTab: navigateToTab,
                                    ),
                                  ),
                                  BookingScreen(
                                    scrollController: scrollControllerList[1],
                                  ),
                                  ServicesScreen(
                                    scrollController: scrollControllerList[2],
                                  ),
                                  ChatUsersScreen(
                                    scrollController: scrollControllerList[3],
                                  ),
                                ],
                              );
                            },
                      ),
                      IgnorePointer(
                        ignoring: !_showOverlay ? true : false,
                        child: newDrawer(UiUtils.customRequestCounter),
                      ),
                      SafeArea(
                        child: Align(
                          alignment: Alignment.bottomCenter,
                          child: Padding(
                            padding: const EdgeInsets.only(
                              bottom: 10,
                              left: 15,
                              right: 15,
                            ),
                            child: bottomBar(),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildGridView(int pageIndex) {
    // Calculate the starting index for the current page
    // 'pageIndex' represents the current page number (0 for the first page, 1 for the second page, etc.)
    final int startIndex = pageIndex * 9;
    // Calculate the ending index for the current page's sublist
    // 'startIndex + 9' would normally give the next 9 items, but we need to make sure it doesn't exceed the list length
    // .clamp ensures the 'endIndex' is within valid bounds (0 to gridItems.length)
    final int endIndex = (startIndex + 9).clamp(0, gridItems.length);
    final itemsForPage = gridItems.sublist(startIndex, endIndex);

    return Padding(
      padding: const EdgeInsets.all(10),
      child: GridView.builder(
        padding: EdgeInsets.zero,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate:
            const SliverGridDelegateWithFixedCrossAxisCountAndFixedHeight(
              crossAxisCount: 3,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              height: 80,
            ),
        shrinkWrap: true,
        itemCount: itemsForPage.length,
        itemBuilder: (context, index) {
          return _buildDraggableItem(startIndex + index);
        },
      ),
    );
  }

  Widget _buildDraggableItem(int index) {
    return LongPressDraggable<int>(
      data: index,
      key: ValueKey(gridItems[index].name),
      // Unique Key
      onDragUpdate: _handleAutoScroll,
      onDragStarted: () {
        setState(() {
          draggingIndex = index;
          _draggedItemSize = 60;
          isDragging = true;
        });
      },
      onDraggableCanceled: (_, __) {
        setState(() {
          isDragging = false;
          _draggedItemSize = 80;
          draggingIndex = -1;
        });
      },
      feedback: Material(
        color: Colors.transparent,
        child: _buildBox(gridItems[index], true),
      ),
      childWhenDragging: Opacity(
        opacity: 0.3,
        child: _buildBox(gridItems[index], false),
      ),
      child: DragTarget<int>(
        onWillAcceptWithDetails: (details) => true,
        onAcceptWithDetails: (details) {
          final draggedIndex = details.data;
          setState(() {
            final draggedItem = gridItems[draggedIndex];
            gridItems.removeAt(draggedIndex);
            gridItems.insert(index, draggedItem);
            _savePositions();
          });
        },
        builder: (context, candidateData, rejectedData) {
          return Builder(
            builder: (BuildContext context) {
              return GestureDetector(
                onTap: () {
                  onClickOfDrawerItems(gridItems[index].name, context);
                },
                child: TweenAnimationBuilder<double>(
                  duration: const Duration(milliseconds: 200),
                  curve: Curves.easeOut,
                  tween: Tween<double>(
                    begin: _draggedItemSize,
                    end: _draggedItemSize,
                  ),
                  builder: (context, size, child) {
                    return SizedBox(width: size, height: size, child: child);
                  },
                  child: _buildBox(gridItems[index], false),
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> changePassword() async {
    Navigator.pushNamed(context, Routes.changePassword);
  }

  void onClickOfDrawerItems(String name, BuildContext context) {
    if (name == 'notifications') {
      _toggleOverlay();
      Navigator.of(context).pushNamed(Routes.notificationsScreen);
    } else if (name == 'promoCodeLbl') {
      _toggleOverlay();

      Navigator.of(context).pushNamed(Routes.promoCode);
    } else if (name == 'cashCollection') {
      _toggleOverlay();
      Navigator.of(context).pushNamed(Routes.cashCollection);
    } else if (name == 'settlementHistory') {
      _toggleOverlay();
      Navigator.of(context).pushNamed(Routes.settlementHistoryScreen);
    } else if (name == 'withdrawalRequest') {
      _toggleOverlay();
      Navigator.of(context).pushNamed(Routes.withdrawalRequests);
    } else if (name == 'bookingPaymentManagement') {
      _toggleOverlay();
      Navigator.of(context).pushNamed(Routes.bookingPaymentDataScreen);
    } else if (name == 'reviewsTitleLbl') {
      _toggleOverlay();
      Navigator.pushNamed(context, Routes.reviewsScreen);
    } else if (name == 'subscriptions') {
      _toggleOverlay();
      Navigator.of(context)
          .pushNamed(Routes.subscriptionScreen, arguments: {"from": "drawer"})
          .then((value) {
            if (value == 'subscription') {
              context.read<FetchHomeDataCubit>().getHomeData();
            }
          });
    } else if (name == 'languageLbl') {
      _toggleOverlay();
      // Navigate to language selection screen
      Navigator.pushNamed(
        context,
        Routes.languageSelectionScreen,
        arguments: {"title": "selectLanguage"},
      );
    } else if (name == 'darkThemeLbl') {
      context.read<AppThemeCubit>().toggleTheme();
    } else if (name == 'changePassword') {
      _toggleOverlay();
      changePassword();
    } else if (name == 'contactUs') {
      _toggleOverlay();
      Navigator.pushNamed(context, Routes.contactUsRoute);
    } else if (name == 'aboutUs') {
      _toggleOverlay();
      Navigator.pushNamed(
        context,
        Routes.appSettings,
        arguments: {'title': 'aboutUs'},
      );
    } else if (name == 'privacyPolicyLbl') {
      _toggleOverlay();
      Navigator.pushNamed(
        context,
        Routes.appSettings,
        arguments: {'title': 'privacyPolicyLbl'},
      );
    } else if (name == 'termsConditionLbl') {
      _toggleOverlay();
      Navigator.pushNamed(
        context,
        Routes.appSettings,
        arguments: {'title': 'termsConditionLbl'},
      );
    } else if (name == 'shareApp') {
      UiUtils.share(
        context: context,
        context.read<FetchSystemSettingsCubit>().getPlayStoreURL,
      );
    } else if (name == 'logoutLbl') {
      _toggleOverlay();
      UiUtils.showAnimatedDialog(
        context: context,
        child: const LogoutAccountDialog(),
      );
    } else if (name == 'deleteAccount') {
      _toggleOverlay();
      UiUtils.showAnimatedDialog(
        context: context,
        child: MultiBlocProvider(
          providers: [
            BlocProvider<DeleteProviderAccountCubit>(
              create: (BuildContext context) => DeleteProviderAccountCubit(),
            ),
            BlocProvider<SignInCubit>(
              create: (BuildContext context) => SignInCubit(),
            ),
          ],
          child: DeleteProviderAccountDialog(),
        ),
      );
    } else if (name == 'support') {
      _toggleOverlay();
      Navigator.pushNamed(
        context,
        Routes.chatMessages,
        arguments: {
          "chatUser": ChatUser(
            id: "-",
            name: "customerSupport".translate(context: context),
            receiverType: "0",
            unReadChats: 0,
            bookingId: "-1",
            senderId:
                context.read<ProviderDetailsCubit>().providerDetails.user?.id ??
                "0",
          ),
        },
      );
    }
  }

  Widget _buildBox(({String name, String icon}) gridItem, bool isDragging) {
    return CustomContainer(
      padding: isDragging ? const EdgeInsets.all(20) : null,
      alignment: Alignment.center,
      color: isDragging
          ? context.colorScheme.secondaryColor.withValues(alpha: 0.7)
          : context.colorScheme.secondaryColor,
      borderRadius: UiUtils.borderRadiusOf10,
      border: Border.all(
        color: context.colorScheme.lightGreyColor.withValues(alpha: 0.2),
        width: 0.5,
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          CustomSvgPicture(
            svgImage: gridItem.icon,
            color: context.colorScheme.accentColor,
            width: 20,
            height: 20,
          ),
          CustomText(
            gridItem.name == 'darkThemeLbl'
                ? (context.watch<AppThemeCubit>().state.appTheme ==
                          AppTheme.dark
                      ? 'lightThemeLbl'.translate(context: context)
                      : 'darkThemeLbl'.translate(context: context))
                : gridItem.name.translate(context: context),
            color: context.colorScheme.blackColor,
            maxLines: 2,
            fontSize: 10,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget newDrawer(String totalJobRequests) {
    return SafeArea(
      top: false,
      child: GestureDetector(
        onTap: () {
          _toggleOverlay();
        },
        child: Stack(
          children: [
            if (_showOverlay)
              AnimatedContainer(
                duration: const Duration(milliseconds: 1000),
                child: BackdropFilter(
                  filter: ImageFilter.blur(sigmaX: 5, sigmaY: 5),
                  child: CustomContainer(
                    color: context.colorScheme.blackColor.withValues(alpha: .2),
                  ),
                ),
              ),
            AnimatedPositioned(
              duration: const Duration(milliseconds: 500),
              left: 20,
              right: 20,
              bottom: _showOverlay ? 80 : 20,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 500),
                curve: Curves.easeInOut,
                width: _showOverlay
                    ? MediaQuery.of(context).size.width * 0.9
                    : 0,
                height: _showOverlay ? null : 0,
                decoration: BoxDecoration(
                  color: context.colorScheme.primaryColor,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    const BoxShadow(color: Colors.black26, blurRadius: 8),
                  ],
                ),
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height,
                ),
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CustomContainer(
                        padding: const EdgeInsets.only(
                          top: 15,
                          left: 15,
                          right: 15,
                        ),
                        child: Row(
                          children: [
                            CustomContainer(
                              width: 50,
                              height: 50,
                              borderRadius: UiUtils.borderRadiusOf10,
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(
                                  UiUtils.borderRadiusOf10,
                                ),
                                child: doUserHasProfileImage()
                                    ? CustomCachedNetworkImage(
                                        imageUrl: getProfileImage(),
                                      )
                                    : const CustomSvgPicture(
                                        svgImage: AppAssets.drProfile,
                                      ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  CustomText(
                                    _getUserName(),
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.blackColor,
                                    fontWeight: FontWeight.w700,
                                  ),
                                  CustomText(
                                    _getEmail(),
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.blackColor,
                                    fontSize: 14.0,
                                    fontWeight: FontWeight.w400,
                                    maxLines: 2,
                                  ),
                                ],
                              ),
                            ),
                            CustomSvgPicture(
                              svgImage: AppAssets.edit,
                              color: context.colorScheme.accentColor,
                              height: 20,
                              width: 20,
                              onTap: () {
                                _toggleOverlay();
                                Navigator.pushNamed(
                                  context,
                                  Routes.registration,
                                  arguments: {'isEditing': true},
                                );
                              },
                            ),
                          ],
                        ),
                      ),
                      const Divider(
                        thickness: 0.2,
                        indent: 50.0,
                        endIndent: 30.0,
                      ),
                      CustomJobRequestSection(jobRequests: totalJobRequests),
                      SizedBox(
                        height: 290,
                        child: PageView.builder(
                          controller: pagecontrollerforGridView,
                          itemCount: pagesCount,
                          onPageChanged: (index) {
                            setState(() {
                              _currentPage = index;
                            });
                          },
                          itemBuilder: (context, pageIndex) {
                            return _buildGridView(pageIndex);
                          },
                        ),
                      ),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: List.generate(
                          pagesCount,
                          (index) => AnimatedContainer(
                            duration: const Duration(milliseconds: 300),
                            margin: const EdgeInsets.all(4),
                            width: _currentPage == index ? 12 : 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: _currentPage == index
                                  ? context.colorScheme.accentColor
                                  : context.colorScheme.lightGreyColor,
                              borderRadius: BorderRadius.circular(4),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void navigateToTab(int index) {
    selectedIndexOfBottomNavigationBar.value = index;
    pageController.jumpToPage(index);
  }

  void onItemTapped(int index) {
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      selectedIndexOfBottomNavigationBar.value = index;
    });
    if (_showOverlay) {
      _toggleOverlay();
    }

    pageController.jumpToPage(selectedIndexOfBottomNavigationBar.value);
  }

  Widget buildGroupTitle(String titleTxt) {
    return CustomContainer(
      padding: const EdgeInsetsDirectional.only(start: 10, top: 10, bottom: 10),
      child: CustomText(
        titleTxt,
        fontSize: 14,
        color: Theme.of(context).colorScheme.blackColor,
      ),
    );
  }

  Widget buildDrawerItem({
    required String? icon,
    required String title,
    required VoidCallback onItemTap,
    bool? isSwitch,
    bool? isSubscription,
  }) {
    return ListTile(
      visualDensity: const VisualDensity(vertical: -4),
      //change -4 to required one TO INCREASE SPACE BTWN ListTiles
      leading: CustomSvgPicture(
        svgImage: icon!,
        color: (title == 'logoutLbl'.translate(context: context))
            ? AppColors.redColor
            : Theme.of(context).colorScheme.accentColor,
        height: 20,
        width: 20,
      ),
      trailing: isSwitch ?? false
          ? CustomSwitch(
              thumbColor:
                  context.watch<AppThemeCubit>().state.appTheme == AppTheme.dark
                  ? Colors.green
                  : Colors.red,
              value:
                  context.watch<AppThemeCubit>().state.appTheme ==
                  AppTheme.dark,
              onChanged: (bool val) {
                onItemTap.call();
              },
            )
          : isSubscription ?? false
          ? CustomContainer(
              padding: const EdgeInsets.all(5),
              color:
                  context
                          .read<ProviderDetailsCubit>()
                          .providerDetails
                          .subscriptionInformation
                          ?.isSubscriptionActive ==
                      "active"
                  ? Colors.green.withValues(alpha: 0.3)
                  : Colors.red.withValues(alpha: 0.3),
              borderRadius: 5,
              child: CustomText(
                context
                    .read<ProviderDetailsCubit>()
                    .providerDetails
                    .subscriptionInformation!
                    .translatedStatus!
                    .toString(),
                fontSize: 14,
                color: Theme.of(context).colorScheme.blackColor,
              ),
            )
          : const SizedBox(),
      title: CustomText(
        title,
        fontWeight: (icon != '') ? FontWeight.w500 : FontWeight.normal,
        fontSize: 16.0,
        color: (title == 'logoutLbl'.translate(context: context))
            ? AppColors.redColor
            : Theme.of(context).colorScheme.blackColor,
      ),
      selectedTileColor: Theme.of(context).colorScheme.lightGreyColor,
      onTap: onItemTap,
      hoverColor: Theme.of(context).colorScheme.lightGreyColor,
      horizontalTitleGap: 0,
    );
  }

  void bottomState(int index) {
    // Dismiss keyboard when navigating between tabs
    FocusManager.instance.primaryFocus?.unfocus();
    previousSelectedIndex = selectedIndexOfBottomNavigationBar.value;

    setState(() {
      selectedIndexOfBottomNavigationBar.value = index;

      pageController.jumpToPage(index);
    });

    try {
      if (previousSelectedIndex == selectedIndexOfBottomNavigationBar.value &&
          scrollControllerList[selectedIndexOfBottomNavigationBar.value]
              .positions
              .isNotEmpty) {
        scrollControllerList[selectedIndexOfBottomNavigationBar.value]
            .animateTo(
              0,
              duration: const Duration(milliseconds: 500),
              curve: Curves.easeInOut,
            );
      }
    } catch (_) {}
  }

  static Widget bottomBarTextButton(
    BuildContext context,
    int selectedIndex,
    int index,
    void Function() onPressed,
    String? imgName,
  ) {
    return InkWell(
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            CustomSvgPicture(
              svgImage: imgName!,
              color: selectedIndex != index
                  ? context.colorScheme.lightGreyColor
                  : context.colorScheme.accentColor,
            ),
            selectedIndex == index
                ? const SizedBox(height: 2)
                : const SizedBox(),
          ],
        ),
      ),
      onTap: () => onPressed(),
    );
  }

  Widget bottomBar() {
    return CustomContainer(
      height: kBottomNavigationBarHeight,
      // padding: const EdgeInsets.symmetric(horizontal: 15),
      color: context.colorScheme.secondaryColor,
      borderRadius: UiUtils.borderRadiusOf10,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          bottomBarTextButton(
            context,
            selectedIndexOfBottomNavigationBar.value,
            0,
            () {
              bottomState(0);
            },
            AppAssets.home,
          ),
          bottomBarTextButton(
            context,
            selectedIndexOfBottomNavigationBar.value,
            1,
            () {
              bottomState(1);
            },
            AppAssets.booking,
          ),

          // Placeholder for FAB in the middle
          FloatingActionButton.small(
            onPressed: _toggleOverlay,
            backgroundColor: context.colorScheme.accentColor,
            elevation: 0,
            shape: const CircleBorder(),
            child: Icon(
              _showOverlay
                  ? Icons.keyboard_arrow_down
                  : Icons.keyboard_arrow_up,
              size: 30,
              color: AppColors.whiteColors,
            ),
          ),

          // Right side buttons
          bottomBarTextButton(
            context,
            selectedIndexOfBottomNavigationBar.value,
            2,
            () {
              bottomState(2);
            },
            AppAssets.services,
          ),
          bottomBarTextButton(
            context,
            selectedIndexOfBottomNavigationBar.value,
            3,
            () {
              bottomState(3);
            },
            AppAssets.chat,
          ),
        ],
      ),
    );
  }
}
