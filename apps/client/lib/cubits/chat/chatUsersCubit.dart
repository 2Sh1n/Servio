import 'package:e_demand/app/generalImports.dart';

abstract class ChatUsersState {}

class ChatUsersInitial extends ChatUsersState {}

class ChatUsersFetchInProgress extends ChatUsersState {}

class ChatUsersFetchFailure extends ChatUsersState {
  final String errorMessage;

  ChatUsersFetchFailure({required this.errorMessage});
}

class ChatUsersFetchSuccess extends ChatUsersState {
  final List<ChatUser> chatUsers;
  final int totalOffset;
  final bool moreChatUserFetchError;
  final bool moreChatUserFetchProgress;
  final int totalUnreadUsers;

  ChatUsersFetchSuccess({
    required this.chatUsers,
    required this.totalOffset,
    required this.moreChatUserFetchError,
    required this.moreChatUserFetchProgress,
    required this.totalUnreadUsers,
  });

  ChatUsersFetchSuccess copyWith({
    List<ChatUser>? newChatUsers,
    int? newTotalOffset,
    bool? newFetchMorechatUsersInProgress,
    bool? newFetchMorechatUsersError,
    int? newTotalUnreadUsers,
  }) {
    return ChatUsersFetchSuccess(
        chatUsers: newChatUsers ?? chatUsers,
        totalOffset: newTotalOffset ?? totalOffset,
        moreChatUserFetchProgress:
            newFetchMorechatUsersInProgress ?? moreChatUserFetchProgress,
        moreChatUserFetchError:
            newFetchMorechatUsersError ?? moreChatUserFetchError,
        totalUnreadUsers: newTotalUnreadUsers ?? totalUnreadUsers);
  }
}

class ChatUsersCubit extends Cubit<ChatUsersState> {
  final ChatRepository _chatRepository;

  ChatUsersCubit(this._chatRepository) : super(ChatUsersInitial());
  StreamSubscription<ChatNotificationData>? _streamSubscription;

  void registerNotificationListener() {
    if (_streamSubscription != null) {
      _streamSubscription!.cancel();
      _streamSubscription = null;
    }
    _streamSubscription = ChatNotificationsUtils
        .notificationStreamController.stream
        .listen((event) {
      makeUserFirstOrAddFirst(event.fromUser);
    });
  }

  void makeUserFirstOrAddFirst(ChatUser chatUser) {
    if (state is ChatUsersFetchSuccess) {
      final stateAs = state as ChatUsersFetchSuccess;
      int? index;
      try {
        index = stateAs.chatUsers.indexWhere((element) {
          return element == chatUser;
        });
      } catch (_) {}
      //if already has that user, replace it and make it first as the latest message is from them
      if (index != null && index >= 0) {
        final tempList = stateAs.chatUsers;
        //if we have 0 count, but a new message came, increase count by 1
        final bool increaseUserCount =
            tempList[index].unreadNotificationsCount == 0;
        if (chatUser.hashCode !=
            ChatNotificationsUtils.currentChattingUserHashCode) {
          chatUser.unReadChats = tempList[index].unreadNotificationsCount + 1;
        }

        tempList.removeAt(index);
        tempList.insert(0, chatUser);
        emit(stateAs.copyWith(
            newChatUsers: tempList,
            newTotalUnreadUsers: increaseUserCount &&
                    chatUser.hashCode !=
                        ChatNotificationsUtils.currentChattingUserHashCode
                ? stateAs.totalUnreadUsers + 1
                : null));
      }
    }
  }

  bool isLoading() {
    if (state is ChatUsersFetchInProgress) {
      return true;
    }
    return false;
  }

  Future<void> fetchChatUsers(
      {required String orderStatus, required String filterType}) async {
    emit(ChatUsersFetchInProgress());
    try {
      final Map<String, dynamic> data = await _chatRepository.fetchChatUsers(
        offset: 0,
        orderStatus: orderStatus,
        filterType: filterType,
      );

      //registration of notification listener if the chat messages were fetched successfully
      registerNotificationListener();
      return emit(
        ChatUsersFetchSuccess(
          chatUsers: data['chatUsers'],
          totalOffset: data['totalItems'],
          totalUnreadUsers: data['totalUnreadUsers'],
          moreChatUserFetchError: false,
          moreChatUserFetchProgress: false,
        ),
      );
    } catch (e) {
      emit(
        ChatUsersFetchFailure(
          errorMessage: e.toString(),
        ),
      );
    }
  }

  Future<void> fetchMoreChatUsers(
      {required String orderStatus, required String filterType}) async {
    if (state is ChatUsersFetchSuccess) {
      final stateAs = state as ChatUsersFetchSuccess;
      if (stateAs.moreChatUserFetchProgress) {
        return;
      }
      try {
        emit(stateAs.copyWith(newFetchMorechatUsersInProgress: true));

        final Map moreTransactionResult = await _chatRepository.fetchChatUsers(
          offset: stateAs.chatUsers.length,
          orderStatus: orderStatus,
          filterType: filterType,
        );

        final List<ChatUser> chatUsers = stateAs.chatUsers;

        chatUsers.addAll(moreTransactionResult['chatUsers']);

        emit(
          ChatUsersFetchSuccess(
            chatUsers: chatUsers,
            totalOffset: moreTransactionResult['totalItems'],
            totalUnreadUsers: moreTransactionResult['totalUnreadUsers'],
            moreChatUserFetchError: false,
            moreChatUserFetchProgress: false,
          ),
        );
      } catch (e) {
        emit(
          (state as ChatUsersFetchSuccess).copyWith(
            newFetchMorechatUsersInProgress: false,
            newFetchMorechatUsersError: true,
          ),
        );
      }
    }
  }

  bool hasMore() {
    if (state is ChatUsersFetchSuccess) {
      return (state as ChatUsersFetchSuccess).chatUsers.length <
          (state as ChatUsersFetchSuccess).totalOffset;
    }
    return false;
  }

  void disposeNotificationListener() {
    if (_streamSubscription != null) {
      _streamSubscription!.cancel();
    }
  }

  void removeUserFromList(ChatUser userToRemove) {
    if (state is ChatUsersFetchSuccess) {
      final stateAs = state as ChatUsersFetchSuccess;
      final updatedList = List<ChatUser>.from(stateAs.chatUsers)
        ..removeWhere((user) => user == userToRemove);

      emit(stateAs.copyWith(
        newChatUsers: updatedList,
        newTotalOffset: stateAs.totalOffset - 1,
      ));
    }
  }

  @override
  Future<void> close() async {
    disposeNotificationListener();
    super.close();
  }
}
