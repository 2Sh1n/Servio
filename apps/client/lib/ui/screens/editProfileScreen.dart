import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:image_cropper/image_cropper.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({
    final Key? key,
    // this.phoneNumberWithCountryCode,
    this.phoneNumberWithOutCountryCode,
    this.countryCode,
    this.countryCodeName,
    required this.uid,
    this.source,
    required this.loginType,
    this.userEmail,
    this.userName,
    required this.isNewUser,
  }) : super(key: key);

  final String? phoneNumberWithOutCountryCode;
  final String? countryCode;
  final String? countryCodeName;
  final String uid;
  final String? userEmail;
  final String? userName;
  final LogInType? loginType;
  final String? source;
  final bool isNewUser;

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();

  static Route route(final RouteSettings routeSettings) => CupertinoPageRoute(
        builder: (final BuildContext context) {
          Map arguments = {};
          if (routeSettings.arguments != null) {
            arguments = routeSettings.arguments as Map;
          }

          return EditProfileScreen(
            phoneNumberWithOutCountryCode:
                arguments['phoneNumberWithOutCountryCode'] ?? '',
            countryCode: arguments['countryCode'] ?? '',
            countryCodeName: arguments['countryCodeName'] ?? '',
            source: arguments['source'] ?? '',
            uid: arguments['uid'] ?? '',
            loginType: arguments["loginType"],
            userEmail: arguments["userEmail"] ?? '',
            userName: arguments["userName"] ?? '',
            isNewUser: arguments["isNewUser"],
          );
        },
      );
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final TextEditingController usernameController = TextEditingController();
  final TextEditingController phoneNumberController = TextEditingController();
  final TextEditingController emailController = TextEditingController();
  final TextEditingController passwordController = TextEditingController();
  final TextEditingController confirmPasswordController =
      TextEditingController();

  FocusNode nameFocusNode = FocusNode();
  FocusNode phoneFocusNode = FocusNode();
  FocusNode emailFocusNode = FocusNode();
  FocusNode passwordFocusNode = FocusNode();
  FocusNode confirmPasswordFocusNode = FocusNode();

  UserDetailsModel? userDetails;
  File? selectedImage;
  GlobalKey<FormState> formKey = GlobalKey<FormState>();

  @override
  void dispose() {
    nameFocusNode.dispose();
    phoneFocusNode.dispose();
    emailFocusNode.dispose();
    passwordFocusNode.dispose();
    confirmPasswordFocusNode.dispose();
    emailController.dispose();
    phoneNumberController.dispose();
    usernameController.dispose();
    passwordController.dispose();
    confirmPasswordController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();

    Future.delayed(
      Duration.zero,
      () async {
        if (widget.countryCodeName != '' &&
            widget.countryCodeName != null &&
            widget.countryCodeName != 'null') {
          context.read<CountryCodeCubit>().selectCountryCode(
                await context.read<CountryCodeCubit>().getCountryByCountryCode(
                      context,
                      countryLocale: widget.countryCodeName!,
                    ),
              );
        }
      },
    );

    if (widget.phoneNumberWithOutCountryCode != '' &&
        widget.phoneNumberWithOutCountryCode != null &&
        widget.phoneNumberWithOutCountryCode != 'null') {
      // Phone number without country code (country code is shown in prefix)
      phoneNumberController.text = widget.phoneNumberWithOutCountryCode!;

      Future.delayed(
        Duration.zero,
        () {
          userDetails = context.read<UserDetailsCubit>().getUserDetails();
        },
      );
    } else if (HiveRepository.getUserMobileNumber != null) {
      // Phone number without country code
      phoneNumberController.text = HiveRepository.getUserMobileNumber ?? '';
    }

    if (widget.userEmail != '' &&
        widget.userEmail != null &&
        widget.userEmail != 'null') {
      emailController.text = widget.userEmail!;

      Future.delayed(
        Duration.zero,
        () {
          userDetails = context.read<UserDetailsCubit>().getUserDetails();
        },
      );
    } else if (HiveRepository.getUserEmailId != null) {
      emailController.text = HiveRepository.getUserEmailId ?? '';
    }
    if (widget.userName!.trim() != '' &&
        widget.userName != null &&
        widget.userName != 'null') {
      usernameController.text = widget.userName!;

      Future.delayed(
        Duration.zero,
        () {
          userDetails = context.read<UserDetailsCubit>().getUserDetails();
        },
      );
    } else if (HiveRepository.getUsername != null) {
      usernameController.text = HiveRepository.getUsername ?? '';
    }
  }

  Future<void> _getFromGallery() async {
    final XFile? pickedFile = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1800,
      maxHeight: 1800,
    );

    final croppedFile = await _croppedImage(pickedFile!.path);

    setState(() {
      selectedImage = File(croppedFile!.path);
    });
  }

  Future<CroppedFile?> _croppedImage(String pickedFilePath) async {
    return ImageCropper().cropImage(
      sourcePath: pickedFilePath,
      compressFormat: ImageCompressFormat.png,
      uiSettings: [
        AndroidUiSettings(
          aspectRatioPresets: [
            CropAspectRatioPreset.square,
          ],
          cropStyle: CropStyle.circle,
          toolbarTitle: "cropImage".translate(context: context),
          toolbarColor: Theme.of(context).colorScheme.accentColor,
          toolbarWidgetColor: AppColors.whiteColors,
          initAspectRatio: CropAspectRatioPreset.square,
          hideBottomControls: true,
          activeControlsWidgetColor: Theme.of(context).colorScheme.primaryColor,
        ),
        IOSUiSettings(
          title: "cropImage".translate(context: context),
          aspectRatioPresets: [
            CropAspectRatioPreset.square,
          ],
          cropStyle: CropStyle.circle,
        ),
      ],
    );
  }

  @override
  Widget build(final BuildContext context) =>
      AnnotatedRegion<SystemUiOverlayStyle>(
        value: UiUtils.getSystemUiOverlayStyle(context: context),
        child: Scaffold(
          backgroundColor: Theme.of(context).colorScheme.primaryColor,
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: ''.translate(context: context),
          ),
          body: SingleChildScrollView(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
              child: BlocConsumer<UpdateUserCubit, UpdateUserState>(
                listener: (context, UpdateUserState updateUserState) {
                  if (updateUserState is UpdateUserSuccess) {
                    final existingUserDetails =
                        (context.read<UserDetailsCubit>().state as UserDetails)
                            .userDetailsModel;

                    final newUserDetails = existingUserDetails.fromMapCopyWith(
                        updateUserState.updatedDetails.toMap());
                    context
                        .read<UserDetailsCubit>()
                        .changeUserDetails(newUserDetails);
                  }
                },
                builder: (final context, UpdateUserState updateUserState) {
                  final userProfilePictureURL =
                      HiveRepository.getUserProfilePictureURL;
                  return Form(
                    key: formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        CustomText(
                          (widget.isNewUser ? "createProfile" : "editProfile")
                              .translate(context: context),
                          fontSize: 24,
                          fontWeight: FontWeight.w600,
                        ),
                        const CustomSizedBox(
                          height: 18,
                        ),
                        CustomInkWellContainer(
                          onTap: () {
                            _getFromGallery();
                          },
                          showRippleEffect: false,
                          child: CustomContainer(
                            width: 100,
                            height: 100,
                            borderRadius: UiUtils.borderRadiusOf50,
                            border: Border.all(
                                color: Theme.of(context)
                                    .colorScheme
                                    .lightGreyColor,
                                width: 0.5),
                            padding: const EdgeInsets.all(12),
                            child: Stack(
                              children: [
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(
                                      UiUtils.borderRadiusOf50),
                                  child: selectedImage != null
                                      ? Image.file(
                                          File(
                                            selectedImage?.path ?? '',
                                          ),
                                          width: 100,
                                          height: 100,
                                        )
                                      : (userProfilePictureURL == null ||
                                              userProfilePictureURL == '')
                                          ? const CustomSvgPicture(
                                              svgImage: AppAssets.drProfile,
                                              width: 100,
                                              height: 100,
                                            )
                                          : CustomCachedNetworkImage(
                                              networkImageUrl: HiveRepository
                                                      .getUserProfilePictureURL ??
                                                  '',
                                              width: 100,
                                              height: 100,
                                            ),
                                ),
                                Align(
                                  alignment: AlignmentDirectional.center,
                                  child: CustomContainer(
                                    height: 100,
                                    width: 100,
                                    color: Colors.black.withAlpha(10),
                                    borderRadius: UiUtils.borderRadiusOf50,
                                    child: Center(
                                      child: CustomSvgPicture(
                                        svgImage: AppAssets.attCamera,
                                        color: Theme.of(context)
                                            .colorScheme
                                            .secondaryColor,
                                        height: 25,
                                        width: 25,
                                      ),
                                    ),
                                  ),
                                )
                              ],
                            ),
                          ),
                        ),
                        const CustomSizedBox(height: 28),
                        CustomTextFormField(
                          labelText: "username".translate(context: context),
                          hintText: "enterUsername".translate(context: context),
                          controller: usernameController,
                          currentFocusNode: nameFocusNode,
                          textInputType: TextInputType.name,
                          prefix: const CustomSvgPicture(
                            svgImage: AppAssets.drProfile,
                            boxFit: BoxFit.scaleDown,
                          ),
                          validator: (username) => isTextFieldEmpty(
                              context: context, value: username),
                        ),
                        const CustomSizedBox(
                          height: 10,
                        ),
                        CustomTextFormField(
                          labelText: "phoneNumber".translate(context: context),
                          hintText:
                              "enterPhoneNumber".translate(context: context),
                          currentFocusNode: phoneFocusNode,
                          controller: phoneNumberController,
                          isReadOnly: widget.loginType == LogInType.phone,
                          textInputAction: TextInputAction.done,
                          inputFormatters: UiUtils.allowOnlyDigits(),
                          prefix: Padding(
                            padding: const EdgeInsetsDirectional.only(
                                start: 12, bottom: 2),
                            child:
                                BlocBuilder<CountryCodeCubit, CountryCodeState>(
                              builder: (final BuildContext context,
                                  final CountryCodeState state) {
                                var code = '--';

                                if (state is CountryCodeFetchSuccess) {
                                  code = state.selectedCountry!.callingCode;
                                }

                                return CustomInkWellContainer(
                                  onTap: widget.loginType == LogInType.phone
                                      ? null
                                      : () {
                                          if (allowOnlySingleCountry ||
                                              (state is CountryCodeFetchSuccess &&
                                                  state.temporaryCountryList !=
                                                      null &&
                                                  state.temporaryCountryList!
                                                          .length ==
                                                      1)) {
                                            return;
                                          }
                                          Navigator.pushNamed(context,
                                                  countryCodePickerRoute)
                                              .then((Object? value) {
                                            Future.delayed(const Duration(
                                                    milliseconds: 250))
                                                .then((final value) {
                                              context
                                                  .read<CountryCodeCubit>()
                                                  .fillTemporaryList();
                                            });
                                          });
                                        },
                                  child: IntrinsicWidth(
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Text(
                                          code,
                                          style: TextStyle(
                                              fontSize: 16,
                                              fontWeight: FontWeight.normal,
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .blackColor),
                                        ),
                                        if (widget.loginType !=
                                                LogInType.phone &&
                                            !(allowOnlySingleCountry ||
                                                (state is CountryCodeFetchSuccess &&
                                                    state.temporaryCountryList !=
                                                        null &&
                                                    state.temporaryCountryList!
                                                            .length ==
                                                        1)))
                                          Icon(
                                            Icons.arrow_drop_down,
                                            color: Theme.of(context)
                                                .colorScheme
                                                .blackColor,
                                            size: 24,
                                          )
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                          textInputType: TextInputType.number,
                          validator: (mobileNumber) {
                            // Make phone number optional for Apple and Google login
                            if (widget.loginType == LogInType.apple ||
                                widget.loginType == LogInType.google) {
                              return null;
                            }
                            return isTextFieldEmpty(
                                value: mobileNumber, context: context);
                          },
                        ),
                        const CustomSizedBox(
                          height: 10,
                        ),
                        CustomTextFormField(
                          labelText: "email".translate(context: context),
                          hintText: "enterYourEmailAddress"
                              .translate(context: context),
                          controller: emailController,
                          currentFocusNode: emailFocusNode,
                          prefix: const CustomSvgPicture(
                            svgImage: AppAssets.email,
                            boxFit: BoxFit.scaleDown,
                          ),
                          isReadOnly: widget.loginType == LogInType.email ||
                              widget.loginType == LogInType.apple ||
                              widget.loginType == LogInType.google,
                          textInputType: TextInputType.emailAddress,
                          validator: (email) => isValidEmail(
                              email: email ?? '', context: context),
                        ),
                        if (widget.isNewUser &&
                            widget.loginType != LogInType.apple &&
                            widget.loginType != LogInType.google &&
                            (context.read<SystemSettingCubit>().state
                                is SystemSettingFetchSuccess) &&
                            (context.read<SystemSettingCubit>().state
                                        as SystemSettingFetchSuccess)
                                    .systemSettingDetails
                                    .loginSettings
                                    ?.isPasswordLoginEnabled ==
                                true) ...[
                          const CustomSizedBox(
                            height: 10,
                          ),
                          CustomPasswordFieldWithValidators(
                            controller: passwordController,
                            currentFocusNode: passwordFocusNode,
                            nextFocusNode: confirmPasswordFocusNode,
                            labelText: "password",
                            textInputAction: TextInputAction.next,
                            validator: (password) => isValidPassword(
                                password: password ?? '', context: context),
                          ),
                          const CustomSizedBox(
                            height: 10,
                          ),
                          CustomTextFormField(
                            labelText: "confirmPassword",
                            controller: confirmPasswordController,
                            currentFocusNode: confirmPasswordFocusNode,
                            isPassword: true,
                            textInputAction: TextInputAction.done,
                            validator: (confirmPassword) =>
                                confirmPasswordValidator(
                              password: passwordController.text,
                              confirmPassword: confirmPassword ?? '',
                              context: context,
                            ),
                          ),
                        ],
                        const CustomSizedBox(
                          height: 24,
                        ),
                        BlocConsumer<UpdateUserCubit, UpdateUserState>(
                          listener: (final context, final state) {
                            if (state is UpdateUserSuccess) {
                              ClarityService.logAction(
                                ClarityActions.profileUpdateSaved,
                                {
                                  'is_new_user': widget.isNewUser,
                                  'login_type':
                                      widget.loginType?.name.toString() ?? '',
                                  'email': emailController.text.trim(),
                                  'phone': phoneNumberController.text.trim(),
                                },
                              );
                              UiUtils.showMessage(
                                context,
                                'profileUpdateSuccess'
                                    .translate(context: context),
                                ToastificationType.success,
                              );

                              if (!widget.isNewUser) {
                                //user is editing their profile details
                                Navigator.pop(context);
                              }
                            } else if (state is UpdateUserFail) {
                              UiUtils.showMessage(
                                context,
                                state.error,
                                ToastificationType.error,
                              );
                            }
                          },
                          builder: (final BuildContext context,
                              UpdateUserState state) {
                            Widget? child;
                            if (state is UpdateUserInProgress) {
                              child = const CustomCircularProgressIndicator(
                                  color: AppColors.whiteColors);
                            } else if (state is UpdateUserSuccess ||
                                state is UpdateUserFail) {
                              child = null;
                            }
                            return CustomSizedBox(
                              width: MediaQuery.sizeOf(context).width,
                              height: 48,
                              child: CustomRoundedButton(
                                onTap: () =>
                                    _handleSaveChangesClick(context, state),
                                buttonTitle: (widget.isNewUser
                                        ? "continue"
                                        : "saveChanges")
                                    .translate(context: context),
                                backgroundColor:
                                    Theme.of(context).colorScheme.accentColor,
                                showBorder: false,
                                widthPercentage: 0.9,
                                child: child,
                              ),
                            );
                          },
                        )
                      ],
                    ),
                  );
                },
              ),
            ),
          ),
        ),
      );

  Future<void> _handleSaveChangesClick(
      BuildContext context, UpdateUserState state) async {
    if (!formKey.currentState!.validate()) {
      return;
    }

    UiUtils.removeFocus();
    if (state is UpdateUserInProgress) {
      return;
    }
    //if user is registering their profile first time
    if (widget.isNewUser) {
      final latitude = HiveRepository.getLatitude ?? "0.0";
      final longitude = HiveRepository.getLongitude ?? "0.0";

      String? fcmId;
      try {
        await FirebaseMessaging.instance.getToken().then((value) async {
          fcmId = value;
        });
      } catch (_) {}

      // Get country code for the phone field
      String? phoneCountryCode;
      String? phoneNumber;

      if (widget.loginType == LogInType.phone) {
        // Phone users - use widget country code
        phoneCountryCode = widget.countryCode;
        phoneNumber = widget.phoneNumberWithOutCountryCode;
      } else {
        // Email/Google/Apple users - get country code from selector
        final phoneInput = phoneNumberController.text.trim();
        if (phoneInput.isNotEmpty) {
          try {
            phoneCountryCode =
                context.read<CountryCodeCubit>().getSelectedCountryCode();
          } catch (e) {
            phoneCountryCode = null;
          }
          phoneNumber = phoneInput;
        } else {
          // Phone number is optional for Apple/Google login
          phoneCountryCode = null;
          phoneNumber = null;
        }
      }

      await AuthenticationRepository.loginUser(
              uid: widget.uid,
              fcmId: fcmId,
              latitude: latitude.toString(),
              longitude: longitude.toString(),
              loginType: widget.loginType!,
              mobileNumber: phoneNumber,
              countryCode: phoneCountryCode,
              userName: usernameController.text.trim(),
              email: emailController.text.trim(),
              password: passwordController.text.trim().isNotEmpty
                  ? passwordController.text.trim()
                  : null)
          .then(
        (final UserDetailsModel value) async {
          ClarityService.logAction(
            ClarityActions.signupCompleted,
            {
              'uid': widget.uid,
              'login_type': widget.loginType?.name.toString() ?? '',
              'country_code': widget.countryCode ?? '',
            },
          );
          ClarityService.logAction(
            ClarityActions.loginSuccess,
            {
              'uid': widget.uid,
              'login_type': widget.loginType?.name.toString() ?? '',
            },
          );
          context.read<UserDetailsCubit>().setUserDetails(value);

          // For new user registration, manage_user already sets all data
          // No need to call update_user afterward

          //update user authenticated status
          HiveRepository.setUserFirstTimeInApp = false;
          HiveRepository.setUserLoggedIn = true;

          if (mounted) {
            context.read<AuthenticationCubit>().checkStatus();
          }

          Future.delayed(
            Duration.zero,
            () async {
              if (widget.source == "dialog") {
                try {
                  await context.read<SystemSettingCubit>().getSystemSettings();
                  final futureAPIs = <Future>[
                    context.read<HomeScreenCubit>().fetchHomeScreenData(),
                    context
                        .read<BookingCubit>()
                        .fetchBookingDetails(status: ''),
                    context.read<MyRequestListCubit>().fetchRequests(),
                    context
                        .read<CartCubit>()
                        .getCartDetails(isReorderCart: false),
                    context.read<BookmarkCubit>().fetchBookmark(type: 'list'),
                    context.read<UserDetailsCubit>().loadUserDetails()
                  ];

                  await Future.wait(futureAPIs);
                } catch (_) {}

                Navigator.pop(context);
                Navigator.pop(context);
              } else {
                await context.read<SystemSettingCubit>().getSystemSettings();
                context.read<HomeScreenCubit>().fetchHomeScreenData();
                UiUtils.bottomNavigationBarGlobalKey.currentState
                    ?.selectedIndexOfBottomNavigationBar.value = 0;

                Navigator.pop(context);

                Navigator.pushReplacementNamed(context, navigationRoute);
              }
            },
          );
        },
      );
    } else {
      //if user is editing their profile then directly update their data

      // Get country code for phone field
      String? phoneCountryCode;
      String? phoneNumber;

      if (widget.loginType == LogInType.phone) {
        // Phone users - use existing country code
        phoneCountryCode = widget.countryCode;
        phoneNumber = widget.phoneNumberWithOutCountryCode
            ?.toString()
            .replaceAll(widget.countryCode.toString(), '');
      } else {
        // Email/Google/Apple users - get country code from selector
        final phoneInput = phoneNumberController.text.trim();
        if (phoneInput.isNotEmpty) {
          try {
            phoneCountryCode =
                context.read<CountryCodeCubit>().getSelectedCountryCode();
          } catch (e) {
            phoneCountryCode = null;
          }
          phoneNumber = phoneInput;
        } else {
          // Phone number is optional for Apple/Google login
          phoneCountryCode = null;
          phoneNumber = null;
        }
      }

      final UpdateUserDetails updateUserDetails = UpdateUserDetails(
        email: emailController.value.text,
        phone: phoneNumber,
        countryCode: phoneCountryCode ?? '',
        username: usernameController.value.text,
        image: selectedImage ?? '',
        loginType: widget.loginType?.name.toString(),
        uid: widget.uid,
      );

      context.read<UpdateUserCubit>().updateUserDetails(updateUserDetails);
    }
  }
}
