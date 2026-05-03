import '../../../../app/generalImports.dart';

class Form6BankInfo extends StatelessWidget {
  final GlobalKey<FormState> formKey;
  final TextEditingController bankNameController;
  final TextEditingController bankCodeController;
  final TextEditingController accountNameController;
  final TextEditingController accountNumberController;
  final TextEditingController swiftCodeController;
  final TextEditingController taxNameController;
  final TextEditingController taxNumberController;
  final FocusNode bankNameFocus;
  final FocusNode bankCodeFocus;
  final FocusNode accountNameFocus;
  final FocusNode accountNumberFocus;
  final FocusNode swiftCodeFocus;
  final FocusNode taxNameFocus;
  final FocusNode taxNumberFocus;

  const Form6BankInfo({
    Key? key,
    required this.formKey,
    required this.bankNameController,
    required this.bankCodeController,
    required this.accountNameController,
    required this.accountNumberController,
    required this.swiftCodeController,
    required this.taxNameController,
    required this.taxNumberController,
    required this.bankNameFocus,
    required this.bankCodeFocus,
    required this.accountNameFocus,
    required this.accountNumberFocus,
    required this.swiftCodeFocus,
    required this.taxNameFocus,
    required this.taxNumberFocus,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 15),
      child: Form(
        key: formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 10),
            CustomTextFormField(
              bottomPadding: 15,
              labelText: 'bankNmLbl'.translate(context: context),
              controller: bankNameController,
              currentFocusNode: bankNameFocus,
              nextFocusNode: bankCodeFocus,
              // validator: (String? name) => Validator.nullCheck(context, name),
            ),
            CustomTextFormField(
              bottomPadding: 15,
              labelText: 'bankCodeLbl'.translate(context: context),
              controller: bankCodeController,
              currentFocusNode: bankCodeFocus,
              nextFocusNode: accountNameFocus,
              // validator: (String? bankCode) =>
              //     Validator.nullCheck(context, bankCode),
            ),
            CustomTextFormField(
              bottomPadding: 15,
              labelText: 'accountName'.translate(context: context),
              controller: accountNameController,
              currentFocusNode: accountNameFocus,
              nextFocusNode: accountNumberFocus,
              // validator: (String? accountName) =>
              //     Validator.nullCheck(context, accountName),
            ),
            CustomTextFormField(
              bottomPadding: 15,
              labelText: 'accNumLbl'.translate(context: context),
              controller: accountNumberController,
              currentFocusNode: accountNumberFocus,
              nextFocusNode: swiftCodeFocus,
              // validator: (String? accNumber) =>
              //     Validator.nullCheck(context, accNumber),
            ),
            CustomTextFormField(
              bottomPadding: 15,
              labelText: 'swiftCode'.translate(context: context),
              controller: swiftCodeController,
              currentFocusNode: swiftCodeFocus,
              nextFocusNode: taxNameFocus,
              // validator: (String? swiftCode) =>
              //     Validator.nullCheck(context, swiftCode),
            ),
            CustomTextFormField(
              bottomPadding: 15,
              labelText: 'taxName'.translate(context: context),
              controller: taxNameController,
              currentFocusNode: taxNameFocus,
              nextFocusNode: taxNumberFocus,
              // validator: (String? taxName) =>
              //     Validator.nullCheck(context, taxName),
            ),
            CustomTextFormField(
              bottomPadding: 15,
              labelText: 'taxNumber'.translate(context: context),
              controller: taxNumberController,
              currentFocusNode: taxNumberFocus,
              // validator: (String? taxNumber) =>
              //     Validator.nullCheck(context, taxNumber),
            ),
          ],
        ),
      ),
    );
  }
}
