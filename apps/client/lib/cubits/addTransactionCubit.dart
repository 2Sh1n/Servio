import 'package:e_demand/app/generalImports.dart';

//State
abstract class AddTransactionState {}

class AddTransactionInitial extends AddTransactionState {}

class AddTransactionInProgress extends AddTransactionState {}

class AddTransactionSuccess extends AddTransactionState {
  AddTransactionSuccess({
    required this.message,
    required this.error,
    this.paypalUrl,
    this.paystackUrl,
    this.flutterwaveUrl,
    this.xenditUrl,
    this.transactionId,
  });
  final String message;
  final bool error;
  final String? paypalUrl;
  final String? paystackUrl;
  final String? flutterwaveUrl;
  final String? xenditUrl;
  final String? transactionId;
}

class AddTransactionFailure extends AddTransactionState {
  AddTransactionFailure({required this.errorMessage});
  final String errorMessage;
}

//cubit
class AddTransactionCubit extends Cubit<AddTransactionState> {
  AddTransactionCubit({required this.bookingRepository})
      : super(AddTransactionInitial());
  final BookingRepository bookingRepository;

  ///This method is used to fetch booking details
  Future<void> addTransaction({
    required final String status,
    required final String orderID,
    final String? isAdditionalCharge,
    final String? paymentGatewayName,
    final String? transactionId,
    final String? isReorder,
  }) async {
    try {
      emit(AddTransactionInProgress());

      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.status: status,
        ApiParam.orderId: orderID,
        ApiParam.isAdditionalCharge: isAdditionalCharge,
        ApiParam.paymentMethod: paymentGatewayName,
        ApiParam.transactionId: transactionId,
        ApiParam.isReorder: isReorder
      };

      await bookingRepository
          .addOnlinePaymentTransaction(parameter: parameter)
          .then((final value) {
        emit(
          AddTransactionSuccess(
            message: value['message'],
            error: value['error'],
            paypalUrl: value['paypal_link'] ?? '',
            paystackUrl: value['paystack_link'] ?? '',
            flutterwaveUrl: value['flutterwave_link'] ?? '',
            xenditUrl: value['xendit_link'] ?? '',
            transactionId: value['transaction_id'] ?? '',
          ),
        );
      });
    } catch (e) {
      emit(AddTransactionFailure(errorMessage: e.toString()));
    }
  }

  String? getTransactionId() {
    if (state is AddTransactionSuccess) {
      return (state as AddTransactionSuccess).transactionId;
    }
    return null;
  }
}
