import 'package:e_demand/app/generalImports.dart';

abstract class GoogleLoginState {}

class GoogleLoginInitialState extends GoogleLoginState {}

class GoogleLoginInProgressState extends GoogleLoginState {}

class GoogleLoginSuccessState extends GoogleLoginState {
  final User? userDetails;
  final String message;

  GoogleLoginSuccessState({
    required this.message,
    this.userDetails,
  });
}

class GoogleLoginFailureState extends GoogleLoginState {
  String errorMessage;

  GoogleLoginFailureState({required this.errorMessage});
}

class GoogleLoginCubit extends Cubit<GoogleLoginState> {
  GoogleLoginCubit() : super(GoogleLoginInitialState());
  AuthenticationRepository _authenticationRepository =
      AuthenticationRepository();

  Future<void> loginWithGoogle() async {
    emit(GoogleLoginInProgressState());
    try {
      final Map<String, dynamic> response =
          await _authenticationRepository.signInWithGoogle();

      if (!response["isError"]) {
        final userDetails = response["userDetails"] as User?;
        emit(
          GoogleLoginSuccessState(
              message: response["message"], userDetails: userDetails),
        );
        await FirebaseAnalytics.instance.logLogin(
          loginMethod: 'signInWithGoogle',
        );
      } else {
        emit(GoogleLoginFailureState(errorMessage: response["message"]));
      }
    } catch (e) {
      emit(
        GoogleLoginFailureState(errorMessage: e.toString()),
      );
    }
  }
}
