import 'package:e_demand/app/generalImports.dart';

abstract class ChangePasswordState {}

class ChangePasswordInitial extends ChangePasswordState {}

class ChangePasswordInProgress extends ChangePasswordState {}

class ChangePasswordSuccess extends ChangePasswordState {
  final String message;

  ChangePasswordSuccess({required this.message});
}

class ChangePasswordFail extends ChangePasswordState {
  final String error;

  ChangePasswordFail({required this.error});
}

class ChangePasswordCubit extends Cubit<ChangePasswordState> {
  ChangePasswordCubit() : super(ChangePasswordInitial());

  final AuthenticationRepository _authenticationRepository =
      AuthenticationRepository();

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required LogInType loginType,
  }) async {
    emit(ChangePasswordInProgress());
    try {
      final Map<String, dynamic> result =
          await _authenticationRepository.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        loginType: loginType,
      );

      if (result['error'] == false) {
        emit(ChangePasswordSuccess(
          message: result['message'] ?? 'Password changed successfully',
        ));
      } else {
        emit(ChangePasswordFail(
            error: result['message'] ?? 'Failed to change password'));
      }
    } catch (e) {
      emit(ChangePasswordFail(error: e.toString()));
    }
  }

  Future<void> setPassword({
    required String newPassword,
    required LogInType loginType,
    String? resetToken,
    String? phoneNumber,
    String? countryCode,
  }) async {
    emit(ChangePasswordInProgress());
    try {
      final Map<String, dynamic> result =
          await _authenticationRepository.changePassword(
        newPassword: newPassword,
        resetToken: resetToken,
        phoneNumber: phoneNumber,
        countryCode: countryCode,
        loginType: loginType,
      );

      if (result['error'] == false) {
        emit(ChangePasswordSuccess(
          message: result['message'] ?? 'Password set successfully',
        ));
      } else {
        emit(ChangePasswordFail(
            error: result['message'] ?? 'Failed to set password'));
      }
    } catch (e) {
      emit(ChangePasswordFail(error: e.toString()));
    }
  }

  void reset() {
    emit(ChangePasswordInitial());
  }
}
