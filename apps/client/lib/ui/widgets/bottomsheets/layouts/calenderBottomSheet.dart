import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart' as intl;
import 'package:intl/intl.dart';

class CalenderBottomSheet extends StatefulWidget {
  const CalenderBottomSheet({
    required this.providerId,
    required this.advanceBookingDays,
    final Key? key,
    this.selectedDate,
    this.selectedTime,
    this.orderId,
    this.customJobRequestId,
  }) : super(key: key);

  final String providerId;
  final String advanceBookingDays;
  final DateTime? selectedDate;
  final String? selectedTime;
  final String? orderId;
  final String? customJobRequestId;

  @override
  State<CalenderBottomSheet> createState() => _CalenderBottomSheetState();
}

class _CalenderBottomSheetState extends State<CalenderBottomSheet>
    with TickerProviderStateMixin {
  late DateTime selectedDate;
  String? selectedTime;
  String? message;
  int? selectedTimeSlotIndex;
  List<String> listOfDay = [
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
    "saturday",
    "sunday"
  ];

  void fetchTimeSlots() {
    context.read<TimeSlotCubit>().getTimeslotDetails(
        providerID: int.parse(widget.providerId),
        selectedDate: selectedDate,
        customJobRequestId: widget.customJobRequestId ?? '');
  }

  @override
  void initState() {
    super.initState();
    selectedDate = widget.selectedDate ?? DateTime.now();
    ClarityService.logAction(
      ClarityActions.timeslotPickerOpened,
      {
        'provider_id': widget.providerId,
        'selected_date': selectedDate,
        'custom_job_request_id': widget.customJobRequestId ?? '',
      },
    );

    Future.delayed(Duration.zero).then((final value) {
      fetchTimeSlots();
    });
  }

  Widget verticalSpacing() {
    return const CustomSizedBox(
      height: 10,
    );
  }

  @override
  Widget build(final BuildContext context) => PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (didPop) {
            return;
          } else {
            Navigator.of(context).pop(
              {
                "selectedDate":
                    intl.DateFormat('yyyy-MM-dd').format(selectedDate),
                "selectedTime": selectedTime,
                "message": message,
                "isSaved": false
              },
            );
          }
        },
        child: SafeArea(
          child: StatefulBuilder(
            builder: (final BuildContext context, final setStater) =>
                BottomSheetLayout(
              title: "selectDateAndTime".translate(context: context),
              child: CustomSizedBox(
                height: context.screenHeight * 0.8,
                child: Align(
                  child: CustomContainer(
                    color: context.colorScheme.secondaryColor,
                    borderRadius: UiUtils.borderRadiusOf10,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 15),
                          child: CustomText(
                            "selectDateOfTheService"
                                .translate(context: context),
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: context.colorScheme.blackColor,
                          ),
                        ),
                        verticalSpacing(),
                        SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          child: Row(
                            children: List.generate(
                                int.parse(widget.advanceBookingDays), (index) {
                              final DateTime date =
                                  DateTime.now().add(Duration(days: index));
                              final String day = listOfDay[date.weekday - 1]
                                  .translate(context: context);
                              return CustomInkWellContainer(
                                onTap: () {
                                  if (DateFormat("yyyy-MM-dd")
                                          .format(selectedDate) ==
                                      DateFormat("yyyy-MM-dd").format(
                                          DateTime.parse(date.toString()))) {
                                    return;
                                  }
                                  selectedTime = null;
                                  selectedTimeSlotIndex = null;
                                  selectedDate =
                                      DateTime.parse(date.toString());
                                  setStater(() {});
                                  fetchTimeSlots();
                                },
                                child: CustomContainer(
                                  padding: const EdgeInsets.all(15),
                                  margin:
                                      const EdgeInsets.symmetric(horizontal: 5),
                                  border: intl.DateFormat("yyyy-MM-dd")
                                              .format(selectedDate) ==
                                          intl.DateFormat("yyyy-MM-dd")
                                              .format(date)
                                      ? Border.all(
                                          color:
                                              context.colorScheme.accentColor)
                                      : null,
                                  color: context.colorScheme.secondaryColor,
                                  borderRadius: UiUtils.borderRadiusOf10,
                                  child: Column(
                                    children: [
                                      CustomText(
                                        "${date.day} / ${date.month}",
                                        color: context.colorScheme.blackColor,
                                        fontWeight: FontWeight.w700,
                                      ),
                                      CustomText(
                                        day.length > 3
                                            ? day.substring(0, 3)
                                            : day,
                                        color:
                                            context.colorScheme.lightGreyColor,
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            }),
                          ),
                        ),
                        verticalSpacing(),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 15),
                          child: CustomText(
                            "selectTimeOfTheService"
                                .translate(context: context),
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: context.colorScheme.blackColor,
                          ),
                        ),
                        verticalSpacing(),
                        Expanded(
                          child: BlocConsumer<TimeSlotCubit, TimeSlotState>(
                            listener: (final context, final state) {
                              if (state is TimeSlotFetchSuccess) {
                                if (state.isError) {
                                  UiUtils.showMessage(
                                    context,
                                    state.message,
                                    ToastificationType.warning,
                                  );
                                }
                              }
                            },
                            builder: (final context, final state) {
                              //timeslot background color
                              final Color disabledTimeSlotColor = context
                                  .colorScheme.lightGreyColor
                                  .withValues(alpha: 0.35);
                              final Color selectedTimeSlotColor =
                                  context.colorScheme.accentColor;
                              final Color defaultTimeSlotColor =
                                  context.colorScheme.secondaryColor;

                              //timeslot border color
                              final Color disabledTimeSlotBorderColor = context
                                  .colorScheme.lightGreyColor
                                  .withValues(alpha: 0.35);
                              final Color selectedTimeSlotBorderColor =
                                  context.colorScheme.accentColor;
                              final Color defaultTimeSlotBorderColor =
                                  context.colorScheme.accentColor;

                              //timeslot text color
                              final Color disabledTimeSlotTextColor =
                                  context.colorScheme.blackColor;
                              const Color selectedTimeSlotTextColor =
                                  AppColors.whiteColors;
                              final Color defaultTimeSlotTextColor =
                                  context.colorScheme.blackColor;

                              if (state is TimeSlotFetchSuccess) {
                                return state.isError
                                    ? Center(
                                        child: CustomText(state.message),
                                      )
                                    : GridView.count(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 15,
                                          vertical: 10,
                                        ),
                                        crossAxisCount: 3,
                                        crossAxisSpacing: 10,
                                        mainAxisSpacing: 20,
                                        childAspectRatio: 2.7,
                                        children: List<Widget>.generate(
                                              state.slotsData.length,
                                              (index) {
                                                return CustomInkWellContainer(
                                                  onTap: () {
                                                    if (state.slotsData[index]
                                                            .isAvailable ==
                                                        0) {
                                                      return;
                                                    }

                                                    ClarityService.logAction(
                                                      ClarityActions
                                                          .timeslotSlotSelected,
                                                      {
                                                        'provider_id':
                                                            widget.providerId,
                                                        'selected_date':
                                                            selectedDate,
                                                        'slot_time': state
                                                                .slotsData[
                                                                    index]
                                                                .time ??
                                                            '',
                                                        'custom_job_request_id':
                                                            widget.customJobRequestId ??
                                                                '',
                                                      },
                                                    );
                                                    selectedTime = state
                                                        .slotsData[index].time;
                                                    message = state
                                                        .slotsData[index]
                                                        .message;
                                                    selectedTimeSlotIndex =
                                                        index;
                                                    setState(() {});
                                                  },
                                                  child: slotItemContainer(
                                                    backgroundColor: state
                                                                .slotsData[
                                                                    index]
                                                                .isAvailable ==
                                                            0
                                                        ? disabledTimeSlotColor
                                                        : selectedTimeSlotIndex ==
                                                                index
                                                            ? selectedTimeSlotColor
                                                            : defaultTimeSlotColor,
                                                    borderColor: state
                                                                .slotsData[
                                                                    index]
                                                                .isAvailable ==
                                                            0
                                                        ? disabledTimeSlotBorderColor
                                                        : selectedTimeSlotIndex ==
                                                                index
                                                            ? selectedTimeSlotBorderColor
                                                            : defaultTimeSlotBorderColor,
                                                    titleColor: state
                                                                .slotsData[
                                                                    index]
                                                                .isAvailable ==
                                                            0
                                                        ? disabledTimeSlotTextColor
                                                        : selectedTimeSlotIndex ==
                                                                index
                                                            ? selectedTimeSlotTextColor
                                                            : defaultTimeSlotTextColor,
                                                    title:
                                                        (state.slotsData[index]
                                                                    .time ??
                                                                '')
                                                            .formatTime(),
                                                  ),
                                                );
                                              },
                                            ) +
                                            <Widget>[
                                              CustomInkWellContainer(
                                                onTap: () {
                                                  displayTimePicker(context);
                                                },
                                                child: slotItemContainer(
                                                    backgroundColor:
                                                        Colors.transparent,
                                                    titleColor: context
                                                        .colorScheme
                                                        .accentColor,
                                                    borderColor: context
                                                        .colorScheme
                                                        .accentColor,
                                                    title: selectedTime ??
                                                        "addSlot".translate(
                                                            context: context)),
                                              )
                                            ],
                                      );
                              }
                              if (state is TimeSlotFetchFailure) {
                                return ErrorContainer(
                                  onTapRetry: () {
                                    fetchTimeSlots();
                                  },
                                  errorMessage: state.errorMessage
                                      .translate(context: context),
                                );
                              }
                              return Center(
                                child: CustomText(
                                    "loading".translate(context: context)),
                              );
                            },
                          ),
                        ),
                        _getCloseAndContinueNavigateButton()
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      );

  Widget slotItemContainer({
    required Color backgroundColor,
    required Color borderColor,
    required Color titleColor,
    required String title,
  }) {
    return CustomContainer(
      width: 150,
      height: 20,
      color: backgroundColor,
      borderRadius: UiUtils.borderRadiusOf10,
      border: Border.all(
        width: 0.5,
        color: borderColor,
      ),
      child: Center(
        child: CustomText(
          title,
          color: titleColor,
        ),
      ),
    );
  }

  Future displayTimePicker(final BuildContext context) async {
    // Get current locale using common method from StringExtension
    final currentLocale = StringExtension.getCurrentLocale(context);

    final TimeOfDay? time = await showTimePicker(
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

    if (time != null) {
      setState(() {
        selectedTime = "${time.hour}:${time.minute}:00".convertTime();
        selectedTimeSlotIndex = null;
        ClarityService.logAction(
          ClarityActions.timeslotCustomTimeEntered,
          {
            'provider_id': widget.providerId,
            'selected_date': selectedDate,
            'custom_time': selectedTime ?? '',
            'custom_job_request_id': widget.customJobRequestId ?? '',
          },
        );
      });
    }
  }

  Widget _getCloseAndContinueNavigateButton() =>
      BlocConsumer<ValidateCustomTimeCubit, ValidateCustomTimeState>(
        listener: (
          final BuildContext context,
          final ValidateCustomTimeState validateCustomTimeState,
        ) {
          if (validateCustomTimeState is ValidateCustomTimeSuccess) {
            if (!validateCustomTimeState.error) {
              Navigator.of(context).pop(
                {
                  "selectedDate":
                      intl.DateFormat('yyyy-MM-dd').format(selectedDate),
                  "selectedTime": selectedTime,
                  "message": message,
                  "isSaved": true
                },
              );
            } else {
              UiUtils.showMessage(context, validateCustomTimeState.message,
                  ToastificationType.error);
            }
          } else if (validateCustomTimeState is ValidateCustomTimeFailure) {
            UiUtils.showMessage(
              context,
              validateCustomTimeState.errorMessage,
              ToastificationType.error,
            );
          }
        },
        builder: (context, validateCustomTimeState) {
          Widget? child;
          if (validateCustomTimeState is ValidateCustomTimeInProgress) {
            child = const CustomCircularProgressIndicator(
                color: AppColors.whiteColors);
          } else if (validateCustomTimeState is ValidateCustomTimeFailure ||
              validateCustomTimeState is ValidateCustomTimeSuccess) {
            child = null;
          }
          return CloseAndConfirmButton(
            closeButtonPressed: () {
              Navigator.pop(context);
            },
            confirmButtonPressed: () {
              if (selectedTime == null) {
                ClarityService.logAction(
                  ClarityActions.timeslotValidationFailed,
                  {
                    'provider_id': widget.providerId,
                    'selected_date': selectedDate,
                    'reason': 'time_not_selected',
                    'custom_job_request_id': widget.customJobRequestId ?? '',
                  },
                );
                UiUtils.showMessage(
                  context,
                  'pleaseSelectTime'.translate(context: context),
                  ToastificationType.warning,
                );
                return;
              }
              context.read<ValidateCustomTimeCubit>().validateCustomTime(
                    providerId: widget.providerId,
                    selectedDate: intl.DateFormat('yyyy-MM-dd')
                        .format(DateTime.parse("$selectedDate"))
                        .toString(),
                    selectedTime: selectedTime.toString(),
                    orderId: widget.orderId,
                    customJobRequestId: widget.customJobRequestId,
                  );
            },
            confirmButtonChild: child,
            showProgressIndicator: false,
            confirmButtonName: "continue",
          );
        },
      );
}
