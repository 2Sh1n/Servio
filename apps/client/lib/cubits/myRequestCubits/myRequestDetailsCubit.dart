import 'package:e_demand/app/generalImports.dart';

abstract class MyRequestDetailsState {}

class MyRequestDetailsInitial extends MyRequestDetailsState {}

class MyRequestDetailsInProgress extends MyRequestDetailsState {}

class MyRequestDetailsSuccess extends MyRequestDetailsState {
  MyRequestDetailsSuccess(
      {required this.totalReqCount,
      required this.isLoadingMoreReq,
      required this.isLoadingMoreError,
      required this.requestBidders,
      required this.requestCustomJob});

  final List<Bidder> requestBidders;
  final CustomJob requestCustomJob;
  final int totalReqCount;
  final bool isLoadingMoreReq;
  final bool isLoadingMoreError;

  MyRequestDetailsSuccess copyWith({
    final int? totalReqCount,
    final bool? isLoadingMoreReq,
    final bool? isLoadingMoreError,
    final List<Bidder>? requestBidders,
    final CustomJob? requestCustomJob,
  }) =>
      MyRequestDetailsSuccess(
        isLoadingMoreReq: isLoadingMoreReq ?? this.isLoadingMoreReq,
        isLoadingMoreError: isLoadingMoreError ?? this.isLoadingMoreError,
        totalReqCount: totalReqCount ?? this.totalReqCount,
        requestBidders: requestBidders ?? this.requestBidders,
        requestCustomJob: requestCustomJob ?? this.requestCustomJob,
      );
}

class MyRequestDetailsFailure extends MyRequestDetailsState {
  MyRequestDetailsFailure({required this.errorMessage});

  final String errorMessage;
}

class MyRequestDetailsCubit extends Cubit<MyRequestDetailsState> {
  MyRequestDetailsCubit() : super(MyRequestDetailsInitial());

  Future<void> fetchRequestDetails({required String customJobRequestId}) async {
    final MyRequestRepository myRequestRepository = MyRequestRepository();
    try {
      emit(MyRequestDetailsInProgress());
      final data = await myRequestRepository.fetchMyCustomJobBidders(
        customJobReqId: customJobRequestId,
      );
      emit(MyRequestDetailsSuccess(
        isLoadingMoreReq: false,
        isLoadingMoreError: false,
        requestBidders: data['bidders'],
        requestCustomJob: data['customJob'],
        totalReqCount: data['total'],
      ));
    } catch (e) {
      emit(MyRequestDetailsFailure(errorMessage: e.toString()));
    }
  }

  Future<void> fetchMoreReq({required String customJobRequestId}) async {
    final MyRequestDetailsSuccess currentState =
        state as MyRequestDetailsSuccess;
    try {
      final MyRequestRepository myRequestRepository = MyRequestRepository();
      final List<Bidder> reqOldData = currentState.requestBidders;
      if (currentState.isLoadingMoreReq) {
        return;
      }
      emit(currentState.copyWith(isLoadingMoreReq: true));
      final Map<String, dynamic> reqData = await myRequestRepository
          .fetchMyCustomJobBidders(customJobReqId: customJobRequestId);
      reqOldData.addAll(reqData["data"]);
      emit(
        currentState.copyWith(
          requestBidders: reqOldData,
          isLoadingMoreReq: false,
        ),
      );
    } catch (error) {
      emit(
        currentState.copyWith(
          isLoadingMoreReq: false,
          isLoadingMoreError: true,
        ),
      );
    }
  }

  bool hasMoreReq() {
    if (state is MyRequestDetailsSuccess) {
      return (state as MyRequestDetailsSuccess).requestBidders.length <
          (state as MyRequestDetailsSuccess).totalReqCount.toInt();
    }
    return false;
  }
}
