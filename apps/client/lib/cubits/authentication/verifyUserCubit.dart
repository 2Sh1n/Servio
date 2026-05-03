import 'package:e_demand/app/generalImports.dart';

class CheckIsUserExistsCubit extends Cubit<CheckIsUserExistsState> {
  CheckIsUserExistsCubit({required this.authenticationRepository})
    : super(CheckIsUserExistsInitial());
  AuthenticationRepository authenticationRepository;

  Future<void> isUserExists({
    final String? mobileNumber,
    final String? countryCode,
    final String? email,
    required final String uid,
    required LogInType loginType,
    String? userName,
    String? userEmail,
    bool isForForgotPassword = false,
  }) async {
    emit(CheckIsUserExistsInProgress(loginType: loginType));

    try {
      final Map<String, dynamic> value = await authenticationRepository
          .isUserExists(
            uid: uid,
            mobileNumber: mobileNumber,
            countryCode: countryCode,
            email: email,
            isForForgotPassword: isForForgotPassword,
            loginType: loginType,
          );

      emit(
        CheckIsUserExistsSuccess(
          statusCode: value["status_code"],
          countryCode: countryCode,
          mobileNumber: mobileNumber,
          email: email,
          uid: uid,
          loginType: loginType,
          userEmail: userEmail,
          userName: userName,
          authenticationType: value["authenticationType"],
          isError: value["error"],
          errorMessage: value["message"] ?? '',
          hasPassword: value["has_password"] ?? false,
        ),
      );
      //}
    } catch (e) {
      emit(CheckIsUserExistsFailure(errorMessage: e.toString()));
    }
  }
}

//State

abstract class CheckIsUserExistsState {}

class CheckIsUserExistsInitial extends CheckIsUserExistsState {}

class CheckIsUserExistsInProgress extends CheckIsUserExistsState {
  LogInType loginType;

  CheckIsUserExistsInProgress({required this.loginType});
}

class CheckIsUserExistsSuccess extends CheckIsUserExistsState {
  CheckIsUserExistsSuccess({
    required this.statusCode,
    this.mobileNumber,
    this.email,
    required this.authenticationType,
    this.countryCode,
    this.userEmail,
    this.userName,
    required this.uid,
    required this.loginType,
    required this.isError,
    this.errorMessage = '',
    this.hasPassword = false,
  });

  final String statusCode;
  final String authenticationType;
  final String uid;
  final String? countryCode;
  final String? mobileNumber;
  final String? email;
  final String? userEmail;
  final String? userName;
  final LogInType loginType;
  final bool isError;
  final String errorMessage;
  final bool hasPassword;
}

class CheckIsUserExistsFailure extends CheckIsUserExistsState {
  CheckIsUserExistsFailure({required this.errorMessage});

  final String errorMessage;
}
