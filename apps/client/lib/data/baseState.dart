abstract class BaseState {}

class InitialState extends BaseState {}

class LoadingState<T> extends BaseState {
  LoadingState({this.loading});

  final T? loading;
}

class FailureState extends BaseState {
  FailureState(this.message);

  final String message;
}

class SuccessState<T> extends BaseState {
  SuccessState({
    required this.data,
    required this.message,
    required this.error,
    this.isLoadingMoreData = false,
    this.loadMoreDataErrorMessage = '',
    this.total = 0,
    this.isLoadingMoreError = false,
    this.totalamout = '0',
  });

  final T data;
  final bool isLoadingMoreData;
  final String loadMoreDataErrorMessage;
  final int total;
  final String message;
  final bool error;
  final bool isLoadingMoreError;
  final String totalamout;

  bool get hasMoreData {
    if (data is List) {
      return (data as List).length < total;
    }
    return false;
  }

  SuccessState<T> copyWith({
    T? data,
    bool? isLoadingMoreData,
    String? loadMoreDataErrorMessage,
    int? totalDataCount,
    String? message,
    bool? error,
    bool? isLoadingMoreError,
  }) {
    return SuccessState<T>(
      data: data ?? this.data,
      isLoadingMoreData: isLoadingMoreData ?? this.isLoadingMoreData,
      loadMoreDataErrorMessage:
          loadMoreDataErrorMessage ?? this.loadMoreDataErrorMessage,
      total: totalDataCount ?? total,
      message: message ?? this.message,
      error: error ?? this.error,
      isLoadingMoreError: isLoadingMoreError ?? this.isLoadingMoreError,
      totalamout: totalamout,
    );
  }

  SuccessState<T> copyWithLoadingMoreData() {
    return SuccessState<T>(
      data: data,
      isLoadingMoreData: true,
      loadMoreDataErrorMessage: loadMoreDataErrorMessage,
      total: total,
      message: message,
      error: error,
      isLoadingMoreError: isLoadingMoreError,
      totalamout: totalamout,
    );
  }

  SuccessState<T> copyWithErrorOnLoadingMoreData() {
    return SuccessState<T>(
      data: data,
      loadMoreDataErrorMessage: 'somethingWentWrongWhileLoadingMoreData',
      total: total,
      message: message,
      error: error,
      isLoadingMoreError: true,
      totalamout: totalamout,
    );
  }

  Map<String, dynamic> toJson() => {
        'data': data,
        'isLoadingMoreData': isLoadingMoreData,
        'loadMoreDataErrorMessage': loadMoreDataErrorMessage,
        'totalDataCount': total,
        'message': message,
        'error': error,
        'isLoadingMoreError': isLoadingMoreError,
        'balance': totalamout,
      };
}
