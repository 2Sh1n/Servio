import 'package:e_demand/app/generalImports.dart';

// Delete Chat States
abstract class DeleteChatState {}

class DeleteChatInitial extends DeleteChatState {}

class DeleteChatInProgress extends DeleteChatState {}

class DeleteChatFailure extends DeleteChatState {
  final String errorMessage;

  DeleteChatFailure({required this.errorMessage});
}

class DeleteChatSuccess extends DeleteChatState {}

// Delete Chat Cubit
class DeleteChatCubit extends Cubit<DeleteChatState> {
  final ChatRepository chatRepository;

  DeleteChatCubit(this.chatRepository) : super(DeleteChatInitial());

  Future<void> deleteChat(String partnerId, String bookingId) async {
    emit(DeleteChatInProgress());
    try {
      await chatRepository.deleteChat(
          partnerId: partnerId, bookingId: bookingId);
      emit(DeleteChatSuccess());
    } catch (e) {
      emit(DeleteChatFailure(errorMessage: e.toString()));
    }
  }
}
