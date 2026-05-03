import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';

class AppSettingsScreen extends StatefulWidget {
  const AppSettingsScreen({required this.title, final Key? key})
      : super(key: key);
  final String title;

  static Route<AppSettingsScreen> route(final RouteSettings routeSettings) =>
      CupertinoPageRoute(
        builder: (final _) =>
            AppSettingsScreen(title: routeSettings.arguments as String),
      );

  @override
  State<AppSettingsScreen> createState() => _AppSettingsScreenState();
}

//about_us / privacy_policy / terms_conditions / contact_us / instructions
class _AppSettingsScreenState extends State<AppSettingsScreen> {
  String getType() {
    if (widget.title == "aboutUs") {
      return context.read<SystemSettingCubit>().aboutUs;
    }
    if (widget.title == "termsofservice") {
      return context.read<SystemSettingCubit>().termCondition;
    }
    if (widget.title == "privacyAndPolicy") {
      return context.read<SystemSettingCubit>().privacyPolicy;
    }
    if (widget.title == "contactUs") {
      return context.read<SystemSettingCubit>().contactUs;
    }
    return '';
  }

  @override
  Widget build(final BuildContext context) => InterstitialAdWidget(
        child: AnnotatedSafeArea(
          isAnnotated: true,
          child:Scaffold(
          backgroundColor: context.colorScheme.primaryColor,
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: widget.title.translate(context: context),
          ),
          bottomNavigationBar: const BannerAdWidget(),
          body: SingleChildScrollView(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: HtmlWidget(
                getType(),
                textStyle: TextStyle(color: context.colorScheme.blackColor),
              ),
            ),
          ),
        ),
      ));
}
