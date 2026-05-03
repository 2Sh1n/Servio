import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

enum AppTheme { dark, light }

// Common theme data that both light and dark themes share
final commonThemeData = ThemeData(
  useMaterial3: true,
  fontFamily: "Lexend",
);

final Map<AppTheme, ThemeData> appThemeData = {
  AppTheme.light: commonThemeData.copyWith(
    timePickerTheme: TimePickerThemeData(
      dialBackgroundColor: AppColors.lightAccentColor.withValues(alpha: 0.1),
      backgroundColor: AppColors.lightPrimaryColor,
      hourMinuteTextColor: AppColors.lightAccentColor,
      hourMinuteColor: AppColors.lightAccentColor.withValues(alpha: 0.1),
      cancelButtonStyle: ButtonStyle(
        foregroundColor: WidgetStatePropertyAll(AppColors.lightAccentColor),
      ),
      confirmButtonStyle: ButtonStyle(
        foregroundColor: WidgetStatePropertyAll(AppColors.lightAccentColor),
      ),
      dayPeriodColor: AppColors.lightAccentColor,
      dayPeriodTextColor: AppColors.lightSubHeadingColor1,
      dialHandColor: AppColors.lightAccentColor,
    ),
    scaffoldBackgroundColor: AppColors.lightPrimaryColor,
    brightness: Brightness.light,
    primaryColor: AppColors.lightPrimaryColor,
    secondaryHeaderColor: AppColors.lightSubHeadingColor1,
    textSelectionTheme: TextSelectionThemeData(
      cursorColor: AppColors.lightAccentColor,
      selectionHandleColor: AppColors.lightAccentColor,
    ),
  ),
  AppTheme.dark: commonThemeData.copyWith(
    timePickerTheme: TimePickerThemeData(
      helpTextStyle: TextStyle(color: AppColors.darkSubHeadingColor1),
      dialBackgroundColor: AppColors.darkAccentColor.withValues(alpha: 0.5),
      backgroundColor: AppColors.darkPrimaryColor,
      hourMinuteTextColor: AppColors.darkAccentColor,
      hourMinuteColor: AppColors.darkPrimaryColor.withValues(alpha: 0.1),
      cancelButtonStyle: ButtonStyle(
        foregroundColor: WidgetStatePropertyAll(AppColors.darkAccentColor),
      ),
      confirmButtonStyle: ButtonStyle(
        foregroundColor: WidgetStatePropertyAll(AppColors.darkAccentColor),
      ),
      dayPeriodColor: AppColors.darkAccentColor,
      dayPeriodTextColor: AppColors.darkSubHeadingColor1,
      dialHandColor: AppColors.darkAccentColor,
    ),
    brightness: Brightness.dark,
    primaryColor: AppColors.darkPrimaryColor,
    secondaryHeaderColor: AppColors.darkSubHeadingColor1,
    scaffoldBackgroundColor: AppColors.darkPrimaryColor,
    textSelectionTheme: TextSelectionThemeData(
      cursorColor: AppColors.darkAccentColor,
      selectionHandleColor: AppColors.darkAccentColor,
    ),
  )
};
