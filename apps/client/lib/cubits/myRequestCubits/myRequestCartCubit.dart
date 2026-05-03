import 'package:e_demand/app/generalImports.dart';

@immutable
abstract class MyRequestCartState {}

class MyRequestCartInitial extends MyRequestCartState {}

class MyRequestCartInProgress extends MyRequestCartState {
  final String? bidderId;

  MyRequestCartInProgress({this.bidderId});
}

class MyRequestCartSuccess extends MyRequestCartState {
  MyRequestCartSuccess(
      {this.bidderId, required this.isBidderCart, required this.bidderData});

  final String? bidderId;
  final bool isBidderCart;
  final Bidder bidderData;
}

class MyRequestCartFailure extends MyRequestCartState {
  MyRequestCartFailure({this.bidderId, required this.errorMessage});

  final String errorMessage;
  final String? bidderId;
}

class MyRequestCartCubit extends Cubit<MyRequestCartState> {
  MyRequestCartCubit(this.cartRepository) : super(MyRequestCartInitial());
  MyRequestRepository cartRepository;

  void emitSuccess(final Bidder bidder) {
    emit(MyRequestCartSuccess(
      isBidderCart: true,
      bidderData: bidder,
    ));
  }

  String getProviderIDFromCartData() {
    if (state is MyRequestCartSuccess) {
      final Bidder cartData = (state as MyRequestCartSuccess).bidderData;
      return cartData.id ?? '0';
    }
    return '0';
  }

  String getCartTotalAmount(
      {required bool isAtStoreBooked, required String isFrom}) {
    if (state is MyRequestCartSuccess && isFrom == 'customJobRequest') {
      final Bidder cartData = (state as MyRequestCartSuccess).bidderData;

      final double visitingCharge =
          isAtStoreBooked ? 0 : double.parse(cartData.visitingCharges ?? "0");

      return (double.parse(cartData.finalTotal ?? '0') + visitingCharge)
          .toString();
    }
    return '0';
  }

  String getCartSubTotalAmount(
      {required bool isAtStoreBooked, required String isFrom}) {
    if (state is MyRequestCartSuccess && isFrom == 'customJobRequest') {
      final Bidder cartData = (state as MyRequestCartSuccess).bidderData;

      return double.parse(cartData.finalTotal ?? '0').toString();
    }
    return '0';
  }

  bool isPayLaterAllowed({required String isFrom}) {
    if (state is MyRequestCartSuccess && isFrom == 'customJobRequest') {
      final Bidder cartData = (state as MyRequestCartSuccess).bidderData;

      return cartData.isPayLaterAllowed == 1;
    }
    return false;
  }

  bool isOnlinePaymentAllowed({required String isFrom}) {
    if (state is MyRequestCartSuccess && isFrom == 'customJobRequest') {
      final Bidder cartData = (state as MyRequestCartSuccess).bidderData;

      return cartData.isOnlinePaymentAllowed == 1;
    }
    return false;
  }

  void clearCartCubit() {
    emit(MyRequestCartSuccess(
      bidderData: Bidder(),
      isBidderCart: false,
    ));
  }

  bool checkAtStoreProviderAvailable({required String isFrom}) {
    if (state is MyRequestCartSuccess && isFrom == 'customJobRequest') {
      return (state as MyRequestCartSuccess).bidderData.atStore == "1";
    }
    return false;
  }

  bool checkAtDoorstepProviderAvailable({required String isFrom}) {
    if (state is MyRequestCartSuccess && isFrom == 'customJobRequest') {
      return (state as MyRequestCartSuccess).bidderData.atDoorstep == "1";
    }
    return false;
  }
}
