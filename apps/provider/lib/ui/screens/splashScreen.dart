import 'package:flutter/material.dart';

import '../../app/generalImports.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  SplashScreenState createState() => SplashScreenState();
}

class SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    APICall();
  }

  void APICall() {
    Future.delayed(Duration.zero).then((value) {
      try {
        context.read<ProviderDetailsCubit>().setUserInfo(
          HiveRepository.getProviderDetails(),
        );

        context.read<FetchSystemSettingsCubit>().getSettings(
          isAnonymous: false,
        );
        context.read<LanguageListCubit>().getLanguageList();
      } catch (_) {}
    });
  }

  Future<void> checkIsUserAuthenticated({
    required bool isNeedToShowAppUpdate,
  }) async {
    final languageCubit = context.read<LanguageDataCubit>();
    final currentState = languageCubit.state;

    // Handle current state immediately if already success
    if (currentState is GetLanguageDataSuccess) {
      await _handleNavigation(isNeedToShowAppUpdate);
      return;
    }

    await for (final state in languageCubit.stream) {
      if (state is GetLanguageDataSuccess) {
        await _handleNavigation(isNeedToShowAppUpdate);
        break; // Exit the stream listener once we get success
      } else if (state is GetLanguageDataError) {
        // If language data fails to load, still proceed with navigation
        // This ensures the app doesn't get stuck on splash screen
        await _handleNavigation(isNeedToShowAppUpdate);
        break;
      }
    }
  }

  Future<void> _handleNavigation(bool isNeedToShowAppUpdate) async {
    await Future.delayed(const Duration(seconds: 2));

    if (!mounted) return; // <-- prevents using context if widget is gone

    final authState = context.read<AuthenticationCubit>().state;
    final providerDetails = context
        .read<ProviderDetailsCubit>()
        .providerDetails
        .providerInformation;

    if (authState == AuthenticationState.authenticated) {
      if (providerDetails?.isApproved == '1') {
        Navigator.of(context).pushReplacementNamed(Routes.main);
      } else if (providerDetails?.isApproved == '0') {
        Navigator.pushReplacementNamed(
          context,
          Routes.registration,
          arguments: {'isEditing': false},
        );
      } else {
        Navigator.of(context).pushReplacementNamed(Routes.loginScreenRoute);
      }
    } else if (authState == AuthenticationState.unAuthenticated ||
        authState == AuthenticationState.firstTime) {
      Navigator.of(context).pushReplacementNamed(Routes.loginScreenRoute);
    }

    if (!mounted) return; // <-- double-check if screen is still mounted
    if (isNeedToShowAppUpdate) {
      Navigator.pushNamed(
        context,
        Routes.appUpdateScreen,
        arguments: {'isForceUpdate': false},
      );
    }
  }

  Future<void> getDefaultLanguage(AppLanguage appLanguage) async {
    // Let getLanguageData handle all the logic:
    // - Check updated_at and use cached data if available
    // - Fetch from API if needed
    // - Handle errors with fallback to cached or local data
    // No need for try-catch here since getLanguageData has built-in error handling
    context.read<LanguageDataCubit>().getLanguageData(
      languageData: appLanguage,
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion(
      value: SystemUiOverlayStyle(
        statusBarColor: AppColors.splashScreenGradientTopColor,
        systemNavigationBarColor: AppColors.splashScreenGradientBottomColor,
      ),
      child: Scaffold(
        body: BlocConsumer<FetchSystemSettingsCubit, FetchSystemSettingsState>(
          listener: (BuildContext context, FetchSystemSettingsState state) async {
            if (state is FetchSystemSettingsSuccess) {
              try {
                //update provider subscription information, backup for get latest payment method
                final SubscriptionInformation subscriptionInformation =
                    state.subscriptionInformation;
                context.read<ProviderDetailsCubit>().updateProviderDetails(
                  subscriptionInformation: subscriptionInformation,
                );

                final AppSetting _appSetting = state.appSetting;
                final GeneralSettings _generalSettings = state.generalSettings;

                UiUtils.maxCharactersInATextMessage = _generalSettings
                    .maxCharactersInATextMessage
                    .toString()
                    .toInt();
                UiUtils.maxFilesOrImagesInOneMessage = _generalSettings
                    .maxFilesOrImagesInOneMessage
                    .toString()
                    .toInt();
                UiUtils.maxFileSizeInMBCanBeSent = _generalSettings
                    .maxFileSizeInMBCanBeSent
                    .toString()
                    .toInt();

                // assign currency values
                //  context.read<FetchSystemSettingsCubit>().getSystemCurrency();
                context
                    .read<FetchSystemSettingsCubit>()
                    .SystemCurrencyCountryCode;
                // UiUtils.decimalPointsForPrice = _appSetting.decimalPoint;
                context.read<CountryCodeCubit>().loadAllCountryCode(context);

                if (context
                        .read<FetchSystemSettingsCubit>()
                        .appSetting
                        .providerAppMaintenanceMode ==
                    '1') {
                  Navigator.pushReplacementNamed(
                    context,
                    Routes.maintenanceModeScreen,
                    arguments: _appSetting.getMessageForProviderApplication(),
                  );
                  return;
                }

                // here we will check current version and updated version from panel
                // if application current version is less than updated version then
                // we will show app update screen

                final String? latestAndroidVersion =
                    _appSetting.providerCurrentVersionAndroidApp;
                final String? latestIOSVersion =
                    _appSetting.providerCurrentVersionIosApp;

                final PackageInfo packageInfo =
                    await PackageInfo.fromPlatform();

                final String currentApplicationVersion = packageInfo.version;

                final Version currentVersion = Version.parse(
                  currentApplicationVersion,
                );
                final latestVersionAndroid = Version.parse(
                  latestAndroidVersion == ''
                      ? "1.0.0"
                      : latestAndroidVersion ?? '1.0.0',
                );
                final latestVersionIos = Version.parse(
                  latestIOSVersion == ''
                      ? "1.0.0"
                      : latestIOSVersion ?? "1.0.0",
                );

                if ((Platform.isAndroid &&
                        latestVersionAndroid > currentVersion) ||
                    (Platform.isIOS && latestVersionIos > currentVersion)) {
                  // If it is force update then we will show app update with only Update button
                  if (_appSetting.providerCompulsaryUpdateForceUpdate == '1') {
                    Navigator.pushReplacementNamed(
                      context,
                      Routes.appUpdateScreen,
                      arguments: {'isForceUpdate': true},
                    );
                    return;
                  } else {
                    // If it is normal update then
                    // we will pass true here for isNeedToShowAppUpdate
                    checkIsUserAuthenticated(isNeedToShowAppUpdate: true);
                  }
                } else {
                  //if no update available then we will pass false here for isNeedToShowAppUpdate
                  checkIsUserAuthenticated(isNeedToShowAppUpdate: false);
                }
              } catch (_) {}
            }
          },
          builder: (BuildContext context, FetchSystemSettingsState state) {
            if (state is FetchSystemSettingsFailure) {
              return Center(
                child: ErrorContainer(
                  errorMessage: state.errorMessage.translate(context: context),
                  onTapRetry: () {
                    APICall();
                  },
                ),
              );
            }
            return BlocListener<LanguageDataCubit, LanguageDataState>(
              listener: (context, state) {
                if (state is GetLanguageDataSuccess) {
                  HiveRepository.storeLanguage(
                    data: state.jsonData,
                    lang: state.currentLanguage,
                  );
                }
              },
              child: BlocListener<LanguageListCubit, LanguageListState>(
                listener: (context, languageListState) {
                  if (languageListState is GetLanguageListSuccess) {
                    // Check if there's a stored language in Hive
                    final storedLanguage = HiveRepository.getCurrentLanguage();

                    AppLanguage languageToUse;

                    if (storedLanguage != null) {
                      // Find the matching language in the list (to get updated info like updatedAt)
                      final matchingLanguage = languageListState.languages
                          .firstWhere(
                            (lang) =>
                                lang.languageCode ==
                                storedLanguage.languageCode,
                            orElse: () => languageListState.defaultLanguage!,
                          );

                      // Use the language from the list if found, otherwise use default
                      languageToUse = matchingLanguage;
                    } else {
                      // No stored language, use default from API
                      languageToUse = languageListState.defaultLanguage!;
                    }

                    getDefaultLanguage(languageToUse);
                  }
                },
                child: Stack(
                  children: [
                    CustomContainer(
                      gradient: LinearGradient(
                        colors: [
                          AppColors.splashScreenGradientTopColor,
                          AppColors.splashScreenGradientBottomColor,
                        ],
                        stops: [0, 1],
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                      ),
                      width: MediaQuery.sizeOf(context).width,
                      height: context.screenHeight,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 15,
                        vertical: 10,
                      ),
                      child: const Center(
                        child: Image(image: AssetImage(AppAssets.splashLogo)),
                      ),
                    ),
                    if (showCompanyLogoOnSplash)
                      const Padding(
                        padding: EdgeInsets.only(bottom: 50),
                        child: Align(
                          alignment: Alignment.bottomCenter,
                          child: CustomSvgPicture(
                            svgImage: AppAssets.wrteamLogo,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}
