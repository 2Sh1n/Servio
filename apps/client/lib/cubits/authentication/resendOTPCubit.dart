import 'package:e_demand/app/generalImports.dart';

abstract class ResendOtpState {}

class ResendOtpInitial extends ResendOtpState {}

class ResendOtpInProcess extends ResendOtpState {}

class ResendOtpSuccess extends ResendOtpState {}

class ResendOtpFail extends ResendOtpState {
  ResendOtpFail(this.error);

  final dynamic error;
}

class ResendOtpCubit extends Cubit<ResendOtpState> {
  ResendOtpCubit() : super(ResendOtpInitial());
  final AuthenticationRepository authRepo = AuthenticationRepository();

  Future<void> resendOtp({
    final String? phoneNumber,
    final String? countryCode,
    final String? email,
    required final String authenticationType,
    final VoidCallback? onOtpSent,
  }) async {
    try {
      emit(ResendOtpInProcess());

      // Check if it's email or phone
      final bool isEmail = email != null && email.isNotEmpty;

      if (authenticationType == "sms_gateway" || isEmail) {
        final Map<String, dynamic> response =
            await authRepo.resendOTPUsingSMSGateway(
          phoneNumber: isEmail ? null : phoneNumber,
          countryCode: isEmail ? null : countryCode,
          email: isEmail ? email : null,
        );
        if (response["error"]) {
          emit(ResendOtpFail(response["message"]));
        } else {
          onOtpSent?.call();
          emit(ResendOtpSuccess());
        }
      } else {
        await authRepo.verifyPhoneNumber(
          "$countryCode$phoneNumber",
          onError: (final err) {
            emit(ResendOtpFail(err));
          },
          onCodeSent: () {
            onOtpSent?.call();
            emit(ResendOtpSuccess());
          },
        );
      }
    } on FirebaseAuthException catch (error) {
      emit(ResendOtpFail(error.message));
    } catch (e) {
      emit(ResendOtpFail(e.toString()));
    }
  }

  void setDefaultOtpState() {
    emit(ResendOtpInitial());
  }
}
