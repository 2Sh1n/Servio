import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class EmailOrPhoneField extends StatefulWidget {
  const EmailOrPhoneField({
    required this.controller,
    final Key? key,
    this.borderColor,
    required this.isReadOnly,
    required this.onChanged,
    this.isPhoneAuthEnabled = true,
    this.isEmailAuthEnabled = true,
  }) : super(key: key);

  final TextEditingController controller;
  final Color? borderColor;
  final bool isReadOnly;
  final Function(String)? onChanged;
  final bool isPhoneAuthEnabled;
  final bool isEmailAuthEnabled;

  @override
  State<EmailOrPhoneField> createState() => _EmailOrPhoneFieldState();
}

class _EmailOrPhoneFieldState extends State<EmailOrPhoneField> {
  int? numberLength;
  bool isEmailMode = false;

  @override
  void initState() {
    super.initState();
    numberLength = widget.controller.text.length;
    _detectInputType(widget.controller.text);
  }

  bool _isNumeric(String text) {
    if (text.isEmpty) return true;
    return RegExp(r'^[0-9]+$').hasMatch(text);
  }

  void _detectInputType(String text) {
    // When both phone and email are enabled, show phone mode by default
    // Switch to email mode only when non-numeric characters are detected
    if (widget.isPhoneAuthEnabled && widget.isEmailAuthEnabled) {
      if (text.isEmpty) {
        setState(() {
          isEmailMode = false;
        });
        return;
      }

      // If input contains any non-numeric characters, switch to email mode
      if (!_isNumeric(text)) {
        setState(() {
          isEmailMode = true;
        });
      } else {
        // Input is purely numeric, keep phone mode
        setState(() {
          isEmailMode = false;
        });
      }
    } else if (widget.isEmailAuthEnabled) {
      // Only email is enabled
      setState(() {
        isEmailMode = true;
      });
    } else {
      // Only phone is enabled
      setState(() {
        isEmailMode = false;
      });
    }
  }

  String? _validateInput(String? value) {
    if (value == null || value.isEmpty) {
      return "fieldMustNotBeEmpty".translate(context: context);
    }

    // Detect if it's a phone number or email
    if (_isNumeric(value)) {
      // Validate as phone number
      return isValidMobileNumber(number: value, context: context);
    } else {
      // Validate as email
      return isValidEmail(email: value, context: context);
    }
  }

  String _getHintText() {
    if (widget.isPhoneAuthEnabled && widget.isEmailAuthEnabled) {
      return "enterEmailOrPhoneNumber".translate(context: context);
    } else if (widget.isEmailAuthEnabled) {
      return "enterEmail".translate(context: context);
    } else {
      return "hintMobileNumber".translate(context: context);
    }
  }

  @override
  Widget build(final BuildContext context) => TextFormField(
        controller: widget.controller,
        keyboardType: widget.isPhoneAuthEnabled && widget.isEmailAuthEnabled
            ? TextInputType.text
            : (isEmailMode ? TextInputType.emailAddress : TextInputType.phone),
        style: TextStyle(
            fontSize: 16, color: Theme.of(context).colorScheme.blackColor),
        readOnly: widget.isReadOnly,
        inputFormatters: widget.isPhoneAuthEnabled && !widget.isEmailAuthEnabled
            ? [FilteringTextInputFormatter.digitsOnly]
            : null,
        validator: (final value) => _validateInput(value),
        onChanged: (text) {
          _detectInputType(text);
          setState(() {
            numberLength = text.length;
          });
          widget.onChanged?.call(text);
        },
        decoration: InputDecoration(
          contentPadding: const EdgeInsetsDirectional.only(
            bottom: 2,
            end: 12,
            start: 12,
          ),
          filled: true,
          fillColor: Theme.of(context).colorScheme.secondaryColor,
          hintStyle: TextStyle(
              fontSize: 16,
              color: Theme.of(context).colorScheme.lightGreyColor),
          focusedBorder: OutlineInputBorder(
            borderSide: BorderSide(
              color: widget.borderColor ?? context.colorScheme.accentColor,
            ),
            borderRadius: const BorderRadius.all(
                Radius.circular(UiUtils.borderRadiusOf10)),
          ),
          enabledBorder: OutlineInputBorder(
            borderSide: BorderSide(
                color: widget.borderColor ??
                    Theme.of(context).colorScheme.accentColor),
            borderRadius: const BorderRadius.all(
                Radius.circular(UiUtils.borderRadiusOf10)),
          ),
          border: const OutlineInputBorder(
            borderSide: BorderSide(color: Colors.transparent),
            borderRadius:
                BorderRadius.all(Radius.circular(UiUtils.borderRadiusOf10)),
          ),
          errorStyle: const TextStyle(fontSize: 10),
          hintText: _getHintText(),
          prefixIcon: !isEmailMode && widget.isPhoneAuthEnabled
              ? Padding(
                  padding:
                      const EdgeInsetsDirectional.only(start: 12, bottom: 2),
                  child: BlocBuilder<CountryCodeCubit, CountryCodeState>(
                    builder: (final BuildContext context,
                        final CountryCodeState state) {
                      var code = '--';

                      if (state is CountryCodeFetchSuccess) {
                        code = state.selectedCountry!.callingCode;
                      }

                      return CustomInkWellContainer(
                        onTap: () {
                          if (allowOnlySingleCountry ||
                              (state is CountryCodeFetchSuccess &&
                                  state.temporaryCountryList != null &&
                                  state.temporaryCountryList!.length == 1)) {
                            return;
                          }
                          Navigator.pushNamed(context, countryCodePickerRoute)
                              .then((Object? value) {
                            Future.delayed(const Duration(milliseconds: 250))
                                .then((final value) {
                              context
                                  .read<CountryCodeCubit>()
                                  .fillTemporaryList();
                            });
                          });
                        },
                        child: IntrinsicWidth(
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                code,
                                style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.normal,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .blackColor),
                              ),
                              if (!(allowOnlySingleCountry ||
                                  (state is CountryCodeFetchSuccess &&
                                      state.temporaryCountryList != null &&
                                      state.temporaryCountryList!.length == 1)))
                                Icon(
                                  Icons.arrow_drop_down,
                                  color:
                                      Theme.of(context).colorScheme.blackColor,
                                  size: 24,
                                )
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                )
              : null,
        ),
      );
}
