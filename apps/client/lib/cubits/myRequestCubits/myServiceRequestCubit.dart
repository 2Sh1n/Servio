
import 'package:e_demand/app/generalImports.dart';

abstract class MyRequestState {}

class MyRequestInitial extends MyRequestState {}

class MyRequestLoading extends MyRequestState {}

class MyRequestFailure extends MyRequestState {
  final String errorMessage;
  MyRequestFailure(this.errorMessage);
}

class MyRequestSSuccess extends MyRequestState {
  MyRequestSSuccess(this.response);
  final Map<String, dynamic> response;
}

class MyRequestCubit extends Cubit<MyRequestState> {
  MyRequestCubit() : super(MyRequestInitial());
  final MyRequestRepository _myRequestRepository = MyRequestRepository();

  Future<void> submitCustomJobRequest({
    required Map<String, dynamic> parameters,
  }) async {
    emit(MyRequestLoading());
    try {
      emit(MyRequestLoading());
      final result = await _myRequestRepository.submitCustomJobRequest(
        parameters: parameters,
      );

      if (result['error'] == false) {
        ClarityService.logAction(
          ClarityActions.customJobRequestCreated,
          {
            'custom_job_request_id':
                result['data']?['custom_job_request_id']?.toString() ?? '',
            'category_id': parameters['category_id']?.toString() ?? '',
            'service_title': parameters['service_title']?.toString() ?? '',
            'min_price': parameters['min_price']?.toString() ?? '',
            'max_price': parameters['max_price']?.toString() ?? '',
            'requested_start_date':
                parameters['requested_start_date']?.toString() ?? '',
            'requested_start_time':
                parameters['requested_start_time']?.toString() ?? '',
            'requested_end_date':
                parameters['requested_end_date']?.toString() ?? '',
            'requested_end_time':
                parameters['requested_end_time']?.toString() ?? '',
            'status': 'success',
          },
        );
        emit(MyRequestSSuccess(result));
      } else {
        emit(MyRequestFailure(result['message'].toString()));
      }
    } catch (e) {
      ClarityService.logAction(
        ClarityActions.customJobRequestCreated,
        {
          'status': 'error',
          'message': e.toString(),
        },
      );
      emit(MyRequestFailure(e.toString()));
    }
  }
}
