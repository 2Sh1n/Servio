import '../../app/generalImports.dart';

abstract class FetchBookingDetailsState {}

class FetchBookingDetailsInitial extends FetchBookingDetailsState {}

class FetchBookingDetailsInProgress extends FetchBookingDetailsState {}

class FetchBookingDetailsSuccess extends FetchBookingDetailsState {
  final Booking booking;

  FetchBookingDetailsSuccess({
    required this.booking,
  });
}

class FetchBookingDetailsFailure extends FetchBookingDetailsState {
  final String errorMessage;

  FetchBookingDetailsFailure(this.errorMessage);
}

class FetchBookingDetailsCubit extends Cubit<FetchBookingDetailsState> {
  FetchBookingDetailsCubit() : super(FetchBookingDetailsInitial());
  final BookingRepository _bookingsRepository = BookingRepository();

  Future<void> fetchBookingDetails({required String bookingId}) async {
    try {
      emit(FetchBookingDetailsInProgress());

      final result = await _bookingsRepository.fetchBookingDetails(
        parameter: {
          ApiParam.limit: 1,
          ApiParam.offset: 0,
          ApiParam.id: bookingId,
          //ApiParam.customRequestOrders: bookingId,
        },
      );

      emit(
        FetchBookingDetailsSuccess(
          booking: result['bookingDetails'].first as Booking,
        ),
      );
    } catch (e) {
      emit(FetchBookingDetailsFailure(e.toString()));
    }
  }
}
