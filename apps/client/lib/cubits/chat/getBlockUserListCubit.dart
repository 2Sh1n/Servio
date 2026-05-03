import 'package:e_demand/data/model/chat/blockUserList.dart';

import '../../app/generalImports.dart';

abstract class GetBlockedUsersState {}

class GetBlockedUsersInitial extends GetBlockedUsersState {}

class GetBlockedUsersInProgress extends GetBlockedUsersState {}

class GetBlockedUsersSuccess extends GetBlockedUsersState {
  final List<BlockedUserModel> blockedProviders;
  GetBlockedUsersSuccess({required this.blockedProviders});
}

class GetBlockedUsersFailure extends GetBlockedUsersState {
  final String errorMessage;
  GetBlockedUsersFailure({required this.errorMessage});
}

class GetBlockedUsersCubit extends Cubit<GetBlockedUsersState> {
  final ChatRepository chatRepository;

  GetBlockedUsersCubit(this.chatRepository) : super(GetBlockedUsersInitial());

  Future<void> getBlockedUsers() async {
    emit(GetBlockedUsersInProgress());
    try {
      final List<BlockedUserModel> providers =
          await chatRepository.getBlockedProviders();
      emit(GetBlockedUsersSuccess(blockedProviders: providers));
    } catch (e) {
      emit(GetBlockedUsersFailure(errorMessage: e.toString()));
    }
  }

  Future<void> unblockUser(String providerId) async {
    try {
      // Call the unblock API
      await chatRepository.unblockUser(providerId: providerId);

      // Only update the list if API call is successful
      if (state is GetBlockedUsersSuccess) {
        final currentState = state as GetBlockedUsersSuccess;
        final updatedList =
            List<BlockedUserModel>.from(currentState.blockedProviders)
              ..removeWhere((u) => u.id == providerId);
        emit(GetBlockedUsersSuccess(blockedProviders: updatedList));
      }
    } catch (e) {
      // If API call fails, throw the error to be handled by the UI
      throw ApiException(e.toString());
    }
  }
}
