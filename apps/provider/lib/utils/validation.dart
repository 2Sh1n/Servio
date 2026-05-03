import 'package:edemand_partner/app/generalImports.dart';

class Validator {
  static String? validateNumber(BuildContext context, String number) {
    if (number.isEmpty) {
      return 'fieldMustNotBeEmpty'.translate(context: context);
    } else if (number.length < UiUtils.minimumMobileNumberDigit ||
        number.length > UiUtils.maximumMobileNumberDigit) {
      return 'mobileNumberShouldBeInRange'.translate(context: context);
    }

    return null;
  }

  static String? validatePassword(BuildContext context, String? password) {
    if (password == null || password.isEmpty) {
      return 'fieldMustNotBeEmpty'.translate(context: context);
    }

    // Check minimum length
    if (password.length < 8) {
      return 'passwordMustBeAtLeast8Chars'.translate(context: context);
    }

    // Check for uppercase letter
    if (!password.contains(RegExp('[A-Z]'))) {
      return 'passwordMustHaveUppercase'.translate(context: context);
    }

    // Check for lowercase letter
    if (!password.contains(RegExp('[a-z]'))) {
      return 'passwordMustHaveLowercase'.translate(context: context);
    }

    // Check for number
    if (!password.contains(RegExp('[0-9]'))) {
      return 'passwordMustHaveNumber'.translate(context: context);
    }

    // Check for special character
    if (!password.contains(RegExp(r'[!@#$%^&*(),.?":{}|<>]'))) {
      return 'passwordMustHaveSpecialChar'.translate(context: context);
    }

    return null;
  }

  static String? nullCheck(
    BuildContext context,
    String? value, {
    int? requiredLength,
    bool? nonZero,
  }) {
    if (value!.isEmpty) {
      return 'fieldMustNotBeEmpty'.translate(context: context);
    } else if (nonZero == true) {
      if (value == '0') {
        return 'enterMoreThenZero'.translate(context: context);
      }
    } else if (requiredLength != null) {
      if (value.length < requiredLength) {
        return '${"textMustBe".translate(context: context)} $requiredLength ${"characterLong".translate(context: context)}';
      } else {
        return null;
      }
    }

    return null;
  }

  static String? validateLatitude(BuildContext context, String? latitude) {
    if (latitude!.isEmpty) {
      return 'fieldMustNotBeEmpty'.translate(context: context);
    } else if (!_isLatitudeValid(latitude)) {
      return 'pleaseEnterValidLatitude'.translate(context: context);
    }
    return null;
  }

  static String? validateLongitude(BuildContext context, String? longitude) {
    if (longitude!.isEmpty) {
      return 'fieldMustNotBeEmpty'.translate(context: context);
    } else if (!_isLongitudeValid(longitude)) {
      return 'pleaseEnterValidLongitude'.translate(context: context);
    }
    return null;
  }

  static String? validateEmail(BuildContext context, String? email) {
    if (email!.isEmpty) {
      return 'fieldMustNotBeEmpty'.translate(context: context);
    } else if (!_isValidateEmail(email)) {
      return 'pleaseEnterValidEmail'.translate(context: context);
    }
    return null;
  }

  /// Replace extra coma from String
  ///
  static String filterAddressString(String text) {
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
    text = text.trim();
    final String filterdText = text
        .replaceAll(removeComaFromString, ',')
        .replaceAll(leadingAndTrailing, '');

    return filterdText;
  }

  static bool _isValidateEmail(String email) {
    final bool emailValid = RegExp(
      r"^[a-zA-Z0-9.a-zA-Z0-9.!#$%&'*+-/=?^_`{|}~]+@[a-zA-Z0-9]+\.[a-zA-Z]+",
    ).hasMatch(email);

    return emailValid;
  }

  static bool _isLatitudeValid(String latitude) {
    final bool validLatitude = RegExp(
      r'^-?(90(\.0{1,7})?|[0-8][0-9](\.\d{1,7})?|[0-9](\.\d{1,7})?)$',
    ).hasMatch(latitude);

    return validLatitude;
  }

  static bool _isLongitudeValid(String longitude) {
    final bool validLongitude = RegExp(
      r'^-?(180(\.0{1,7})?|1[0-7][0-9](\.\d{1,7})?|[0-9]{1,2}(\.\d{1,7})?)$',
    ).hasMatch(longitude);

    return validLongitude;
  }
}
