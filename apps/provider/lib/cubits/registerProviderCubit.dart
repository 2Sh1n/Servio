import '../../app/generalImports.dart';

abstract class RegisterProviderState {}

class RegisterProviderInitial extends RegisterProviderState {}

class RegisterProviderInProgress extends RegisterProviderState {}

class RegisterProviderSuccess extends RegisterProviderState {
  RegisterProviderSuccess({required this.isError, required this.message});
  final bool isError;
  final String message;
}

class RegisterProviderFailure extends RegisterProviderState {
  RegisterProviderFailure({required this.errorMessage});
  final String errorMessage;
}

class RegisterProviderCubit extends Cubit<RegisterProviderState> {
  RegisterProviderCubit() : super(RegisterProviderInitial());
  final AuthRepository _authRepository = AuthRepository();

  Future<void> registerProvider({
    required Map<String, dynamic> parameter,
  }) async {
    try {
      emit(RegisterProviderInProgress());

      // Log registration attempt
      ClarityService.logAction(ClarityActions.registrationAttempt, {
        'phone_number': parameter['phone']?.toString() ?? '',
        'country_code': parameter['country_code']?.toString() ?? '',
      });

      final Map<String, dynamic> responseData = await _authRepository
          .registerProvider(parameters: parameter, isAuthTokenRequired: false);

      if (!responseData['error']) {
        // Log successful registration
        ClarityService.logAction(ClarityActions.registrationCompleted, {
          'username': parameter['username']?.toString() ?? '',
          'company_name': parameter['company_name']?.toString() ?? '',
        });

        emit(
          RegisterProviderSuccess(
            isError: responseData['error'],
            message: responseData['message'],
          ),
        );
        return;
      }

      emit(RegisterProviderFailure(errorMessage: responseData['message']));
    } catch (e, st) {
      emit(RegisterProviderFailure(errorMessage: st.toString()));
    }
  }
}
