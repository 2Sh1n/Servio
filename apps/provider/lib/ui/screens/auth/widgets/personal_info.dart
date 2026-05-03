import 'package:flutter/material.dart';
import '../../../../app/generalImports.dart';

class PersonalInfo extends StatelessWidget {
  final TextEditingController userNameController;
  final TextEditingController emailController;
  final TextEditingController mobileNumberController;
  final FocusNode userNmFocus;
  final FocusNode emailFocus;
  final FocusNode mobNoFocus;
  final GlobalKey<FormState> formKey;
  final Map<String, dynamic> pickedLocalImages;
  final PickImage pickNationalIdImage;
  final PickImage pickAddressProofImage;
  final PickImage pickPassportImage;
  final String? atStore;
  final String? atDoorstepAllowed;
  final String? isPreBookingChatAllowed;
  final String? isPostBookingChatAllowed;
  final Function(String?) onAtStoreChanged;
  final Function(String?) onAtDoorstepChanged;
  final Function(String?) onPreBookingChatChanged;
  final Function(String?) onPostBookingChatChanged;
  final Function showCameraAndGalleryOption;

  // Multi-language support for username
  final List<AppLanguage>? languages;
  final AppLanguage? defaultLanguage;
  final int? selectedLanguageIndex;
  final Function(int)? onLanguageChanged;
  final Map<String, TextEditingController>? userNameControllers;
  final String? Function(String?)? getUserNameValidator;

  // Login type to determine field editability
  final LoginType? loginType;

  const PersonalInfo({
    Key? key,
    required this.userNameController,
    required this.emailController,
    required this.mobileNumberController,
    required this.userNmFocus,
    required this.emailFocus,
    required this.mobNoFocus,
    required this.formKey,
    required this.pickedLocalImages,
    required this.pickNationalIdImage,
    required this.pickAddressProofImage,
    required this.pickPassportImage,
    required this.atStore,
    required this.atDoorstepAllowed,
    required this.isPreBookingChatAllowed,
    required this.isPostBookingChatAllowed,
    required this.onAtStoreChanged,
    required this.onAtDoorstepChanged,
    required this.onPreBookingChatChanged,
    required this.onPostBookingChatChanged,
    required this.showCameraAndGalleryOption,
    // Multi-language support for username
    this.languages,
    this.defaultLanguage,
    this.selectedLanguageIndex,
    this.onLanguageChanged,
    this.userNameControllers,
    this.getUserNameValidator,
    this.loginType,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    // Don't show shimmer here - parent form handles loading state
    // This widget is displayed inside proivderRegistrationForm which has its own shimmer

    final bool isPassportVerificationStatus =
        (context.read<FetchSystemSettingsCubit>().state
                as FetchSystemSettingsSuccess)
            .generalSettings
            .passportVerificationStatus!;
    final bool isNationalIdVerificationStatus =
        (context.read<FetchSystemSettingsCubit>().state
                as FetchSystemSettingsSuccess)
            .generalSettings
            .nationalIdVerificationStatus!;
    final bool isAddressIdVerificationStatus =
        (context.read<FetchSystemSettingsCubit>().state
                as FetchSystemSettingsSuccess)
            .generalSettings
            .addressIdVerificationStatus!;
    final bool isPassportRequiredStatus =
        (context.read<FetchSystemSettingsCubit>().state
                as FetchSystemSettingsSuccess)
            .generalSettings
            .passportRequiredStatus!;
    final bool isNationalIdRequiredStatus =
        (context.read<FetchSystemSettingsCubit>().state
                as FetchSystemSettingsSuccess)
            .generalSettings
            .nationalIdRequiredStatus!;
    final bool isAddressIdRequiredStatus =
        (context.read<FetchSystemSettingsCubit>().state
                as FetchSystemSettingsSuccess)
            .generalSettings
            .addressIdRequiredStatus!;

    return Form(
      key: formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 10),
          // Language Tabs for Username (only show if more than one language)
          if (languages != null && languages!.length > 1) ...[
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              child: Container(
                margin: const EdgeInsets.only(bottom: 20),
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: languages!.asMap().entries.map((entry) {
                      final int index = entry.key;
                      final AppLanguage language = entry.value;
                      final bool isSelected = selectedLanguageIndex == index;

                      return GestureDetector(
                        onTap: () => onLanguageChanged?.call(index),
                        child: Container(
                          margin: const EdgeInsets.symmetric(horizontal: 4),
                          padding: const EdgeInsets.symmetric(
                            vertical: 12,
                            horizontal: 12,
                          ),
                          constraints: const BoxConstraints(minWidth: 80),
                          decoration: BoxDecoration(
                            color: isSelected
                                ? Theme.of(context).colorScheme.accentColor
                                : Theme.of(context).colorScheme.secondaryColor,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: isSelected
                                  ? Theme.of(context).colorScheme.accentColor
                                  : Theme.of(
                                      context,
                                    ).colorScheme.lightGreyColor,
                            ),
                          ),
                          child: Text(
                            language.languageName,
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: isSelected
                                  ? AppColors.lightPrimaryColor
                                  : Theme.of(context).colorScheme.blackColor,
                              fontWeight: isSelected
                                  ? FontWeight.bold
                                  : FontWeight.normal,
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ),
            ),
          ],
          // Username Field (Multi-language)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: Column(
              children: [
                if (languages != null && languages!.isNotEmpty) ...[
                  CustomTextFormField(
                    labelText:
                        languages![selectedLanguageIndex ?? 0].languageCode ==
                            defaultLanguage?.languageCode
                        ? '${'userNmLbl'.translate(context: context)} *'
                        : '${'userNmLbl'.translate(context: context)} (${languages![selectedLanguageIndex ?? 0].languageName})',
                    hintText:
                        languages![selectedLanguageIndex ?? 0].languageCode ==
                            defaultLanguage?.languageCode
                        ? null
                        : '${'userNmLbl'.translate(context: context)} (${languages![selectedLanguageIndex ?? 0].languageName})',
                    controller:
                        userNameControllers?[languages![selectedLanguageIndex ??
                                0]
                            .languageCode] ??
                        userNameController,
                    currentFocusNode: userNmFocus,
                    prefix: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(left: 8.0),
                          child: Icon(
                            Icons.person,
                            color: userNmFocus.hasFocus
                                ? context.colorScheme.accentColor
                                : context.colorScheme.blackColor,
                          ),
                        ),
                        Container(
                          margin: const EdgeInsets.symmetric(horizontal: 8),
                          width: 1,
                          height: 15,
                          color: context.colorScheme.lightGreyColor,
                        ),
                      ],
                    ),
                    nextFocusNode: emailFocus,
                    validator:
                        getUserNameValidator ??
                        (String? userName) =>
                            Validator.nullCheck(context, userName),
                  ),
                ] else ...[
                  // Fallback to original field if languages not loaded
                  CustomTextFormField(
                    labelText: 'userNmLbl'.translate(context: context),
                    controller: userNameController,
                    currentFocusNode: userNmFocus,
                    prefix: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(left: 8.0),
                          child: Icon(
                            Icons.person,
                            color: userNmFocus.hasFocus
                                ? context.colorScheme.accentColor
                                : context.colorScheme.blackColor,
                          ),
                        ),
                        Container(
                          margin: const EdgeInsets.symmetric(horizontal: 8),
                          width: 1,
                          height: 15,
                          color: context.colorScheme.lightGreyColor,
                        ),
                      ],
                    ),
                    nextFocusNode: emailFocus,
                    validator: (String? userName) =>
                        Validator.nullCheck(context, userName),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 5),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: CustomTextFormField(
              labelText: 'emailLbl'.translate(context: context),
              controller: emailController,
              currentFocusNode: emailFocus,
              prefix: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(left: 8.0),
                    child: Icon(
                      Icons.mail,
                      color: emailFocus.hasFocus
                          ? context.colorScheme.accentColor
                          : context.colorScheme.blackColor,
                    ),
                  ),
                  Container(
                    margin: const EdgeInsets.symmetric(horizontal: 8),
                    width: 1,
                    height: 15,
                    color: context.colorScheme.lightGreyColor,
                  ),
                ],
              ),
              nextFocusNode: mobNoFocus,
              textInputType: TextInputType.emailAddress,
              isReadOnly: loginType?.isEmail ?? false,
              validator: (String? email) => Validator.nullCheck(context, email),
            ),
          ),
          const SizedBox(height: 5),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: CustomTextFormField(
              labelText: 'mobNoLbl'.translate(context: context),
              controller: mobileNumberController,
              currentFocusNode: mobNoFocus,
              prefix: _buildCountryCodePrefix(context),
              textInputType: TextInputType.phone,
              inputFormatters: UiUtils.allowOnlyDigits(),
              isReadOnly: loginType?.isPhone ?? true,
              validator: (String? mobileNumber) =>
                  Validator.nullCheck(context, mobileNumber),
            ),
          ),
          if (isPassportVerificationStatus ||
              isNationalIdVerificationStatus ||
              isAddressIdVerificationStatus) ...[
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              child: Row(
                children: [
                  const Expanded(child: Divider(thickness: 0.5)),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 8.0),
                    child: CustomText(
                      'idProofLbl'.translate(context: context),
                      color: Theme.of(context).colorScheme.blackColor,
                    ),
                  ),
                  const Expanded(child: Divider(thickness: 0.5)),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 8),
              child: CustomText(
                'idProofsRecommend'.translate(context: context),
                color: Theme.of(context).colorScheme.blackColor,
                fontSize: 12,
              ),
            ),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 15),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    if (isNationalIdVerificationStatus)
                      _idImageWidget(
                        context,
                        imageController: pickNationalIdImage,
                        titleTxt: 'nationalIdLbl'.translate(context: context),
                        imageHintText: 'chooseFileLbl'.translate(
                          context: context,
                        ),
                        imageType: 'nationalIdImage',
                        oldImage:
                            context
                                .read<ProviderDetailsCubit>()
                                .providerDetails
                                .providerInformation
                                ?.nationalId ??
                            '',
                        isRequired: isNationalIdRequiredStatus,
                      ),
                    if (isAddressIdVerificationStatus)
                      _idImageWidget(
                        context,
                        imageController: pickAddressProofImage,
                        titleTxt: 'addressLabel'.translate(context: context),
                        imageHintText: 'chooseFileLbl'.translate(
                          context: context,
                        ),
                        imageType: 'addressIdImage',
                        oldImage:
                            context
                                .read<ProviderDetailsCubit>()
                                .providerDetails
                                .providerInformation
                                ?.addressId ??
                            '',
                        isRequired: isAddressIdRequiredStatus,
                      ),
                    if (isPassportVerificationStatus)
                      _idImageWidget(
                        context,
                        imageController: pickPassportImage,
                        titleTxt: 'passportLbl'.translate(context: context),
                        imageHintText: 'chooseFileLbl'.translate(
                          context: context,
                        ),
                        imageType: 'passportIdImage',
                        oldImage:
                            context
                                .read<ProviderDetailsCubit>()
                                .providerDetails
                                .providerInformation
                                ?.passport ??
                            '',
                        isRequired: isPassportRequiredStatus,
                      ),
                  ],
                ),
              ),
            ),
          ],
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: Row(
              children: [
                const Expanded(child: Divider(thickness: 0.5)),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8.0),
                  child: CustomText(
                    'bookingSetting'.translate(context: context),
                    color: Theme.of(context).colorScheme.blackColor,
                  ),
                ),
                const Expanded(child: Divider(thickness: 0.5)),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: Row(
              children: [
                Expanded(
                  child: CustomCheckIconTextButton(
                    title: 'atStore',
                    isSelected: atStore == "1",
                    onTap: () {
                      onAtStoreChanged(atStore == "1" ? "0" : "1");
                    },
                  ),
                ),
                const SizedBox(width: 5),
                Expanded(
                  child: CustomCheckIconTextButton(
                    title: 'atDoorstep',
                    isSelected: atDoorstepAllowed == "1",
                    onTap: () {
                      onAtDoorstepChanged(atDoorstepAllowed == "1" ? "0" : "1");
                    },
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          if ((context.read<FetchSystemSettingsCubit>().state
                      as FetchSystemSettingsSuccess)
                  .generalSettings
                  .allowPostBookingChat! ||
              (context.read<FetchSystemSettingsCubit>().state
                      as FetchSystemSettingsSuccess)
                  .generalSettings
                  .allowPreBookingChat!)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              child: Row(
                children: [
                  const Expanded(child: Divider(thickness: 0.5)),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 8.0),
                    child: CustomText(
                      'chatSetting'.translate(context: context),
                      color: Theme.of(context).colorScheme.blackColor,
                    ),
                  ),
                  const Expanded(child: Divider(thickness: 0.5)),
                ],
              ),
            ),
          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: Row(
              children: [
                if ((context.read<FetchSystemSettingsCubit>().state
                        as FetchSystemSettingsSuccess)
                    .generalSettings
                    .allowPreBookingChat!)
                  Expanded(
                    child: CustomCheckIconTextButton(
                      title: "preBooking",
                      isSelected: isPreBookingChatAllowed == "1",
                      onTap: () {
                        onPreBookingChatChanged(
                          isPreBookingChatAllowed == "1" ? "0" : "1",
                        );
                      },
                    ),
                  ),
                if ((context.read<FetchSystemSettingsCubit>().state
                            as FetchSystemSettingsSuccess)
                        .generalSettings
                        .allowPostBookingChat! &&
                    (context.read<FetchSystemSettingsCubit>().state
                            as FetchSystemSettingsSuccess)
                        .generalSettings
                        .allowPreBookingChat!)
                  const SizedBox(width: 5),
                if ((context.read<FetchSystemSettingsCubit>().state
                        as FetchSystemSettingsSuccess)
                    .generalSettings
                    .allowPostBookingChat!)
                  Expanded(
                    child: CustomCheckIconTextButton(
                      title: "postBooking",
                      isSelected: isPostBookingChatAllowed == "1",
                      onTap: () {
                        onPostBookingChatChanged(
                          isPostBookingChatAllowed == "1" ? "0" : "1",
                        );
                      },
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _idImageWidget(
    BuildContext context, {
    required String titleTxt,
    required String imageHintText,
    required PickImage imageController,
    required String imageType,
    required String oldImage,
    required bool isRequired,
  }) {
    return CustomContainer(
      margin: const EdgeInsets.only(right: 8),
      padding: const EdgeInsets.all(16),
      width: MediaQuery.of(context).size.width / 1.4,
      color: context.colorScheme.secondaryColor,
      borderRadius: UiUtils.borderRadiusOf10,
      child: StatefulBuilder(
        builder: (context, setState) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  CustomText(
                    titleTxt,
                    fontSize: 16,
                    fontWeight: FontWeight.w500,
                  ),
                  if (isRequired) ...[
                    const SizedBox(width: 4),
                    const CustomText(
                      '*',
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                      color: Colors.red,
                    ),
                  ] else ...[
                    const SizedBox(width: 4),
                    CustomText(
                      '(Optional)',
                      fontSize: 12,
                      fontWeight: FontWeight.w400,
                      color: context.colorScheme.lightGreyColor,
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 5),
              pickedLocalImages[imageType] != ''
                  ? CustomText(
                      pickedLocalImages[imageType].split('/').last,
                      fontSize: 12,
                      fontWeight: FontWeight.w400,
                      maxLines: 1,
                      color: context.colorScheme.lightGreyColor,
                    )
                  : oldImage != 'null'
                  ? CustomText(
                      oldImage.split('/').last,
                      fontSize: 12,
                      fontWeight: FontWeight.w400,
                      maxLines: 1,
                      color: context.colorScheme.lightGreyColor,
                    )
                  : const SizedBox(height: 12),
              const SizedBox(height: 5),
              _imagePicker(
                context,
                imageType: imageType,
                imageController: imageController,
                oldImage: oldImage,
                hintLabel: imageHintText,
                width: MediaQuery.of(context).size.width / 1.5,
                onImagePicked: (newImage) {
                  setState(() {
                    pickedLocalImages[imageType] = newImage;
                  });
                },
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _imagePicker(
    BuildContext context, {
    required PickImage imageController,
    required String oldImage,
    required String hintLabel,
    required String imageType,
    double? width,
    required void Function(String newImagePath) onImagePicked,
  }) {
    return imageController.ListenImageChange((BuildContext context, image) {
      if (image == null) {
        if (pickedLocalImages[imageType] != '') {
          return GestureDetector(
            onTap: () {
              showCameraAndGalleryOption(
                imageController: imageController,
                title: hintLabel,
              ).then((_) {
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  onImagePicked(pickedLocalImages[imageType]!);
                });
              });
            },
            child: CustomContainer(
              color: context.colorScheme.accentColor.withAlpha(20),
              borderRadius: UiUtils.borderRadiusOf10,
              height: 100,
              width: (width ?? MediaQuery.sizeOf(context).width) - 5.0,
              child: ClipRRect(
                borderRadius: const BorderRadius.all(
                  Radius.circular(UiUtils.borderRadiusOf10),
                ),
                child: Image.file(
                  File(pickedLocalImages[imageType]!),
                  fit: BoxFit.cover,
                ),
              ),
            ),
          );
        }

        if (oldImage.isNotEmpty) {
          return GestureDetector(
            onTap: () {
              showCameraAndGalleryOption(
                imageController: imageController,
                title: hintLabel,
              ).then((_) {
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  onImagePicked(pickedLocalImages[imageType]!);
                });
              });
            },
            child: Stack(
              children: [
                CustomContainer(
                  borderRadius: UiUtils.borderRadiusOf10,
                  height: 100,
                  width: (width ?? MediaQuery.sizeOf(context).width) - 5.0,
                  child: ClipRRect(
                    borderRadius: const BorderRadius.all(
                      Radius.circular(UiUtils.borderRadiusOf10),
                    ),
                    child: CustomCachedNetworkImage(
                      imageUrl: oldImage,
                      fit: BoxFit.cover,
                    ),
                  ),
                ),
                CustomContainer(
                  borderRadius: UiUtils.borderRadiusOf10,
                  color: context.colorScheme.lightGreyColor.withAlpha(40),
                  height: 100,
                  width: (width ?? MediaQuery.sizeOf(context).width) - 5.0,
                  child: Center(
                    child: CustomSvgPicture(
                      svgImage: AppAssets.edit,
                      color: AppColors.whiteColors,
                      height: 25,
                      width: 25,
                    ),
                  ),
                ),
              ],
            ),
          );
        }

        return CustomInkWellContainer(
          onTap: () {
            showCameraAndGalleryOption(
              imageController: imageController,
              title: hintLabel,
            ).then((_) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                onImagePicked(pickedLocalImages[imageType]!);
              });
            });
          },
          child: CustomContainer(
            borderRadius: UiUtils.borderRadiusOf10,
            color: context.colorScheme.accentColor.withAlpha(20),
            height: 100,
            width: (width ?? MediaQuery.sizeOf(context).width) - 5.0,
            child: Center(
              child: CustomSvgPicture(
                svgImage: AppAssets.addCircle,
                color: context.colorScheme.accentColor,
                height: 50,
                width: 50,
              ),
            ),
          ),
        );
      }

      pickedLocalImages[imageType] = image?.path;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        onImagePicked(image!.path);
      });

      return GestureDetector(
        onTap: () {
          showCameraAndGalleryOption(
            imageController: imageController,
            title: hintLabel,
          ).then((_) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              onImagePicked(image.path);
            });
          });
        },
        child: CustomContainer(
          borderRadius: UiUtils.borderRadiusOf10,
          height: 100,
          width: (width ?? MediaQuery.sizeOf(context).width) - 5.0,
          child: ClipRRect(
            borderRadius: const BorderRadius.all(
              Radius.circular(UiUtils.borderRadiusOf10),
            ),
            child: Image.file(File(image.path), fit: BoxFit.cover),
          ),
        ),
      );
    });
  }

  Widget _buildCountryCodePrefix(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(start: 12.0, bottom: 2, top: 2),
      child: BlocBuilder<CountryCodeCubit, CountryCodeState>(
        builder: (BuildContext context, CountryCodeState state) {
          String code = '--';

          if (state is CountryCodeFetchSuccess) {
            code = state.selectedCountry!.callingCode;
          }

          return SizedBox(
            height: 24,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                GestureDetector(
                  onTap: () {
                    // Only allow changing country code when email login (phone is editable)
                    if (loginType?.isPhone ?? true) {
                      return; // Read-only, can't change
                    }

                    if (allowOnlySingleCountry ||
                        (state is CountryCodeFetchSuccess &&
                            state.temporaryCountryList != null &&
                            state.temporaryCountryList!.length == 1)) {
                      return;
                    }
                    Navigator.pushNamed(
                      context,
                      Routes.countryCodePickerRoute,
                    ).then((Object? value) {
                      Future.delayed(const Duration(milliseconds: 250)).then((
                        value,
                      ) {
                        context.read<CountryCodeCubit>().fillTemporaryList();
                      });
                    });
                  },
                  child: Row(
                    children: [
                      Builder(
                        builder: (BuildContext context) {
                          if (state is CountryCodeFetchSuccess) {
                            return SizedBox(
                              width: 24,
                              height: 16,
                              child:
                                  state.selectedCountry!.flagImage.startsWith(
                                    'http',
                                  )
                                  ? Image.network(
                                      state.selectedCountry!.flagImage,
                                      fit: BoxFit.cover,
                                    )
                                  : Image.asset(
                                      state.selectedCountry!.flag,
                                      fit: BoxFit.cover,
                                    ),
                            );
                          }
                          if (state is CountryCodeFetchFail) {
                            return ErrorContainer(
                              errorMessage: state.error.toString().translate(
                                context: context,
                              ),
                            );
                          }
                          return const CustomCircularProgressIndicator();
                        },
                      ),
                      const SizedBox(width: 8),
                      if (!allowOnlySingleCountry &&
                          !(state is CountryCodeFetchSuccess &&
                              state.temporaryCountryList != null &&
                              state.temporaryCountryList!.length == 1) &&
                          !(loginType?.isPhone ??
                              true)) // Only show dropdown when email login
                        CustomSvgPicture(
                          svgImage: AppAssets.spDown,
                          height: 5,
                          width: 5,
                          color: Theme.of(context).colorScheme.accentColor,
                        ),
                    ],
                  ),
                ),
                VerticalDivider(
                  thickness: 1,
                  indent: 6,
                  endIndent: 6,
                  color: Theme.of(context).colorScheme.lightGreyColor,
                ),
                Text(
                  code,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.blackColor,
                    fontWeight: FontWeight.w400,
                    fontSize: 14,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(width: 4),
              ],
            ),
          );
        },
      ),
    );
  }
}
