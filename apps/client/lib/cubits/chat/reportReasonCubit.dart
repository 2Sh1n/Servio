import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/model/chat/reportReasonModel.dart';

// Get Report Reasons States
abstract class GetReportReasonsState {}

class GetReportReasonsInitial extends GetReportReasonsState {}

class GetReportReasonsInProgress extends GetReportReasonsState {}

class GetReportReasonsFailure extends GetReportReasonsState {
  final String errorMessage;

  GetReportReasonsFailure({required this.errorMessage});
}

class GetReportReasonsSuccess extends GetReportReasonsState {
  final List<ReportReasonModel> reportReasons;

  GetReportReasonsSuccess({required this.reportReasons});
}

// Get Report Reasons Cubit
class GetReportReasonsCubit extends Cubit<GetReportReasonsState>
    implements StateStreamableSource<GetReportReasonsState> {
  final ChatRepository chatRepository;

  GetReportReasonsCubit(this.chatRepository) : super(GetReportReasonsInitial());

  Future<void> getReportReasons() async {
    emit(GetReportReasonsInProgress());
    try {
      final reportReasons = await chatRepository.getReportReasons();
      emit(GetReportReasonsSuccess(reportReasons: reportReasons));
    } catch (e) {
      emit(GetReportReasonsFailure(errorMessage: e.toString()));
    }
  }
}

// Submit Report Cubit
