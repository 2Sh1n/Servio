import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class MyRequestListScreen extends StatefulWidget {
  const MyRequestListScreen({required this.scrollController, final Key? key})
      : super(key: key);
  final ScrollController scrollController;

  @override
  State<MyRequestListScreen> createState() => _MyRequestListScreenState();
}

class _MyRequestListScreenState extends State<MyRequestListScreen> {
  @override
  void initState() {
    Future.delayed(Duration.zero).then((final value) {
      fetchMyRequestList();
    });

    widget.scrollController.addListener(fetchMoreRequestDetails);
    super.initState();
  }

  @override
  void dispose() {
    super.dispose();
  }

  void fetchMoreRequestDetails() {
    if (mounted && !context.read<MyRequestListCubit>().hasMoreReq()) {
      return;
    }
// nextPageTrigger will have a value equivalent to 70% of the list size.
    final nextPageTrigger =
        0.7 * widget.scrollController.position.maxScrollExtent;

// _scrollController fetches the next paginated data when the current position of the user on the screen has surpassed
    if (widget.scrollController.position.pixels > nextPageTrigger) {
      if (mounted) {
        context.read<MyRequestListCubit>().fetchMoreReq();
      }
    }
  }

  void fetchMyRequestList() {
    context.read<MyRequestListCubit>().fetchRequests();
  }

  @override
  Widget build(final BuildContext context) =>
      AnnotatedRegion<SystemUiOverlayStyle>(
        value: UiUtils.getSystemUiOverlayStyle(context: context),
        child: SafeArea(
          bottom: Platform.isIOS,
          top: false,
          child: Scaffold(
            appBar: UiUtils.getSimpleAppBar(
              context: context,
              title: 'myRequest'.translate(context: context),
              centerTitle: true,
              isLeadingIconEnable: false,
              fontWeight: FontWeight.w600,
              fontSize: 18,
              elevation: 0.5,
            ),
            body: BlocBuilder<SystemSettingCubit, SystemSettingState>(
              builder: (context, systemState) {
                // Show loading shimmer if system settings are not loaded yet
                if (systemState is! SystemSettingFetchSuccess) {
                  if (systemState is SystemSettingFetchFailure) {
                    return ErrorContainer(
                      errorMessage: systemState.errorMessage
                          .toString()
                          .translate(context: context),
                      onTapRetry: () async {
                        await context
                            .read<SystemSettingCubit>()
                            .getSystemSettings();
                        fetchMyRequestList();
                      },
                      showRetryButton: true,
                    );
                  }
                  return _getLoadingShimmerEffect();
                }

                return CustomRefreshIndicator(
                  displacment: 12,
                  onRefreshCallback: () {
                    fetchMyRequestList();
                  },
                  child: BlocBuilder<MyRequestListCubit, MyRequestListState>(
                      builder: (context, state) {
                    if (HiveRepository.getUserToken == '') {
                      return ErrorContainer(
                        errorMessage:
                            'youAreNotLoggedIn'.translate(context: context),
                        subErrorMessage: 'pleaseLoginToSeeYourRequests'
                            .translate(context: context),
                        showRetryButton: true,
                        buttonName: 'login'.translate(context: context),
                        onTapRetry: () {
                          //passing source as dialog instead of booking
                          //because there is no condition added for booking so using dialog,
                          Navigator.pushNamed(context, loginRoute,
                              arguments: {'source': 'dialog'});
                        },
                      );
                    }
                    if (state is MyRequestListInProgress) {
                      return _getLoadingShimmerEffect();
                    }
                    if (state is MyRequestListFailure) {
                      return ErrorContainer(
                        errorMessage:
                            state.errorMessage.translate(context: context),
                        onTapRetry: fetchMyRequestList,
                        showRetryButton: true,
                      );
                    }
                    if (state is MyRequestListSuccess) {
                      if (state.requestList.isEmpty) {
                        return Stack(
                          children: [
                            NoDataFoundWidget(
                              titleKey: 'noServiceRequested'
                                  .translate(context: context),
                              subtitleKey: 'noServiceRequestedSubTitle'
                                  .translate(context: context),
                              showRetryButton: true,
                              onTapRetry: fetchMyRequestList,
                            ),
                            Align(
                              alignment: Alignment.bottomCenter,
                              child: CustomContainer(
                                height: kBottomNavigationBarHeight,
                                width: context.screenWidth,
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 10, vertical: 5),
                                margin: EdgeInsetsDirectional.only(
                                    bottom: kBottomNavigationBarHeight
                                            .rh(context) +
                                        MediaQuery.of(context).padding.bottom +
                                        5),
                                child: CustomRoundedButton(
                                  onTap: () {
                                    Navigator.pushNamed(
                                      context,
                                      requestServiceFormScreen,
                                    );
                                  },
                                  buttonTitle: 'requestService'
                                      .translate(context: context),
                                  showBorder: false,
                                  widthPercentage: .95,
                                  backgroundColor:
                                      context.colorScheme.accentColor,
                                  textSize: 16,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        );
                      }
                      return Stack(
                        children: [
                          _getMyRequestList(
                              myRequestList: state.requestList,
                              isLoadingMoreError: state.isLoadingMoreError),
                          Align(
                            alignment: Alignment.bottomCenter,
                            child: CustomContainer(
                              height: kBottomNavigationBarHeight,
                              width: context.screenWidth,
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 10, vertical: 5),
                              margin: EdgeInsetsDirectional.only(
                                  bottom: kBottomNavigationBarHeight
                                          .rh(context) +
                                      MediaQuery.of(context).padding.bottom +
                                      5),
                              child: CustomRoundedButton(
                                onTap: () {
                                  Navigator.pushNamed(
                                    context,
                                    requestServiceFormScreen,
                                  );
                                },
                                buttonTitle: 'requestService'
                                    .translate(context: context),
                                showBorder: false,
                                widthPercentage: .95,
                                backgroundColor:
                                    context.colorScheme.accentColor,
                                textSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      );
                    }
                    return const CustomSizedBox();
                  }),
                );
              },
            ),
          ),
        ),
      );

  Widget _getMyRequestList({
    required final List<MyRequestListModel> myRequestList,
    required final bool isLoadingMoreError,
  }) {
    final isTablet = ResponsiveHelper.isTabletDevice(context);

    if (isTablet) {
      return GridView.builder(
        controller: widget.scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(
            15, 15, 15, kBottomNavigationBarHeight.rh(context) * 2),
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCountAndFixedHeight(
          crossAxisCount: 2,
          mainAxisSpacing: 1,
          crossAxisSpacing: 15,
          height: 170.rh(context),
        ),
        itemCount: myRequestList.length,
        itemBuilder: (final BuildContext context, int index) {
          if (index >= myRequestList.length) {
            return CustomContainer(
              margin: EdgeInsetsDirectional.only(
                  bottom:
                      kBottomNavigationBarHeight.rh(context) * 2.rh(context)),
              child: CustomLoadingMoreContainer(
                isError: isLoadingMoreError,
                onErrorButtonPressed: () {
                  fetchMoreRequestDetails();
                },
              ),
            );
          }
          return MyRequestCardContainer(
            myRequestDetails: myRequestList[index],
          );
        },
      );
    } else {
      return ListView.builder(
        controller: widget.scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
            15, 15, 15, kBottomNavigationBarHeight * 2),
        itemCount: myRequestList.length,
        itemBuilder: (final BuildContext context, int index) {
          if (index >= myRequestList.length) {
            return CustomContainer(
              margin: EdgeInsetsDirectional.only(
                  bottom:
                      kBottomNavigationBarHeight.rh(context) * 2.rh(context)),
              child: CustomLoadingMoreContainer(
                isError: isLoadingMoreError,
                onErrorButtonPressed: () {
                  fetchMoreRequestDetails();
                },
              ),
            );
          }
          return MyRequestCardContainer(
            myRequestDetails: myRequestList[index],
          );
        },
      );
    }
  }

  Widget _getLoadingShimmerEffect() {
    return ResponsiveHelper.isTabletDevice(context)
        ? GridView.builder(
            controller: widget.scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(15, 15, 15, 15),
            gridDelegate:
                const SliverGridDelegateWithFixedCrossAxisCountAndFixedHeight(
              crossAxisCount: 2,
              mainAxisSpacing: 15,
              crossAxisSpacing: 15,
              height: 170,
            ),
            itemCount: 10,
            itemBuilder: (final BuildContext context, int index) {
              return CustomShimmerLoadingContainer(
                borderRadius: 10,
                margin: const EdgeInsets.symmetric(vertical: 10),
                width: context.screenWidth * 0.9,
                height: context.screenHeight * 0.24,
              );
            },
          )
        : ListView.builder(
            controller: widget.scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(15, 15, 15, 15),
            itemCount: 10,
            itemBuilder: (final BuildContext context, int index) {
              return CustomShimmerLoadingContainer(
                borderRadius: 10,
                margin: const EdgeInsets.symmetric(vertical: 10),
                width: context.screenWidth * 0.9,
                height: context.screenHeight * 0.24,
              );
            },
          );
  }
}
