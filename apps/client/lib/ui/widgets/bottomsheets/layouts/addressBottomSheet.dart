import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class AddressSheet extends StatefulWidget {
  const AddressSheet({
    required this.addressDataModel,
    final Key? key,
    this.addressId,
    this.isUpdateAddress,
  }) : super(key: key);
  final bool? isUpdateAddress;
  final String? addressId;
  final AddressModel addressDataModel;

  @override
  State<AddressSheet> createState() => _AddressSheetState();
}

class _AddressSheetState extends State<AddressSheet> {
  int locationTypeAddressIndex = 0;
  late AddressModel addressDataModel;
  Map<int, String> locationType = {0: "home", 1: "Office", 2: "Other"};
  bool isSetDefaultAddress = false;
  final GlobalKey<FormState> _addressFormKey = GlobalKey<FormState>();

  late final TextEditingController completeAddressFieldController =
      TextEditingController(text: addressDataModel.address);
  late final TextEditingController mobileNumberFieldController =
      TextEditingController(text: addressDataModel.mobile);
  late final TextEditingController floorFieldController =
      TextEditingController(text: addressDataModel.area);
  late final TextEditingController cityFieldController =
      TextEditingController(text: addressDataModel.cityName);

  @override
  void initState() {
    super.initState();
    addressDataModel = widget.addressDataModel;
    locationTypeAddressIndex = widget.addressDataModel.type == "Office"
        ? 1
        : widget.addressDataModel.type == "Other"
            ? 2
            : 0;
  }

  @override
  Widget build(final BuildContext context) => CustomContainer(
      color: context.colorScheme.primaryColor,
      margin: EdgeInsetsDirectional.only(
          bottom: MediaQuery.viewPaddingOf(context).bottom),
      borderRadiusStyle: const BorderRadius.only(
        topLeft: Radius.circular(UiUtils.borderRadiusOf20),
        topRight: Radius.circular(UiUtils.borderRadiusOf20),
      ),
      //  height: context.screenHeight * 0.7,
      child: BottomSheetLayout(
        title: 'enterCompleteAddress',
        child: Column(
          children: [
            SingleChildScrollView(
              padding: const EdgeInsetsDirectional.only(
                top: 10,
                start: 15,
                end: 15,
              ),
              child: Form(
                key: _addressFormKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CustomTextFormField(
                      bottomPadding: 10,
                      validator: (p0) =>
                          isTextFieldEmpty(context: context, value: p0),
                      controller: completeAddressFieldController,
                      labelText: 'addressLine1'
                          .translateAndMakeItCompulsory(context: context),
                      hintText: 'enterAddressLine1'.translate(context: context),
                    ),
                    CustomTextFormField(
                      bottomPadding: 10,
                      validator: (p0) =>
                          isTextFieldEmpty(value: p0, context: context),
                      controller: floorFieldController,
                      labelText:
                          'area'.translateAndMakeItCompulsory(context: context),
                      hintText: 'enterArea'.translate(context: context),
                    ),
                    CustomTextFormField(
                      bottomPadding: 10,
                      validator: (p0) =>
                          isTextFieldEmpty(value: p0, context: context),
                      controller: cityFieldController,
                      labelText:
                          'city'.translateAndMakeItCompulsory(context: context),
                      hintText: 'enterCity'.translate(context: context),
                    ),
                    CustomTextFormField(
                      bottomPadding: 10,
                      validator: (number) => isValidMobileNumber(
                          number: number ?? '', context: context),
                      inputFormatters: UiUtils.allowOnlyDigits(),
                      controller: mobileNumberFieldController,
                      textInputType: TextInputType.number,
                      labelText: 'mobileNumber'
                          .translateAndMakeItCompulsory(context: context),
                      hintText:
                          'enterYourPhoneNumber'.translate(context: context),
                    ),
                    StatefulBuilder(
                      builder: (final context, final setState) {
                        addressDataModel = widget.addressDataModel.copyWith(
                            type: locationType[locationTypeAddressIndex]);
                        return Row(
                          children: <Widget>[
                            Expanded(
                              child: FlatOutlinedButton(
                                isSelected: locationTypeAddressIndex == 0,
                                name: 'home'.translate(context: context),
                                onTap: () {
                                  addressDataModel =
                                      addressDataModel.copyWith(type: 'Home');
                                  locationTypeAddressIndex = 0;

                                  setState(() {});
                                },
                              ),
                            ),
                            const CustomSizedBox(
                              width: 10,
                            ),
                            Expanded(
                              child: FlatOutlinedButton(
                                isSelected: locationTypeAddressIndex == 1,
                                name: 'office'.translate(context: context),
                                onTap: () {
                                  addressDataModel =
                                      addressDataModel.copyWith(type: 'Office');
                                  locationTypeAddressIndex = 1;
                                  setState(() {});
                                },
                              ),
                            ),
                            const CustomSizedBox(
                              width: 10,
                            ),
                            Expanded(
                              child: FlatOutlinedButton(
                                isSelected: locationTypeAddressIndex == 2,
                                name: 'other'.translate(context: context),
                                onTap: () {
                                  addressDataModel =
                                      addressDataModel.copyWith(type: 'Other');
                                  locationTypeAddressIndex = 2;
                                  setState(() {});
                                },
                              ),
                            ),
                          ],
                        );
                      },
                    ),
                    const CustomSizedBox(
                      height: 10,
                    ),
                    Row(
                      children: [
                        StatefulBuilder(
                          builder: (final context, final setState) =>
                              CustomSizedBox(
                            height: 24,
                            width: 24,
                            child: Checkbox(
                              activeColor: context.colorScheme.accentColor,
                              fillColor: WidgetStateProperty.all(
                                context.colorScheme.primaryColor,
                              ),
                              side: WidgetStateBorderSide.resolveWith(
                                (states) => BorderSide(
                                    width: 1.0,
                                    color: context.colorScheme.accentColor),
                              ),
                              shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(
                                      UiUtils.borderRadiusOf5)),
                              visualDensity: VisualDensity.standard,
                              checkColor: context.colorScheme.accentColor,
                              value: isSetDefaultAddress,
                              onChanged: (final value) {
                                isSetDefaultAddress = !isSetDefaultAddress;
                                addressDataModel = addressDataModel.copyWith(
                                  isDefault: isSetDefaultAddress ? "1" : "0",
                                );
                                setState(() {});
                              },
                            ),
                          ),
                        ),
                        const CustomSizedBox(
                          width: 5,
                        ),
                        CustomInkWellContainer(
                            onTap: () {
                              isSetDefaultAddress = !isSetDefaultAddress;
                              addressDataModel = addressDataModel.copyWith(
                                isDefault: isSetDefaultAddress ? "1" : "0",
                              );
                              setState(() {});
                            },
                            child: CustomText('setDefaultAddress'
                                .translate(context: context))),
                      ],
                    ),
                    const CustomSizedBox(
                      height: 10,
                    ),
                  ],
                ),
              ),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: EdgeInsetsDirectional.only(
                    bottom: MediaQuery.of(context).viewInsets.bottom),
                child: BlocConsumer<AddAddressCubit, AddAddressState>(
                  listener: (final context, final state) {
                    if (state is AddAddressSuccess) {
                      UiUtils.showMessage(
                        context,
                        widget.isUpdateAddress ?? false
                            ? 'addressUpdatedSuccessfully'
                                .translate(context: context)
                            : 'addressAddedSuccessfully'
                                .translate(context: context),
                        ToastificationType.success,
                      );
                    } else if (state is AddAddressFail) {
                      UiUtils.showMessage(
                        context,
                        state.error,
                        ToastificationType.error,
                      );
                    }
                  },
                  builder: (final context, AddAddressState state) {
                    Widget? child;
                    if (state is AddAddressInProgress) {
                      child = const CustomCircularProgressIndicator(
                        color: AppColors.whiteColors,
                      );
                    }
                    return CloseAndConfirmButton(
                      confirmButtonName:
                          'saveAddress'.translate(context: context),
                      confirmButtonPressed: () {
                        if (state is AddAddressInProgress) {
                          return;
                        }
                        UiUtils.removeFocus();

                        if (_addressFormKey.currentState!.validate()) {
                          final floor = floorFieldController.value.text.trim();

                          addressDataModel = addressDataModel.copyWith(
                            mobile:
                                mobileNumberFieldController.value.text.trim(),
                            address: completeAddressFieldController.value.text
                                .trim(),
                            area: floor,
                            cityName: cityFieldController.value.text.trim(),
                          );
                          if (widget.isUpdateAddress ?? false) {
                            final AddressModel model = addressDataModel
                                .copyWith(addressId: widget.addressId);

                            context.read<AddAddressCubit>().addAddress(model);
                          } else {
                            context
                                .read<AddAddressCubit>()
                                .addAddress(addressDataModel);
                          }
                        }
                      },
                      confirmButtonChild: child,
                      closeButtonPressed: () {
                        Navigator.pop(context);
                      },
                    );
                  },
                ),
              ),
            )
          ],
        ),
      ));
}
