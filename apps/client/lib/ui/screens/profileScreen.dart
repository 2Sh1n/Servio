import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/cubits/authentication/logoutCubit.dart'
    show LogoutCubit;

import 'package:e_demand/ui/widgets/customSwitch.dart';
import 'package:flutter/material.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    required this.scrollController,
    final Key? key,
    this.currentVersion,
  }) : super(key: key);
  final String? currentVersion;
  final ScrollController scrollController;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  String? latestVersionOfAndroid, latestVersionOfIOS;
  bool _isShareInProgress = false;

  @override
  void initState() {
    super.initState();
    getLatestVersions();
  }

  @override
  void dispose() {
    widget.scrollController.dispose();
    super.dispose();
  }

  void getLatestVersions() {
    Future.delayed(Duration.zero).then((final value) {
      latestVersionOfAndroid =
          context.read<SystemSettingCubit>().androidVersion;
      latestVersionOfIOS = context.read<SystemSettingCubit>().iOSVersion;
    });
  }

  Widget _getText({
    required final String text,
    final double? fontSize,
    final Color? fontColor,
    final FontWeight? fontWeight,
  }) =>
      CustomText(
        text,
        fontSize: fontSize,
        color: fontColor,
        fontWeight: fontWeight,
        maxLines: 1,
      );

  Widget _buildSectionTitleWidget({
    required final String title,
    final Color? color,
  }) =>
      Padding(
        padding: const EdgeInsetsDirectional.only(bottom: 16),
        child: Align(
          alignment: AlignmentDirectional.centerStart,
          child: _getText(
            text: title,
            fontColor: color ?? context.colorScheme.lightGreyColor,
            fontWeight: FontWeight.w400,
            fontSize: 14,
          ),
        ),
      );

  Widget _buildListItemWidget({
    final GlobalKey? key,
    required final String imageName,
    required final String title,
    final bool showDarkModeToggle = false,
    final VoidCallback? onTap,
    final bool showLanguageName = false,
    final double bottomPadding = 0,
    final Color? imageColor,
  }) =>
      Padding(
        padding: EdgeInsetsDirectional.only(bottom: bottomPadding),
        child: CustomInkWellContainer(
          key: key,
          onTap: onTap,
          child: Row(
            children: [
              CustomSvgPicture(
                avoideResponsive: true,
                svgImage: imageName,
                color: imageColor ?? context.colorScheme.accentColor,
                height: 24,
                width: 24,
              ),
              const CustomSizedBox(
                width: 16,
              ),
              Expanded(
                flex: 8,
                child: CustomText(
                  title,
                  color: context.colorScheme.blackColor,
                  fontWeight: FontWeight.w500,
                  fontSize: 16,
                ),
              ),
              if (HiveRepository.getCurrentLanguage() != null)
                if (showLanguageName) ...[
                  _getText(
                    text: HiveRepository.getCurrentLanguage()!.languageName,
                    fontSize: 12,
                    fontColor: context.colorScheme.lightGreyColor,
                  ),
                ],
              if (showDarkModeToggle) ...[
                CustomSwitch(
                  thumbColor: context.read<AppThemeCubit>().state.appTheme ==
                          AppTheme.dark
                      ? Colors.green
                      : Colors.red,
                  trackColor: context.colorScheme.lightGreyColor.withAlpha(40),
                  value: context.read<AppThemeCubit>().state.appTheme ==
                      AppTheme.dark,
                  onChanged: (final bool value) {
                    HiveRepository.setDarkThemeEnable = value;

                    if (value) {
                      context.read<AppThemeCubit>().changeTheme(AppTheme.dark);
                    } else {
                      context.read<AppThemeCubit>().changeTheme(AppTheme.light);
                    }
                  },
                )
              ],
              if (!showLanguageName && !showDarkModeToggle) ...[
                Icon(
                  Icons.chevron_right,
                  color: context.colorScheme.lightGreyColor,
                  size: 24,
                ),
              ],
            ],
          ),
        ),
      );

  Widget backgroundContainer({required final Widget child}) => CustomContainer(
        color: context.colorScheme.secondaryColor,
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 16),
        child: child,
      );

  @override
  Widget build(final BuildContext context) => AnnotatedRegion(
        value: UiUtils.getSystemUiOverlayStyle(context: context),
        child: Scaffold(
          backgroundColor: context.colorScheme.primaryColor,
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: 'profile'.translate(context: context),
            centerTitle: true,
            isLeadingIconEnable: false,
            fontWeight: FontWeight.w600,
            fontSize: 18,
            elevation: 0.5,
          ),
          body: SingleChildScrollView(
            controller: widget.scrollController,
            padding: const EdgeInsetsDirectional.only(bottom: 65),
            child: BlocBuilder<UserDetailsCubit, UserDetailsState>(
              builder: (final BuildContext context, UserDetailsState state) {
                final token = HiveRepository.getUserToken;

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    BlocBuilder<UserDetailsCubit, UserDetailsState>(
                      builder: (final BuildContext context,
                          final UserDetailsState userDetails) {
                        if (userDetails is UserDetails) {
                          return CustomInkWellContainer(
                            onTap: () {
                              if (token == '') {
                                Navigator.pushNamed(
                                  context,
                                  loginRoute,
                                  arguments: {'source': 'profileScreen'},
                                );
                              } else {
                                final UserDetailsModel userDetails = context
                                    .read<UserDetailsCubit>()
                                    .getUserDetails();

                                Navigator.pushNamed(
                                  context,
                                  editProfileRoute,
                                  arguments: {
                                    'source': 'profileScreen',
                                    "isNewUser": false,
                                    "countryCodeName":
                                        userDetails.countryCodeName,
                                    "countryCode": userDetails.countryCode,
                                    "phoneNumberWithOutCountryCode":
                                        userDetails.phone,
                                    "loginType": userDetails.loginType,
                                  },
                                );
                              }
                            },
                            child: Padding(
                              padding: const EdgeInsetsDirectional.only(top: 8),
                              child: backgroundContainer(
                                child: Row(
                                  children: [
                                    CustomContainer(
                                      width: 72,
                                      height: 72,
                                      borderRadius: UiUtils.borderRadiusOf50,
                                      border: Border.all(
                                        color:
                                            context.colorScheme.lightGreyColor,
                                        width: 0.5,
                                      ),
                                      padding: const EdgeInsets.all(8),
                                      child: ClipRRect(
                                        borderRadius: BorderRadius.circular(
                                            UiUtils.borderRadiusOf50),
                                        child: (HiveRepository
                                                        .getUserProfilePictureURL ==
                                                    null ||
                                                HiveRepository
                                                        .getUserProfilePictureURL ==
                                                    '')
                                            ? CustomSvgPicture(
                                                svgImage: AppAssets.drProfile,
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .blackColor,
                                              )
                                            : CustomCachedNetworkImage(
                                                height: 100,
                                                width: 100,
                                                networkImageUrl: HiveRepository
                                                    .getUserProfilePictureURL
                                                    .toString(),
                                              ),
                                      ),
                                    ),
                                    const CustomSizedBox(
                                      width: 16,
                                    ),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          if (token != '') ...{
                                            Padding(
                                              padding:
                                                  const EdgeInsetsDirectional
                                                      .only(bottom: 4),
                                              child: _getText(
                                                text: HiveRepository
                                                        .getUsername ??
                                                    '',
                                                fontSize: 18,
                                                fontWeight: FontWeight.w600,
                                                fontColor: context
                                                    .colorScheme.blackColor,
                                              ),
                                            ),
                                            if (HiveRepository
                                                    .getUserMobileNumber !=
                                                '')
                                              Padding(
                                                padding:
                                                    const EdgeInsetsDirectional
                                                        .only(bottom: 4),
                                                child: _getText(
                                                  text:
                                                      "${HiveRepository.getUserMobileCountryCode ?? ''}${HiveRepository.getUserMobileNumber ?? ''}",
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.w400,
                                                  fontColor: context.colorScheme
                                                      .lightGreyColor,
                                                ),
                                              ),
                                            _getText(
                                              text: HiveRepository
                                                      .getUserEmailId ??
                                                  '',
                                              fontSize: 12,
                                              fontWeight: FontWeight.w400,
                                              fontColor: context
                                                  .colorScheme.lightGreyColor,
                                            ),
                                          } else ...{
                                            _getText(
                                              text: 'guest'
                                                  .translate(context: context),
                                              fontSize: 18,
                                              fontWeight: FontWeight.w600,
                                              fontColor: context
                                                  .colorScheme.blackColor,
                                            ),
                                            _getText(
                                              text: "login_or_signup"
                                                  .translate(context: context),
                                              fontSize: 12,
                                              fontWeight: FontWeight.w400,
                                              fontColor: context
                                                  .colorScheme.blackColor,
                                            )
                                          },
                                        ],
                                      ),
                                    ),
                                    if (token != '')
                                      const CustomContainer(
                                        width: 18,
                                        height: 18,
                                        borderRadius: UiUtils.borderRadiusOf5,
                                        child: CustomSvgPicture(
                                          svgImage: AppAssets.edit,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        }
                        return const SizedBox.shrink();
                      },
                    ),
                    const CustomSizedBox(
                      height: 8,
                    ),
                    backgroundContainer(
                      child: Column(
                        children: [
                          _buildSectionTitleWidget(
                              title: 'content'.translate(context: context)),
                          if (token != '') ...[
                            _buildListItemWidget(
                              imageName: AppAssets.drNotification,
                              title: 'notification'.translate(context: context),
                              bottomPadding: 16,
                              onTap: () {
                                Navigator.pushNamed(context, notificationRoute);
                              },
                            ),
                            _buildListItemWidget(
                              imageName: AppAssets.drFavorite,
                              title: 'bookmark'.translate(context: context),
                              bottomPadding: 16,
                              onTap: () {
                                Navigator.pushNamed(
                                  context,
                                  bookmarkRoute,
                                );
                              },
                            ),
                            _buildListItemWidget(
                              imageName: AppAssets.drAddress,
                              title: 'address'.translate(context: context),
                              bottomPadding: 16,
                              onTap: () {
                                Navigator.pushNamed(
                                  context,
                                  manageAddressScreen,
                                  arguments: {
                                    'appBarTitle': 'address',
                                  },
                                );
                              },
                            ),
                            _buildListItemWidget(
                              onTap: () {
                                Navigator.pushNamed(
                                    context, paymentDetailsScreen);
                              },
                              imageName: AppAssets.drPayment,
                              title: 'payment'.translate(context: context),
                              bottomPadding: 16,
                            ),
                            _buildListItemWidget(
                              onTap: () {
                                Navigator.pushNamed(context, blogsRoute);
                              },
                              imageName: AppAssets.drBlogs,
                              title: 'blogs'.translate(context: context),
                              bottomPadding: 16,
                            ),
                            _buildListItemWidget(
                              onTap: () {
                                Navigator.pushNamed(context, chatUsersList);
                              },
                              imageName: AppAssets.drChat,
                              title: 'chat'.translate(context: context),
                              bottomPadding: 16,
                            ),
                            _buildListItemWidget(
                              onTap: () {
                                Navigator.pushNamed(context, chatMessages,
                                    arguments: {
                                      "chatUser": ChatUser(
                                        id: "-",
                                        name: "customerSupport"
                                            .translate(context: context),
                                        translatedName: "customerSupport"
                                            .translate(context: context),
                                        receiverType: "0",
                                        senderType: "0",
                                        unReadChats: 0,
                                        bookingId: "-1",
                                        senderId: context
                                                .read<UserDetailsCubit>()
                                                .getUserDetails()
                                                .id ??
                                            "0",
                                      ),
                                    });
                              },
                              imageName: AppAssets.support,
                              title:
                                  'customerSupport'.translate(context: context),
                            ),
                          ] else ...[
                            _buildListItemWidget(
                              onTap: () {
                                Navigator.pushNamed(context, blogsRoute);
                              },
                              imageName: AppAssets.drBlogs,
                              title: 'blogs'.translate(context: context),
                              bottomPadding: 16,
                            ),
                          ],
                        ],
                      ),
                    ),
                    const CustomSizedBox(
                      height: 8,
                    ),
                    backgroundContainer(
                      child: Column(
                        children: [
                          _buildSectionTitleWidget(
                              title: 'preference'.translate(context: context)),
                          _buildListItemWidget(
                            showLanguageName: true,
                            imageName: AppAssets.drLanguage,
                            title: 'language'.translate(context: context),
                            bottomPadding: 16,
                            onTap: () {
                              UiUtils.showBottomSheet(
                                context: context,
                                child: const ChooseLanguageBottomSheet(),
                              );
                            },
                          ),
                          _buildListItemWidget(
                            showDarkModeToggle: true,
                            imageName: AppAssets.drTheme,
                            title: 'darkMode'.translate(context: context),
                          ),
                        ],
                      ),
                    ),
                    const CustomSizedBox(
                      height: 8,
                    ),
                    backgroundContainer(
                      child: Column(
                        children: [
                          _buildSectionTitleWidget(
                              title: 'legal'.translate(context: context)),
                          _buildListItemWidget(
                            imageName: AppAssets.drTerms,
                            title: 'termsofservice'.translate(context: context),
                            bottomPadding: 16,
                            onTap: () {
                              Navigator.of(context).pushNamed(appSettingsRoute,
                                  arguments: "termsofservice");
                            },
                          ),
                          _buildListItemWidget(
                            imageName: AppAssets.drPrivacy,
                            title:
                                'privacyAndPolicy'.translate(context: context),
                            onTap: () {
                              Navigator.of(context).pushNamed(appSettingsRoute,
                                  arguments: "privacyAndPolicy");
                            },
                          ),
                        ],
                      ),
                    ),
                    const CustomSizedBox(
                      height: 8,
                    ),
                    backgroundContainer(
                      child: Column(
                        children: [
                          _buildSectionTitleWidget(
                              title: 'others'.translate(context: context)),
                          _buildListItemWidget(
                            imageName: AppAssets.drBecomeProvider,
                            title: 'becomeProvider'.translate(context: context),
                            bottomPadding: 16,
                            onTap: () => _handleBecomeProviderClick(context),
                          ),
                          Builder(builder: (context) {
                            return _buildListItemWidget(
                              key: _shareButtonKey,
                              imageName: AppAssets.drShare,
                              title: 'shareApp'.translate(context: context),
                              bottomPadding: 16,
                              onTap: () => _handleShareAppClick(context),
                            );
                          }),
                          _buildListItemWidget(
                            imageName: AppAssets.drRateUs,
                            bottomPadding: 16,
                            title: 'rateApp'.translate(context: context),
                            onTap: () => _handleRateAppClick(context),
                          ),
                          _buildListItemWidget(
                            imageName: AppAssets.drHelp,
                            title: 'faqs'.translate(context: context),
                            bottomPadding: 16,
                            onTap: () {
                              Navigator.pushNamed(context, faqsRoute);
                            },
                          ),
                          _buildListItemWidget(
                            imageName: AppAssets.drContactUs,
                            title: 'contactUs'.translate(context: context),
                            bottomPadding: 16,
                            onTap: () {
                              Navigator.of(context).pushNamed(
                                contactUsRoute,
                              );
                            },
                          ),
                          _buildListItemWidget(
                            imageName: AppAssets.drAboutUs,
                            title: 'aboutUs'.translate(context: context),
                            onTap: () {
                              Navigator.of(context).pushNamed(appSettingsRoute,
                                  arguments: "aboutUs");
                            },
                          ),
                        ],
                      ),
                    ),
                    if (token != '') ...[
                      const CustomSizedBox(
                        height: 8,
                      ),
                      const Divider(
                        color: AppColors.redColor,
                        height: 0.5,
                        thickness: 0.5,
                      ),
                      backgroundContainer(
                        child: Column(
                          children: [
                            _buildSectionTitleWidget(
                                title: 'account'.translate(context: context),
                                color: AppColors.redColor),
                            if ((context
                                        .watch<SystemSettingCubit>()
                                        .loginSettings
                                        ?.isPasswordLoginEnabled ??
                                    false) &&
                                (context
                                        .watch<UserDetailsCubit>()
                                        .getUserDetails()
                                        .loginType
                                        ?.hasPassword ??
                                    false))
                              _buildListItemWidget(
                                imageName: AppAssets.drProfile,
                                imageColor: AppColors.redColor,
                                title: 'changePassword'
                                    .translate(context: context),
                                bottomPadding: 16,
                                onTap: () {
                                  Navigator.pushNamed(
                                      context, changePasswordRoute);
                                },
                              ),
                            _buildListItemWidget(
                              imageName: AppAssets.drLogout,
                              title:
                                  'logoutFromApp'.translate(context: context),
                              imageColor: AppColors.redColor,
                              bottomPadding: 16,
                              onTap: () {
                                UiUtils.showAnimatedDialog(
                                    context: context,
                                    child: BlocProvider<LogoutCubit>(
                                      create: (context) => LogoutCubit(
                                          AuthenticationRepository()),
                                      child: const LogoutAccountDialog(),
                                    ));
                              },
                            ),
                            _buildListItemWidget(
                              imageName: AppAssets.drDeleteAccount,
                              title:
                                  'deleteAccount'.translate(context: context),
                              imageColor: AppColors.redColor,
                              onTap: () {
                                UiUtils.showAnimatedDialog(
                                  context: context,
                                  child: BlocProvider(
                                    create: (context) => DeleteUserAccountCubit(
                                        AuthenticationRepository()),
                                    child: const DeleteUserAccountDialog(),
                                  ),
                                );
                              },
                            ),
                          ],
                        ),
                      ),
                      const Divider(
                        color: AppColors.redColor,
                        height: 0.5,
                        thickness: 0.5,
                      ),
                    ],
                    CustomSizedBox(
                        height: 70,
                        child: Center(
                          child: CustomText(
                            "$appName ${Platform.isAndroid ? "v$latestVersionOfAndroid" : "v$latestVersionOfIOS"}",
                            fontSize: 14,
                            fontWeight: FontWeight.w400,
                            color: context.colorScheme.lightGreyColor,
                          ),
                        )),
                  ],
                );
              },
            ),
          ),
        ),
      );

  Future<void> _handleBecomeProviderClick(BuildContext context) async {
    final String providerAppURL =
        context.read<SystemSettingCubit>().getProviderAppURL;

    if (await canLaunchUrl(Uri.parse(providerAppURL))) {
      try {
        launchUrl(Uri.parse(providerAppURL),
            mode: LaunchMode.externalApplication);
      } catch (e) {
        UiUtils.showMessage(
          context,
          'somethingWentWrong'.translate(context: context),
          ToastificationType.error,
        );
      }
    }
  }

  final GlobalKey _shareButtonKey = GlobalKey();

  void _handleShareAppClick(BuildContext context) {
    if (_isShareInProgress) return;

    _isShareInProgress = true;
    try {
      UiUtils.share(
        context: context,
        context.read<SystemSettingCubit>().getCustomerAppURL,
      );
    } catch (e) {
      UiUtils.showMessage(
        context,
        'somethingWentWrong'.translate(context: context),
        ToastificationType.warning,
      );
    } finally {
      Future.delayed(const Duration(milliseconds: 1000), () {
        _isShareInProgress = false;
      });
    }
  }

  Future<void> _handleRateAppClick(BuildContext context) async {
    final String customerAppURL =
        context.read<SystemSettingCubit>().getCustomerAppURL;
    if (await canLaunchUrl(Uri.parse(customerAppURL))) {
      launchUrl(Uri.parse(customerAppURL),
          mode: LaunchMode.externalApplication);
    } else {
      UiUtils.showMessage(context, 'unableToOpen'.translate(context: context),
          ToastificationType.error);
    }
  }
}
