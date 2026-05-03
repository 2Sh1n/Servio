import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';

String? isValidMobileNumber(
    {required final String number, required final BuildContext context}) {
  if (number.isEmpty) {
    return "fieldMustNotBeEmpty".translate(context: context);
  } else if (number.length < UiUtils.minimumMobileNumberDigit ||
      number.length > UiUtils.maximumMobileNumberDigit) {
    return "mobileNumberShouldBeBetween6And15Numbers"
        .translate(context: context);
  }

  return null;
}

String? isValidEmail(
    {required final String email, required BuildContext context}) {
  if (RegExp(
    r'^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$',
  ).hasMatch(email)) {
    return null;
  }
  return "pleaseEnterValidEmail".translate(context: context);
}

String? isTextFieldEmpty(
    {required final String? value, required final BuildContext context}) {
  if (value!.isEmpty) {
    return "fieldMustNotBeEmpty".translate(context: context);
  }

  return null;
}

/// Validate password with all requirements
///
String? isValidPassword(
    {required final String password, required BuildContext context}) {
  if (password.isEmpty) {
    return "fieldMustNotBeEmpty".translate(context: context);
  }
  if (password.length < 8) {
    return "passwordMustBeAtLeast8Characters".translate(context: context);
  }
  // Check for at least one uppercase letter
  if (!RegExp('[A-Z]').hasMatch(password)) {
    return "passwordMustHaveUppercase".translate(context: context);
  }
  // Check for at least one lowercase letter
  if (!RegExp('[a-z]').hasMatch(password)) {
    return "passwordMustHaveLowercase".translate(context: context);
  }
  // Check for at least one number
  if (!RegExp('[0-9]').hasMatch(password)) {
    return "passwordMustHaveNumber".translate(context: context);
  }
  // Check for at least one special character
  if (!RegExp(r'[!@#$%^&*(),.?":{}|<>]').hasMatch(password)) {
    return "passwordMustHaveSpecialChar".translate(context: context);
  }
  return null;
}

String? confirmPasswordValidator({
  required final String password,
  required final String confirmPassword,
  required BuildContext context,
}) {
  if (confirmPassword.isEmpty) {
    return "fieldMustNotBeEmpty".translate(context: context);
  }
  if (password != confirmPassword) {
    return "passwordsDoNotMatch".translate(context: context);
  }
  return null;
}

String filterAddressString(String text) {
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

  final String filteredText = text
      .trim()
      .replaceAll(removeComaFromString, ",")
      .replaceAll(leadingAndTrailing, '');

  return filteredText;
}
