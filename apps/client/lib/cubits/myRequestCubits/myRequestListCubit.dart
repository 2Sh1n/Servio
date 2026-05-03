import 'package:e_demand/app/generalImports.dart';

abstract class MyRequestListState {}

class MyRequestListInitial extends MyRequestListState {}

class MyRequestListInProgress extends MyRequestListState {}

class MyRequestListSuccess extends MyRequestListState {
  MyRequestListSuccess(
      {required this.totalReqCount,
      required this.isLoadingMoreReq,
      required this.isLoadingMoreError,
      required this.requestList});

  final List<MyRequestListModel> requestList;
  final int totalReqCount;
  final bool isLoadingMoreReq;
  final bool isLoadingMoreError;

  MyRequestListSuccess copyWith({
    final List<MyRequestListModel>? requestList,
    final int? totalReqCount,
    final bool? isLoadingMoreReq,
    final bool? isLoadingMoreError,
  }) =>
      MyRequestListSuccess(
        isLoadingMoreReq: isLoadingMoreReq ?? this.isLoadingMoreReq,
        isLoadingMoreError: isLoadingMoreError ?? this.isLoadingMoreError,
        totalReqCount: totalReqCount ?? this.totalReqCount,
        requestList: requestList ?? this.requestList,
      );
}

class MyRequestListFailure extends MyRequestListState {
  MyRequestListFailure({required this.errorMessage});

  final String errorMessage;
}

class MyRequestListCubit extends Cubit<MyRequestListState> {
  MyRequestListCubit() : super(MyRequestListInitial());

  Future<void> fetchRequests({String? id}) async {
    final MyRequestRepository myRequestRepository = MyRequestRepository();
    try {
      emit(MyRequestListInProgress());
      final data =
          await myRequestRepository.fetchMyCustomJobRequests(parameter: {
            ApiParam.id: ?id,
          });
      emit(MyRequestListSuccess(
        isLoadingMoreReq: false,
        isLoadingMoreError: false,
        requestList: data['data'],
        totalReqCount: data['total'],
      ));
    } catch (e) {
      emit(MyRequestListFailure(errorMessage: e.toString()));
    }
  }

  Future<void> fetchMoreReq() async {
    if (state is MyRequestListSuccess) {
      if ((state as MyRequestListSuccess).isLoadingMoreReq) {
        return;
      }
    }

    final MyRequestListSuccess currentState = state as MyRequestListSuccess;
    emit(
      currentState.copyWith(
        totalReqCount: currentState.totalReqCount,
        requestList: currentState.requestList,
        isLoadingMoreReq: true,
        isLoadingMoreError: false,
      ),
    );

    final Map<String, dynamic> parameter = <String, dynamic>{
      ApiParam.limit: UiUtils.limitOfAPIData,
      ApiParam.offset: (state as MyRequestListSuccess).requestList.length,
    };
    final myRequestRepository = MyRequestRepository();

    await myRequestRepository
        .fetchMyCustomJobRequests(parameter: parameter)
        .then((final value) {
      final List<MyRequestListModel> oldList = currentState.requestList;

      oldList.addAll(value['data']);

      emit(
        MyRequestListSuccess(
          isLoadingMoreError: false,
          isLoadingMoreReq: false,
          requestList: oldList,
          totalReqCount: value['total'],
        ),
      );
    }).catchError((final e) {
      emit(
        MyRequestListSuccess(
          isLoadingMoreError: true,
          isLoadingMoreReq: false,
          requestList: currentState.requestList,
          totalReqCount: currentState.totalReqCount,
        ),
      );
    });
  }

  bool hasMoreReq() {
    if (state is MyRequestListSuccess) {
      return (state as MyRequestListSuccess).requestList.length <
          (state as MyRequestListSuccess).totalReqCount;
    }
    return false;
  }
}
