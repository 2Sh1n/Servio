import 'package:edemand_partner/app/generalImports.dart';
import 'package:edemand_partner/ui/widgets/customTooltipContainer.dart';

import 'package:flutter/material.dart';

class ChatUsersScreen extends StatefulWidget {
  const ChatUsersScreen({super.key, required this.scrollController});
  final ScrollController scrollController;

  @override
  _ChatUsersScreenState createState() => _ChatUsersScreenState();
}

class _ChatUsersScreenState extends State<ChatUsersScreen>
    with AutomaticKeepAliveClientMixin, TickerProviderStateMixin {
  late final AnimationController _animationController = AnimationController(
    vsync: this,
  );
  late Animation<double> fontAnimation = Tween<double>(begin: 22.0, end: 20.0)
      .animate(
        CurvedAnimation(
          parent: _animationController,
          curve: Curves.easeInOut,
          reverseCurve: Curves.easeInOut,
        ),
      );
  late Animation<double> fontAnimation2 = Tween<double>(begin: 16.0, end: 8.0)
      .animate(
        CurvedAnimation(
          parent: _animationController,
          curve: Curves.easeInOut,
          reverseCurve: Curves.easeInOut,
        ),
      );

  void scrollListener() {
    _animationController.value = UiUtils.inRange(
      currentValue: widget.scrollController.offset,
      minValue: widget.scrollController.position.minScrollExtent,
      maxValue: widget.scrollController.position.maxScrollExtent,
      newMaxValue: 1.0,
      newMinValue: 0.0,
    );
  }

  @override
  void initState() {
    Future.delayed(Duration.zero, () {
      fetchChatUsers();
    });
    widget.scrollController.addListener(scrollListener);
    super.initState();
  }

  @override
  void dispose() {
    widget.scrollController.removeListener(scrollListener);
    widget.scrollController.dispose();
    super.dispose();
  }

  void fetchChatUsers() {
    context.read<ChatUsersCubit>().fetchChatUsers();
  }

  Widget _buildShimmerLoader() {
    return LayoutBuilder(
      builder: (context, boxConstraints) {
        return SizedBox(
          height: double.maxFinite,
          child: ListView.builder(
            padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
            physics: const NeverScrollableScrollPhysics(),
            itemCount: 10,
            itemBuilder: (context, index) {
              return _buildOneChatUserShimmerLoader();
            },
          ),
        );
      },
    );
  }

  Widget _buildOneChatUserShimmerLoader() {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 8),
      child: ShimmerLoadingContainer(
        child: CustomShimmerContainer(height: 80, borderRadius: 0),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: UiUtils.getSystemUiOverlayStyle(context: context),
      child: SafeArea(
        child: Scaffold(
          backgroundColor: Theme.of(context).colorScheme.primaryColor,
          body: NestedScrollView(
            controller: widget.scrollController,
            headerSliverBuilder: (context, _) {
              return [
                SliverPersistentHeader(
                  delegate: AppBarPersistentHeaderDelegate(
                    child: Center(
                      child: AnimatedBuilder(
                        animation: _animationController,
                        builder: (context, _) {
                          return Column(
                            mainAxisAlignment: MainAxisAlignment.spaceAround,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  const SizedBox(width: 35),
                                  Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      CustomText(
                                        'chats'.translate(context: context),
                                        fontSize: fontAnimation.value,
                                        fontWeight: FontWeight.bold,
                                        color: Theme.of(
                                          context,
                                        ).colorScheme.blackColor,
                                      ),
                                      context.watch<ChatUsersCubit>().state
                                              is ChatUsersFetchSuccess
                                          ? HeadingAmountAnimation(
                                              key: ValueKey(
                                                (context
                                                            .read<
                                                              ChatUsersCubit
                                                            >()
                                                            .state
                                                        as ChatUsersFetchSuccess)
                                                    .totalOffset,
                                              ),
                                              text:
                                                  '${(context.read<ChatUsersCubit>().state as ChatUsersFetchSuccess).totalOffset.toString()} ${'chats'.translate(context: context)}',
                                              textStyle: TextStyle(
                                                color: context
                                                    .colorScheme
                                                    .lightGreyColor,
                                                fontWeight: FontWeight.w400,
                                                fontSize: fontAnimation2.value,
                                              ),
                                            )
                                          : SizedBox(
                                              width:
                                                  MediaQuery.of(
                                                    context,
                                                  ).size.width *
                                                  0.7,
                                              height: fontAnimation2.value,
                                            ),
                                    ],
                                  ),
                                  Padding(
                                    padding: const EdgeInsetsDirectional.only(
                                      end: 15,
                                    ),
                                    child: CustomToolTip(
                                      toolTipMessage: "blockedUsers".translate(
                                        context: context,
                                      ),
                                      child: CustomInkWellContainer(
                                        onTap: () {
                                          Navigator.pushNamed(
                                            context,
                                            Routes.blockUserScreen,
                                          );
                                        },
                                        child: CustomSvgPicture(
                                          height: 20,
                                          width: 20,
                                          svgImage: AppAssets.drBlockedUsers,
                                          color: context.colorScheme.blackColor,
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const CustomShadowLineForDevider(
                                direction: ShadowDirection.centerToSides,
                              ),
                            ],
                          );
                        },
                      ),
                    ),
                  ),
                  pinned: true,
                ),
              ];
            },
            body: NotificationListener<ScrollNotification>(
              onNotification: (ScrollNotification notification) {
                if (notification is ScrollEndNotification &&
                    notification.metrics.extentAfter == 0) {
                  if (mounted && context.read<ChatUsersCubit>().hasMore()) {
                    context.read<ChatUsersCubit>().fetchMoreChatUsers();
                  }
                }
                return false;
              },
              child: BlocBuilder<ChatUsersCubit, ChatUsersState>(
                builder: (context, state) {
                  if (state is ChatUsersFetchSuccess) {
                    return CustomRefreshIndicator(
                      displacment: 12,
                      onRefresh: () {
                        fetchChatUsers();
                      },
                      child: state.chatUsers.isEmpty
                          ? SizedBox(
                              height: double.maxFinite,
                              width: double.maxFinite,
                              child: SingleChildScrollView(
                                physics: const AlwaysScrollableScrollPhysics(),
                                child: SizedBox(
                                  height:
                                      MediaQuery.of(context).size.height * 0.7,
                                  child: Center(
                                    child: NoDataContainer(
                                      titleKey: "noChatsFound".translate(
                                        context: context,
                                      ),
                                      subTitleKey: 'noChatsFoundSubTitle'
                                          .translate(context: context),
                                    ),
                                  ),
                                ),
                              ),
                            )
                          : SizedBox(
                              height: double.maxFinite,
                              width: double.maxFinite,
                              child: SingleChildScrollView(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 15,
                                  vertical: 10,
                                ),
                                physics: const AlwaysScrollableScrollPhysics(),
                                child: Column(
                                  children: [
                                    ...List.generate(state.chatUsers.length, (
                                      index,
                                    ) {
                                      final currentChatUser =
                                          state.chatUsers[index];

                                      return ChatUserItemWidget(
                                        chatUser: currentChatUser.copyWith(
                                          receiverType: "2",
                                          unReadChats: 0,
                                          id: state.chatUsers[index].id,
                                          bookingId: state
                                              .chatUsers[index]
                                              .bookingId
                                              .toString(),
                                          bookingStatus: state
                                              .chatUsers[index]
                                              .bookingTranslatedStatus
                                              .toString(),
                                          name: state.chatUsers[index].name
                                              .toString(),
                                          profile:
                                              state.chatUsers[index].profile,
                                          senderId:
                                              context
                                                  .read<ProviderDetailsCubit>()
                                                  .providerDetails
                                                  .user
                                                  ?.id ??
                                              "0",
                                        ),
                                      );
                                    }),
                                    if (state.moreChatUserFetchProgress)
                                      _buildOneChatUserShimmerLoader(),
                                    if (state.moreChatUserFetchError &&
                                        !state.moreChatUserFetchProgress)
                                      CustomLoadingMoreContainer(
                                        isError: true,
                                        onErrorButtonPressed: () {
                                          context
                                              .read<ChatUsersCubit>()
                                              .fetchMoreChatUsers();
                                        },
                                      ),
                                    const SizedBox(height: 80),
                                  ],
                                ),
                              ),
                            ),
                    );
                  }
                  if (state is ChatUsersFetchFailure) {
                    return Center(
                      child: ErrorContainer(
                        errorMessage: state.errorMessage,
                        onTapRetry: () {
                          fetchChatUsers();
                        },
                      ),
                    );
                  }
                  return _buildShimmerLoader();
                },
              ),
            ),
          ),
        ),
      ),
    );
  }

  @override
  bool get wantKeepAlive => true;
}
