import 'package:edemand_partner/app/generalImports.dart';
import 'package:edemand_partner/cubits/chat/unblockUserCubit.dart';
import 'package:edemand_partner/cubits/chat/deleteChatCubit.dart';
import 'package:edemand_partner/ui/widgets/bottomSheets/layouts/reportReasonBottomSheet.dart';
import 'package:edemand_partner/ui/widgets/customWarningBottomSheet.dart';

import 'package:flutter/material.dart';

class ChatMessagesScreen extends StatefulWidget {
  final ChatUser chatUser;

  const ChatMessagesScreen({super.key, required this.chatUser});

  @override
  State<ChatMessagesScreen> createState() => _ChatMessagesScreenState();

  static Route route(RouteSettings routeSettings) {
    final Map<String, dynamic> arguments =
        routeSettings.arguments as Map<String, dynamic>;

    return CupertinoPageRoute(
      builder: (_) => ChatMessagesScreen(
        chatUser: arguments["chatUser"] ?? ChatUser.fromJson({}),
      ),
    );
  }
}

class _ChatMessagesScreenState extends State<ChatMessagesScreen> {
  final _chatMessageSendTextController = TextEditingController();

  late final ScrollController _scrollController = ScrollController()
    ..addListener(_notificationsScrollListener);

  //to check sent or received messages
  String senderId = '-1';

  void _notificationsScrollListener() {
    if (context.read<ChatMessagesCubit>().hasMore()) {
      final nextPageTrigger = 0.7 * _scrollController.position.maxScrollExtent;

      if (_scrollController.position.pixels > nextPageTrigger) {
        if (mounted) {
          context.read<ChatMessagesCubit>().fetchMoreChatMessages(
            bookingId: widget.chatUser.bookingId.toString(),
            type: widget.chatUser.receiverType,
            customerId: widget.chatUser.id.toString(),
          );
        }
      }
    }
  }

  @override
  void initState() {
    Routes.currentRoute = Routes.chatMessages;

    Future.delayed(Duration.zero, () {
      fetchChatMessages();
      senderId = widget.chatUser.senderId ?? "0";
    });
    //register user id with which the current user is talking with
    ChatNotificationsUtils.currentChattingUserHashCode =
        widget.chatUser.hashCode;
    super.initState();
  }

  @override
  void dispose() {
    _scrollController.removeListener(_notificationsScrollListener);
    _scrollController.dispose();
    _chatMessageSendTextController.dispose();

    super.dispose();
  }

  void fetchChatMessages() {
    context.read<ChatMessagesCubit>().fetchChatMessages(
      bookingId: widget.chatUser.bookingId.toString(),
      type: widget.chatUser.receiverType.toString(),
      chatUsersCubitArgument: context.read<ChatUsersCubit>(),
      customerId: widget.chatUser.id,
    );
  }

  Widget _buildShimmerLoader() {
    return LayoutBuilder(
      builder: (context, boxConstraints) {
        return SizedBox(
          height: double.maxFinite,
          child: SingleChildScrollView(
            child: Column(
              children: List.generate(
                15,
                (index) => _buildOneChatShimmerLoader(boxConstraints),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildOneChatShimmerLoader(BoxConstraints boxConstraints) {
    final bool isStart = Random().nextBool();
    return Align(
      alignment: isStart
          ? AlignmentDirectional.centerStart
          : AlignmentDirectional.centerEnd,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: ShimmerLoadingContainer(
          child: CustomShimmerContainer(
            height: 30,
            width: boxConstraints.maxWidth * 0.8,
            borderRadius: 12,
          ),
        ),
      ),
    );
  }

  Widget _buildDateLabel({required DateTime date}) {
    return CustomText(
      date.isToday()
          ? "today".translate(context: context)
          : date.isYesterday()
          ? "yesterday".translate(context: context)
          : date.toString().split(" ").first.formatDate(),
      color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.5),
      fontSize: 12,
    );
  }

  Widget _loadingMoreChatsWidget() {
    return CustomContainer(
      padding: const EdgeInsets.symmetric(vertical: 5, horizontal: 10),
      borderRadius: 12,
      color: Theme.of(context).colorScheme.secondaryColor,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: 12,
            height: 12,
            child: CustomCircularProgressIndicator(
              color: Theme.of(context).colorScheme.accentColor,
            ),
          ),
          const SizedBox(width: 10),
          Flexible(
            child: CustomText(
              "loadingMoreChats".translate(context: context),
              maxLines: 1,
              fontSize: 12,
              color: Theme.of(context).colorScheme.lightGreyColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _loadingMoreErrorWidget({required Function() onTapRetry}) {
    return GestureDetector(
      onTap: onTapRetry,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 5, horizontal: 10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          color: Theme.of(context).colorScheme.secondaryColor,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.refresh,
              size: 16,
              color: Theme.of(context).colorScheme.accentColor,
            ),
            const SizedBox(width: 10),
            Flexible(
              child: CustomText(
                "errorLoadingMoreRetry".translate(context: context),
                maxLines: 1,
                fontSize: 12,
                color: AppColors.redColor,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> handleClick(int item) async {
    switch (item) {
      case 0:
        // Check if user is already blocked
        final currentState = context.read<ChatMessagesCubit>().state;
        if (currentState is ChatMessagesFetchSuccess) {
          if (currentState.isBlockedByProvider) {
            // Show unblock confirmation
            UiUtils.showModelBottomSheets(
              context: context,
              child: BlocProvider<UnblockUserCubit>(
                create: (context) => UnblockUserCubit(ChatRepository()),
                child: BlocConsumer<UnblockUserCubit, UnblockUserState>(
                  listener: (context, state) {
                    if (state is UnblockUserSuccess) {
                      context.read<ChatMessagesCubit>().updateBlockStatus(
                        isBlockedByUser: currentState.isBlockedByUser,
                        isBlockedByProvider: false,
                      );
                      UiUtils.showMessage(
                        context,
                        state.message,
                        ToastificationType.success,
                      );
                      Navigator.pop(context);
                    }
                    if (state is UnblockUserFailure) {
                      UiUtils.showMessage(
                        context,
                        state.errorMessage,
                        ToastificationType.error,
                      );
                      Navigator.pop(context);
                    }
                  },
                  builder: (context, state) {
                    return CustomWarningBottomSheet(
                      closeText: 'close'.translate(context: context),
                      conformButtonColor: state is UnblockUserInProgress
                          ? context.colorScheme.lightGreyColor
                          : null,
                      conformText: 'unblock'.translate(context: context),
                      onTapCloseText: () {
                        Navigator.pop(context);
                      },
                      detailsWarningMessage: 'unblockUserWarning'.translate(
                        context: context,
                      ),
                      onTapConformText: () async {
                        if (state is UnblockUserInProgress) {
                          return;
                        }
                        await context.read<UnblockUserCubit>().unblockUser(
                          widget.chatUser.id.toString(),
                        );
                      },
                    );
                  },
                ),
              ),
            );
          } else {
            // Show report reason bottom sheet
            UiUtils.showModelBottomSheets(
              context: context,
              child: ReportReasonBottomSheet(
                userId: widget.chatUser.id.toString(),
                isBlocked: currentState.isBlockedByUser,
                onBlockStatusChanged: (isBlocked) {
                  context.read<ChatMessagesCubit>().updateBlockStatus(
                    isBlockedByUser: currentState.isBlockedByUser,
                    isBlockedByProvider: isBlocked,
                  );
                },
              ),
            );
          }
        }
        break;
      case 1:
        UiUtils.showModelBottomSheets(
          context: context,
          child: BlocProvider<DeleteChatCubit>(
            create: (context) => DeleteChatCubit(ChatRepository()),
            child: BlocConsumer<DeleteChatCubit, DeleteChatState>(
              listener: (context, state) {
                if (state is DeleteChatSuccess) {
                  // Remove user from chat list
                  context.read<ChatUsersCubit>().removeUserFromList(
                    widget.chatUser,
                  );
                  UiUtils.showMessage(
                    context,
                    'chatDeletedSuccessfully',
                    ToastificationType.success,
                  );
                  Navigator.pop(context); // Pop the bottom sheet
                  Navigator.pop(context); // Pop back to chat list
                }
                if (state is DeleteChatFailure) {
                  UiUtils.showMessage(
                    context,
                    state.errorMessage,
                    ToastificationType.error,
                  );
                  Navigator.pop(context);
                }
              },
              builder: (context, state) {
                return CustomWarningBottomSheet(
                  closeText: 'close'.translate(context: context),
                  conformButtonColor: state is DeleteChatInProgress
                      ? context.colorScheme.lightGreyColor
                      : null,
                  conformText: 'deleteChat'.translate(context: context),
                  onTapCloseText: () {
                    Navigator.pop(context);
                  },
                  detailsWarningMessage: 'deleteChatWarning'.translate(
                    context: context,
                  ),
                  onTapConformText: () async {
                    if (state is DeleteChatInProgress) {
                      return;
                    }
                    await context.read<DeleteChatCubit>().deleteChat(
                      widget.chatUser.id.toString(),
                      widget.chatUser.bookingId.toString(),
                    );
                  },
                );
              },
            ),
          ),
        );
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final ChatMessagesState state = context.read<ChatMessagesCubit>().state;
    final bool isChatMessagesEmpty =
        state is ChatMessagesFetchSuccess && state.chatMessages.isEmpty;
    final size = MediaQuery.of(context).size;
    return PopScope(
      canPop: true,
      onPopInvokedWithResult: (didPop, _) {
        //removing currently talking user's id
        ChatNotificationsUtils.currentChattingUserHashCode = null;

        //clearing current route when going back to make the onTap of notification routing properly work
        Routes.currentRoute = '';
      },
      child: Scaffold(
        appBar: AppBar(
          surfaceTintColor: Theme.of(context).colorScheme.primaryColor,
          automaticallyImplyLeading: false,
          toolbarHeight: size.height * .08,
          elevation: 1,
          titleSpacing: 2,
          backgroundColor: Theme.of(context).colorScheme.primaryColor,
          leading: const CustomBackArrow(),
          title: SizedBox(
            width: double.maxFinite,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.start,
              children: [
                //it means customer chatting with customer
                if (widget.chatUser.receiverType == "2") ...[
                  Expanded(
                    flex: 1,
                    child: CustomContainer(
                      clipBehavior: Clip.antiAlias,
                      height: 40,
                      width: 40,
                      borderRadius: UiUtils.borderRadiusOf8,
                      child:
                          widget.chatUser.avatar.trim().isEmpty ||
                              widget.chatUser.avatar.toLowerCase() == "null"
                          ? const CustomSvgPicture(svgImage: AppAssets.dProfile)
                          : CustomCachedNetworkImage(
                              imageUrl: widget.chatUser.avatar,
                              fit: BoxFit.fill,
                              height: 40,
                              width: 40,
                            ),
                    ),
                  ),
                  const SizedBox(width: 15),
                ],

                Expanded(
                  flex: 5,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CustomText(
                        widget.chatUser.userName.translate(context: context),
                        color: Theme.of(context).colorScheme.blackColor,
                        fontWeight: FontWeight.w600,
                        fontSize: 18.0,
                      ),
                      if (widget.chatUser.receiverType == "2") ...[
                        if (widget.chatUser.bookingId != "0") ...[
                          CustomText(
                            "${"bookingId".translate(context: context)}- ${widget.chatUser.bookingId}",
                            color: Theme.of(context).colorScheme.lightGreyColor,
                            fontSize: 14.0,
                          ),
                        ] else ...[
                          CustomText(
                            "preBookingEnquiry".translate(context: context),
                            color: Theme.of(context).colorScheme.lightGreyColor,
                            fontSize: 14.0,
                          ),
                        ],
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          actions: [
            if (widget.chatUser.receiverType != "0") ...[
              PopupMenuButton<int>(
                onSelected: (item) => handleClick(item),
                itemBuilder: (context) => [
                  if (widget.chatUser.bookingStatus?.toLowerCase() !=
                          "cancelled" &&
                      widget.chatUser.bookingStatus?.toLowerCase() !=
                          "completed") ...[
                    PopupMenuItem<int>(
                      value: 0,
                      child: BlocBuilder<ChatMessagesCubit, ChatMessagesState>(
                        builder: (context, state) {
                          if (state is ChatMessagesFetchSuccess) {
                            return CustomText(
                              state.isBlockedByProvider
                                  ? 'unblock'.translate(context: context)
                                  : 'block&Report'.translate(context: context),
                              color: Theme.of(context).colorScheme.accentColor,
                            );
                          }
                          return CustomText(
                            'block&Report'.translate(context: context),
                            color: Theme.of(context).colorScheme.accentColor,
                          );
                        },
                      ),
                    ),
                  ],
                  if (!isChatMessagesEmpty) ...[
                    PopupMenuItem<int>(
                      value: 1,
                      child: CustomText(
                        'deleteChat'.translate(context: context),
                        color: Theme.of(context).colorScheme.accentColor,
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ],
        ),
        bottomNavigationBar:
            (widget.chatUser.bookingStatus?.toLowerCase() != "cancelled" &&
                widget.chatUser.bookingStatus?.toLowerCase() != "completed")
            ? BlocBuilder<ChatMessagesCubit, ChatMessagesState>(
                builder: (context, state) {
                  if (state is ChatMessagesFetchSuccess) {
                    if (state.isBlockedByUser || state.isBlockedByProvider) {
                      return CustomContainer(
                        borderRadius: 12,
                        padding: const EdgeInsets.all(10),
                        margin: const EdgeInsets.symmetric(
                          vertical: 10,
                          horizontal: 15,
                        ),
                        color: context.colorScheme.accentColor.withValues(
                          alpha: 0.3,
                        ),
                        child: CustomText(
                          state.isBlockedByProvider
                              ? "youHaveBlockedThisUser".translate(
                                  context: context,
                                )
                              : "youHaveBeenBlockedByUser".translate(
                                  context: context,
                                ),
                          color: context.colorScheme.accentColor,
                          fontSize: 12,
                          textAlign: TextAlign.center,
                        ),
                      );
                    }
                    final generalSettings = context
                        .read<FetchSystemSettingsCubit>()
                        .generalSettings;
                    final bool showAttachmentButton =
                        (generalSettings.enableChatImageUpload == true) ||
                        (generalSettings.enableChatFileUpload == true);
                    return Container(
                      padding: EdgeInsets.only(
                        bottom: MediaQuery.of(context).viewInsets.bottom,
                      ),
                      color: Theme.of(context).colorScheme.secondaryColor,
                      child: ChatMessageSendingWidget(
                        showAttachmentButton: showAttachmentButton,
                        onMessageSend: () {
                          if (_chatMessageSendTextController.text
                              .trim()
                              .isNotEmpty) {
                            context.read<ChatMessagesCubit>().sendChatMessage(
                              context: context,
                              chatMessage: ChatMessage(
                                messageType: ChatMessageType.textMessage,
                                files: [],
                                message: _chatMessageSendTextController.text
                                    .trim(),
                                isLocallyStored: true,
                                id: DateTime.now().microsecondsSinceEpoch
                                    .toString(),
                                sendOrReceiveDateTime: DateTime.now(),
                                receiverId: widget.chatUser.providerId,
                                senderId: senderId,
                                senderDetails: SenderDetails.fromJson({}),
                              ),
                              receiverId: widget.chatUser.id.toString(),
                              chatUserCubit: context.read<ChatUsersCubit>(),
                              chattingWith: widget.chatUser,
                              bookingId: widget.chatUser.bookingId,
                            );
                            _chatMessageSendTextController.clear();
                          }
                        },
                        onAttachmentTap: () {
                          // Dismiss keyboard when attachment button is tapped
                          UiUtils.removeFocus();
                          UiUtils.showModelBottomSheets(
                            context: context,
                            child: AttachmentDialogWidget(
                              enableImageUpload:
                                  generalSettings.enableChatImageUpload == true,
                              enableFileUpload:
                                  generalSettings.enableChatFileUpload == true,
                              onCancel: () {
                                Navigator.pop(context);
                              },
                              onItemSelected: (selectedFilePaths, isImage) {
                                Navigator.pop(context);
                                context
                                    .read<ChatMessagesCubit>()
                                    .sendChatMessage(
                                      context: context,
                                      chatMessage: ChatMessage(
                                        messageType: isImage
                                            ? ChatMessageType.imageMessage
                                            : ChatMessageType.fileMessage,
                                        message: '',
                                        files: selectedFilePaths.map((e) {
                                          return MessageDocument(
                                            fileName: e.fileName,
                                            fileSize: e.fileSize,
                                            fileType: e.fileType,
                                            fileUrl: e.fileUrl,
                                          );
                                        }).toList(),
                                        isLocallyStored: true,
                                        id: DateTime.now()
                                            .microsecondsSinceEpoch
                                            .toString(),
                                        sendOrReceiveDateTime: DateTime.now(),
                                        receiverId: widget.chatUser.providerId,
                                        senderId: senderId,
                                        senderDetails: SenderDetails.fromJson(
                                          {},
                                        ),
                                      ),
                                      receiverId: widget.chatUser.id.toString(),
                                      chatUserCubit: context
                                          .read<ChatUsersCubit>(),
                                      chattingWith: widget.chatUser,
                                      bookingId: widget.chatUser.bookingId,
                                    );
                              },
                            ),
                          );
                          return;
                        },
                        textController: _chatMessageSendTextController,
                      ),
                    );
                  }
                  return const SizedBox.shrink();
                },
              )
            : CustomContainer(
                borderRadius: 12,
                padding: const EdgeInsets.all(10),
                margin: const EdgeInsets.symmetric(
                  vertical: 10,
                  horizontal: 15,
                ),
                color: Theme.of(
                  context,
                ).colorScheme.accentColor.withValues(alpha: 0.3),
                child: CustomText(
                  "youCantMessageToProviderMessage".translate(context: context),
                  color: Theme.of(context).colorScheme.accentColor,
                  fontSize: 12,
                  textAlign: TextAlign.center,
                ),
              ),
        body: Padding(
          padding: const EdgeInsetsDirectional.only(start: 8, end: 8),
          child: BlocBuilder<ChatMessagesCubit, ChatMessagesState>(
            builder: (context, state) {
              if (state is ChatMessagesFetchSuccess) {
                return Stack(
                  children: [
                    state.chatMessages.isEmpty
                        ? widget.chatUser.bookingStatus?.toLowerCase() !=
                                      "cancelled" &&
                                  widget.chatUser.bookingStatus
                                          ?.toLowerCase() !=
                                      "completed"
                              ? _emptyChatWidget(
                                  state.isBlockedByUser,
                                  state.isBlockedByProvider,
                                )
                              : Center(
                                  child: NoDataContainer(
                                    titleKey: "noChatHistoryFound".translate(
                                      context: context,
                                    ),
                                    subTitleKey: 'noChatHistoryFoundDescription'
                                        .translate(context: context),
                                  ),
                                )
                        : ListView.builder(
                            controller: _scrollController,
                            reverse: true,
                            physics: const AlwaysScrollableScrollPhysics(),
                            itemCount: state.chatMessages.length,
                            itemBuilder: (context, index) {
                              return Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  //if it's 1st item - reverse scroll so last then show date label
                                  //or if an item's date is not the same as next item, show date label
                                  if (index ==
                                          (state.chatMessages.length - 1) ||
                                      (!state
                                          .chatMessages[index]
                                          .sendOrReceiveDateTime
                                          .isSameAs(
                                            state
                                                .chatMessages[index ==
                                                        (state
                                                                .chatMessages
                                                                .length -
                                                            1)
                                                    ? (state
                                                              .chatMessages
                                                              .length -
                                                          1)
                                                    : index + 1]
                                                .sendOrReceiveDateTime,
                                          )))
                                    Padding(
                                      padding: const EdgeInsets.only(
                                        top: 10,
                                        bottom: 5,
                                      ),
                                      child: Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.center,
                                        children: [
                                          const Expanded(
                                            child: CustomShadowLineForDevider(
                                              direction:
                                                  ShadowDirection.rightToLeft,
                                            ),
                                          ),
                                          Padding(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 8.0,
                                            ),
                                            child: _buildDateLabel(
                                              date: state
                                                  .chatMessages[index]
                                                  .sendOrReceiveDateTime,
                                            ),
                                          ),
                                          const Expanded(
                                            child: CustomShadowLineForDevider(
                                              direction:
                                                  ShadowDirection.leftToRight,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  SingleChatMessageItem(
                                    key: ValueKey(state.chatMessages[index].id),
                                    senderId: senderId,
                                    //if it's 1st item, show time by default, if sender id and time is not same show time, otherwise don't show time
                                    showTime: index == 0
                                        ? true
                                        : !(UiUtils.formatTimeWithDateTime(
                                                    state
                                                        .chatMessages[index]
                                                        .sendOrReceiveDateTime,
                                                  ) ==
                                                  UiUtils.formatTimeWithDateTime(
                                                    state
                                                        .chatMessages[index - 1]
                                                        .sendOrReceiveDateTime,
                                                  ) &&
                                              state
                                                      .chatMessages[index]
                                                      .senderId ==
                                                  state
                                                      .chatMessages[index - 1]
                                                      .senderId),
                                    chatMessage: state.chatMessages[index],
                                    isLoading: state.loadingIds.contains(
                                      state.chatMessages[index].id,
                                    ),
                                    isError: state.errorIds.contains(
                                      state.chatMessages[index].id,
                                    ),
                                    onRetry: (ChatMessage chatMessage) {
                                      context
                                          .read<ChatMessagesCubit>()
                                          .sendChatMessage(
                                            context: context,
                                            chatMessage: chatMessage,
                                            receiverId: widget.chatUser.id
                                                .toString(),
                                            chatUserCubit: context
                                                .read<ChatUsersCubit>(),
                                            chattingWith: widget.chatUser,
                                            bookingId:
                                                widget.chatUser.bookingId,
                                            isRetry: true,
                                          );
                                    },
                                  ),
                                  if (index == 0) //padding to latest message
                                    const SizedBox(height: 10),
                                ],
                              );
                            },
                          ),
                    Align(
                      alignment: Alignment.topCenter,
                      child: Padding(
                        padding: const EdgeInsets.only(top: 25),
                        child: state.moreChatMessageFetchProgress
                            ? _loadingMoreChatsWidget()
                            : state.moreChatMessageFetchError
                            ? _loadingMoreErrorWidget(
                                onTapRetry: () {
                                  context
                                      .read<ChatMessagesCubit>()
                                      .fetchMoreChatMessages(
                                        bookingId: widget.chatUser.bookingId
                                            .toString(),
                                        type: widget.chatUser.receiverType
                                            .toString(),
                                        customerId: widget.chatUser.id
                                            .toString(),
                                      );
                                },
                              )
                            : const SizedBox.shrink(),
                      ),
                    ),
                  ],
                );
              }
              if (state is ChatMessagesFetchFailure) {
                return Center(
                  child: ErrorContainer(
                    errorMessage: state.errorMessage,
                    onTapRetry: () {
                      fetchChatMessages();
                    },
                  ),
                );
              }
              return _buildShimmerLoader();
            },
          ),
        ),
      ),
    );
  }

  Widget _emptyChatWidget(bool isBlockedByUser, bool isBlockedByProvider) {
    return Center(
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CustomContainer(
              borderRadius: 50,
              height: 80,
              width: 80,
              color: Theme.of(
                context,
              ).colorScheme.accentColor.withValues(alpha: 0.3),
              child: Icon(
                Icons.question_answer_rounded,
                color: Theme.of(context).colorScheme.accentColor,
                size: 45,
              ),
            ),
            const SizedBox(height: 10),
            if (!isBlockedByUser && !isBlockedByProvider) ...[
              CustomText(
                (widget.chatUser.receiverType == "2"
                        ? "customerChatEmptyScreenHeading"
                        : "adminChatEmptyScreenHeading")
                    .translate(context: context),
                fontWeight: FontWeight.w600,
                fontSize: 20,
                textAlign: TextAlign.center,
              ),
              if (UiUtils.chatPredefineMessagesForProvider.isNotEmpty &&
                  widget.chatUser.receiverType == "2") ...[
                const SizedBox(height: 20),
                ListView.separated(
                  shrinkWrap: true,
                  itemCount: UiUtils.chatPredefineMessagesForProvider.length,
                  itemBuilder: (context, index) {
                    return _buildPredefineMessageWidget(
                      message: UiUtils.chatPredefineMessagesForProvider[index],
                    );
                  },
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: 10),
                ),
              ],
              if (UiUtils.chatPredefineMessagesForAdmin.isNotEmpty &&
                  widget.chatUser.receiverType == "0") ...[
                const SizedBox(height: 20),
                ListView.separated(
                  shrinkWrap: true,
                  itemCount: UiUtils.chatPredefineMessagesForAdmin.length,
                  itemBuilder: (context, index) {
                    return _buildPredefineMessageWidget(
                      message: UiUtils.chatPredefineMessagesForAdmin[index],
                    );
                  },
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: 10),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildPredefineMessageWidget({required String message}) {
    return GestureDetector(
      onTap: () {
        context.read<ChatMessagesCubit>().sendChatMessage(
          context: context,
          chatMessage: ChatMessage(
            messageType: ChatMessageType.textMessage,
            files: [],
            message: message.translate(context: context),
            isLocallyStored: true,
            id: DateTime.now().microsecondsSinceEpoch.toString(),
            sendOrReceiveDateTime: DateTime.now(),
            receiverId: widget.chatUser.providerId,
            senderId: senderId,
            senderDetails: SenderDetails.fromJson({}),
          ),
          receiverId: widget.chatUser.id.toString(),
          chatUserCubit: context.read<ChatUsersCubit>(),
          chattingWith: widget.chatUser,
          bookingId: widget.chatUser.bookingId,
        );
      },
      child: Align(
        alignment: Alignment.center,
        child: CustomContainer(
          color: Theme.of(
            context,
          ).colorScheme.accentColor.withValues(alpha: 0.1),
          borderRadius: 12,
          padding: const EdgeInsets.all(10),
          child: CustomText(
            message.translate(context: context),
            color: Theme.of(context).colorScheme.accentColor,
            textAlign: TextAlign.center,
            fontSize: 12,
          ),
        ),
      ),
    );
  }
}
