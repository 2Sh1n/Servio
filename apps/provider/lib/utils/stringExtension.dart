import 'package:edemand_partner/app/generalImports.dart';
import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';

extension StringExtension on String {
  String translate({required BuildContext context}) {
    return (AppLocalization.of(context)!.getTranslatedValues(this) ?? this)
        .trim();
  }

  /// Replace extra coma from String
  ///
  String removeExtraComma() {
    const String middleDuplicateComaRegex = ',(.?),';
    const String leadingAndTrailingComa = r'(^,)|(,$)';
    final RegExp removeComaFromString = RegExp(
      middleDuplicateComaRegex,
      caseSensitive: false,
      multiLine: true,
    );

    final RegExp leadingAndTrailing = RegExp(
      leadingAndTrailingComa,
      multiLine: true,
      caseSensitive: false,
    );

    final String filteredText = trim()
        .replaceAll(removeComaFromString, ',')
        .replaceAll(leadingAndTrailing, '');

    return filteredText;
  }

  String capitalize() {
    if (isEmpty) return this;
    return "${this[0].toUpperCase()}${substring(1).toLowerCase()}";
  }

  String trimLatLong() {
    return length < 10 ? this : substring(0, indexOf('.') + 7);
  }

  String formatDate() {
    try {
      late DateTime dateTime;
      bool dateTimeSet = false;

      // Try to parse the date string with common formats
      try {
        // Try ISO format first
        dateTime = DateTime.parse(this);
        dateTimeSet = true;
      } catch (_) {
        // Try common date formats
        final List<String> possibleFormats = [
          'dd-MM-yyyy',
          'dd/MM/yyyy',
          'MM-dd-yyyy',
          'MM/dd/yyyy',
          'yyyy-MM-dd',
          'yyyy/MM/dd',
          'dd-MM-yyyy HH:mm:ss.SSSZ',
          'dd/MM/yyyy HH:mm:ss.SSSZ',
          'MM-dd-yyyy HH:mm:ss.SSSZ',
          'MM/dd/yyyy HH:mm:ss.SSSZ',
          'yyyy-MM-dd HH:mm:ss.SSSZ',
          'yyyy/MM/dd HH:mm:ss.SSSZ',
          'dd-MM-yyyy HH:mm:ss',
          'dd/MM/yyyy HH:mm:ss',
          'MM-dd-yyyy HH:mm:ss',
          'MM/dd/yyyy HH:mm:ss',
          'yyyy-MM-dd HH:mm:ss',
          'yyyy/MM/dd HH:mm:ss',
        ];

        for (final format in possibleFormats) {
          try {
            dateTime = DateFormat(format).parse(this);
            dateTimeSet = true;
            break;
          } catch (_) {
            continue;
          }
        }

        if (!dateTimeSet) {
          // If all formats fail, try to parse with the system date format
          try {
            dateTime = DateFormat(dateAndTimeSetting["dateFormat"]).parse(this);
            dateTimeSet = true;
          } catch (_) {
            // If parsing fails, try appending time and using ISO format
            try {
              dateTime = DateTime.parse("$this 00:00:00.000Z");
              dateTimeSet = true;
            } catch (_) {
              // Return original string if all parsing fails
              return this;
            }
          }
        }
      }

      return DateFormat("${dateAndTimeSetting["dateFormat"]}").format(dateTime);
    } catch (e) {
      // Return the original string if formatting fails
      return this;
    }
  }

  String formatTime() {
    if (dateAndTimeSetting["use24HourFormat"]) return this;
    return DateFormat(
      "hh:mm a",
    ).format(DateFormat('HH:mm').parse(this)).toString();
  }

  String formatDateAndTimeOnlyDate() {
    final Map<String, String> dateAndTimeSetting = {"dateFormat": "MM/dd/yyyy"};
    return DateFormat(
      dateAndTimeSetting["dateFormat"],
    ).format(DateTime.parse(this));
  }

  String formatAccountNumber() {
    if (length > 5) {
      return '*' * (length - 4) + substring(length - 4);
    }
    return this;
  }

  String formatDateAndTime() {
    try {
      late DateTime dateTime;
      bool dateTimeSet = false;

      // Try to parse the date string with common formats
      try {
        // Try ISO format first
        dateTime = DateTime.parse(this);
        dateTimeSet = true;
      } catch (_) {
        // Try common date formats
        final List<String> possibleFormats = [
          'dd-MM-yyyy HH:mm',
          'dd/MM/yyyy HH:mm',
          'MM-dd-yyyy HH:mm',
          'MM/dd/yyyy HH:mm',
          'yyyy-MM-dd HH:mm',
          'yyyy/MM/dd HH:mm',
        ];

        for (final format in possibleFormats) {
          try {
            dateTime = DateFormat(format).parse(this);
            dateTimeSet = true;
            break;
          } catch (_) {
            continue;
          }
        }

        if (!dateTimeSet) {
          // If all formats fail, try to parse with the system date format
          final String datePart = split(" ").first;
          final String timePart = split(" ").length > 1
              ? split(" ")[1]
              : "00:00";
          try {
            dateTime = DateFormat(
              '${dateAndTimeSetting["dateFormat"]} HH:mm',
            ).parse("$datePart $timePart");
            dateTimeSet = true;
          } catch (_) {
            // Return original if parsing fails
            return this;
          }
        }
      }

      if (dateAndTimeSetting["use24HourFormat"]) {
        final String date = DateFormat(
          dateAndTimeSetting["dateFormat"],
        ).format(dateTime);
        final String time = DateFormat('HH:mm').format(dateTime);
        return "$date $time";
      }

      return DateFormat(
        '${dateAndTimeSetting["dateFormat"]} hh:mm a',
      ).format(dateTime);
    } catch (e) {
      // Return the original string if parsing fails
      return this;
    }
  }

  //price format
  String priceFormat(BuildContext context) {
    // Handle null or empty strings
    if (isEmpty || this == 'null' || this == '') {
      return NumberFormat.currency(
        locale: Platform.localeName,
        name: context
            .read<FetchSystemSettingsCubit>()
            .SystemCurrencyCountryCode,
        symbol: context.read<FetchSystemSettingsCubit>().SystemCurrency,
        decimalDigits: int.parse(
          context.read<FetchSystemSettingsCubit>().SystemDecimalPoint,
        ),
      ).format(0.0);
    }

    try {
      final double newPrice = double.parse(replaceAll(",", ''));
      return NumberFormat.currency(
        locale: Platform.localeName,
        name: context
            .read<FetchSystemSettingsCubit>()
            .SystemCurrencyCountryCode,
        symbol: context.read<FetchSystemSettingsCubit>().SystemCurrency,
        decimalDigits: int.parse(
          context.read<FetchSystemSettingsCubit>().SystemDecimalPoint,
        ),
      ).format(newPrice);
    } catch (e) {
      // Return formatted zero if parsing fails
      return NumberFormat.currency(
        locale: Platform.localeName,
        name: context
            .read<FetchSystemSettingsCubit>()
            .SystemCurrencyCountryCode,
        symbol: context.read<FetchSystemSettingsCubit>().SystemCurrency,
        decimalDigits: int.parse(
          context.read<FetchSystemSettingsCubit>().SystemDecimalPoint,
        ),
      ).format(0.0);
    }
  }

  String extractFileName() {
    try {
      return split('/').last;
    } catch (_) {
      return '';
    }
  }

  String formatPercentage() {
    return '${toString()} %';
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

  dynamic toInt() {
    if (isEmpty) return this;
    return int.tryParse(this);
  }
}

/// Locale and language helpers shared across the app
class LocaleUtils {
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
    final String lowerCode = normalizedCode.toLowerCase();
    if (DateFormat.localeExists(lowerCode)) {
      return lowerCode;
    }

    // Try base language code (e.g., 'ar' from 'arb', 'en' from 'en_US')
    if (normalizedCode.contains('_')) {
      final String baseLanguage = normalizedCode.split('_').first.toLowerCase();
      if (DateFormat.localeExists(baseLanguage)) {
        return baseLanguage;
      }
    }

    // Try extracting 2-letter code (common pattern: "arb" -> "ar")
    if (normalizedCode.length > 2) {
      final String twoLetterCode = lowerCode.substring(0, 2);
      if (DateFormat.localeExists(twoLetterCode)) {
        return twoLetterCode;
      }
    }

    // Invalid language code
    return null;
  }

  /// Gets current locale with proper fallback chain
  /// Step 1: Try LanguageDataCubit
  /// Step 2: Try storedLanguage from HiveRepository
  /// Step 3: Try defaultLanguage from LanguageListCubit
  /// Step 4: Fallback to 'en'
  static Locale getCurrentLocale(BuildContext context) {
    String? validLanguageCode;

    // Step 1: Try to get from LanguageDataCubit
    try {
      final languageState = context.read<LanguageDataCubit>().state;
      if (languageState is GetLanguageDataSuccess) {
        validLanguageCode = getValidLanguageCode(
          languageState.currentLanguage.languageCode,
        );
      }
    } catch (_) {
      // LanguageDataCubit not available, continue to next step
    }

    // Step 2: If Step 1 failed, try stored language
    if (validLanguageCode == null) {
      final AppLanguage? storedLanguage = HiveRepository.getCurrentLanguage();
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
      } catch (_) {
        // LanguageListCubit not available, continue to next step
      }
    }

    // Step 4: Final fallback to English (always valid)
    return Locale(validLanguageCode ?? 'en');
  }

  /// Validates and normalizes language code for DateFormat usage
  /// Returns a valid locale code, falling back to default language or "en"
  static String getValidLanguageCodeOrFallback(String? languageCode) {
    // Use the public method for validation
    final String? validCode = getValidLanguageCode(languageCode);

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
      final AppLanguage? currentLanguage = HiveRepository.getCurrentLanguage();

      if (currentLanguage != null && currentLanguage.languageCode.isNotEmpty) {
        final String defaultCode = currentLanguage.languageCode.trim();

        // Check if default locale is supported (try both original and lowercase)
        if (DateFormat.localeExists(defaultCode)) {
          return defaultCode;
        }

        // Try lowercase version
        final String lowerDefaultCode = defaultCode.toLowerCase();
        if (DateFormat.localeExists(lowerDefaultCode)) {
          return lowerDefaultCode;
        }

        // Try base language code (e.g., 'ar' from 'arb', 'en' from 'en_US')
        if (defaultCode.contains('_')) {
          final String baseLanguage = defaultCode
              .split('_')
              .first
              .toLowerCase();
          if (DateFormat.localeExists(baseLanguage)) {
            return baseLanguage;
          }
        }

        // Try extracting 2-letter code
        if (defaultCode.length > 2) {
          final String twoLetterCode = lowerDefaultCode.substring(0, 2);
          if (DateFormat.localeExists(twoLetterCode)) {
            return twoLetterCode;
          }
        }
      }
    } catch (_) {
      // Error getting default language, use "en"
    }

    // Final fallback to "en"
    return Intl.defaultLocale?.split('_').first ?? 'en';
  }
}
