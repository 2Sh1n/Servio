import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class SetPasswordScreen extends StatefulWidget {
  const SetPasswordScreen({
    this.resetToken,
    this.email,
    this.phoneNumber,
    this.countryCode,
    required this.source,
    this.isFirstTimePasswordSet = false,
    this.authenticationType = 'sms_gateway',
    final Key? key,
  }) : super(key: key);

  final String? resetToken;
  final String? email;
  final String? phoneNumber;
  final String? countryCode;
  final bool isFirstTimePasswordSet;
  final String authenticationType;
  final String source;

  @override
  State<SetPasswordScreen> createState() => _SetPasswordScreenState();

  static Route route(final RouteSettings routeSettings) {
    final arguments = routeSettings.arguments as Map<String, dynamic>;
    return CupertinoPageRoute(
      builder: (final _) => MultiBlocProvider(
        providers: [
          BlocProvider(create: (context) => ChangePasswordCubit()),
        ],
        child: SetPasswordScreen(
          source: arguments['source'] as String? ?? '',
          resetToken: arguments['resetToken'] as String?,
          email: arguments['email'] as String?,
          phoneNumber: arguments['phoneNumber'] as String?,
          countryCode: arguments['countryCode'] as String?,
          isFirstTimePasswordSet:
              arguments['isFirstTimePasswordSet'] as bool? ?? false,
          authenticationType:
              arguments['authenticationType'] as String? ?? 'sms_gateway',
        ),
      ),
    );
  }
}

class _SetPasswordScreenState extends State<SetPasswordScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();

  @override
  void dispose() {
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  void _onSetPasswordButtonPressed() {
    UiUtils.removeFocus();

    if (!_formKey.currentState!.validate()) {
      return;
    }

    // For Firebase authentication, pass phone number and country code
    // For SMS Gateway, pass reset token
    if (widget.authenticationType == 'firebase' && widget.phoneNumber != null) {
      context.read<ChangePasswordCubit>().setPassword(
            newPassword: _newPasswordController.text,
            phoneNumber: widget.phoneNumber,
            countryCode: widget.countryCode,
            loginType: LogInType.phone,
          );
    } else {
      context.read<ChangePasswordCubit>().setPassword(
            newPassword: _newPasswordController.text,
            resetToken: widget.resetToken,
            loginType: widget.email != null && widget.email!.isNotEmpty
                ? LogInType.email
                : LogInType.phone,
          );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.primaryColor,
      appBar: UiUtils.getSimpleAppBar(
        context: context,
        title: "setPassword",
      ),
      body: BlocListener<ChangePasswordCubit, ChangePasswordState>(
        listener: (context, state) async {
          if (state is ChangePasswordSuccess) {
            UiUtils.showMessage(
              context,
              state.message.translate(context: context),
              ToastificationType.success,
            );

            if (widget.isFirstTimePasswordSet) {
              // First-time password set - auto-login with the new password
              final bool isEmail =
                  widget.email != null && widget.email!.isNotEmpty;
              final String loginInput =
                  isEmail ? widget.email! : widget.phoneNumber!;

              try {
                await Future.delayed(const Duration(milliseconds: 500));

                // Call login API with password
                final latitude = HiveRepository.getLatitude ?? '0.0';
                final longitude = HiveRepository.getLongitude ?? '0.0';

                String? fcmId;
                try {
                  await FirebaseMessaging.instance
                      .getToken()
                      .then((value) async {
                    fcmId = value;
                  });
                } catch (_) {}

                final UserDetailsModel userDetails =
                    await AuthenticationRepository.loginUser(
                  uid: '',
                  fcmId: fcmId,
                  latitude: latitude,
                  longitude: longitude,
                  loginType: isEmail ? LogInType.email : LogInType.phone,
                  mobileNumber: isEmail ? null : widget.phoneNumber,
                  countryCode: isEmail ? null : widget.countryCode,
                  email: isEmail ? widget.email : null,
                  password: _newPasswordController.text,
                );

                if (mounted) {
                  context.read<UserDetailsCubit>().setUserDetails(userDetails);
                  HiveRepository.setUserFirstTimeInApp = false;
                  HiveRepository.setUserLoggedIn = true;
                  context.read<AuthenticationCubit>().checkStatus();

                  // Navigate to home
                  Navigator.of(context).pushNamedAndRemoveUntil(
                    navigationRoute,
                    (route) => false,
                  );
                }
              } catch (e) {
                if (mounted) {
                  UiUtils.showMessage(
                    context,
                    e.toString().translate(context: context),
                    ToastificationType.error,
                  );
                  // If auto-login fails, navigate to login screen
                  Navigator.of(context).pushNamedAndRemoveUntil(
                    loginRoute,
                    (route) => false,
                    arguments: {
                      'source': widget.source,
                      'prefillValue': loginInput,
                    },
                  );
                }
              }
            } else {
              // Forgot password - navigate back to login with pre-filled email/phone
              Navigator.of(context).pushNamedAndRemoveUntil(
                loginRoute,
                (route) => false,
                arguments: {
                  'source': widget.source,
                  'prefillValue': widget.email ?? widget.phoneNumber ?? '',
                },
              );
            }
          } else if (state is ChangePasswordFail) {
            UiUtils.showMessage(
              context,
              state.error.translate(context: context),
              ToastificationType.error,
            );
          }
        },
        child: SafeArea(
          child: Form(
            key: _formKey,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CustomText(
                    "createNewPassword".translate(context: context),
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Theme.of(context).colorScheme.blackColor,
                  ),
                  const CustomSizedBox(height: 10),
                  CustomText(
                    "enterNewPasswordToContinue".translate(context: context),
                    fontSize: 14,
                    color: Theme.of(context)
                        .colorScheme
                        .blackColor
                        .withValues(alpha: 0.6),
                  ),
                  const CustomSizedBox(height: 30),
                  CustomPasswordFieldWithValidators(
                    controller: _newPasswordController,
                    labelText: "newPassword".translate(context: context),
                    textInputAction: TextInputAction.next,
                    validator: (value) => isValidPassword(
                      password: value ?? '',
                      context: context,
                    ),
                  ),
                  const CustomSizedBox(height: 20),
                  CustomTextFormField(
                    controller: _confirmPasswordController,
                    labelText: "confirmPassword".translate(context: context),
                    isPassword: true,
                    textInputAction: TextInputAction.done,
                    validator: (value) => confirmPasswordValidator(
                      password: _newPasswordController.text,
                      confirmPassword: value ?? '',
                      context: context,
                    ),
                  ),
                  const CustomSizedBox(height: 40),
                  BlocBuilder<ChangePasswordCubit, ChangePasswordState>(
                    builder: (context, state) {
                      final isLoading = state is ChangePasswordInProgress;
                      return CustomRoundedButton(
                        widthPercentage: 1,
                        backgroundColor:
                            Theme.of(context).colorScheme.accentColor,
                        buttonTitle: isLoading
                            ? ""
                            : "setPassword".translate(context: context),
                        showBorder: false,
                        onTap: isLoading ? null : _onSetPasswordButtonPressed,
                        titleColor: AppColors.whiteColors,
                        child: isLoading
                            ? const CustomCircularProgressIndicator()
                            : null,
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
