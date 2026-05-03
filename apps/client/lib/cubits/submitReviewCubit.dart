
import 'package:e_demand/app/generalImports.dart';

abstract class SubmitReviewState {}

class SubmitReviewInitial extends SubmitReviewState {}

class SubmitReviewInProgress extends SubmitReviewState {}

class SubmitReviewSuccess extends SubmitReviewState {
  SubmitReviewSuccess(
      {required this.message, required this.error, required this.data});

  final String message;
  final bool error;
  Map<String, dynamic> data;
}

class SubmitReviewFailure extends SubmitReviewState {
  SubmitReviewFailure({required this.errorMessage});

  final dynamic errorMessage;
}

class SubmitReviewCubit extends Cubit<SubmitReviewState> {
  SubmitReviewCubit({required this.bookingRepository})
      : super(SubmitReviewInitial());
  BookingRepository bookingRepository = BookingRepository();

  Future<void> submitReview({
    required final String serviceId,
    required final String ratingStar,
    required final String reviewComment,
    final String? customJobRequestId,
    final List<XFile?>? reviewImages,
    final List<String>? deletedPastReviewImages,
  }) async {
    try {
      emit(SubmitReviewInProgress());
      await bookingRepository
          .submitReviewToService(
        serviceId: serviceId,
        ratingStar: ratingStar,
        reviewComment: reviewComment,
        reviewImages: reviewImages,
        customJobRequestId: customJobRequestId,
        deletedPastReviewImages: deletedPastReviewImages,
      )
          .then((final value) async {
        if (value['error'] == true) {
          ClarityService.logAction(
            ClarityActions.serviceReviewSubmitted,
            {
              'service_id': serviceId,
              'rating': ratingStar,
              'has_comment': reviewComment.trim().isNotEmpty,
              'has_images': (reviewImages?.isNotEmpty ?? false),
              'custom_job_request_id': customJobRequestId ?? '',
              'result': 'error',
              'error_message': value['message']?.toString() ?? '',
            },
          );
          emit(SubmitReviewFailure(errorMessage: value['message']));
        } else {
          ClarityService.logAction(
            ClarityActions.serviceReviewSubmitted,
            {
              'service_id': serviceId,
              'rating': ratingStar,
              'has_comment': reviewComment.trim().isNotEmpty,
              'has_images': (reviewImages?.isNotEmpty ?? false),
              'custom_job_request_id': customJobRequestId ?? '',
              'result': 'success',
            },
          );
          //emit success state
          emit(SubmitReviewSuccess(
            message: value['message'],
            error: value['error'],
            data: value["data"] ?? {},
          ));
        }
      });
    } catch (e) {
      ClarityService.logAction(
        ClarityActions.serviceReviewSubmitted,
        {
          'service_id': serviceId,
          'rating': ratingStar,
          'has_comment': reviewComment.trim().isNotEmpty,
          'has_images': (reviewImages?.isNotEmpty ?? false),
          'custom_job_request_id': customJobRequestId ?? '',
          'result': 'error',
          'error_message': e.toString(),
        },
      );
      emit(SubmitReviewFailure(errorMessage: e.toString()));
    }
  }
}
