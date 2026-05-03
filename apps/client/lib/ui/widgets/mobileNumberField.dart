// ignore_for_file: file_names

import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class MobileNumberFiled extends StatefulWidget {
  const MobileNumberFiled(
      {required this.controller,
      final Key? key,
      this.borderColor,
      required this.isReadOnly,
      required this.onChanged})
      : super(key: key);
  final TextEditingController controller;
  final Color? borderColor;
  final bool isReadOnly;
  final Function(String)? onChanged;

  @override
  State<MobileNumberFiled> createState() => _MobileNumberFiledState();
}

class _MobileNumberFiledState extends State<MobileNumberFiled> {
  int? numberLength;

  @override
  void initState() {
    super.initState();
    numberLength = widget.controller.text.length;
  }

  @override
  Widget build(final BuildContext context) => TextFormField(
        controller: widget.controller,
        inputFormatters: UiUtils.allowOnlyDigits(),
        keyboardType: TextInputType.phone,
        style: TextStyle(
            fontSize: 16, color: Theme.of(context).colorScheme.blackColor),
        readOnly: widget.isReadOnly,
        validator: (final value) =>
            isValidMobileNumber(number: value!, context: context),
        onChanged: (number) {
          setState(() {
            numberLength = number.length;
          });
          widget.onChanged?.call(number);
        },
        decoration: InputDecoration(
          contentPadding: const EdgeInsetsDirectional.only(
            bottom: 2,
            end: 12,
          ),
          filled: true,
          fillColor: Theme.of(context).colorScheme.secondaryColor,
          hintStyle: TextStyle(
              fontSize: 16,
              color: Theme.of(context).colorScheme.lightGreyColor),
          focusedBorder: OutlineInputBorder(
            borderSide: BorderSide(
                color: widget.borderColor ??
                    ((numberLength! < UiUtils.minimumMobileNumberDigit)
                        ? context.colorScheme.lightGreyColor
                        : (numberLength! > UiUtils.maximumMobileNumberDigit)
                            ? AppColors.redColor
                            : context.colorScheme.accentColor)),
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
          hintText: "hintMobileNumber".translate(context: context),
          prefixIcon: Padding(
            padding: const EdgeInsetsDirectional.only(start: 12, bottom: 2),
            child: BlocBuilder<CountryCodeCubit, CountryCodeState>(
              builder:
                  (final BuildContext context, final CountryCodeState state) {
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
                        context.read<CountryCodeCubit>().fillTemporaryList();
                      });
                    });
                  },
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Builder(
                        builder: (BuildContext context) {
                          if (state is CountryCodeFetchSuccess) {
                            return Center(
                              child: CustomText(
                                code,
                                fontWeight: FontWeight.w500,
                                color: Theme.of(context).colorScheme.blackColor,
                                fontSize: 16,
                              ),
                            );
                          }
                          if (state is CountryCodeFetchFail) {
                            return Center(
                              child: CustomText(
                                state.error
                                    .toString()
                                    .translate(context: context),
                              ),
                            );
                          }
                          return const CustomCircularProgressIndicator();
                        },
                      ),
                      const CustomSizedBox(
                        width: 5,
                      ),
                      if (!allowOnlySingleCountry &&
                          !(state is CountryCodeFetchSuccess &&
                              state.temporaryCountryList != null &&
                              state.temporaryCountryList!.length == 1))
                        CustomSvgPicture(
                          svgImage: AppAssets.spDown,
                          height: 5,
                          width: 5,
                          color: Theme.of(context).colorScheme.lightGreyColor,
                        ),
                      const CustomSizedBox(
                        width: 12,
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ),
      );
}
