import 'package:flutter/material.dart';

extension AppColors on ColorScheme {
  //light theme colors
  //bg color
  static Color lightPrimaryColor = const Color(0xffF2F1F6); //background color
  //card color
  static Color lightSecondaryColor = const Color(0xffFFFFFF);
  //main color
  static Color lightAccentColor = const Color(0xff2F88EB);
  //text color
  static Color lightSubHeadingColor1 = const Color(0xff212121);

  //dark theme colors
  //background color
  static Color darkPrimaryColor = const Color(0xff000000);
  //card color
  static Color darkSecondaryColor = const Color(0xff212121);
  //main color
  static Color darkAccentColor = const Color(0xff0079FF);
  //text color
  static Color darkSubHeadingColor1 = const Color(0xffFFFFFF);

  //same for both light and dark theme
  //light grey color for dividers, borders, text etc..
  Color get lightGreyColor => const Color(0xff8B8B8B);

  //splashScreen GradientColor
  static Color splashScreenGradientTopColor = const Color(0xff2050D2);
  static Color splashScreenGradientBottomColor = const Color(0xff143386);

  Color get primaryColor =>
      brightness == Brightness.light ? lightPrimaryColor : darkPrimaryColor;

  Color get secondaryColor =>
      brightness == Brightness.light ? lightSecondaryColor : darkSecondaryColor;

  Color get accentColor =>
      brightness == Brightness.light ? lightAccentColor : darkAccentColor;

  Color get blackColor => brightness == Brightness.light
      ? lightSubHeadingColor1
      : darkSubHeadingColor1;

  Color get shimmerBaseColor => brightness == Brightness.light
      ? shimmerBaseColorLight
      : shimmerBaseColorDark;

  Color get shimmerHighlightColor => brightness == Brightness.light
      ? shimmerHighlightColorLight
      : shimmerHighlightColorDark;

  Color get shimmerContentColor => brightness == Brightness.light
      ? shimmerContentColorLight
      : shimmerContentColorDark;

  //dark theme colors
  static Color shimmerBaseColorDark = Colors.grey.withValues(alpha: 0.5);
  static Color shimmerHighlightColorDark = Colors.grey.withValues(alpha: 0.005);
  static Color shimmerContentColorDark = Colors.black.withValues(alpha: 0.3);

//light theme colors
  static Color shimmerBaseColorLight = Colors.black.withValues(alpha: 0.05);
  static Color shimmerHighlightColorLight =
      Colors.black.withValues(alpha: 0.005);
  static Color shimmerContentColorLight = Colors.white;

// Other colors
  static const Color redColor = Color(0xffd33a3a);
  static const Color whiteColors = Colors.white;
  static const Color orangeColor = Colors.orange;

  static const Color ratingStarColor = Colors.amber;

  static Color get greenColor => Colors.green;

  static Color get yellowColor => Colors.yellow;

  static Color get awaitingOrderColor => Colors.black;

  static Color get confirmedOrderColor => const Color(0xff009EA8);

  static Color get startedOrderColor => const Color(0xff0079FF);

  static Color get rescheduledOrderColor => const Color(0xffFF9900);

  static Color get cancelledOrderColor => const Color(0xffC60000);

  static Color get completedOrderColor => const Color(0xff1E9400);

  static Color get pendingPaymentStatusColor => const Color(0xffFF9900);

  static Color get failedPaymentStatusColor => const Color(0xffC60000);

  static Color get successPaymentStatusColor => const Color(0xff1E9400);
}
