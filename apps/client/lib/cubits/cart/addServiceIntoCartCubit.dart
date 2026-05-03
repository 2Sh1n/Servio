import 'package:e_demand/app/generalImports.dart';

@immutable
abstract class AddServiceIntoCartState {}

class AddServiceIntoCartInitial extends AddServiceIntoCartState {}

class AddServiceIntoCartInProgress extends AddServiceIntoCartState {}

class AddServiceIntoCartProviderMismatch extends AddServiceIntoCartState {
  AddServiceIntoCartProviderMismatch({
    required this.currentProviderName,
    required this.currentProviderId,
    required this.newProviderName,
    required this.serviceId,
    required this.quantity,
  });

  final String currentProviderName;
  final String currentProviderId;
  final String newProviderName;
  final int serviceId;
  final int quantity;
}

class AddServiceIntoCartSuccess extends AddServiceIntoCartState {
  AddServiceIntoCartSuccess(
      {required this.cartDetails,
      required this.successMessage,
      required this.error});

  final String successMessage;
  final Cart cartDetails;
  final bool error;
}

class AddServiceIntoCartFailure extends AddServiceIntoCartState {
  AddServiceIntoCartFailure({required this.errorMessage});

  final String errorMessage;
}

class AddServiceIntoCartCubit extends Cubit<AddServiceIntoCartState> {
  AddServiceIntoCartCubit(this.cartRepository)
      : super(AddServiceIntoCartInitial());
  CartRepository cartRepository;

  Future<void> addServiceIntoCart({
    required final int serviceId,
    required final int quantity,
    final String? serviceProviderId,
    final String? serviceProviderName,
    final CartCubit? cartCubit,
  }) async {
    try {
      if (cartCubit != null &&
          serviceProviderId != null &&
          serviceProviderId.isNotEmpty &&
          cartCubit.state is CartFetchSuccess) {
        final cartState = cartCubit.state as CartFetchSuccess;
        final cartProviderId = cartCubit.getProviderIDFromCartData();
        final cartData = cartState.cartData;

        if (cartProviderId != '0' &&
            cartProviderId.trim() != serviceProviderId.trim() &&
            cartData.cartDetails != null &&
            cartData.cartDetails!.isNotEmpty) {
          final currentProviderName =
              cartData.translatedProviderName ?? cartData.providerName ?? '';

          emit(AddServiceIntoCartProviderMismatch(
            currentProviderName: currentProviderName,
            currentProviderId: cartProviderId,
            newProviderName: serviceProviderName ?? '',
            serviceId: serviceId,
            quantity: quantity,
          ));
          return;
        }
      }

      emit(AddServiceIntoCartInProgress());

      await cartRepository
          .addServiceIntoCart(
              useAuthToken: true, serviceId: serviceId, quantity: quantity)
          .then((final value) {
        if (value['error'] == false) {
          ClarityService.logAction(
            ClarityActions.cartItemAdded,
            {
              'service_id': serviceId,
              'quantity': quantity,
            },
          );
        }
        emit(
          AddServiceIntoCartSuccess(
            error: value["error"] == true,
            successMessage: value['message'] ?? '',
            cartDetails: value['cartData'] as Cart,
          ),
        );
      }).catchError((final onError) {
        ClarityService.logAction(
          ClarityActions.cartItemAdded,
          {
            'service_id': serviceId,
            'quantity': quantity,
            'result': 'error',
            'message': onError.toString(),
          },
        );
        emit(AddServiceIntoCartFailure(errorMessage: onError.toString()));
      });
    } catch (e) {
      ClarityService.logAction(
        ClarityActions.cartItemAdded,
        {
          'service_id': serviceId,
          'quantity': quantity,
          'result': 'error',
          'message': e.toString(),
        },
      );
      emit(AddServiceIntoCartFailure(errorMessage: e.toString()));
    }
  }
}
