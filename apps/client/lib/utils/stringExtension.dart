import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

extension StringExtension on String {
  String capitalize() =>
      "${this[0].toUpperCase()}${substring(1).toLowerCase()}";

  dynamic translateAndMakeItCompulsory({required final BuildContext context}) =>
      (AppLocalization.of(context)!.getTranslatedValues(this) ?? this)
          .trim()
          .makeItCompulsory();

  String makeItCompulsory() => '$this *';

  /// Validates if language code is valid using DateFormat.localeExists
  /// Returns valid language code or null if invalid
  static String? getValidLanguageCode(String? languageCode) {
    if (languageCode == null || languageCode.isEmpty) {
      return null;
    }

    final String normalizedCode = languageCode.trim();

    // Check if locale exists (valid language code)
    if (DateFormat.localeExists(normalizedCode)) {
      return normalizedCode;
    }

    // Try lowercase version
    final lowerCode = normalizedCode.toLowerCase();
    if (DateFormat.localeExists(lowerCode)) {
      return lowerCode;
    }

    // Try base language code (e.g., 'ar' from 'arb', 'en' from 'en_US')
    if (normalizedCode.contains('_')) {
      final baseLanguage = normalizedCode.split('_').first.toLowerCase();
      if (DateFormat.localeExists(baseLanguage)) {
        return baseLanguage;
      }
    }

    // Try extracting 2-letter code (common pattern: "arb" -> "ar")
    if (normalizedCode.length > 2) {
      final twoLetterCode = lowerCode.substring(0, 2);
      if (DateFormat.localeExists(twoLetterCode)) {
        return twoLetterCode;
      }
    }

    // Invalid language code
    return null;
  }

  /// Gets current locale with proper fallback chain
  /// Step 1: Try languageState
  /// Step 2: Try storedLanguage
  /// Step 3: Try defaultLanguage from LanguageListCubit
  /// Step 4: Fallback to 'en'
  static Locale getCurrentLocale(BuildContext context) {
    String? validLanguageCode;

    // Step 1: Try to get from languageState
    try {
      final languageState = context.read<LanguageDataCubit>().state;
      if (languageState is GetLanguageDataSuccess) {
        validLanguageCode = getValidLanguageCode(
          languageState.currentLanguage.languageCode,
        );
      }
    } catch (e) {
      // LanguageDataCubit not available, continue to next step
    }

    // Step 2: If Step 1 failed, try stored language
    if (validLanguageCode == null) {
      final storedLanguage = HiveRepository.getCurrentLanguage();
      if (storedLanguage != null) {
        validLanguageCode = getValidLanguageCode(storedLanguage.languageCode);
      }
    }

    // Step 3: If Step 2 failed, try default language from LanguageListCubit
    if (validLanguageCode == null) {
      try {
        final languageListState = context.read<LanguageListCubit>().state;
        if (languageListState is GetLanguageListSuccess &&
            languageListState.defaultLanguage != null) {
          validLanguageCode = getValidLanguageCode(
            languageListState.defaultLanguage!.languageCode,
          );
        }
      } catch (e) {
        // LanguageListCubit not available, continue to next step
      }
    }

    // Step 4: Final fallback to English (always valid)
    return Locale(validLanguageCode ?? 'en');
  }

  /// Validates and normalizes language code for DateFormat usage
  /// Returns a valid locale code, falling back to default language or "en"
  /// This method uses getValidLanguageCode internally and provides fallback
  static String _getValidLanguageCode(String? languageCode) {
    // Use the public method for validation
    final validCode = getValidLanguageCode(languageCode);

    // If validation succeeded, return the valid code
    if (validCode != null) {
      return validCode;
    }

    // If validation failed, fallback to default language code
    return _getDefaultLanguageCode();
  }

  /// Gets default language code, falling back to "en" if not available
  static String _getDefaultLanguageCode() {
    try {
      final currentLanguage = HiveRepository.getCurrentLanguage();
      if (currentLanguage != null && currentLanguage.languageCode.isNotEmpty) {
        final String defaultCode = currentLanguage.languageCode.trim();

        // Check if default locale is supported (try both original and lowercase)
        if (DateFormat.localeExists(defaultCode)) {
          return defaultCode;
        }

        // Try lowercase version
        final lowerDefaultCode = defaultCode.toLowerCase();
        if (DateFormat.localeExists(lowerDefaultCode)) {
          return lowerDefaultCode;
        }

        // Try base language code (e.g., 'ar' from 'arb', 'en' from 'en_US')
        if (defaultCode.contains('_')) {
          final baseLanguage = defaultCode.split('_').first.toLowerCase();
          if (DateFormat.localeExists(baseLanguage)) {
            return baseLanguage;
          }
        }

        // Try extracting 2-letter code
        if (defaultCode.length > 2) {
          final twoLetterCode = lowerDefaultCode.substring(0, 2);
          if (DateFormat.localeExists(twoLetterCode)) {
            return twoLetterCode;
          }
        }
      }
    } catch (e) {
      // Error getting default language, use "en"
    }
    // Final fallback to "en"
    return Intl.defaultLocale?.split('_').first ?? 'en';
  }

  Color toColor() {
    final String hexString = this;
    final buffer = StringBuffer();
    if (hexString.length == 6 || hexString.length == 7) buffer.write('ff');
    buffer.write(hexString.replaceFirst('#', ''));
    return Color(int.parse(buffer.toString(), radix: 16));
  }

  String translate({required final BuildContext context}) =>
      (AppLocalization.of(context)!.getTranslatedValues(this) ?? this).trim();

  String convertTime() {
    final List<String> parts = split(':');

    // Extract hours, minutes, and seconds
    final int hours = int.parse(parts[0]);
    final int minutes = int.parse(parts[1]);
    final int seconds = int.parse(parts[2]);

    // Convert to the desired format
    final String formattedTime =
        '${hours.toString().padLeft(2, '0')}:${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';

    return formattedTime;
  }

  String formatDate() {
    final languageCode = StringExtension._getValidLanguageCode(
      HiveRepository.getCurrentLanguage()?.languageCode,
    );

    DateTime dateTime;
    try {
      // First, try parsing as ISO format (YYYY-MM-DD)
      dateTime = DateTime.parse("$this 00:00:00.000Z");
    } catch (e) {
      try {
        // Remove time part if present (e.g., "29-11-2025 00:00:00.000Z" -> "29-11-2025")
        final dateOnly = split(' ').first.trim();
        final parts = dateOnly.split('-');

        if (parts.length == 3) {
          final firstPart = int.tryParse(parts[0]) ?? 0;
          final secondPart = int.tryParse(parts[1]) ?? 0;
          final thirdPart = int.tryParse(parts[2]) ?? 0;

          // Check if it's DD-MM-YYYY format (first part is day if > 12)
          if (firstPart > 12 && secondPart <= 12 && thirdPart > 1000) {
            // DD-MM-YYYY format (e.g., 29-11-2025)
            dateTime = DateTime(thirdPart, secondPart, firstPart);
          } else if (firstPart <= 12 && secondPart > 12 && thirdPart > 1000) {
            // MM-DD-YYYY format
            dateTime = DateTime(thirdPart, firstPart, secondPart);
          } else if (firstPart > 1000) {
            // YYYY-MM-DD format
            dateTime = DateTime(firstPart, secondPart, thirdPart);
          } else {
            // Try direct parse as fallback
            dateTime = DateTime.parse(dateOnly);
          }
        } else {
          // If format is unexpected, try direct parse
          dateTime = DateTime.parse(dateOnly);
        }
      } catch (e2) {
        // If all parsing fails, return original string
        return this;
      }
    }

    return DateFormat(
      "${dateAndTimeSetting["dateFormat"]}",
      languageCode,
    ).format(dateTime);
  }

  String formatTime() {
    if (dateAndTimeSetting["use24HourFormat"]) return this;
    final languageCode = StringExtension._getValidLanguageCode(
      HiveRepository.getCurrentLanguage()?.languageCode,
    );
    return DateFormat(
      "hh:mm a",
      languageCode,
    ).format(DateFormat('HH:mm').parse(this)).toString();
  }

  String formatDateForBlogs() {
    try {
      // Extract only the date part (e.g., "2025-07-23")
      final trimmed = split(" ").first;

      // Parse date
      final parsedDate = DateTime.parse(trimmed);

      // Format as "Month Day, Year"
      final languageCode = StringExtension._getValidLanguageCode(
        HiveRepository.getCurrentLanguage()?.languageCode,
      );
      return DateFormat("MMMM d, y", languageCode).format(parsedDate);
    } catch (e) {
      return this; // or return "Invalid date" if preferred
    }
  }

  String formatDateAndTime() {
    //  input will be in dd-mm-yyyy hh:mm:ss format
    if (dateAndTimeSetting["use24HourFormat"]) {
      //format the date only return the time as it is
      final String date = split(" ").first;
      return "${date.formatDate()} ${split(" ")[1]}";
    }
    final languageCode = StringExtension._getValidLanguageCode(
      HiveRepository.getCurrentLanguage()?.languageCode,
    );
    return DateFormat(
      '${dateAndTimeSetting["dateFormat"]} hh:mm a',
      languageCode,
    ).format(DateTime.parse(this));
  }

  String priceFormat(BuildContext context) {
    if (isEmpty || "null" == this) return this;
    final double newPrice = double.parse(replaceAll(",", ''));

    return NumberFormat.currency(
      locale: Platform.localeName,
      name: context.read<SystemSettingCubit>().systemCurrencyCountryCode,
      symbol: context.read<SystemSettingCubit>().systemCurrency,
      decimalDigits: int.parse(
        context.read<SystemSettingCubit>().systemDecimalPoints,
      ),
    ).format(newPrice);
  }

  void debugPrint() {
    if (kDebugMode) {}
  }

  bool isImage() => [
    'png',
    'jpg',
    'jpeg',
    'gif',
    'webp',
    'bmp',
    'wbmp',
    'pbm',
    'pgm',
    'ppm',
    'tga',
    'ico',
    'cur',
  ].contains(toLowerCase().split('.').lastOrNull ?? '');

  dynamic convertToInt() {
    if (isEmpty) return this;
    return int.tryParse(this);
  }
}
