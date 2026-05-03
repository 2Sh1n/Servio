import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';

import '../../../app/generalImports.dart';

class AppSettingScreen extends StatelessWidget {
  const AppSettingScreen({super.key, required this.title});

  final String title;

  static Route<AppSettingScreen> route(RouteSettings routeSettings) {
    final Map<String, dynamic> parameter =
        routeSettings.arguments as Map<String, dynamic>;
    return CupertinoPageRoute(
      builder: (_) => AppSettingScreen(title: parameter['title']),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedSafeArea(
      child: InterstitialAdWidget(
        child: Scaffold(
          backgroundColor: Theme.of(context).colorScheme.primaryColor,
          appBar: UiUtils.getSimpleAppBar(
            statusBarColor: context.colorScheme.secondaryColor,
            context: context,
            title: title.translate(context: context),
            elevation: 1,
          ),
          bottomNavigationBar: const BannerAdWidget(),
          body: BlocBuilder<FetchSystemSettingsCubit, FetchSystemSettingsState>(
            builder: (BuildContext context, FetchSystemSettingsState state) {
              if (state is FetchSystemSettingsInProgress) {
                return Center(
                  child: CustomCircularProgressIndicator(
                    color: AppColors.whiteColors,
                  ),
                );
              }

              if (state is FetchSystemSettingsFailure) {
                return Center(
                  child: ErrorContainer(
                    onTapRetry: () {
                      context.read<FetchSystemSettingsCubit>().getSettings(
                        isAnonymous: false,
                      );
                    },
                    errorMessage: state.errorMessage.translate(
                      context: context,
                    ),
                  ),
                );
              }
              if (state is FetchSystemSettingsSuccess) {
                final bool isPrivacyPolicy = title == 'privacyPolicyLbl';
                final bool isAboutUs = title == 'aboutUs';
                final bool isTermAndCondition = title == 'termsConditionLbl';

                final bool termAndConditionHasData =
                    isTermAndCondition && state.termsAndConditions.isNotEmpty;
                final bool privacyPolicyHasData =
                    isPrivacyPolicy && state.privacyPolicy.isNotEmpty;
                final bool aboutUsHasData =
                    isAboutUs && state.aboutUs.isNotEmpty;

                if (termAndConditionHasData ||
                    privacyPolicyHasData ||
                    aboutUsHasData) {
                  return SingleChildScrollView(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 15,
                      vertical: 10,
                    ),
                    clipBehavior: Clip.none,
                    child: HtmlWidget(
                      isTermAndCondition
                          ? state.termsAndConditions
                          : isPrivacyPolicy
                          ? state.privacyPolicy
                          : isAboutUs
                          ? state.aboutUs
                          : state.contactUs,
                      textStyle: TextStyle(
                        color: context.colorScheme.blackColor,
                      ),
                    ),
                  );
                }
                return NoDataContainer(
                  titleKey: 'noDataFound'.translate(context: context),
                  subTitleKey: 'noDataFoundSubTitle'.translate(
                    context: context,
                  ),
                );
              }

              return const CustomContainer();
            },
          ),
        ),
      ),
    );
  }
}
