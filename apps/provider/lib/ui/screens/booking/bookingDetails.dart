import 'package:edemand_partner/ui/widgets/bottomSheets/layouts/additionalChargesBottomSheet.dart';
import 'package:edemand_partner/ui/widgets/dialog/layouts/verifyOTPDialog.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/customerInfoWidget.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/statusAndInvoiceWidget.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/bookingDateAndTimeWidget.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/uploadedProofWidget.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/serviceDetailsWidget.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/notesWidget.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/priceSectionWidget.dart';
import 'package:edemand_partner/ui/screens/booking/widgets/bookingDetailsShimmer.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:open_file/open_file.dart';
import '../../../app/generalImports.dart';
import '../../../cubits/downloadInvoiceCubit.dart';

typedef PaymentGatewayDetails = ({String paymentType, String paymentImage});

class BookingDetails extends StatefulWidget {
  const BookingDetails({super.key, this.bookingsModel, this.bookingId})
    : assert(
        bookingsModel != null || bookingId != null,
        'Either bookingsModel or bookingId must be provided',
      );

  final BookingsModel? bookingsModel;
  final String? bookingId;

  @override
  BookingDetailsState createState() => BookingDetailsState();

  static Route<BookingDetails> route(RouteSettings routeSettings) {
    final Map arguments = routeSettings.arguments as Map;

    // Check if we have a bookingsModel or just bookingId
    final BookingsModel? bookingsModel = arguments['bookingsModel'];
    final String? bookingId = arguments['bookingId'];

    if (bookingsModel != null) {
      // Existing flow - model is passed directly
      return CupertinoPageRoute(
        builder: (_) => BlocProvider.value(
          value: arguments['cubit'] as UpdateBookingStatusCubit,
          child: BookingDetails(bookingsModel: bookingsModel),
        ),
      );
    } else {
      // New flow - only bookingId is passed, need to fetch details
      return CupertinoPageRoute(
        builder: (_) => MultiBlocProvider(
          providers: [
            BlocProvider(create: (_) => UpdateBookingStatusCubit()),
            BlocProvider(create: (_) => FetchBookingsDetailsCubit()),
          ],
          child: BookingDetails(bookingId: bookingId),
        ),
      );
    }
  }
}

class BookingDetailsState extends State<BookingDetails> {
  Map<String, String>? currentStatusOfBooking;
  Map<String, String>? temporarySelectedStatusOfBooking;
  int totalServiceQuantity = 0;
  BookingsModel? currentBookingModel;

  DateTime? selectedRescheduleDate;
  String? selectedRescheduleTime;
  List<Map<String, String>> filters = [];
  List<Map<String, dynamic>>? selectedProofFiles;
  List<Map<String, dynamic>>? additionalCharged;
  ValueNotifier<bool> isBillDetailsCollapsed = ValueNotifier(true);
  String? otpFromProvider;
  String? _loadingButtonValue;

  /// Whether we need to fetch booking details (when only bookingId is provided)
  bool get _needsToFetchDetails =>
      widget.bookingsModel == null && widget.bookingId != null;

  @override
  void initState() {
    super.initState();
    currentBookingModel = widget.bookingsModel;

    if (_needsToFetchDetails) {
      // Fetch booking details when only bookingId is provided
      context.read<FetchBookingsDetailsCubit>().fetchBookingDetails(
        bookingId: widget.bookingId!,
      );
    } else {
      _initializeWithModel();
    }

    Future.delayed(Duration.zero, () {
      filters = [
        {'value': '1', 'title': 'awaiting'.translate(context: context)},
        {'value': '2', 'title': 'confirmed'.translate(context: context)},
        {'value': '3', 'title': 'started'.translate(context: context)},
        {'value': '4', 'title': 'rescheduled'.translate(context: context)},
        {'value': '5', 'title': 'booking_ended'.translate(context: context)},
        {'value': '6', 'title': 'completed'.translate(context: context)},
        {'value': '7', 'title': 'cancelled'.translate(context: context)},
      ];
    });
  }

  void _initializeWithModel() {
    _getTotalQuantity();
    _getTranslatedInitialStatus();
  }

  BookingsModel? get bookingModel =>
      currentBookingModel ?? widget.bookingsModel;

  void _getTotalQuantity() {
    final model = bookingModel;
    if (model == null) return;
    model.services?.forEach((Services service) {
      totalServiceQuantity += int.parse(service.quantity!);
    });
    setState(() {});
  }

  void _getTranslatedInitialStatus() {
    Future.delayed(Duration.zero, () {
      final model = bookingModel;
      if (model == null) return;
      final String? initialStatusValue = _getStatusValueFromTitle(model.status);
      if (initialStatusValue != null) {
        currentStatusOfBooking = filters.where((Map<String, String> element) {
          return element['value'] == initialStatusValue;
        }).toList()[0];
      }

      setState(() {});
    });
  }

  // Don't translate this because we need to send this title in api;
  List<Map<String, String>> getStatusForApi = [
    {'value': '1', 'title': 'awaiting'},
    {'value': '2', 'title': 'confirmed'},
    {'value': '3', 'title': 'started'},
    {'value': '4', 'title': 'rescheduled'},
    {'value': '5', 'title': 'booking_ended'},
    {'value': '6', 'title': 'completed'},
    {'value': '7', 'title': 'cancelled'},
  ];

  // Helper method to get status value from status title
  String? _getStatusValueFromTitle(String? statusTitle) {
    if (statusTitle == null) return null;
    final status = getStatusForApi.firstWhere(
      (e) => e['title'] == statusTitle,
      orElse: () => {},
    );
    return status['value'];
  }

  @override
  Widget build(BuildContext context) {
    // If we need to fetch details, wrap with BlocConsumer
    if (_needsToFetchDetails) {
      return BlocConsumer<FetchBookingsDetailsCubit, FetchBookingsState>(
        listener: (context, state) {
          if (state is FetchBookingsSuccess && state.bookings.isNotEmpty) {
            currentBookingModel = state.bookings.first;
            _initializeWithModel();
            setState(() {});
          }
        },
        builder: (context, state) {
          if (state is FetchBookingsInProgress) {
            return _buildScaffold(
              context,
              body: const BookingDetailsShimmer(),
              showBottomBar: false,
            );
          } else if (state is FetchBookingsFailure) {
            return _buildScaffold(
              context,
              body: Center(
                child: ErrorContainer(
                  errorMessage: state.errorMessage,
                  onTapRetry: () {
                    context
                        .read<FetchBookingsDetailsCubit>()
                        .fetchBookingDetails(bookingId: widget.bookingId!);
                  },
                ),
              ),
              showBottomBar: false,
            );
          } else if (state is FetchBookingsSuccess &&
              state.bookings.isNotEmpty) {
            return _buildMainContent(context);
          }
          return _buildScaffold(
            context,
            body: const BookingDetailsShimmer(),
            showBottomBar: false,
          );
        },
      );
    }

    return _buildMainContent(context);
  }

  Widget _buildScaffold(
    BuildContext context, {
    required Widget body,
    bool showBottomBar = true,
  }) {
    return PopScope(
      canPop:
          context.watch<UpdateBookingStatusCubit>().state
              is! UpdateBookingStatusInProgress,
      child: Scaffold(
        backgroundColor: Theme.of(context).colorScheme.primaryColor,
        appBar: AppBar(
          surfaceTintColor: Theme.of(context).colorScheme.secondaryColor,
          backgroundColor: Theme.of(context).colorScheme.secondaryColor,
          elevation: 10,
          leading: CustomBackArrow(
            canGoBack:
                context.watch<UpdateBookingStatusCubit>().state
                    is! UpdateBookingStatusInProgress,
          ),
          title: CustomText(
            'bookingInformation'.translate(context: context),
            color: context.colorScheme.blackColor,
            fontWeight: FontWeight.w500,
            fontSize: 16,
          ),
        ),
        body: body,
        bottomNavigationBar: showBottomBar ? bottomBarWidget() : null,
      ),
    );
  }

  Widget _buildMainContent(BuildContext context) {
    final model = bookingModel;
    if (model == null) {
      return _buildScaffold(
        context,
        body: const BookingDetailsShimmer(),
        showBottomBar: false,
      );
    }

    return PopScope(
      canPop:
          context.watch<UpdateBookingStatusCubit>().state
              is! UpdateBookingStatusInProgress,
      child: Scaffold(
        backgroundColor: Theme.of(context).colorScheme.primaryColor,
        appBar: AppBar(
          surfaceTintColor: Theme.of(context).colorScheme.secondaryColor,
          backgroundColor: Theme.of(context).colorScheme.secondaryColor,
          elevation: 10,
          leading: CustomBackArrow(
            canGoBack:
                context.watch<UpdateBookingStatusCubit>().state
                    is! UpdateBookingStatusInProgress,
          ),
          title: Column(
            children: [
              CustomText(
                'bookingInformation'.translate(context: context),
                color: context.colorScheme.blackColor,
                fontWeight: FontWeight.w500,
                fontSize: 16,
              ),
              if (model.customJobRequestId != null)
                CustomText(
                  'customServiceRequest'.translate(context: context),
                  color: context.colorScheme.lightGreyColor,
                  fontWeight: FontWeight.w400,
                  fontSize: 14,
                ),
            ],
          ),
        ),

        body: mainWidget(),

        bottomNavigationBar: bottomBarWidget(),
      ),
    );
  }

  Widget onMapsBtn() {
    final model = bookingModel;
    if (model == null) return const SizedBox.shrink();
    return CustomInkWellContainer(
      onTap: () async {
        UiUtils.openMap(
          context,
          latitude: model.latitude,
          longitude: model.longitude,
        );
      },
      child: CustomContainer(
        padding: const EdgeInsets.all(10),
        color: Theme.of(context).colorScheme.accentColor.withValues(alpha: 0.3),
        borderRadius: UiUtils.borderRadiusOf5,
        child: Text(
          'onMapsLbl'.translate(context: context),
          style: TextStyle(color: Theme.of(context).colorScheme.accentColor),
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _getStatusButtonsForValue(
    String? currentStatusValue,
  ) {
    switch (currentStatusValue) {
      case '1': // awaiting
        return [
          {'value': '2', 'title': 'confirm'.translate(context: context)},
          {'value': '7', 'title': 'cancel'.translate(context: context)},
        ];
      case '2': // confirmed
        return [
          {'value': '3', 'title': 'start'.translate(context: context)},
          {'value': '4', 'title': 'reschedule'.translate(context: context)},
          {'value': '7', 'title': 'cancel'.translate(context: context)},
        ];
      case '4': // rescheduled
        return [
          {'value': '3', 'title': 'start'.translate(context: context)},
          {'value': '7', 'title': 'cancel'.translate(context: context)},
        ];
      case '3': // started
        return [
          {'value': '5', 'title': 'booking_ended'.translate(context: context)},
          {'value': '6', 'title': 'complete'.translate(context: context)},
        ];
      case '5': // booking_ended
        return [
          {'value': '6', 'title': 'complete'.translate(context: context)},
        ];
      case '6': // completed
        return [
          {
            'value': 'invoice',
            'title': 'downloadInvoice'.translate(context: context),
          },
        ];
      case '7': // cancelled
      default:
        return [];
    }
  }

  Future<void> _handleStatusUpdate(String statusValue) async {
    final model = bookingModel;
    if (model == null) return;

    if (statusValue == 'invoice') {
      context.read<DownloadInvoiceCubit>().downloadInvoice(
        bookingId: model.id ?? '',
        buttonScreenName: 'bookingDetails',
      );
      return;
    }

    final UpdateBookingStatusState currentState = context
        .read<UpdateBookingStatusCubit>()
        .state;
    if (currentState is UpdateBookingStatusInProgress) {
      return;
    }

    setState(() {
      _loadingButtonValue = statusValue;
    });

    Map<String, String>? bookingStatus;
    final List<Map<String, String>> selectedBookingStatus = getStatusForApi
        .where((Map<String, String> element) {
          return element['value'] == statusValue;
        })
        .toList();

    if (selectedBookingStatus.isNotEmpty) {
      bookingStatus = selectedBookingStatus[0];
    }
    if (statusValue == '3') {
      final proofResult = await UiUtils.showModelBottomSheets(
        context: context,
        child: UploadProofBottomSheet(preSelectedFiles: selectedProofFiles),
      );
      if (proofResult == null) {
        setState(() => _loadingButtonValue = null);
        return;
      }

      selectedProofFiles = proofResult;
      setState(() {});
    } else if (statusValue == '5') {
      final proofResult = await UiUtils.showModelBottomSheets(
        context: context,
        child: UploadProofBottomSheet(preSelectedFiles: selectedProofFiles),
      );
      if (proofResult == null) {
        setState(() => _loadingButtonValue = null);
        return;
      }

      selectedProofFiles = proofResult;
      setState(() {});

      final chargesResult = await UiUtils.showModelBottomSheets(
        context: context,
        useSafeArea: true,
        child: AdditionalChargesBottomSheet(
          additionalCharges: additionalCharged,
        ),
      );
      if (chargesResult == null) {
        setState(() => _loadingButtonValue = null);
        return;
      }

      additionalCharged = chargesResult;
      setState(() {});
    } else if (statusValue == '4') {
      final Map? result = await UiUtils.showModelBottomSheets(
        context: context,
        isScrollControlled: true,
        enableDrag: true,
        child: CustomContainer(
          height: context.screenHeight * 0.7,
          borderRadiusStyle: const BorderRadius.only(
            topRight: Radius.circular(UiUtils.borderRadiusOf20),
            topLeft: Radius.circular(UiUtils.borderRadiusOf20),
          ),
          child: CalenderBottomSheet(
            advanceBookingDays: model.advanceBookingDays!,
          ),
        ),
      );

      selectedRescheduleDate = result?['selectedDate'];
      selectedRescheduleTime = result?['selectedTime'];
      if (selectedRescheduleDate == null || selectedRescheduleTime == null) {
        setState(() => _loadingButtonValue = null);
        return;
      }
    }
    if (statusValue == '6' &&
        context.read<FetchSystemSettingsCubit>().isOrderOTPConfirmationEnable) {
      if (otpFromProvider == null || otpFromProvider!.trim().isEmpty) {
        final dialogResult = await UiUtils.showAnimatedDialog(
          context: context,
          child: VerifyOTPDialog(userId: model.customerId ?? ''),
        );

        if (dialogResult != null) {
          otpFromProvider = dialogResult.toString();
        } else {
          otpFromProvider = '';
          setState(() => _loadingButtonValue = null);
          return;
        }
      }
    }

    if (statusValue == '6' && model.paymentMethod == "cod") {
      if (context
          .read<FetchSystemSettingsCubit>()
          .isOrderOTPConfirmationEnable) {
        await UiUtils.showAnimatedDialog(
          context: context,
          child: CustomDialogLayout(
            title: "collectCashFromCustomer",
            confirmButtonName: "okay",
            cancelButtonName: "cancel",
            confirmButtonBackgroundColor: Theme.of(
              context,
            ).colorScheme.accentColor,
            cancelButtonBackgroundColor: Theme.of(
              context,
            ).colorScheme.secondaryColor,
            showProgressIndicator: false,
            cancelButtonPressed: () => Navigator.pop(context),
            confirmButtonPressed: () => Navigator.pop(context),
          ),
        );
      } else {
        await UiUtils.showAnimatedDialog(
          context: context,
          child: CustomDialogLayout(
            title: "collectdCash",
            confirmButtonName: "okay",
            cancelButtonName: "cancel",
            confirmButtonBackgroundColor: Theme.of(
              context,
            ).colorScheme.accentColor,
            cancelButtonBackgroundColor: Theme.of(
              context,
            ).colorScheme.secondaryColor,
            showProgressIndicator: false,
            cancelButtonPressed: () => Navigator.pop(context),
            confirmButtonPressed: () => Navigator.pop(context),
          ),
        );
      }
    }
    // Get translated status from filters list based on status value
    String translatedStatus = model.translatedStatus.toString();
    final translatedStatusFilter = filters.firstWhere(
      (f) => f['value'] == statusValue,
      orElse: () => <String, String>{},
    );
    if (translatedStatusFilter.isNotEmpty &&
        translatedStatusFilter['title'] != null) {
      translatedStatus = translatedStatusFilter['title']!;
    }

    context.read<UpdateBookingStatusCubit>().updateBookingStatus(
      orderId: int.parse(model.id!),
      customerId: int.parse(model.customerId!),
      status: bookingStatus?['title'] ?? model.status!,
      translatedStatus: translatedStatus,
      otp: otpFromProvider ?? '',
      date: selectedRescheduleDate?.toString().split(' ')[0],
      time: selectedRescheduleTime,
      proofData: selectedProofFiles,
      additionalCharges: additionalCharged,
    );
  }

  Widget bottomBarWidget() {
    final model = bookingModel;
    if (model == null) return const SizedBox.shrink();

    return BlocConsumer<DownloadInvoiceCubit, DownloadInvoiceState>(
      listener: (BuildContext context, DownloadInvoiceState downloadState) async {
        if (downloadState is DownloadInvoiceSuccess) {
          if (downloadState.bookingId == model.id &&
              downloadState.buttonScreenName == 'bookingDetails') {
            try {
              final appDocDirPath =
                  (await getApplicationDocumentsDirectory()).path;
              final targetFileName =
                  "$appName-${"invoice".translate(context: context)}-${model.id}.pdf";
              final File file = File("$appDocDirPath/$targetFileName");

              await file.writeAsBytes(downloadState.invoiceData).then((
                final value,
              ) {
                OpenFile.open(file.path);
              });
            } catch (e) {
              UiUtils.showMessage(
                context,
                'somethingWentWrong'.translate(context: context),
                ToastificationType.error,
              );
            }
          }
        } else if (downloadState is DownloadInvoiceFailure) {
          if (downloadState.bookingId == model.id) {
            UiUtils.showMessage(
              context,
              downloadState.errorMessage,
              ToastificationType.error,
            );
          }
        }
      },
      builder: (BuildContext context, DownloadInvoiceState downloadState) {
        return BlocConsumer<UpdateBookingStatusCubit, UpdateBookingStatusState>(
          listener: (BuildContext context, UpdateBookingStatusState state) {
            if (state is UpdateBookingStatusSuccess) {
              _loadingButtonValue = null;
              if (state.error == 'true') {
                UiUtils.showMessage(
                  context,
                  state.message,
                  ToastificationType.error,
                );
                setState(() {
                  selectedProofFiles = [];
                  additionalCharged = [];
                });
                return;
              }

              context.read<FetchBookingsCubit>().updateBookingDetailsLocally(
                bookingID: state.orderId.toString(),
                bookingStatus: state.status,
                listOfUploadedImages: state.imagesList,
                listOfAdditionalCharged: state.additionalCharges,
                bookingTranslatedStatus: state.translatedStatus,
              );

              if (currentBookingModel != null) {
                currentBookingModel!.status = state.status;
                currentBookingModel!.translatedStatus = state.translatedStatus;

                final String? statusValue = _getStatusValueFromTitle(
                  state.status,
                );
                if (statusValue == '3') {
                  currentBookingModel!.workStartedProof = state.imagesList;
                } else if (statusValue == '5') {
                  currentBookingModel!.workCompletedProof = state.imagesList;
                  currentBookingModel!.additionalCharges =
                      state.additionalCharges;
                }
              }

              UiUtils.showMessage(
                context,
                'updatedSuccessfully',
                ToastificationType.success,
              );
            } else if (state is UpdateBookingStatusFailure) {
              _loadingButtonValue = null;
            }
          },
          builder: (BuildContext context, UpdateBookingStatusState state) {
            final bool isLoading = state is UpdateBookingStatusInProgress;
            final String? statusToUse = _getStatusValueFromTitle(
              isLoading
                  ? model.status
                  : (currentBookingModel?.status ?? model.status),
            );
            final List<Map<String, dynamic>> buttons =
                _getStatusButtonsForValue(statusToUse);

            if (buttons.isEmpty) {
              return const SizedBox.shrink();
            }

            final downloadState = context.watch<DownloadInvoiceCubit>().state;
            final bool isInvoiceDownloading =
                downloadState is DownloadInvoiceInProgress &&
                downloadState.bookingId == model.id &&
                downloadState.buttonScreenName == 'bookingDetails';

            return CustomContainer(
              color: Theme.of(context).colorScheme.secondaryColor,
              child: Padding(
                padding: EdgeInsetsDirectional.only(
                  start: 15,
                  end: 15,
                  top: 10,
                  bottom: 10 + MediaQuery.of(context).padding.bottom,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (selectedRescheduleDate != null &&
                        selectedRescheduleTime != null) ...[
                      SizedBox(
                        height: 70,
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceEvenly,
                                children: [
                                  Text(
                                    'selectedDate'.translate(context: context),
                                    style: TextStyle(
                                      fontWeight: FontWeight.w600,
                                      color: context.colorScheme.blackColor,
                                    ),
                                  ),
                                  Text(
                                    selectedRescheduleDate.toString().split(
                                      ' ',
                                    )[0],
                                    style: TextStyle(
                                      color: context.colorScheme.blackColor
                                          .withValues(alpha: 0.7),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceEvenly,
                                children: [
                                  Text(
                                    'selectedTime'.translate(context: context),
                                    style: TextStyle(
                                      fontWeight: FontWeight.w600,
                                      color: context.colorScheme.blackColor,
                                    ),
                                  ),
                                  Text(
                                    selectedRescheduleTime ?? '',
                                    style: TextStyle(
                                      color: context.colorScheme.blackColor
                                          .withValues(alpha: 0.7),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                    ],
                    buttons.length == 1
                        ? CustomRoundedButton(
                            showBorder: false,
                            buttonTitle: buttons[0]['title'] as String,
                            backgroundColor: Theme.of(
                              context,
                            ).colorScheme.accentColor,
                            widthPercentage: 1,
                            height: 50,
                            textSize: 14,
                            onTap: () {
                              if (isLoading ||
                                  (isInvoiceDownloading &&
                                      buttons[0]['value'] == 'invoice')) {
                                return;
                              }
                              _handleStatusUpdate(
                                buttons[0]['value'] as String,
                              );
                            },
                            child:
                                ((_loadingButtonValue == buttons[0]['value'] &&
                                        isLoading) ||
                                    (isInvoiceDownloading &&
                                        buttons[0]['value'] == 'invoice'))
                                ? CustomCircularProgressIndicator(
                                    color: AppColors.whiteColors,
                                  )
                                : null,
                          )
                        : Row(
                            children: buttons.asMap().entries.map((entry) {
                              final int index = entry.key;
                              final Map<String, dynamic> button = entry.value;
                              final bool isThisButtonLoading =
                                  _loadingButtonValue == button['value'] &&
                                  isLoading;
                              return Expanded(
                                child: Padding(
                                  padding: EdgeInsets.only(
                                    right: index < buttons.length - 1 ? 5 : 0,
                                    left: index > 0 ? 5 : 0,
                                  ),
                                  child: CustomRoundedButton(
                                    showBorder: false,
                                    buttonTitle: button['title'] as String,
                                    backgroundColor: Theme.of(
                                      context,
                                    ).colorScheme.accentColor,
                                    widthPercentage: 1,
                                    height: 50,
                                    textSize: 14,
                                    onTap: () {
                                      if (isLoading ||
                                          (isInvoiceDownloading &&
                                              button['value'] == 'invoice')) {
                                        return;
                                      }
                                      _handleStatusUpdate(
                                        button['value'] as String,
                                      );
                                    },
                                    child:
                                        (isThisButtonLoading ||
                                            (isInvoiceDownloading &&
                                                button['value'] == 'invoice'))
                                        ? CustomCircularProgressIndicator(
                                            color: AppColors.whiteColors,
                                          )
                                        : null,
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget mainWidget() {
    final model = bookingModel;
    if (model == null) {
      return const BookingDetailsShimmer();
    }
    return SingleChildScrollView(
      clipBehavior: Clip.none,
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomerInfoWidget(bookingsModel: model),
          const SizedBox(height: 10),
          StatusAndInvoiceWidget(bookingsModel: model),
          const SizedBox(height: 10),
          BookingDateAndTimeWidget(bookingsModel: model),
          Visibility(
            visible: model.workStartedProof?.isNotEmpty ?? false,
            child: UploadedProofWidget(
              title: 'workStartedProof',
              proofData: model.workStartedProof!,
            ),
          ),
          Visibility(
            visible: model.workCompletedProof?.isNotEmpty ?? false,
            child: UploadedProofWidget(
              title: 'workCompletedProof',
              proofData: model.workCompletedProof!,
            ),
          ),
          NotesWidget(remarks: model.remarks ?? ''),
          ServiceDetailsWidget(bookingsModel: model),
          PriceSectionWidget(
            bookingsModel: model,
            isBillDetailsCollapsed: isBillDetailsCollapsed,
          ),
        ],
      ),
    );
  }
}
