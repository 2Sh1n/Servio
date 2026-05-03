// Submit Report States

import 'package:e_demand/app/generalImports.dart';

abstract class SubmitReportState {}

class SubmitReportInitial extends SubmitReportState {}

class SubmitReportInProgress extends SubmitReportState {}

class SubmitReportSuccess extends SubmitReportState {
  final String message;

  SubmitReportSuccess({required this.message});
}

class SubmitReportFailure extends SubmitReportState {
  final String errorMessage;

  SubmitReportFailure({required this.errorMessage});
}

class SubmitReportCubit extends Cubit<SubmitReportState> {
  final ChatRepository chatRepository;

  SubmitReportCubit(this.chatRepository) : super(SubmitReportInitial());

  Future<void> submitReport({
    required String reasonId,
    required String additionalInfo,
    required String providerId,
  }) async {
    emit(SubmitReportInProgress());
    try {
      final message = await chatRepository.blockUserWitReport(
        reasonId: reasonId,
        additionalInfo: additionalInfo,
        providerId: providerId,
      );
      emit(SubmitReportSuccess(message: message));
    } catch (e) {
      emit(SubmitReportFailure(errorMessage: e.toString()));
    }
  }
}
