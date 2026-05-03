import 'package:edemand_partner/app/generalImports.dart';
import 'package:flutter/material.dart';

class SendWithdrawalRequestBottomsheet extends StatefulWidget {
  const SendWithdrawalRequestBottomsheet({super.key});

  @override
  SendWithdrawalRequestScreenState createState() =>
      SendWithdrawalRequestScreenState();
}

class SendWithdrawalRequestScreenState
    extends State<SendWithdrawalRequestBottomsheet> {
  final GlobalKey<FormState> formKey = GlobalKey<FormState>();

  TextEditingController amountController = TextEditingController();

  FocusNode amountFocus = FocusNode();

  @override
  void initState() {
    super.initState();
  }

  @override
  void dispose() {
    amountController.dispose();
    amountFocus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: true,
      child: BottomSheetLayout(
        title: "sendWithdrawalRequest",
        child: withdrawAmountForm(),
      ),
    );
  }

  Widget withdrawAmountForm() {
    return Form(
      key: formKey,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CustomContainer(
              color: Theme.of(context).colorScheme.primaryColor,
              padding: const EdgeInsetsDirectional.all(15),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  BlocBuilder<ProviderDetailsCubit, ProviderDetailsState>(
                    builder: (context, state) {
                      final bankInfo = state.providerDetails.bankInformation;
                      if (bankInfo == null) {
                        return CustomContainer(
                          padding: const EdgeInsets.all(15),
                          color: Theme.of(context).colorScheme.secondaryColor,
                          borderRadius: UiUtils.borderRadiusOf8,
                          child: CustomText(
                            'noBankDetailsAvailable'.translate(
                              context: context,
                            ),
                            color: Theme.of(context).colorScheme.lightGreyColor,
                          ),
                        );
                      }
                      return CustomContainer(
                        padding: const EdgeInsets.all(15),
                        border: Border.all(
                          color: context.colorScheme.blackColor.withAlpha(50),
                        ),
                        color: Theme.of(context).colorScheme.secondaryColor,
                        width: double.maxFinite,
                        borderRadius: UiUtils.borderRadiusOf8,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CustomText(
                              'bankDetailsLbl'.translate(context: context),
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: Theme.of(context).colorScheme.blackColor,
                            ),
                            const SizedBox(height: 12),
                            // Row 1: Bank Name, Account Name
                            Row(
                              children: [
                                if (bankInfo.bankName?.isNotEmpty == true)
                                  Expanded(
                                    child: _buildBankDetailItem(
                                      context,
                                      'bankNmLbl'.translate(context: context),
                                      bankInfo.bankName ?? '',
                                    ),
                                  ),
                                if (bankInfo.bankName?.isNotEmpty == true &&
                                    bankInfo.accountName?.isNotEmpty == true)
                                  const SizedBox(width: 12),
                                if (bankInfo.accountName?.isNotEmpty == true)
                                  Expanded(
                                    child: _buildBankDetailItem(
                                      context,
                                      'accountName'.translate(context: context),
                                      bankInfo.accountName ?? '',
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            // Row 2: Account Number, Bank Code
                            Row(
                              children: [
                                if (bankInfo.accountNumber?.isNotEmpty == true)
                                  Expanded(
                                    child: _buildBankDetailItem(
                                      context,
                                      'accountNumber'.translate(
                                        context: context,
                                      ),
                                      bankInfo.accountNumber ?? '',
                                    ),
                                  ),
                                if (bankInfo.accountNumber?.isNotEmpty ==
                                        true &&
                                    bankInfo.bankCode?.isNotEmpty == true)
                                  const SizedBox(width: 12),
                                if (bankInfo.bankCode?.isNotEmpty == true)
                                  Expanded(
                                    child: _buildBankDetailItem(
                                      context,
                                      'bankCodeLbl'.translate(context: context),
                                      bankInfo.bankCode ?? '',
                                    ),
                                  ),
                              ],
                            ),
                            // Row 3: Swift Code, Tax Name
                            if (bankInfo.swiftCode?.isNotEmpty == true ||
                                bankInfo.taxName?.isNotEmpty == true) ...[
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  if (bankInfo.swiftCode?.isNotEmpty == true)
                                    Expanded(
                                      child: _buildBankDetailItem(
                                        context,
                                        'swiftCode'.translate(context: context),
                                        bankInfo.swiftCode ?? '',
                                      ),
                                    ),
                                  if (bankInfo.swiftCode?.isNotEmpty == true &&
                                      bankInfo.taxName?.isNotEmpty == true)
                                    const SizedBox(width: 12),
                                  if (bankInfo.taxName?.isNotEmpty == true)
                                    Expanded(
                                      child: _buildBankDetailItem(
                                        context,
                                        'taxName'.translate(context: context),
                                        bankInfo.taxName ?? '',
                                      ),
                                    ),
                                ],
                              ),
                            ],
                            // Row 4: Tax Number
                            if (bankInfo.taxNumber?.isNotEmpty == true) ...[
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  Expanded(
                                    child: _buildBankDetailItem(
                                      context,
                                      'taxNumber'.translate(context: context),
                                      bankInfo.taxNumber ?? '',
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 16),
                  CustomTextFormField(
                    labelText: 'amountLbl'.translate(context: context),
                    controller: amountController,
                    currentFocusNode: amountFocus,
                    fillColor: Theme.of(context).colorScheme.secondaryColor,
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d*')),
                    ],
                    validator: (String? val) {
                      if (val != '') {
                        if (double.parse(val!) <= 0) {
                          return 'amountShouldBeGreaterThanZero'.translate(
                            context: context,
                          );
                        } else if (double.parse(val) >
                            double.parse(
                              (context.read<FetchSystemSettingsCubit>().state
                                      as FetchSystemSettingsSuccess)
                                  .availableAmount,
                            )) {
                          return 'bigAmount'.translate(context: context);
                        }
                      }

                      return Validator.nullCheck(context, val);
                    },
                    textInputType: TextInputType.number,
                  ),
                ],
              ),
            ),
            resetAndSubmitButton(),
          ],
        ),
      ),
    );
  }

  Widget resetAndSubmitButton() {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 10,
        top: 10,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Expanded(child: resetBtn()),
          const SizedBox(width: 10),
          Expanded(child: submitBtn()),
        ],
      ),
    );
  }

  Widget submitBtn() {
    return BlocConsumer<SendWithdrawalRequestCubit, SendWithdrawalRequestState>(
      listener: (BuildContext context, SendWithdrawalRequestState state) {
        if (state is SendWithdrawalRequestSuccess) {
          //update amount globally
          context.read<FetchSystemSettingsCubit>().updateAmount(state.balance);

          UiUtils.showMessage(
            context,
            state.message.isNotEmpty ? state.message : 'success',
            ToastificationType.success,
            onMessageClosed: () {},
          );

          // little bit delay because bottom sheet is closing very fast
          Future.delayed(
            const Duration(milliseconds: 500),
          ).then((value) => Navigator.pop(context, true));
        }

        if (state is SendWithdrawalRequestFailure) {
          UiUtils.showMessage(context, 'failed', ToastificationType.error);
        }
      },
      builder: (BuildContext context, SendWithdrawalRequestState state) {
        Widget? child;

        if (state is SendWithdrawalRequestInProgress) {
          child = CustomCircularProgressIndicator(color: AppColors.whiteColors);
        }

        return CustomInkWellContainer(
          onTap: () {
            UiUtils.removeFocus();
            onSubmitClick();
          },
          child: CustomContainer(
            height: 44,
            boxShadow: const [
              BoxShadow(
                color: Color(0x1c343f53),
                offset: Offset(0, -3),
                blurRadius: 10,
              ),
            ],
            color: Theme.of(context).colorScheme.accentColor,
            child: Center(
              child:
                  child ??
                  Text(
                    'submitBtnLbl'.translate(context: context),
                    style: TextStyle(
                      color: AppColors.whiteColors,
                      fontWeight: FontWeight.w700,
                      fontStyle: FontStyle.normal,
                      fontSize: 14.0,
                    ),
                  ),
            ),
          ),
        );
      },
    );
  }

  Widget resetBtn() {
    return CustomInkWellContainer(
      onTap: () {
        amountController.text = '';
        FocusScope.of(context).requestFocus(amountFocus);
        setState(() {});
      },
      child: CustomContainer(
        height: 44,
        boxShadow: const [
          BoxShadow(
            color: Color(0x1c343f53),
            offset: Offset(0, -3),
            blurRadius: 10,
          ),
        ],
        color: Theme.of(context).colorScheme.secondaryColor,
        child: Center(
          child: Text(
            'resetBtnLbl'.translate(context: context),
            style: TextStyle(
              color: Theme.of(context).colorScheme.blackColor,
              fontWeight: FontWeight.w700,
              fontStyle: FontStyle.normal,
              fontSize: 14.0,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBankDetailItem(
    BuildContext context,
    String title,
    String value,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomText(
          title,
          fontSize: 12,
          maxLines: 1,
          color: Theme.of(context).colorScheme.lightGreyColor,
        ),
        const SizedBox(height: 4),
        CustomText(
          value,
          fontSize: 14,
          fontWeight: FontWeight.w600,
          maxLines: 2,
          color: Theme.of(context).colorScheme.blackColor,
        ),
      ],
    );
  }

  Future<void> onSubmitClick() async {
    final bankInfo = context
        .read<ProviderDetailsCubit>()
        .providerDetails
        .bankInformation;

    // Validate required bank details
    final bool hasBankName = bankInfo?.bankName?.isNotEmpty == true;
    final bool hasAccountName = bankInfo?.accountName?.isNotEmpty == true;
    final bool hasAccountNumber = bankInfo?.accountNumber?.isNotEmpty == true;
    final bool hasBankCode = bankInfo?.bankCode?.isNotEmpty == true;

    if (!hasBankName || !hasAccountName || !hasAccountNumber || !hasBankCode) {
      UiUtils.showMessage(
        context,
        'addBankDetailsToWithdraw'.translate(context: context),
        ToastificationType.error,
      );
      return;
    }

    final FormState? form = formKey.currentState;
    if (form == null) return;
    form.save();
    if (form.validate()) {
      context.read<SendWithdrawalRequestCubit>().sendWithdrawalRequest(
        amount: amountController.text,
      );
    }
  }
}
