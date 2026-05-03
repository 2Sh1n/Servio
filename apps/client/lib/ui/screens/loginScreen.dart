import 'package:e_demand/data/enums/userStatusEnum.dart';
import 'package:e_demand/ui/widgets/authenticationScreenBackground.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../app/generalImports.dart';

class LogInScreen extends StatefulWidget {
  const LogInScreen({required this.source, final Key? key}) : super(key: key);
  final String source;

  @override
  State<LogInScreen> createState() => _LogInScreenState();

  static Route route(final RouteSettings routeSettings) {
    final arguments = routeSettings.arguments as Map<String, dynamic>;
    final String source = arguments['source'];

    return CupertinoPageRoute(
      builder: (final _) => LogInScreen(source: source),
    );
  }
}

class _LogInScreenState extends State<LogInScreen> {
  String phoneNumberWithCountryCode = '';
  String onlyPhoneNumber = '';
  String countryCode = '';

  final GlobalKey<FormState> verifyPhoneNumberFormKey = GlobalKey<FormState>();
  final TextEditingController _numberFieldController = TextEditingController();

  ValueNotifier<int> numberLength = ValueNotifier(0);

  final TextEditingController _passwordController = TextEditingController();
  bool showPasswordField = false;
  bool userHasPassword = false;

  @override
  void dispose() {
    _numberFieldController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();

    if (context.read<SystemSettingCubit>().isDemoModeEnabled) {
      _numberFieldController.text = "9876543210";
      _passwordController.text = "Test@123";
      numberLength.value = 10;
    }
  }

  void _onContinueButtonClicked() {
    UiUtils.removeFocus();

    final inputText = _numberFieldController.text.trim();
    if (inputText.isEmpty) {
      return;
    }

    final bool isEmail = !RegExp(r'^[0-9]+$').hasMatch(inputText);

    // Validate based on input type
    if (!isEmail) {
      if (numberLength.value < UiUtils.minimumMobileNumberDigit ||
          numberLength.value > UiUtils.maximumMobileNumberDigit) {
        return;
      }
    }

    final bool isValidInput = verifyPhoneNumberFormKey.currentState!.validate();

    if (!isValidInput) {
      return;
    }

    // Get country code for phone numbers
    final String? countryCallingCode = isEmail
        ? null
        : context.read<CountryCodeCubit>().getSelectedCountryCode();

    // Check if password login is enabled and user wants to login with password
    final bool isPasswordLoginEnabled =
        context
            .read<SystemSettingCubit>()
            .loginSettings
            ?.isPasswordLoginEnabled ??
        false;

    if (isPasswordLoginEnabled &&
        showPasswordField &&
        _passwordController.text.isNotEmpty) {
      // Login with password
      _loginWithPassword(inputText, countryCallingCode);
    } else {
      // Check if user exists and has password
      phoneNumberWithCountryCode = isEmail
          ? inputText
          : (countryCallingCode! + inputText);
      onlyPhoneNumber = isEmail ? '' : inputText;
      countryCode = countryCallingCode ?? '';

      context.read<CheckIsUserExistsCubit>().isUserExists(
        mobileNumber: isEmail ? null : onlyPhoneNumber,
        countryCode: isEmail ? null : countryCode,
        email: isEmail ? inputText : null,
        uid: '',
        loginType: isEmail ? LogInType.email : LogInType.phone,
      );
    }
  }

  bool _isLoggingIn = false;
  bool _isForgotPasswordFlow = false;

  void _handleForgotPasswordClick() {
    final inputText = _numberFieldController.text.trim();
    if (inputText.isEmpty) {
      UiUtils.showMessage(
        context,
        'pleaseEnterEmailOrPhone'.translate(context: context),
        ToastificationType.warning,
      );
      return;
    }

    final bool isEmail = !RegExp(r'^[0-9]+$').hasMatch(inputText);

    final String? countryCallingCode = isEmail
        ? null
        : context.read<CountryCodeCubit>().getSelectedCountryCode();

    phoneNumberWithCountryCode = isEmail
        ? inputText
        : (countryCallingCode! + inputText);
    onlyPhoneNumber = isEmail ? '' : inputText;
    countryCode = countryCallingCode ?? '';

    // Set flag to trigger forgot password flow
    _isForgotPasswordFlow = true;

    // For forgot password, we need to check if user exists first to get authentication type
    // This is necessary because the authentication mode comes from the backend
    context.read<CheckIsUserExistsCubit>().isUserExists(
      mobileNumber: isEmail ? null : onlyPhoneNumber,
      countryCode: isEmail ? null : countryCode,
      email: isEmail ? inputText : null,
      uid: '',
      loginType: isEmail ? LogInType.email : LogInType.phone,
      isForForgotPassword: true,
    );
  }

  Future<void> _loginWithPassword(String input, String? countryCode) async {
    if (_isLoggingIn) return;

    setState(() {
      _isLoggingIn = true;
    });

    final latitude = HiveRepository.getLatitude ?? '0.0';
    final longitude = HiveRepository.getLongitude ?? '0.0';

    String fcmId = '';
    try {
      fcmId = await FirebaseMessaging.instance.getToken() ?? '';
    } catch (_) {}

    if (!mounted) return;

    // Determine if input is email or phone
    final bool isEmail = input.contains('@');

    try {
      // Use manage_user API (loginUser) with password parameter
      final UserDetailsModel userDetails =
          await AuthenticationRepository.loginUser(
            uid: '',
            fcmId: fcmId,
            latitude: latitude,
            longitude: longitude,
            loginType: isEmail ? LogInType.email : LogInType.phone,
            email: isEmail ? input : null,
            mobileNumber: isEmail ? null : input,
            countryCode: isEmail ? null : countryCode,
            password: _passwordController.text,
          );

      // Store user data
      HiveRepository.setUserFirstTimeInApp = false;
      HiveRepository.setUserLoggedIn = true;

      if (mounted) {
        context.read<AuthenticationCubit>().checkStatus();
        context.read<UserDetailsCubit>().setUserDetails(userDetails);

        UiUtils.showMessage(
          context,
          'userLoggedInSuccessfully'.translate(context: context),
          ToastificationType.success,
        );

        // Fetch initial data
        await context.read<SystemSettingCubit>().getSystemSettings();
        await context.read<UserDetailsCubit>().loadUserDetails();
        final futures = <Future>[
          context.read<BookingCubit>().fetchBookingDetails(status: ''),
          context.read<HomeScreenCubit>().fetchHomeScreenData(),
          context.read<CartCubit>().getCartDetails(isReorderCart: false),
          context.read<BookmarkCubit>().fetchBookmark(type: "list"),
          context.read<MyRequestListCubit>().fetchRequests(),
        ];
        Future.wait(futures);

        // Navigate to home
        if (mounted) {
          Navigator.pushNamedAndRemoveUntil(
            context,
            navigationRoute,
            (route) => false,
          );
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoggingIn = false;
        });

        UiUtils.showMessage(
          context,
          e.toString().translate(context: context),
          ToastificationType.error,
        );
      }
    }
  }

  Widget _buildPhoneNumberFiled() => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 20),
    child: Column(
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: EmailOrPhoneField(
                controller: _numberFieldController,
                isReadOnly: false,
                onChanged: (number) {
                  numberLength.value = number.length;
                  setState(() {
                    showPasswordField = false;
                  });
                },
                isPhoneAuthEnabled:
                    context
                        .read<SystemSettingCubit>()
                        .loginSettings
                        ?.isPhoneAuthEnabled ??
                    false,
                isEmailAuthEnabled:
                    context
                        .read<SystemSettingCubit>()
                        .loginSettings
                        ?.isEmailAuthEnabled ??
                    false,
              ),
            ),
            if (!showPasswordField) ...[
              const CustomSizedBox(width: 12),
              _buildSignInWithMobileButton(),
            ],
          ],
        ),
        if (showPasswordField) ...[
          const CustomSizedBox(height: 20),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: CustomTextFormField(
                  controller: _passwordController,
                  autoFocus: true,
                  fillColor: Theme.of(context).colorScheme.secondaryColor,
                  labelText: "password",
                  isPassword: true,
                  textInputAction: TextInputAction.done,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return "fieldMustNotBeEmpty".translate(context: context);
                    }
                    return null;
                  },
                ),
              ),
              const CustomSizedBox(width: 12),
              _buildSignInWithMobileButton(),
            ],
          ),
          const CustomSizedBox(height: 10),
          if (context
                  .read<SystemSettingCubit>()
                  .loginSettings
                  ?.isPasswordLoginEnabled ??
              false)
            Align(
              alignment: Alignment.centerRight,
              child: CustomInkWellContainer(
                onTap: () {
                  _handleForgotPasswordClick();
                },
                child: CustomText(
                  "forgotPassword".translate(context: context),
                  fontSize: 14,
                  color: Theme.of(context).colorScheme.accentColor,
                ),
              ),
            ),
        ],
      ],
    ),
  );

  Widget _buildLoginOrSignupWidget() => CustomText(
    'loginOrSignup'.translate(context: context),
    color: Theme.of(context).colorScheme.blackColor,
    fontWeight: FontWeight.w500,
    fontStyle: FontStyle.normal,
    fontSize: 16,
    textAlign: TextAlign.center,
  );

  Widget _buildWelcomeHeadingWidget() => Column(
    children: [
      CustomText(
        'welcomeTo'.translate(context: context),
        color: Theme.of(context).colorScheme.blackColor,
        fontWeight: FontWeight.w600,
        fontStyle: FontStyle.normal,
        fontSize: 28,
        textAlign: TextAlign.center,
      ),
      CustomText(
        appName,
        color: Theme.of(context).colorScheme.accentColor,
        fontWeight: FontWeight.w600,
        fontStyle: FontStyle.normal,
        fontSize: 28,
        textAlign: TextAlign.center,
      ),
    ],
  );

  @override
  Widget build(final BuildContext context) {
    final Size size = MediaQuery.sizeOf(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: UiUtils.getSystemUiOverlayStyle(context: context),
      child: Scaffold(
        body: SingleChildScrollView(
          child: CustomContainer(
            gradient: LinearGradient(
              colors: [
                context.colorScheme.secondaryColor,
                context.colorScheme.primaryColor,
              ],
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
            ),
            child: MultiBlocListener(
              listeners: [
                BlocListener<CheckIsUserExistsCubit, CheckIsUserExistsState>(
                  listener:
                      (
                        final BuildContext context,
                        final CheckIsUserExistsState state,
                      ) {
                        // Check if user has password and password login is enabled
                        if (state is CheckIsUserExistsSuccess) {
                          final bool isPasswordLoginEnabled =
                              context
                                  .read<SystemSettingCubit>()
                                  .loginSettings
                                  ?.isPasswordLoginEnabled ??
                              false;

                          if (isPasswordLoginEnabled &&
                              state.hasPassword == true) {
                            setState(() {
                              showPasswordField = true;
                              userHasPassword = true;
                            });
                          }
                        }
                        _handleCheckIsUserExitListener(state);
                      },
                ),
              ],
              child: AuthenticationScreenBackground(
                child: Stack(
                  children: [
                    Form(
                      key: verifyPhoneNumberFormKey,
                      child: Padding(
                        padding: ResponsiveHelper.isTabletDevice(context)
                            ? const EdgeInsetsDirectional.symmetric(
                                horizontal: 150,
                              )
                            : const EdgeInsetsDirectional.symmetric(
                                horizontal: 15,
                              ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            CustomSizedBox(height: context.screenHeight * 0.17),
                            _buildLogoWidget(),
                            const CustomSizedBox(height: 40),
                            CustomSizedBox(
                              width: context.screenWidth * 0.7,
                              child: LinearDivider(),
                            ),
                            const CustomSizedBox(height: 24),
                            _buildWelcomeHeadingWidget(),
                            const CustomSizedBox(height: 24),
                            _buildLoginOrSignupWidget(),
                            const CustomSizedBox(height: 24),
                            _buildPhoneNumberFiled(),
                            const CustomSizedBox(height: 32),
                            if (context
                                    .read<SystemSettingCubit>()
                                    .loginSettings
                                    ?.isSocialLoginEnabled ??
                                false) ...[
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  Expanded(
                                    child: CustomContainer(
                                      height: 0.5,
                                      gradient: LinearGradient(
                                        colors: [
                                          context.colorScheme.secondaryColor,
                                          context.colorScheme.lightGreyColor
                                              .withAlpha(80),
                                          context.colorScheme.lightGreyColor,
                                        ],
                                      ),
                                    ),
                                  ),
                                  const CustomSizedBox(width: 12),
                                  CustomText(
                                    " ${"orContinueWith".translate(context: context)} ",
                                    fontWeight: FontWeight.w400,
                                    color: context.colorScheme.lightGreyColor,
                                    fontSize: 14,
                                  ),
                                  const CustomSizedBox(width: 12),
                                  Expanded(
                                    child: CustomContainer(
                                      height: 0.5,
                                      gradient: LinearGradient(
                                        colors: [
                                          context.colorScheme.lightGreyColor,
                                          context.colorScheme.lightGreyColor
                                              .withAlpha(80),
                                          context.colorScheme.secondaryColor,
                                        ],
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const CustomSizedBox(height: 24),
                              if (Platform.isIOS) ...[
                                Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 20,
                                  ),
                                  child: _buildSignInWithAppleButton(),
                                ),
                                const CustomSizedBox(height: 24),
                              ],
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 20,
                                ),
                                child: _buildSignInWithGoogleButton(),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                    Positioned.directional(
                      top: 55,
                      end: 15,
                      textDirection: Directionality.of(context),
                      child: BlocBuilder<VerifyOtpCubit, VerifyOtpState>(
                        builder: (final context, final VerifyOtpState state) =>
                            CustomInkWellContainer(
                              onTap:
                                  (state is SendVerificationCodeInProgressState)
                                  ? () {
                                      UiUtils.showMessage(
                                        context,
                                        'verificationIsInProgress'.translate(
                                          context: context,
                                        ),
                                        ToastificationType.warning,
                                      );
                                    }
                                  : () async {
                                      HiveRepository
                                              .setUserSkippedTheLoginBefore =
                                          true;
                                      if (widget.source == 'dialog') {
                                        Navigator.pop(context);
                                      } else if (Routes.previousRoute ==
                                              onBoardingRoute ||
                                          Routes.previousRoute == splashRoute) {
                                        await Navigator.pushReplacementNamed(
                                          context,
                                          navigationRoute,
                                        );
                                      } else if (Routes.previousRoute ==
                                              navigationRoute ||
                                          Routes.previousRoute == loginRoute) {
                                        Navigator.pop(context);
                                      } else {
                                        await Navigator.pushReplacementNamed(
                                          context,
                                          navigationRoute,
                                        );
                                      }
                                    },
                              child: CustomContainer(
                                border: Border.all(
                                  color: context.colorScheme.lightGreyColor,
                                  width: 0.5,
                                ),
                                borderRadius: UiUtils.borderRadiusOf5,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 20,
                                  vertical: 8,
                                ),
                                child: CustomText(
                                  'skip'.translate(context: context),
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.blackColor,
                                ),
                              ),
                            ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
        bottomNavigationBar: CustomContainer(
          padding: EdgeInsetsDirectional.only(bottom: 15.rh(context)),
          width: size.width,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              CustomText(
                "by_continue_agree".translate(context: context),
                textAlign: TextAlign.center,
                fontWeight: FontWeight.w400,
                color: Theme.of(context).colorScheme.lightGreyColor,
                fontSize: 12,
              ),
              Padding(
                padding: const EdgeInsetsDirectional.only(top: 5),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CustomInkWellContainer(
                      onTap: () {
                        Navigator.of(context).pushNamed(
                          appSettingsRoute,
                          arguments: 'termsofservice',
                        );
                      },
                      child: CustomText(
                        "terms_service".translate(context: context),
                        color: Theme.of(context).colorScheme.blackColor,
                        showUnderline: true,
                        fontWeight: FontWeight.w400,
                        fontSize: 12,
                      ),
                    ),
                    CustomText(
                      " & ",
                      color: Theme.of(context).colorScheme.lightGreyColor,
                      fontSize: 12,
                      fontWeight: FontWeight.w400,
                    ),
                    CustomInkWellContainer(
                      onTap: () {
                        Navigator.of(context).pushNamed(
                          appSettingsRoute,
                          arguments: 'privacyAndPolicy',
                        );
                      },
                      child: CustomText(
                        "privacyAndPolicy".translate(context: context),
                        color: Theme.of(context).colorScheme.blackColor,
                        fontWeight: FontWeight.w400,
                        showUnderline: true,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget
  _buildSignInWithMobileButton() => BlocConsumer<CheckIsUserExistsCubit, CheckIsUserExistsState>(
    listener: (final BuildContext context, final CheckIsUserExistsState state) async {
      if (state is CheckIsUserExistsSuccess) {
        if (state.loginType != LogInType.phone &&
            state.loginType != LogInType.email) {
          return;
        }

        if (state.isError) {
          UiUtils.showMessage(
            context,
            state.errorMessage,
            ToastificationType.warning,
          );
          return;
        }

        final userStatus = UserStatus.fromCode(state.statusCode);

        if (userStatus == null) {
          UiUtils.showMessage(
            context,
            'unknownStatus'.translate(context: context),
            ToastificationType.error,
          );
          return;
        }

        if (userStatus == UserStatus.deactive) {
          UiUtils.showMessage(
            context,
            'yourAccountIsDeActive'.translate(context: context),
            ToastificationType.warning,
          );
          Navigator.pop(context);
          return;
        } else {
          // Check if password login is enabled and user has password
          final bool isPasswordLoginEnabled =
              context
                  .read<SystemSettingCubit>()
                  .loginSettings
                  ?.isPasswordLoginEnabled ??
              false;

          // Check if it's an email or phone
          final bool isEmailAuth =
              state.email != null && state.email!.isNotEmpty;

          // Send OTP for:
          // 1. New users (status 102) - need to verify before registration
          // 2. Forgot password flow
          // 3. Existing users without password (status 101) - need to set password
          if (userStatus == UserStatus.notRegistered ||
              _isForgotPasswordFlow ||
              (userStatus == UserStatus.active &&
                  isPasswordLoginEnabled &&
                  !state.hasPassword)) {
            // Determine if this is for new user registration or password set
            final bool isNewUserRegistration =
                userStatus == UserStatus.notRegistered;
            _isForgotPasswordFlow = false; // Reset the flag

            if (state.authenticationType == "sms_gateway" || isEmailAuth) {
              // SMS Gateway - navigate directly to OTP screen
              // Note: For forgot password and new user, verify_user already sends OTP,
              // so we don't need autoSendOtp flag to avoid duplicate API calls

              // Show success message before navigating
              UiUtils.showMessage(
                context,
                'otpSentSuccessfully'.translate(context: context),
                ToastificationType.success,
              );

              Future.delayed(const Duration(milliseconds: 500), () {
                Navigator.pushNamed(
                  context,
                  otpVerificationRoute,
                  arguments: {
                    'phoneNumberWithCountryCode': phoneNumberWithCountryCode,
                    'phoneNumberWithOutCountryCode': onlyPhoneNumber,
                    'countryCode': countryCode,
                    'email': state.email,
                    'source': widget.source,
                    "userAuthenticationCode": state.statusCode,
                    "authenticationType": state.authenticationType,
                    "hasPassword": state.hasPassword,
                    "isForPasswordSet": !isNewUserRegistration,
                    "autoSendOtp": false,
                  },
                );
              });
            } else {
              // Firebase authentication - send OTP via Firebase then navigate
              context.read<SendVerificationCodeCubit>().sendVerificationCode(
                phoneNumber: phoneNumberWithCountryCode,
                userAuthenticationCode: state.statusCode,
                authenticationType: state.authenticationType,
                hasPassword: state.hasPassword,
                isForPasswordSet: !isNewUserRegistration,
              );
            }
            return;
          }

          // If user has password and password login is enabled, don't send OTP
          if (isPasswordLoginEnabled && state.hasPassword == true) {
            // Password field will be shown via the listener above
            // Don't send OTP, user should login with password
            return;
          }

          if (state.authenticationType == "sms_gateway" || isEmailAuth) {
            UiUtils.showMessage(
              context,
              'otpSentSuccessfully'.translate(context: context),
              ToastificationType.success,
            );
            Future.delayed(const Duration(milliseconds: 500), () {
              Navigator.pushNamed(
                context,
                otpVerificationRoute,
                arguments: {
                  'phoneNumberWithCountryCode': phoneNumberWithCountryCode,
                  'phoneNumberWithOutCountryCode': onlyPhoneNumber,
                  'countryCode': countryCode,
                  'email': state.email,
                  'source': widget.source,
                  "userAuthenticationCode": state.statusCode,
                  "authenticationType": state.authenticationType,
                  "hasPassword": state.hasPassword,
                },
              );
            });
          } else {
            context.read<SendVerificationCodeCubit>().sendVerificationCode(
              phoneNumber: phoneNumberWithCountryCode,
              userAuthenticationCode: state.statusCode,
              authenticationType: state.authenticationType,
              hasPassword: state.hasPassword,
            );
          }
        }
      }
    },
    builder: (context, checkIsUserExistsState) {
      final bool isCheckingUserExists =
          checkIsUserExistsState is CheckIsUserExistsInProgress;
      return BlocConsumer<SendVerificationCodeCubit, SendVerificationCodeState>(
        listener: (final BuildContext context, final verifyPhoneNumberState) =>
            _handleVerifyPhoneNumberListener(context, verifyPhoneNumberState),
        builder:
            (
              final BuildContext context,
              final SendVerificationCodeState verifyPhoneNumberState,
            ) {
              final bool isVerifyingPhoneNumber =
                  verifyPhoneNumberState is SendVerificationCodeInProgressState;

              return ValueListenableBuilder(
                valueListenable: numberLength,
                builder: (context, numberLength, _) {
                  final inputText = _numberFieldController.text.trim();
                  final bool isEmail = !RegExp(r'^[0-9]+$').hasMatch(inputText);

                  // Determine button state
                  bool isValidInput = false;
                  bool isTooLong = false;

                  if (inputText.isEmpty) {
                    isValidInput = false;
                    isTooLong = false;
                  } else if (isEmail) {
                    // Validate email
                    isValidInput = RegExp(
                      r'^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$',
                    ).hasMatch(inputText);
                    isTooLong = false; // Email doesn't have max length check
                  } else {
                    // Validate phone number length
                    isValidInput =
                        numberLength >= UiUtils.minimumMobileNumberDigit &&
                        numberLength <= UiUtils.maximumMobileNumberDigit;
                    isTooLong = numberLength > UiUtils.maximumMobileNumberDigit;
                  }

                  // Determine colors
                  Color backgroundColor;
                  Color iconColor;

                  if (isTooLong) {
                    // Red when exceeding maximum
                    backgroundColor = AppColors.redColor.withAlpha(30);
                    iconColor = AppColors.redColor;
                  } else if (isValidInput) {
                    // Primary accent when valid
                    backgroundColor = context.colorScheme.accentColor;
                    iconColor = AppColors.whiteColors;
                  } else {
                    // Primary with opacity when empty or too short
                    backgroundColor = context.colorScheme.lightGreyColor
                        .withAlpha(30);
                    iconColor = context.colorScheme.lightGreyColor;
                  }

                  return CustomSizedBox(
                    height: 48,
                    width: 48,
                    child: CustomRoundedButton(
                      buttonTitle: '',
                      showBorder: false,
                      widthPercentage: 1,
                      onTap: () {
                        if (verifyPhoneNumberState
                            is SendVerificationCodeInProgressState) {
                          return;
                        } else if (checkIsUserExistsState
                            is CheckIsUserExistsInProgress) {
                          return;
                        } else if (_isLoggingIn) {
                          return;
                        }
                        // Prevent tap if input is invalid
                        if (!isValidInput) {
                          // Trigger form validation to show error messages
                          verifyPhoneNumberFormKey.currentState?.validate();
                          return;
                        }
                        _onContinueButtonClicked();
                      },
                      backgroundColor: backgroundColor,
                      child:
                          (isVerifyingPhoneNumber ||
                              isCheckingUserExists ||
                              _isLoggingIn)
                          ? const CustomSizedBox(
                              height: 20,
                              width: 20,
                              child: CustomCircularProgressIndicator(
                                color: AppColors.whiteColors,
                              ),
                            )
                          : CustomSvgPicture(
                              svgImage:
                                  Directionality.of(
                                    context,
                                  ).toString().contains(
                                    TextDirection.RTL.value.toLowerCase(),
                                  )
                                  ? AppAssets.loginArrowLft
                                  : AppAssets.loginArrow,
                              color: iconColor,
                            ),
                    ),
                  );
                },
              );
            },
      );
    },
  );

  Widget _buildSignInWithGoogleButton() {
    return BlocBuilder<CheckIsUserExistsCubit, CheckIsUserExistsState>(
      builder: (context, verificationState) {
        final bool isVerificationLoading =
            verificationState is CheckIsUserExistsInProgress &&
            verificationState.loginType == LogInType.google;

        return BlocConsumer<GoogleLoginCubit, GoogleLoginState>(
          listener: (context, state) {
            if (state is GoogleLoginFailureState) {
              UiUtils.showMessage(
                context,
                state.errorMessage,
                ToastificationType.error,
              );
            } else if (state is GoogleLoginSuccessState) {
              if (state.userDetails == null) {
                UiUtils.showMessage(
                  context,
                  'loginProcessCanceled'
                      .translate(context: context)
                      .translate(context: context),
                  ToastificationType.warning,
                );
                return;
              }
              context.read<CheckIsUserExistsCubit>().isUserExists(
                uid: state.userDetails?.uid ?? '',
                loginType: LogInType.google,
                userName: state.userDetails?.displayName ?? '',
                userEmail: state.userDetails?.email,
              );
            }
          },
          builder: (context, state) {
            final bool isLoading = state is GoogleLoginInProgressState;

            return CustomRoundedButton(
              buttonTitle: '',
              showBorder: true,
              widthPercentage: 1,
              radius: UiUtils.borderRadiusOf10,
              borderColor: context.colorScheme.lightGreyColor,
              titleColor: Theme.of(context).colorScheme.blackColor,
              backgroundColor:
                  context.watch<SendVerificationCodeCubit>().state
                          is SendVerificationCodeInProgressState ||
                      context.watch<CheckIsUserExistsCubit>().state
                          is CheckIsUserExistsInProgress
                  ? Theme.of(context).colorScheme.lightGreyColor.withAlpha(30)
                  : context.colorScheme.secondaryColor,
              child: isLoading || isVerificationLoading
                  ? const CustomCircularProgressIndicator()
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const CustomSvgPicture(
                          avoideResponsive: true,
                          svgImage: AppAssets.googleIcon,
                          height: 22,
                          width: 22,
                        ),
                        const CustomSizedBox(width: 15),
                        CustomText(
                          'signInWithGoogle'.translate(context: context),
                        ),
                      ],
                    ),
              onTap: () {
                if (context.read<SendVerificationCodeCubit>().state
                    is SendVerificationCodeInProgressState) {
                  return;
                }
                //check that also is verification in process by other method
                if (isLoading ||
                    isVerificationLoading ||
                    verificationState is CheckIsUserExistsInProgress) {
                  return;
                }
                context.read<GoogleLoginCubit>().loginWithGoogle();
              },
            );
          },
        );
      },
    );
  }

  Widget _buildSignInWithAppleButton() {
    return BlocBuilder<CheckIsUserExistsCubit, CheckIsUserExistsState>(
      builder: (context, verificationState) {
        final bool isVerificationLoading =
            verificationState is CheckIsUserExistsInProgress &&
            verificationState.loginType == LogInType.apple;

        return BlocConsumer<AppleLoginCubit, AppleLoginState>(
          listener: (context, state) {
            if (state is AppleLoginFailureState) {
              UiUtils.showMessage(
                context,
                state.errorMessage,
                ToastificationType.error,
              );
            } else if (state is AppleLoginSuccessState) {
              if (state.userDetails == null) {
                UiUtils.showMessage(
                  context,
                  'loginProcessCanceled'.translate(context: context),
                  ToastificationType.warning,
                );
                return;
              }
              context.read<CheckIsUserExistsCubit>().isUserExists(
                uid: state.userDetails?.uid ?? '',
                loginType: LogInType.apple,
                userName: state.userDetails?.displayName ?? '',
                userEmail: state.userDetails?.email,
              );
            }
          },
          builder: (context, state) {
            final bool isLoading = state is AppleLoginInProgressState;

            return CustomRoundedButton(
              buttonTitle: '',
              showBorder: true,
              borderColor: context.colorScheme.lightGreyColor,
              widthPercentage: 1,
              radius: UiUtils.borderRadiusOf10,
              titleColor: Theme.of(context).colorScheme.blackColor,
              backgroundColor:
                  context.watch<SendVerificationCodeCubit>().state
                          is SendVerificationCodeInProgressState ||
                      context.watch<CheckIsUserExistsCubit>().state
                          is CheckIsUserExistsInProgress
                  ? Theme.of(context).colorScheme.lightGreyColor.withAlpha(30)
                  : context.colorScheme.secondaryColor,
              child: isLoading || isVerificationLoading
                  ? const CustomCircularProgressIndicator()
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        CustomSvgPicture(
                          svgImage: AppAssets.appleIcon,
                          avoideResponsive: true,
                          height: 24,
                          width: 24,
                          color: context.colorScheme.blackColor,
                        ),
                        const CustomSizedBox(width: 15),
                        CustomText(
                          'signInWithApple'.translate(context: context),
                        ),
                      ],
                    ),
              onTap: () {
                if (context.read<SendVerificationCodeCubit>().state
                    is SendVerificationCodeInProgressState) {
                  return;
                }
                //check that also is verification in process by other method

                if (isLoading ||
                    isVerificationLoading ||
                    verificationState is CheckIsUserExistsInProgress) {
                  return;
                }
                context.read<AppleLoginCubit>().loginWithApple();
              },
            );
          },
        );
      },
    );
  }

  Future<void> _handleCheckIsUserExitListener(
    CheckIsUserExistsState state,
  ) async {
    if (state is CheckIsUserExistsFailure) {
      UiUtils.showMessage(
        context,
        state.errorMessage,
        ToastificationType.error,
      );
    } else if (state is CheckIsUserExistsSuccess) {
      // Phone and email login are handled in _buildSignInWithMobileButton listener
      // This listener only handles Apple and Google login
      if (state.loginType == LogInType.phone ||
          state.loginType == LogInType.email) {
        return;
      }

      if (state.isError) {
        UiUtils.showMessage(
          context,
          state.errorMessage,
          ToastificationType.warning,
        );
        return;
      }
      final userStatus = UserStatus.fromCode(state.statusCode);

      if (userStatus == null) {
        UiUtils.showMessage(context, 'unknownStatus', ToastificationType.error);
        return;
      }

      if (userStatus == UserStatus.deactive) {
        UiUtils.showMessage(
          context,
          'yourAccountIsDeActive'.translate(context: context),
          ToastificationType.warning,
        );
        // Navigator.pop(context);
        return;
      } else if (userStatus == UserStatus.active) {
        final latitude = HiveRepository.getLatitude ?? "0.0";
        final longitude = HiveRepository.getLongitude ?? "0.0";
        //update fcm id
        String fcmId = '';
        try {
          fcmId = await FirebaseMessaging.instance.getToken() ?? '';
        } catch (_) {}

        await AuthenticationRepository.loginUser(
          uid: state.uid,
          latitude: latitude.toString(),
          longitude: longitude.toString(),
          loginType: state.loginType,
          fcmId: fcmId,
        ).then((final UserDetailsModel value) {
          HiveRepository.setUserFirstTimeInApp = false;
          HiveRepository.setUserLoggedIn = true;

          context.read<AuthenticationCubit>().checkStatus();

          UiUtils.showMessage(
            context,
            "userLoggedInSuccessfully".translate(context: context),
            ToastificationType.success,
          );

          WidgetsBinding.instance.addPostFrameCallback((_) async {
            try {
              await context.read<SystemSettingCubit>().getSystemSettings();
              // List of all async calls
              final futures = <Future>[
                context.read<BookingCubit>().fetchBookingDetails(status: ''),
                context.read<HomeScreenCubit>().fetchHomeScreenData(),
                context.read<CartCubit>().getCartDetails(isReorderCart: false),
                context.read<BookmarkCubit>().fetchBookmark(type: "list"),
                context.read<MyRequestListCubit>().fetchRequests(),
                context.read<UserDetailsCubit>().loadUserDetails(),
              ];

              // Wait for all calls to complete
              Future.wait(futures);
            } catch (e, stack) {
              debugPrint('Error fetching initial data: $e\n$stack');
            }

            // Check if the widget is still mounted before navigating
            if (!mounted) return;

            // Check if password login is enabled
            final bool isPasswordLoginEnabled =
                context
                    .read<SystemSettingCubit>()
                    .loginSettings
                    ?.isPasswordLoginEnabled ??
                false;

            // Check if user has password - if not, redirect to edit profile to set password
            // Only apply this check for phone/email login, NOT for social logins (Apple/Google)
            if (isPasswordLoginEnabled &&
                value.hasPassword == false &&
                state.loginType != LogInType.apple &&
                state.loginType != LogInType.google) {
              Navigator.pushNamed(
                context,
                editProfileRoute,
                arguments: {
                  'source': widget.source,
                  'uid': value.id ?? '',
                  "userEmail": value.email,
                  "userName": value.username,
                  "loginType": state.loginType,
                  "isNewUser": false,
                  "hasPassword": false,
                  "phoneNumberWithOutCountryCode": value.phone,
                  "countryCode": value.countryCode,
                  "userAuthenticationCode": "101",
                },
              );
            } else {
              if (widget.source == 'dialog') {
                Navigator.pop(context);
              } else {
                // Reset bottom navigation bar index
                UiUtils
                        .bottomNavigationBarGlobalKey
                        .currentState
                        ?.selectedIndexOfBottomNavigationBar
                        .value =
                    0;

                // Navigate and remove all previous routes
                Navigator.of(context).pushNamedAndRemoveUntil(
                  navigationRoute,
                  (Route<dynamic> route) => false,
                );
              }
            }
          });
        });
      } else {
        await Navigator.pushNamed(
          context,
          editProfileRoute,
          arguments: {
            'source': widget.source,
            'uid': state.uid,
            "userEmail": state.userEmail,
            "userName": state.userName,
            "loginType": state.loginType,
            "isNewUser": true,
          },
        );
      }
    }
  }

  Widget _buildLogoWidget() {
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.15,
      child: Image.asset(
        Theme.of(context).colorScheme.brightness == Brightness.light
            ? AppAssets.loginLogoLight
            : AppAssets.loginLogoDark,
        fit: BoxFit.contain,
      ),
    );
  }

  void _handleVerifyPhoneNumberListener(
    BuildContext context,
    SendVerificationCodeState sendVerificationCodeState,
  ) {
    if (sendVerificationCodeState is SendVerificationCodeSuccessState) {
      Navigator.pushNamed(
        context,
        otpVerificationRoute,
        arguments: {
          'phoneNumberWithCountryCode': phoneNumberWithCountryCode,
          'phoneNumberWithOutCountryCode': onlyPhoneNumber,
          'countryCode': countryCode,
          'source': widget.source,
          "userAuthenticationCode":
              sendVerificationCodeState.userAuthenticationCode,
          "authenticationType": sendVerificationCodeState.authenticationType,
          "hasPassword": sendVerificationCodeState.hasPassword,
          "isForPasswordSet": sendVerificationCodeState.isForPasswordSet,
        },
      );
    } else if (sendVerificationCodeState is SendVerificationCodeFailureState) {
      String errorMessage = '';

      errorMessage = sendVerificationCodeState.error.toString().translate(
        context: context,
      );
      UiUtils.showMessage(context, errorMessage, ToastificationType.error);
    }
  }
}
