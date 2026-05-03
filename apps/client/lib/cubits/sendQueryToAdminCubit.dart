import '../../app/generalImports.dart';

abstract class SendQueryToAdminState {}

class SendQueryToAdminInitialState extends SendQueryToAdminState {}

class SendQueryToAdminInProgressState extends SendQueryToAdminState {}

class SendQueryToAdminSuccessState extends SendQueryToAdminState {
  final String error;
  final String message;

  SendQueryToAdminSuccessState({
    required this.error,
    required this.message,
  });
}

class SendQueryToAdminFailureState extends SendQueryToAdminState {
  final String errorMessage;

  SendQueryToAdminFailureState(this.errorMessage);
}

class SendQueryToAdminCubit extends Cubit<SendQueryToAdminState> {
  final SystemRepository systemRepository = SystemRepository();

  SendQueryToAdminCubit() : super(SendQueryToAdminInitialState());

  Future<void> sendQueryToAdmin({
    required final String name,
    required final String email,
    required final String subject,
    required final String message,
  }) async {
    try {
      emit(SendQueryToAdminInProgressState());
      final response = await systemRepository.sendQueryToAdmin(parameter: {
        "name": name,
        "message": message,
        "subject": subject,
        "email": email,
      });
      ClarityService.logAction(
        ClarityActions.supportTicketCreated,
        {
          'name': name,
          'email': email,
          'subject': subject,
        },
      );
      emit(
        SendQueryToAdminSuccessState(
          message: response['message'],
          error: response['error'].toString(),
        ),
      );
    } catch (e) {
      ClarityService.logAction(
        ClarityActions.supportTicketCreated,
        {
          'name': name,
          'email': email,
          'subject': subject,
          'result': 'error',
          'message': e.toString(),
        },
      );
      emit(SendQueryToAdminFailureState(e.toString()));
    }
  }
}
