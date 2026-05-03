import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/bottomsheets/layouts/chooseCategoryFromServiceRequest.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class RequestServiceFormScreen extends StatefulWidget {
  const RequestServiceFormScreen({final Key? key}) : super(key: key);

  static Route route(final RouteSettings settings) {
    // final arguments = settings.arguments as Map;
    return CupertinoPageRoute(
      builder: (final context) => const RequestServiceFormScreen(),
    );
  }

  @override
  State<RequestServiceFormScreen> createState() =>
      _RequestServiceFormScreenState();
}

class _RequestServiceFormScreenState extends State<RequestServiceFormScreen> {
  final _formKey = GlobalKey<FormState>();
  TextEditingController serviceTitleController = TextEditingController();
  TextEditingController serviceDescriptionController = TextEditingController();
  TextEditingController serviceTypeController = TextEditingController();
  TextEditingController categoryController = TextEditingController();
  TextEditingController minBudgetController = TextEditingController();
  TextEditingController maxBudgetController = TextEditingController();
  TextEditingController startFromController = TextEditingController();
  TextEditingController endAtController = TextEditingController();
  ScrollController bottomSheetCategoryController = ScrollController();
  String selectedStartDate = '';
  String selectedStartTime = '';
  String selectedEndDate = '';
  String selectedEndTime = '';
  String rawStartTime = '';
  String rawEndTime = '';
  late final Padding prefixPadding = Padding(
    padding: const EdgeInsetsDirectional.only(top: 12, start: 16, end: 0),
    child: CustomText(
      context.read<SystemSettingCubit>().systemCurrency,
      fontSize: 16,
    ),
  );
  String? categoryId;
  String? categoryName;

  @override
  void initState() {
    context.read<AllCategoryCubit>().getAllCategory();
    super.initState();
  }

  @override
  Widget build(final BuildContext context) =>
      AnnotatedRegion<SystemUiOverlayStyle>(
        value: UiUtils.getSystemUiOverlayStyle(context: context),
        child: Scaffold(
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: 'requestService'.translate(context: context),
            isLeadingIconEnable: true,
            elevation: 0.5,
          ),
          body: Stack(
            children: [
              SingleChildScrollView(
                padding: EdgeInsetsDirectional.only(
                    bottom: (kBottomNavigationBarHeight +
                            MediaQuery.of(context).viewPadding.bottom)
                        .rh(context)),
                child: Align(
                  alignment: AlignmentDirectional.topCenter,
                  child: Form(
                    key: _formKey,
                    child: CustomContainer(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CustomTextFormField(
                            controller: serviceTitleController,
                            validator: (value) => isTextFieldEmpty(
                                value: value, context: context),
                            labelText:
                                'serviceTitle'.translate(context: context),
                            hintText:
                                'serviceTitle'.translate(context: context),
                          ),
                          const CustomSizedBox(
                            height: 8,
                          ),
                          CustomTextFormField(
                            validator: (value) => isTextFieldEmpty(
                                value: value, context: context),
                            expands: true,
                            heightVal: 70,
                            textInputType: TextInputType.multiline,
                            controller: serviceDescriptionController,
                            labelText:
                                'shortDescription'.translate(context: context),
                            hintText:
                                'shortDescription'.translate(context: context),
                          ),
                          const CustomSizedBox(
                            height: 8,
                          ),
                          CustomTextFormField(
                            validator: (value) => value!.isEmpty
                                ? 'selectServiceType'
                                    .translate(context: context)
                                : null,
                            callback: () async {
                              final result = await UiUtils.showBottomSheet(
                                context: context,
                                useSafeArea: true,
                                child: BottomSheetCategoryScreen(
                                  categoryId: categoryId ?? '',
                                  scrollController:
                                      bottomSheetCategoryController,
                                ),
                              );
                              if (result != null &&
                                  result is Map<String, dynamic>) {
                                setState(() {
                                  categoryId = result['id'];
                                  categoryName = result['name'];
                                  categoryController.text = categoryName ?? '';
                                });
                              }
                            },
                            isReadOnly: true,
                            controller: categoryController,
                            labelText:
                                'serviceType'.translate(context: context),
                            hintText: 'serviceType'.translate(context: context),
                            suffixIcon: Row(
                              mainAxisSize: MainAxisSize.min,
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                CustomText(
                                  'change'.translate(context: context),
                                  color: context.colorScheme.accentColor,
                                ),
                                Icon(
                                  Icons.arrow_forward_ios,
                                  color: context.colorScheme.accentColor,
                                ),
                                const CustomSizedBox(
                                  width: 10,
                                )
                              ],
                            ),
                          ),
                          const CustomSizedBox(
                            height: 8,
                          ),
                          CustomText(
                            'budget'.translate(context: context),
                            fontSize: 16,
                            color: context.colorScheme.blackColor,
                            fontWeight: FontWeight.w600,
                          ),
                          const CustomSizedBox(
                            height: 8,
                          ),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              CustomText(
                                'min'.translate(context: context),
                                textAlign: TextAlign.center,
                              ),
                              const CustomSizedBox(
                                width: 16,
                              ),
                              Expanded(
                                child: CustomTextFormField(
                                  prefix: prefixPadding,
                                  controller: minBudgetController,
                                  validator: (value) => value!.isEmpty
                                      ? 'enterMinPrice'
                                          .translate(context: context)
                                      : null,
                                  textInputType: TextInputType.number,
                                  allowOnlySingleDecimalPoint: true,
                                  bottomPadding: 0,
                                ),
                              ),
                              const CustomSizedBox(
                                width: 16,
                              ),
                              CustomText(
                                'max'.translate(context: context),
                              ),
                              const CustomSizedBox(
                                width: 16,
                              ),
                              Expanded(
                                child: CustomTextFormField(
                                  prefix: prefixPadding,
                                  controller: maxBudgetController,
                                  textInputType: TextInputType.number,
                                  validator: (value) => value!.isEmpty
                                      ? 'enterMaxPrice'
                                          .translate(context: context)
                                      : null,
                                  allowOnlySingleDecimalPoint: true,
                                  bottomPadding: 0,
                                ),
                              ),
                            ],
                          ),
                          const CustomSizedBox(
                            height: 8,
                          ),
                          CustomText(
                            'requestDuration'.translate(context: context),
                            fontSize: 16,
                            color: context.colorScheme.blackColor,
                            fontWeight: FontWeight.w600,
                          ),
                          const CustomSizedBox(
                            height: 18,
                          ),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              CustomContainer(
                                width: context.screenWidth * 0.3,
                                child: CustomText(
                                  'startFrom'.translate(context: context),
                                ),
                              ),
                              Expanded(
                                child: CustomTextFormField(
                                  validator: (value) => value!.isEmpty
                                      ? 'selectStartDateTime'
                                          .translate(context: context)
                                      : null,
                                  hintText: 'selectStartDateTime'
                                      .translate(context: context),
                                  controller: startFromController,
                                  callback: () {
                                    _selectStartDateTime(context);
                                  },
                                  textInputType: TextInputType.datetime,
                                  isReadOnly: true,
                                  bottomPadding: 0,
                                ),
                              ),
                            ],
                          ),
                          const CustomSizedBox(
                            height: 18,
                          ),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              CustomContainer(
                                width: context.screenWidth * 0.3,
                                child: CustomText(
                                  'endAt'.translate(context: context),
                                ),
                              ),
                              Expanded(
                                child: CustomTextFormField(
                                  bottomPadding: 0,
                                  validator: (value) => value!.isEmpty
                                      ? 'selectEndDateTime'
                                          .translate(context: context)
                                      : null,
                                  hintText: 'selectEndDateTime'
                                      .translate(context: context),
                                  controller: endAtController,
                                  isReadOnly: true,
                                  callback: () {
                                    _selectEndDateTime(context);
                                  },
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              Align(
                alignment: Alignment.bottomCenter,
                child: CustomContainer(
                  height: kBottomNavigationBarHeight +
                      MediaQuery.of(context).viewPadding.bottom,
                  padding: EdgeInsets.only(
                      left: 10.rw(context),
                      right: 10.rw(context),
                      top: 5.rh(context),
                      bottom: MediaQuery.of(context).viewPadding.bottom +
                          5.rh(context)),
                  child: BlocConsumer<MyRequestCubit, MyRequestState>(
                    listener: (context, state) {
                      if (state is MyRequestSSuccess) {
                        context.read<MyRequestListCubit>().fetchRequests();

                        UiUtils.showMessage(
                            context,
                            'requestSuccess'.translate(context: context),
                            ToastificationType.success);
                        Navigator.pop(context);
                      }
                    },
                    builder: (context, state) {
                      return CustomRoundedButton(
                        onTap: () {
                          if (state is MyRequestLoading) return;
                          _submitForm(context);
                        },
                        buttonTitle:
                            'submitRequest'.translate(context: context),
                        showBorder: false,
                        widthPercentage: 0.95,
                        backgroundColor: context.colorScheme.accentColor,
                        textSize: 16,
                        fontWeight: FontWeight.w600,
                        child: context.read<MyRequestCubit>().state
                                    is MyRequestLoading ||
                                context.read<MyRequestListCubit>().state
                                    is MyRequestListInProgress
                            ? const Center(
                                child: CustomCircularProgressIndicator(
                                    color: AppColors.whiteColors),
                              )
                            : null,
                      );
                    },
                  ),
                ),
              )
            ],
          ),
        ),
      );

  Future<void> _selectStartDateTime(BuildContext context) async {
    final DateTime? pickedDate = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (BuildContext context, Widget? widget) => Theme(
        data: ThemeData(
          colorScheme: context.colorScheme.brightness == Brightness.light
              ? ColorScheme.light(
                  primary: context.colorScheme.accentColor,
                )
              : ColorScheme.dark(
                  primary: context.colorScheme.accentColor,
                ),
          datePickerTheme: DatePickerThemeData(
            backgroundColor: context.colorScheme.primaryColor,
            dividerColor: Colors.transparent,
            headerBackgroundColor: context.colorScheme.primaryColor,
            headerForegroundColor: context.colorScheme.accentColor,
          ),
        ),
        child: CustomSizedBox(child: widget),
      ),
    );

    if (pickedDate != null) {
      // Get current locale using common method from StringExtension
      final currentLocale = StringExtension.getCurrentLocale(context);
      final TimeOfDay? pickedTime = await showTimePicker(
        context: context,
        initialTime: TimeOfDay.now(),
        builder: (BuildContext context, Widget? child) {
          // Override locale to use app's selected language
          return Localizations.override(
            context: context,
            locale: currentLocale,
            child: child,
          );
        },
      );

      if (pickedTime != null) {
        final DateTime pickedDateTime = DateTime(
          pickedDate.year,
          pickedDate.month,
          pickedDate.day,
          pickedTime.hour,
          pickedTime.minute,
        );
        setState(() {
          selectedStartDate =
              '${pickedDate.year}-${pickedDate.month}-${pickedDate.day}';
          selectedStartTime = '${pickedDateTime.hour}:${pickedDateTime.minute}';
          startFromController.text =
              pickedDateTime.toString().formatDateAndTime();
          rawStartTime = pickedDateTime.toString();
        });
      }
    }
  }

  Future<void> _selectEndDateTime(BuildContext context) async {
    final DateTime? pickedDate = await showDatePicker(
      context: context,
      initialDate: DateTime.parse(rawStartTime),
      firstDate: DateTime.parse(rawStartTime),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (BuildContext context, Widget? widget) => Theme(
        data: ThemeData(
          colorScheme: context.colorScheme.brightness == Brightness.light
              ? ColorScheme.light(
                  primary: context.colorScheme.accentColor,
                )
              : ColorScheme.dark(
                  primary: context.colorScheme.accentColor,
                ),
          datePickerTheme: DatePickerThemeData(
            backgroundColor: context.colorScheme.primaryColor,
            dividerColor: Colors.transparent,
            headerBackgroundColor: context.colorScheme.primaryColor,
            headerForegroundColor: context.colorScheme.accentColor,
          ),
        ),
        child: CustomSizedBox(child: widget),
      ),
    );

    if (pickedDate != null) {
      // Get current locale using common method from StringExtension
      final currentLocale = StringExtension.getCurrentLocale(context);
      final TimeOfDay? pickedTime = await showTimePicker(
        context: context,
        initialTime: TimeOfDay(
            hour: DateTime.parse(rawStartTime).hour,
            minute: DateTime.parse(rawStartTime).minute),
        builder: (BuildContext context, Widget? child) {
          // Override locale to use app's selected language
          return Localizations.override(
            context: context,
            locale: currentLocale,
            child: child,
          );
        },
      );

      if (pickedTime != null) {
        final DateTime pickedDateTime = DateTime(
          pickedDate.year,
          pickedDate.month,
          pickedDate.day,
          pickedTime.hour,
          pickedTime.minute,
        );

        setState(() {
          selectedEndDate =
              '${pickedDate.year}-${pickedDate.month}-${pickedDate.day}';
          selectedEndTime = '${pickedDateTime.hour}:${pickedDateTime.minute}';
          endAtController.text = pickedDateTime.toString().formatDateAndTime();
          rawEndTime = pickedDateTime.toString();
        });
      }
    }
  }

  Future<void> _submitForm(BuildContext context) async {
    try {
      UiUtils.removeFocus();

      if (_formKey.currentState!.validate()) {
        final bool checkMinMax = double.parse(minBudgetController.text) <
                double.parse(maxBudgetController.text) &&
            (maxBudgetController.text.isNotEmpty &&
                maxBudgetController.text.isNotEmpty);

        final bool checkTime =
            DateTime.parse(rawStartTime).isBefore(DateTime.parse(rawEndTime)) ||
                DateTime.parse(rawStartTime)
                    .isAtSameMomentAs(DateTime.parse(rawEndTime));
        if (!checkTime) {
          UiUtils.showMessage(
              context,
              'invalidTime'.translate(context: context),
              ToastificationType.warning);
          return;
        }
        if (!checkMinMax) {
          UiUtils.showMessage(
              context,
              'minMaxBudgetWarning'.translate(context: context),
              ToastificationType.warning);
          return;
        }
        final Map<String, dynamic> parameters = <String, dynamic>{
          'category_id': categoryId,
          'service_title': serviceTitleController.text,
          'service_short_description': serviceDescriptionController.text,
          'min_price': minBudgetController.text,
          'max_price': maxBudgetController.text,
          'requested_start_date': selectedStartDate,
          'requested_end_date': selectedEndDate,
          'requested_start_time': selectedStartTime,
          'requested_end_time': selectedEndTime,
          'latitude': HiveRepository.getLatitude,
          'longitude': HiveRepository.getLongitude,
        };
        await context.read<MyRequestCubit>().submitCustomJobRequest(
              parameters: parameters,
            );
      }
    } catch (e) {
      UiUtils.showMessage(context, e.toString(), ToastificationType.error);
    }
  }
}
