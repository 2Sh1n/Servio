import '../../app/generalImports.dart';

abstract class SignInState {}

class SignInInitial extends SignInState {}

class SignInInProgress extends SignInState {}

class SignInSuccess extends SignInState {
  SignInSuccess({
    required this.providerDetails,
    required this.error,
    required this.message,
  });

  final ProviderDetails providerDetails;
  final bool error;
  final String message;
}

class SignInFailure extends SignInState {
  SignInFailure(this.errorMessage);

  final String errorMessage;
}

class SignInCubit extends Cubit<SignInState> {
  SignInCubit() : super(SignInInitial());

  final AuthRepository _authRepository = AuthRepository();

  Future<void> signIn({
    String? phoneNumber,
    String? email,
    required String password,
    String? countryCode,
    String? fcmId,
    String? loginType,
  }) async {
    try {
      emit(SignInInProgress());

      // Log login attempt
      final Map<String, dynamic> loginData = {};
      if (email != null && email.isNotEmpty) {
        loginData['email'] = email;
      } else {
        loginData['phone_number'] = phoneNumber;
        loginData['country_code'] = countryCode;
      }
      ClarityService.logAction(ClarityActions.loginAttempt, loginData);

      final Map<String, dynamic> response = await _authRepository.loginUser(
        phoneNumber: phoneNumber,
        email: email,
        password: password,
        countryCode: countryCode,
        fcmId: fcmId,
        loginType: loginType,
      );

      if (response['userDetails'] != null) {
        final providerDetails = response['userDetails'] as ProviderDetails;

        // Log successful login
        ClarityService.logAction(ClarityActions.loginSuccess, {
          'provider_id': providerDetails.providerInformation?.id ?? '',
          'provider_name':
              providerDetails.providerInformation?.getTranslatedUsername(
                HiveRepository.getCurrentLanguage()?.languageCode ?? 'en',
              ) ??
              '',
          'company_name':
              providerDetails.providerInformation?.getTranslatedCompanyName(
                HiveRepository.getCurrentLanguage()?.languageCode ?? 'en',
              ) ??
              '',
        });

        // Set user ID for both analytics platforms
        final userId = providerDetails.user?.id ?? '';
        if (userId.isNotEmpty) {
          ClarityService.setUserId(userId);
        }

        emit(
          SignInSuccess(
            providerDetails: providerDetails,
            error: response['error'],
            message: response['message'],
          ),
        );
      } else {
        // Log login failure
        ClarityService.logAction(ClarityActions.loginFailure, {
          'error_message': response['message'] ?? 'Unknown error',
        });

        emit(SignInFailure(response['message']));
      }
    } catch (e) {
      // Log login failure
      ClarityService.logAction(ClarityActions.loginFailure, {
        'error_message': e.toString(),
      });

      emit(SignInFailure(e.toString()));
    }
  }

  void setInitial() {
    emit(SignInInitial());
  }
}
