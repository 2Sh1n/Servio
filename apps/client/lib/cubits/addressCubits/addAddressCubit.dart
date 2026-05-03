import 'package:e_demand/app/generalImports.dart';

class AddAddressState {}

class AddAddressInitial extends AddAddressState {}

class AddAddressInProgress extends AddAddressState {}

class AddAddressSuccess extends AddAddressState {
  AddAddressSuccess(this.result);
  final dynamic result;
}

class AddAddressFail extends AddAddressState {
  AddAddressFail(this.error);
  final dynamic error;
}

class AddAddressCubit extends Cubit<AddAddressState> {
  AddAddressCubit() : super(AddAddressInitial());
  final AddressRepository addressRepository = AddressRepository();

  Future<void> addAddress(final AddressModel addressDataModel) async {
    try {
      emit(AddAddressInProgress());
      final Map<String, dynamic> response =
          await addressRepository.addAddress(addressDataModel);
      if (response["error"] == true) {
        emit(AddAddressFail(response["message"]));
      } else {
        ClarityService.logAction(
          ClarityActions.addressAdded,
          {
            'address_id': response['data']?['address_id']?.toString() ??
                addressDataModel.addressId ??
                '',
            'city': addressDataModel.cityName ?? '',
            'type': addressDataModel.type ?? '',
            'is_default': addressDataModel.isDefault ?? '',
          },
        );
        emit(AddAddressSuccess(response));
      }
    } catch (e) {
      ClarityService.logAction(
        ClarityActions.addressAdded,
        {
          'status': 'error',
          'message': e.toString(),
        },
      );
      emit(AddAddressFail(e));
    }
  }

  Future<void> updateDefaultAddress(final String addressId) async {
    try {
      emit(AddAddressInProgress());
      final Map<String, dynamic> response =
          await addressRepository.updateDefaultAddress(addressId);
      if (response["error"] == true) {
        emit(AddAddressFail(response["message"]));
      } else {
        ClarityService.logAction(
          ClarityActions.settingsChanged,
          {
            'address_id': addressId,
            'action': 'set_default_address',
          },
        );
        emit(AddAddressSuccess(response));
      }
    } catch (e) {
      ClarityService.logAction(
        ClarityActions.settingsChanged,
        {
          'address_id': addressId,
          'action': 'set_default_address',
          'status': 'error',
          'message': e.toString(),
        },
      );
      emit(AddAddressFail(e));
    }
  }
}
