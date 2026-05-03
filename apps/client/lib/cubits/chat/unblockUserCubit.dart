import 'package:e_demand/app/generalImports.dart';

// States
abstract class UnblockUserState {}

class UnblockUserInitial extends UnblockUserState {}

class UnblockUserInProgress extends UnblockUserState {}

class UnblockUserSuccess extends UnblockUserState {
  final String message;
  final String providerId;
  UnblockUserSuccess({required this.message, required this.providerId});
}

class UnblockUserFailure extends UnblockUserState {
  final String errorMessage;
  UnblockUserFailure({required this.errorMessage});
}

// Cubit
class UnblockUserCubit extends Cubit<UnblockUserState> {
  final ChatRepository _chatRepository;

  UnblockUserCubit(this._chatRepository) : super(UnblockUserInitial());

  Future<void> unblockUser(String providerId) async {
    emit(UnblockUserInProgress());
    try {
      final message = await _chatRepository.unblockUser(providerId: providerId);
      emit(UnblockUserSuccess(message: message, providerId: providerId));
    } catch (e) {
      emit(UnblockUserFailure(errorMessage: e.toString()));
    }
  }
}
