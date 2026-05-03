// ignore_for_file: use_build_context_synchronously
import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/cubits/fetchBookingDetailsCubit.dart';
import 'package:e_demand/ui/screens/bookingsDetails/paymentGatewayManager.dart';
import 'package:e_demand/ui/screens/bookingsDetails/widgets/addressScheduleWidget.dart';
import 'package:e_demand/ui/screens/bookingsDetails/widgets/notesWidget.dart';
import 'package:e_demand/ui/screens/bookingsDetails/widgets/paymentModeWidget.dart';
import 'package:e_demand/ui/screens/bookingsDetails/widgets/providerInfoWidget.dart';
import 'package:e_demand/ui/screens/bookingsDetails/widgets/serviceListWidget.dart';
import 'package:e_demand/ui/screens/bookingsDetails/widgets/statusInvoiceWidget.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

typedef PaymentGatewayDetails = ({String paymentType, String paymentImage});

// ignore: must_be_immutable
class BookingDetails extends StatefulWidget {
  Booking? bookingDetails;
  final String? bookingId;

  BookingDetails({final Key? key, this.bookingDetails, this.bookingId})
      : assert(bookingDetails != null || bookingId != null,
            'Either bookingDetails or bookingId must be provided'),
        super(key: key);

  static Route route(final RouteSettings routeSettings) {
    final Map parameters = routeSettings.arguments as Map;

    // Check if we have a bookingId (from notification) or full bookingDetails
    final bool hasBookingId =
        parameters.containsKey("bookingId") && parameters["bookingId"] != null;

    return CupertinoPageRoute(
      builder: (final BuildContext context) => Builder(builder: (context) {
        return MultiBlocProvider(
          providers: [
            BlocProvider<AddTransactionCubit>(
              create: (context) =>
                  AddTransactionCubit(bookingRepository: BookingRepository()),
            ),
            BlocProvider<ChangeBookingStatusCubit>(
              create: (context) => ChangeBookingStatusCubit(
                  bookingRepository: BookingRepository()),
            ),
            BlocProvider<FetchBookingDetailsCubit>(
              create: (context) => FetchBookingDetailsCubit(),
            ),
          ],
          child: hasBookingId
              ? BookingDetails(
                  bookingId: parameters["bookingId"],
                )
              : BookingDetails(
                  bookingDetails: parameters["bookingDetails"],
                ),
        );
      }),
    );
  }

  @override
  State<BookingDetails> createState() => _BookingDetailsState();
}

class _BookingDetailsState extends State<BookingDetails> {
  // UI State Variables
  ValueNotifier<bool> isBillDetailsCollapsed = ValueNotifier(true);
  ValueNotifier<String> paymentButtonName = ValueNotifier('');

  // Payment Related Variables
  PaymentGatewayManager? _paymentManager;
  late List<Map<String, dynamic>> enabledPaymentMethods = [];
  late String selectedPaymentMethod = '';

  // Booking Related Variables
  dynamic selectedDate;
  dynamic selectedTime;
  String? message;

  // Loading state for when fetching by bookingId
  bool get _isLoadingFromId =>
      widget.bookingId != null && widget.bookingDetails == null;

  // Computed Properties - now use null-safe access
  bool get isPayLaterAllowed => widget.bookingDetails?.isPayLaterAllowed == 1;

  bool get isOnlineAllowed =>
      widget.bookingDetails?.isOnlinePaymentAllowed == 1;

  bool get isBookingCompleted => widget.bookingDetails?.status == "completed";

  bool get isBookingCancelled => widget.bookingDetails?.status == "cancelled";

  bool get isBookingCompletedOrCancelled =>
      isBookingCompleted || isBookingCancelled;

  bool get isChatAllowed =>
      widget.bookingDetails?.isPostBookingChatAllowed == "1";

  bool get isOTPSystemEnabled =>
      context.read<SystemSettingCubit>().isOTPSystemEnable;

  bool get isBookingCancelable =>
      widget.bookingDetails?.isCancelable == "1" && !isBookingCancelled;

  bool get isReorderAllowed => widget.bookingDetails?.isReorderAllowed == "1";

  bool get hasMultipleDaysBooking =>
      widget.bookingDetails?.multipleDaysBooking?.isNotEmpty ?? false;

  bool get hasWorkStartedProof =>
      widget.bookingDetails?.workStartedProof?.isNotEmpty ?? false;

  bool get hasWorkCompletedProof =>
      widget.bookingDetails?.workCompletedProof?.isNotEmpty ?? false;

  bool get isCustomJobRequest =>
      widget.bookingDetails?.isCustomJobRequest ?? false;

  bool get hasAdditionalCharges => _hasAdditionalChargesCondition();

  bool get hasUnpaidAdditionalCharges => _hasUnpaidAdditionalChargesCondition();

  bool get isAwaitingOrConfirmed => _isAwaitingOrConfirmedStatus();

  bool get hasRemarks => widget.bookingDetails?.remarks?.isNotEmpty ?? false;

  bool get isAtStoreBooking {
    final addressId = widget.bookingDetails?.addressId;
    return addressId == "0" ||
        addressId == null ||
        addressId.isEmpty ||
        addressId == '';
  }

  bool _hasAdditionalChargesCondition() {
    return widget.bookingDetails?.additionalCharges != null &&
        widget.bookingDetails!.additionalCharges!.isNotEmpty &&
        _isValueGreaterThanZero(widget.bookingDetails?.totalAdditionalCharge);
  }

  bool _hasUnpaidAdditionalChargesCondition() {
    return widget.bookingDetails?.paymentStatusOfAdditionalCharge == '' &&
        hasAdditionalCharges;
  }

  bool _isAwaitingOrConfirmedStatus() {
    return widget.bookingDetails?.status == "awaiting" ||
        widget.bookingDetails?.status == "confirmed";
  }

  bool _isValueGreaterThanZero(String? value) {
    if (value == null || value.isEmpty || value == 'null') {
      return false;
    }
    final doubleValue = double.tryParse(value.replaceAll(",", '')) ?? 0.0;
    return doubleValue > 0;
  }

  @override
  void initState() {
    super.initState();

    // If we have a bookingId but no bookingDetails, fetch the details
    if (widget.bookingId != null && widget.bookingDetails == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.read<FetchBookingDetailsCubit>().fetchBookingDetails(
              bookingId: widget.bookingId!,
            );
      });
    } else {
      _initializePaymentMethods();
      getPaymentGatewayDetails();
    }
  }

  void _initializePaymentMethods() {
    enabledPaymentMethods = context
        .read<SystemSettingCubit>()
        .getEnabledPaymentMethods(
            isPayLaterAllowed: isPayLaterAllowed,
            isOnlinePaymentAllowed: isOnlineAllowed);
  }

  @override
  void dispose() {
    _paymentManager?.dispose();
    paymentButtonName.dispose();
    isBillDetailsCollapsed.dispose();
    super.dispose();
  }

  ({String paymentImage, String paymentType}) getPaymentGatewayDetails() {
    switch (selectedPaymentMethod) {
      case "cod":
        return (paymentType: "cod", paymentImage: AppAssets.cod);
      case "stripe":
        return (paymentType: "stripe", paymentImage: AppAssets.stripe);
      case "razorpay":
        return (paymentType: "razorpay", paymentImage: AppAssets.razorpay);
      case "paystack":
        return (paymentType: "paystack", paymentImage: AppAssets.paystack);
      case "paypal":
        return (paymentType: "paypal", paymentImage: AppAssets.paypal);
      case "flutterwave":
        return (
          paymentType: "flutterwave",
          paymentImage: AppAssets.flutterwave
        );
      default:
        return (paymentType: "cod", paymentImage: AppAssets.cod);
    }
  }

  @override
  Widget build(final BuildContext context) => AnnotatedSafeArea(
      isAnnotated: true,
      child: Scaffold(
        appBar: _buildAppBar(),
        body: _buildBody(),
      ));

  PreferredSizeWidget _buildAppBar() {
    // When loading from bookingId, show simplified app bar
    if (_isLoadingFromId) {
      return UiUtils.getSimpleAppBar(
        context: context,
        titleWidget: CustomText(
          'bookingInformation'.translate(context: context),
          color: context.colorScheme.blackColor,
          fontWeight: FontWeight.w600,
          fontSize: 18,
        ),
        elevation: 0.5,
      );
    }

    final int servicesCount = widget.bookingDetails!.services?.length ?? 0;
    final String servicesText = servicesCount > 1 ? 'services' : 'service';

    return UiUtils.getSimpleAppBar(
      context: context,
      titleWidget: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          CustomText(
            'bookingInformation'.translate(context: context),
            color: context.colorScheme.blackColor,
            fontWeight: FontWeight.w600,
            fontSize: 18,
          ),
          CustomText(
            "$servicesCount ${servicesText.translate(context: context)}",
            color: context.colorScheme.lightGreyColor,
            fontWeight: FontWeight.w400,
            fontSize: 14,
          ),
        ],
      ),
      elevation: 0.5,
    );
  }

  Widget _buildBody() {
    return BlocListener<ChangeBookingStatusCubit, ChangeBookingStatusState>(
      listener: (listenerContext, state) {
        _handleBookingStatusChange(listenerContext, state,
            parentContext: context);
      },
      child: _isLoadingFromId
          ? _buildFetchBookingDetailsBody()
          : BlocBuilder<BookingCubit, BookingState>(
              builder: (final BuildContext context, final BookingState state) {
                if (state is BookingFetchSuccess) {
                  return _buildBookingDetailsContent();
                }
                return ErrorContainer(
                  errorMessage:
                      'somethingWentWrong'.translate(context: context),
                );
              },
            ),
    );
  }

  Widget _buildFetchBookingDetailsBody() {
    return BlocConsumer<FetchBookingDetailsCubit, FetchBookingDetailsState>(
      listener: (context, state) {
        if (state is FetchBookingDetailsSuccess) {
          // Update widget.bookingDetails and initialize payment methods
          widget.bookingDetails = state.booking;
          _initializePaymentMethods();
          getPaymentGatewayDetails();
          // Also update the booking in BookingCubit if available
          try {
            context.read<BookingCubit>().updateBookingDataLocally(
                  latestBookingData: state.booking,
                );
          } catch (_) {}
        }
      },
      builder: (context, state) {
        if (state is FetchBookingDetailsInProgress) {
          return _buildBookingDetailsShimmer();
        }

        if (state is FetchBookingDetailsFailure) {
          return ErrorContainer(
            errorMessage: state.errorMessage.translate(context: context),
            onTapRetry: () {
              context.read<FetchBookingDetailsCubit>().fetchBookingDetails(
                    bookingId: widget.bookingId!,
                  );
            },
          );
        }

        if (state is FetchBookingDetailsSuccess) {
          return _buildBookingDetailsContent();
        }

        return _buildBookingDetailsShimmer();
      },
    );
  }

  Widget _buildBookingDetailsShimmer() {
    return const SingleChildScrollView(
      padding: EdgeInsets.all(10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Provider Info Shimmer
          CustomShimmerLoadingContainer(
            height: 80,
            borderRadius: UiUtils.borderRadiusOf10,
          ),
          CustomSizedBox(height: 8),
          // Status Section Shimmer
          CustomShimmerLoadingContainer(
            height: 60,
            borderRadius: UiUtils.borderRadiusOf10,
          ),
          CustomSizedBox(height: 8),
          // Address/Schedule Section Shimmer
          CustomShimmerLoadingContainer(
            height: 120,
            borderRadius: UiUtils.borderRadiusOf10,
          ),
          CustomSizedBox(height: 8),
          // Services Section Shimmer
          CustomShimmerLoadingContainer(
            height: 180,
            borderRadius: UiUtils.borderRadiusOf10,
          ),
          CustomSizedBox(height: 8),
          // Pricing Section Shimmer
          CustomShimmerLoadingContainer(
            height: 150,
            borderRadius: UiUtils.borderRadiusOf10,
          ),
          CustomSizedBox(height: 8),
          // Payment Mode Shimmer
          CustomShimmerLoadingContainer(
            height: 100,
            borderRadius: UiUtils.borderRadiusOf10,
          ),
        ],
      ),
    );
  }

  void _handleBookingStatusChange(BuildContext listenerContext, state,
      {required BuildContext parentContext}) {
    if (state is ChangeBookingStatusFailure) {
      UiUtils.showMessage(
        context,
        state.errorMessage.translate(context: context),
        ToastificationType.error,
      );
    } else if (state is ChangeBookingStatusSuccess) {
      UiUtils.showMessage(
        context,
        state.message,
        ToastificationType.success,
      );
      context.read<BookingCubit>().updateBookingDataLocally(
            latestBookingData: state.bookingData,
          );
      widget.bookingDetails = state.bookingData;
    }
  }

  Widget _buildBookingDetailsContent() {
    return Stack(
      children: [
        SingleChildScrollView(
          padding: EdgeInsetsDirectional.only(
            bottom: 10 +
                (isBookingCompleted
                    ? 0
                    : UiUtils.getScrollViewBottomPadding(context)),
            top: 10,
          ),
          child: _buildMainContent(),
        ),
      ],
    );
  }

  Widget _buildMainContent() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildProviderSection(),
        const CustomSizedBox(height: 8),
        _buildStatusSection(),
        const CustomSizedBox(height: 8),
        _buildBookingDetailsSection(),
        const CustomSizedBox(height: 8),
        if (hasRemarks) ...[
          _buildRemarksSection(),
          const CustomSizedBox(height: 8),
        ],
        if (hasWorkStartedProof) ...[
          _buildWorkStartedProofSection(),
          const CustomSizedBox(height: 8),
        ],
        if (hasWorkCompletedProof) ...[
          _buildWorkCompletedProofSection(),
          const CustomSizedBox(height: 8),
        ],
        _buildServicesSection(),
        const CustomSizedBox(height: 8),
        if (hasAdditionalCharges) ...[
          _buildAdditionalChargesSection(),
          const CustomSizedBox(height: 8),
        ],
        _buildPricingSection(),
        if (isBookingCompleted) ...[
          const CustomSizedBox(height: 26),
          _buildActionButtonsSection(),
        ],
        if (!isBookingCompleted) ...[
          const CustomSizedBox(height: kBottomNavigationBarHeight),
        ],
      ],
    );
  }

  Widget _buildProviderSection() {
    return ProviderInfoWidget(
      bookingDetails: widget.bookingDetails!,
      isBookingCompletedOrCancelled: isBookingCompletedOrCancelled,
    );
  }

  Widget _buildStatusSection() {
    return StatusInvoiceWidget(
      status: widget.bookingDetails!.status!,
      translatedStatus: widget.bookingDetails!.translatedStatus!,
      invoiceNumber: widget.bookingDetails!.invoiceNo,
    );
  }

  Widget _buildBookingDetailsSection() {
    return AddressScheduleWidget(
      bookingDetails: widget.bookingDetails!,
      isAtStoreBooking: isAtStoreBooking,
      isOTPSystemEnabled: isOTPSystemEnabled,
      isBookingCancelled: isBookingCancelled,
      isBookingCompleted: isBookingCompleted,
    );
  }

  Widget _buildRemarksSection() {
    return NotesWidget(
      notes: widget.bookingDetails!.remarks!,
    );
  }

  Widget _buildWorkStartedProofSection() {
    return _buildUploadedProofWidget(
      title: "workStartedProof",
      proofData: widget.bookingDetails!.workStartedProof!,
    );
  }

  Widget _buildWorkCompletedProofSection() {
    return _buildUploadedProofWidget(
      title: "workCompletedProof",
      proofData: widget.bookingDetails!.workCompletedProof!,
    );
  }

  Widget _buildServicesSection() {
    if (isCustomJobRequest) {
      return Column(
        children: [
          _buildCustomJobRequestContainer(),
          if (_hasCustomJobProviderNote()) ...[
            const CustomSizedBox(height: 8),
            _buildCustomJobProviderNoteContainer(),
          ],
        ],
      );
    } else {
      return ServiceListWidget(
        servicesList: widget.bookingDetails!.services!,
        isBookingCompleted: isBookingCompleted,
      );
    }
  }

  bool _hasCustomJobProviderNote() {
    final providerNote =
        widget.bookingDetails!.services?.first.customJobServiceProviderNote;
    return providerNote != null && providerNote.isNotEmpty;
  }

  Widget _buildAdditionalChargesSection() {
    return _buildAdditionalCharges();
  }

  Widget _buildPricingSection() {
    final subTotal = (double.parse(
                widget.bookingDetails!.total!.replaceAll(",", '')) -
            double.parse(widget.bookingDetails!.taxAmount!.replaceAll(",", '')))
        .toString();

    // Calculate total amount - subtract visiting charge if it's an at-store booking
    String totalAmount = widget.bookingDetails!.finalTotal!;
    if (isAtStoreBooking) {
      final visitingChargeValue = double.tryParse(
            widget.bookingDetails!.visitingCharges?.replaceAll(",", '') ?? '0',
          ) ??
          0.0;
      final finalTotalValue = double.tryParse(
            widget.bookingDetails!.finalTotal?.replaceAll(",", '') ?? '0',
          ) ??
          0.0;
      final adjustedTotal = finalTotalValue - visitingChargeValue;
      totalAmount = adjustedTotal.toString();
    }

    return Column(
      children: [
        _buildPriceSectionWidget(
          context: context,
          totalAmount: totalAmount,
          promoCodeAmount: widget.bookingDetails!.promoDiscount!,
          promoCodeName: widget.bookingDetails!.promoCode!,
          subTotal: subTotal,
          taxAmount: widget.bookingDetails!.taxAmount!,
          visitingCharge: widget.bookingDetails!.visitingCharges!,
          isAtStoreOptionSelected: isAtStoreBooking,
        ),
        PaymentModeWidget(
          bookingDetails: widget.bookingDetails!,
          isAwaitingOrConfirmed: isAwaitingOrConfirmed,
          hasUnpaidAdditionalCharges: hasUnpaidAdditionalCharges,
          isBookingCancelable: isBookingCancelable,
          onReschedule: (String action) => _handleRescheduleButton(),
          onCancelBooking: _handleCancelBooking,
          onPayAdditionalCharges: _showAdditionalPaymentDialog,
        ),
      ],
    );
  }

  Widget _buildActionButtonsSection() {
    return _buildReorderAndGetInvoiceButton(context);
  }

  Widget _getPriceSectionTile({
    required final BuildContext context,
    required final String heading,
    required final String subHeading,
    required final Color textColor,
    final Color? subHeadingTextColor,
    required final double fontSize,
    final FontWeight? fontWeight,
  }) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          children: [
            Expanded(
              child: CustomText(heading,
                  color: textColor,
                  maxLines: 1,
                  fontWeight: fontWeight,
                  fontSize: fontSize),
            ),
            CustomText(subHeading,
                color: subHeadingTextColor ?? textColor,
                fontWeight: fontWeight,
                maxLines: 1,
                fontSize: fontSize),
          ],
        ),
      );

  Widget _buildPriceSectionWidget({
    required final BuildContext context,
    required final String subTotal,
    required final String taxAmount,
    required final String visitingCharge,
    required final String promoCodeAmount,
    required final String promoCodeName,
    required final String totalAmount,
    required final bool isAtStoreOptionSelected,
  }) {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      padding: const EdgeInsets.all(15),
      child: Column(
        children: [
          CustomInkWellContainer(
            onTap: () {
              isBillDetailsCollapsed.value = !isBillDetailsCollapsed.value;
            },
            child: Row(
              children: [
                Expanded(
                  child: CustomText(
                    "billDetails".translate(context: context),
                    fontSize: 14,
                    fontWeight: FontWeight.w400,
                    color: context.colorScheme.lightGreyColor,
                  ),
                ),
                ValueListenableBuilder(
                    valueListenable: isBillDetailsCollapsed,
                    builder: (context, bool isCollapsed, _) {
                      return AnimatedSwitcher(
                          duration: const Duration(milliseconds: 300),
                          child: isCollapsed
                              ? Icon(Icons.keyboard_arrow_down,
                                  color: context.colorScheme.accentColor,
                                  size: 24)
                              : Icon(Icons.keyboard_arrow_up,
                                  color: context.colorScheme.accentColor,
                                  size: 24));
                    })
              ],
            ),
          ),
          ValueListenableBuilder(
              valueListenable: isBillDetailsCollapsed,
              builder: (context, bool isCollapsed, _) {
                return AnimatedSize(
                  duration: const Duration(milliseconds: 300),
                  child: CustomContainer(
                    constraints: isCollapsed
                        ? const BoxConstraints(maxHeight: 0.0)
                        : const BoxConstraints(
                            maxHeight: double.infinity,
                            maxWidth: double.maxFinite,
                          ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _getPriceSectionTile(
                          context: context,
                          fontSize: 14,
                          heading: 'subTotal'.translate(context: context),
                          subHeading: subTotal.priceFormat(context),
                          textColor: context.colorScheme.blackColor,
                        ),
                        if (promoCodeName != '' && promoCodeAmount != '')
                          _getPriceSectionTile(
                            context: context,
                            fontSize: 14,
                            heading:
                                "${"promoCode".translate(context: context)} ($promoCodeName)",
                            subHeading:
                                "- ${promoCodeAmount.priceFormat(context)}",
                            textColor: context.colorScheme.blackColor,
                          ),
                        if (_isValueGreaterThanZero(taxAmount))
                          _getPriceSectionTile(
                            context: context,
                            fontSize: 14,
                            heading: 'tax'.translate(context: context),
                            subHeading: "+ ${taxAmount.priceFormat(context)}",
                            textColor: context.colorScheme.blackColor,
                          ),
                        // Hide visiting charge if it's an at-store booking
                        // Only show visiting charge if NOT at store AND visiting charge has a valid value
                        if (!isAtStoreBooking &&
                            _isValueGreaterThanZero(visitingCharge))
                          _getPriceSectionTile(
                            context: context,
                            fontSize: 14,
                            heading:
                                'visitingCharge'.translate(context: context),
                            subHeading:
                                "+ ${visitingCharge.priceFormat(context)}",
                            textColor: context.colorScheme.blackColor,
                          ),
                        if (widget.bookingDetails!.additionalCharges != null &&
                            widget.bookingDetails!.additionalCharges!
                                .isNotEmpty &&
                            _isValueGreaterThanZero(widget
                                .bookingDetails!.totalAdditionalCharge)) ...[
                          _getPriceSectionTile(
                            context: context,
                            fontSize: 14,
                            heading:
                                'additionalCharges'.translate(context: context),
                            subHeading:
                                "+ ${widget.bookingDetails!.totalAdditionalCharge!.priceFormat(context)}",
                            textColor: context.colorScheme.blackColor,
                          ),
                        ],
                      ],
                    ),
                  ),
                );
              }),
          const CustomSizedBox(
            height: 10,
          ),
          _getPriceSectionTile(
            context: context,
            fontSize: 20,
            heading: (widget.bookingDetails!.paymentMethod == "cod"
                    ? "totalAmount"
                    : 'paidAmount')
                .translate(context: context),
            subHeading: totalAmount.priceFormat(context),
            textColor: context.colorScheme.blackColor,
            fontWeight: FontWeight.w700,
            subHeadingTextColor: context.colorScheme.accentColor,
          ),
          const CustomSizedBox(
            height: 10,
          ),
          Divider(
            color: context.colorScheme.lightGreyColor,
            thickness: 0.5,
            height: 0.5,
          ),
        ],
      ),
    );
  }

  Widget _buildUploadedProofWidget({
    required final String title,
    required final List<dynamic> proofData,
  }) =>
      CustomContainer(
        color: context.colorScheme.secondaryColor,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(15, 15, 15, 10),
              child: _buildSectionHeadingWidget(context, title: title),
            ),
            CustomSizedBox(
              height: 100,
              width: double.infinity,
              child: ListView.separated(
                  separatorBuilder:
                      (final BuildContext context, final int index) =>
                          const CustomSizedBox(
                            width: 12,
                          ),
                  itemCount: proofData.length,
                  shrinkWrap: true,
                  padding: const EdgeInsets.symmetric(horizontal: 15),
                  scrollDirection: Axis.horizontal,
                  itemBuilder: (final BuildContext context, final int index) {
                    return CustomContainer(
                      height: 100,
                      width: 100,
                      borderRadius: UiUtils.borderRadiusOf10,
                      border: Border.all(
                          color: context.colorScheme.lightGreyColor,
                          width: 0.5),
                      child: CustomInkWellContainer(
                        borderRadius:
                            BorderRadius.circular(UiUtils.borderRadiusOf10),
                        onTap: () {
                          Navigator.pushNamed(
                            context,
                            imagePreview,
                            arguments: {
                              "startFrom": index,
                              "isReviewType": false,
                              "dataURL": proofData,
                            },
                          ).then((Object? value) {
                            //locked in portrait mode only
                            SystemChrome.setPreferredOrientations(
                              [
                                DeviceOrientation.portraitUp,
                                DeviceOrientation.portraitDown
                              ],
                            );
                          });
                        },
                        child: UrlTypeHelper.getType(proofData[index]) ==
                                UrlType.image
                            ? ClipRRect(
                                borderRadius: BorderRadius.circular(
                                    UiUtils.borderRadiusOf10),
                                child: CustomCachedNetworkImage(
                                  networkImageUrl: proofData[index],
                                  fit: BoxFit.cover,
                                  height: 100,
                                  width: 100,
                                ),
                              )
                            : UrlTypeHelper.getType(proofData[index]) ==
                                    UrlType.video
                                ? Center(
                                    child: Icon(
                                      Icons.play_arrow,
                                      color: context.colorScheme.accentColor,
                                    ),
                                  )
                                : const CustomSizedBox(),
                      ),
                    );
                  }),
            ),
            const CustomSizedBox(
              height: 15,
            ),
          ],
        ),
      );

  Widget _buildSectionHeadingWidget(BuildContext context,
      {required String title}) {
    return CustomText(
      title.translate(context: context),
      maxLines: 1,
      fontWeight: FontWeight.w400,
      fontSize: 14,
      color: context.colorScheme.lightGreyColor,
    );
  }

  Widget _buildRatingWidget(BuildContext context,
      {required BookedService service,
      required int index,
      required VoidCallback onTap,
      required bool isCollapsed}) {
    final int serviceRating = int.parse(
      (service.rating ?? 0).toString().isEmpty ? '0' : service.rating ?? '0',
    );

    final bool hasRating = serviceRating != 0;
    final bool canRate = isBookingCompleted && serviceRating == 0;
    final bool canEdit = isBookingCompleted && hasRating;

    if (canRate) {
      return _buildRatingButtonWidget(
        context,
        service: service,
        index: index,
        title: "rate",
        serviceRating: serviceRating,
      );
    } else if (canEdit) {
      return _buildRatingDisplayWidget(
        context,
        service: service,
        index: index,
        serviceRating: serviceRating,
        onTap: onTap,
        isCollapsed: isCollapsed,
      );
    } else {
      return const SizedBox.shrink();
    }
  }

  Widget _buildRatingDisplayWidget(
    BuildContext context, {
    required BookedService service,
    required int index,
    required int serviceRating,
    required VoidCallback onTap,
    required bool isCollapsed,
  }) {
    final bool hasReviewContent = (service.comment ?? '').isNotEmpty ||
        (service.reviewImages ?? []).isNotEmpty;

    return CustomInkWellContainer(
      showRippleEffect: false,
      onTap: onTap,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildStarIcon(),
          const CustomSizedBox(width: 6),
          _buildRatingText(serviceRating),
          if (hasReviewContent) _buildCollapseIcon(isCollapsed),
          const CustomSizedBox(width: 6),
          _buildDivider(),
          const CustomSizedBox(width: 6),
          _buildEditButton(service, index, serviceRating),
        ],
      ),
    );
  }

  Widget _buildStarIcon() {
    return CustomSvgPicture(
      avoideResponsive: true,
      svgImage: AppAssets.icStar,
      height: 16,
      width: 16,
      color: context.colorScheme.accentColor,
    );
  }

  Widget _buildRatingText(int serviceRating) {
    return CustomText(
      serviceRating.toString(),
      fontSize: 14,
      fontWeight: FontWeight.w500,
      color: context.colorScheme.accentColor,
    );
  }

  Widget _buildCollapseIcon(bool isCollapsed) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 300),
      child: isCollapsed
          ? Icon(Icons.arrow_drop_down_sharp,
              color: context.colorScheme.accentColor, size: 12)
          : Icon(Icons.arrow_drop_up_sharp,
              color: context.colorScheme.accentColor, size: 12),
    );
  }

  Widget _buildDivider() {
    return CustomSizedBox(
      height: 12,
      child: VerticalDivider(
        color: context.colorScheme.lightGreyColor,
        thickness: 0.5,
        width: 0.5,
      ),
    );
  }

  Widget _buildEditButton(BookedService service, int index, int serviceRating) {
    return _buildRatingButtonWidget(
      context,
      service: service,
      index: index,
      title: "edit",
      serviceRating: serviceRating,
    );
  }

  Widget _buildRatingButtonWidget(BuildContext context,
      {required BookedService service,
      required int index,
      required String title,
      required int serviceRating}) {
    return CustomInkWellContainer(
      onTap: () {
        UiUtils.showBottomSheet(
          enableDrag: true,
          context: context,
          child: BlocProvider<SubmitReviewCubit>(
            create: (final BuildContext context) =>
                SubmitReviewCubit(bookingRepository: BookingRepository()),
            child: RatingBottomSheet(
              reviewComment: service.comment ?? '',
              ratingStar: serviceRating,
              serviceID: service.serviceId ?? '',
              serviceName: service.translatedTitle ?? '',
              serviceReviewImages: service.reviewImages ?? [],
              customJobRequestId: service.customJobRequestId,
            ),
          ),
        ).then(
          (value) {
            if (value != null) {
              widget.bookingDetails!.services?[index] = service.copyWith(
                comment: value["comment"],
                rating: value["rating"],
                reviewImages: (value["images"]?.isNotEmpty ?? false)
                    ? (value["images"] as List).cast<String>()
                    : [],
              );
              context.read<BookingCubit>().updateBookingDataLocally(
                  latestBookingData: widget.bookingDetails!);
            }
          },
        );
      },
      child: CustomText(
        title.translate(context: context),
        fontWeight: FontWeight.w500,
        fontSize: 14,
        color: context.colorScheme.accentColor,
      ),
    );
  }

  Widget _buildReorderAndGetInvoiceButton(BuildContext context) {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      padding: const EdgeInsets.all(15),
      child: Row(
        children: [
          if (isReorderAllowed) ...[
            Expanded(child: _buildReorderButton()),
            const CustomSizedBox(width: 10),
          ],
          Expanded(child: _buildDownloadInvoiceButton()),
        ],
      ),
    );
  }

  Widget _buildReorderButton() {
    return ReOrderButton(
      bookingDetails: widget.bookingDetails!,
      isReorderFrom: "bookingDetails",
      bookingId: widget.bookingDetails!.id ?? "0",
    );
  }

  Widget _buildDownloadInvoiceButton() {
    return DownloadInvoiceButton(
      bookingId: widget.bookingDetails!.id!,
      buttonScreenName: "bookingDetails",
    );
  }

  void _handleRescheduleButton() {
    UiUtils.showBottomSheet(
      enableDrag: true,
      context: context,
      child: MultiBlocProvider(
        providers: [
          BlocProvider<ValidateCustomTimeCubit>(
            create: (context) =>
                ValidateCustomTimeCubit(cartRepository: CartRepository()),
          ),
          BlocProvider(
            create: (context) => TimeSlotCubit(CartRepository()),
          )
        ],
        child: CalenderBottomSheet(
          advanceBookingDays:
              widget.bookingDetails!.providerAdvanceBookingDays.toString(),
          providerId: widget.bookingDetails!.partnerId.toString(),
          selectedDate: selectedDate,
          selectedTime: selectedTime,
          orderId: widget.bookingDetails!.id.toString(),
          customJobRequestId:
              widget.bookingDetails!.services?[0].customJobRequestId,
        ),
      ),
    ).then(_handleRescheduleResult);
  }

  void _handleRescheduleResult(dynamic value) {
    if (value == null) return;

    final bool isSaved = value['isSaved'];
    // selectedDate is now returned as String (YYYY-MM-DD format) from calenderBottomSheet
    final dateValue = value['selectedDate'];
    if (dateValue is String) {
      selectedDate = DateTime.parse(dateValue);
    } else if (dateValue is DateTime) {
      selectedDate = dateValue;
    }
    selectedTime = value['selectedTime'];
    message = value['message'];

    if (selectedTime != null && selectedDate != null && isSaved) {
      // Format selectedDate as date only (YYYY-MM-DD) without time
      final formattedDate =
          DateFormat('yyyy-MM-dd').format(selectedDate as DateTime);
      context.read<ChangeBookingStatusCubit>().changeBookingStatus(
            pressedButtonName: "reschedule",
            bookingStatus: 'rescheduled',
            bookingId: widget.bookingDetails!.id!,
            selectedTime: selectedTime.toString(),
            selectedDate: formattedDate,
          );
    }
  }

  void _showAdditionalPaymentDialog() {
    UiUtils.showAnimatedDialog(
      context: context,
      child: _buildAdditionalPaymentWidget(
        context: context,
        bookingDetails: widget.bookingDetails!,
      ),
    );
  }

  void _handleCancelBooking() {
    UiUtils.showAnimatedDialog(
      context: context,
      child: CustomDialogLayout(
        icon: CustomContainer(
          height: 70,
          width: 70,
          padding: const EdgeInsets.all(10),
          color: context.colorScheme.secondaryColor,
          borderRadius: UiUtils.borderRadiusOf50,
          child: Icon(
            Icons.help,
            color: context.colorScheme.accentColor,
            size: 70,
          ),
        ),
        title: "areYouSure",
        description: "doYouReallyWantToCancelThisBooking",
        cancelButtonName: "cancel",
        cancelButtonBackgroundColor: context.colorScheme.secondaryColor,
        cancelButtonPressed: () {
          Navigator.of(context).pop();
        },
        confirmButtonName: "ok",
        confirmButtonBackgroundColor: AppColors.redColor,
        confirmButtonPressed: () {
          Navigator.of(context).pop();
          context.read<ChangeBookingStatusCubit>().changeBookingStatus(
                pressedButtonName: "cancelBooking",
                bookingStatus: 'cancelled',
                bookingId: widget.bookingDetails!.id!,
              );
        },
      ),
    );
  }

  // !Additional Payment START
  Widget _buildAdditionalPaymentWidget({
    required final BuildContext context,
    required final Booking bookingDetails,
  }) {
    return CustomContainer(
      borderRadius: UiUtils.borderRadiusOf10,
      color: context.colorScheme.primaryColor,
      padding: const EdgeInsetsDirectional.all(16),
      child: StatefulBuilder(
        builder: (BuildContext context, StateSetter setState) {
          return Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisSize: MainAxisSize.max,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  CustomText(
                    'payAdditionalCharges'.translate(context: context),
                    fontSize: 16,
                    color: context.colorScheme.blackColor,
                    fontWeight: FontWeight.bold,
                  ),
                  GestureDetector(
                    onTap: () {
                      Navigator.pop(context);
                    },
                    child: CustomContainer(
                      alignment: Alignment.center,
                      color: context.colorScheme.primaryColor,
                      borderRadius: UiUtils.borderRadiusOf50,
                      border: Border.all(
                        color: context.colorScheme.accentColor
                            .withValues(alpha: 0.8),
                      ),
                      padding: const EdgeInsets.all(2.5),
                      child: Icon(
                        Icons.close,
                        color: context.colorScheme.blackColor,
                        size: 20,
                      ),
                    ),
                  )
                ],
              ),
              ListView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: enabledPaymentMethods.length,
                padding: EdgeInsets.zero,
                itemBuilder: (context, index) {
                  final Map<String, dynamic> paymentMethod =
                      enabledPaymentMethods[index];
                  return CustomRadioOptionsWidget(
                    image: paymentMethod["image"]!,
                    title: paymentMethod["title"]!,
                    value: paymentMethod["paymentType"]!,
                    groupValue: selectedPaymentMethod,
                    applyAccentColor: false,
                    onChanged: (final Object? selectedValue) {
                      setState(() {
                        selectedPaymentMethod = selectedValue.toString();
                        if (selectedValue.toString() == "cod") {
                          paymentButtonName.value =
                              "payWithCash".translate(context: context);
                        } else {
                          paymentButtonName.value =
                              "makePayment".translate(context: context);
                        }
                      });
                      ClarityService.logAction(
                        ClarityActions.paymentMethodSelected,
                        {
                          'order_id': widget.bookingDetails!.id ?? '',
                          'selected_method': selectedValue.toString(),
                          'current_method':
                              widget.bookingDetails!.paymentMethod ?? '',
                        },
                      );
                      ClarityService.setTag(
                        'payment_method',
                        selectedValue.toString(),
                      );
                    },
                  );
                },
              ),
              const CustomSizedBox(
                height: 10,
              ),
              if (paymentButtonName.value != '')
                BlocConsumer<AddTransactionCubit, AddTransactionState>(
                  listener: (context, state) {
                    if (state is AddTransactionSuccess) {
                      final totalAdditionalCharge =
                          widget.bookingDetails!.totalAdditionalCharge;

                      _processPayment(
                        state: state,
                        totalAdditionalCharge: totalAdditionalCharge,
                      );
                    }
                  },
                  builder: (context, state) {
                    return ValueListenableBuilder(
                      valueListenable: paymentButtonName,
                      builder: (context, String value, _) {
                        return CustomRoundedButton(
                          buttonTitle: value,
                          showBorder: true,
                          widthPercentage: 0.8,
                          backgroundColor: context.colorScheme.accentColor,
                          onTap: () async {
                            ClarityService.logAction(
                              ClarityActions.paymentStarted,
                              {
                                'order_id': widget.bookingDetails!.id ?? '',
                                'payment_method': selectedPaymentMethod,
                                'additional_charge_total': widget
                                        .bookingDetails!
                                        .totalAdditionalCharge ??
                                    '',
                              },
                            );
                            _paymentManager?.dispose();
                            _paymentManager = PaymentGatewayManager(
                              placedOrderId: widget.bookingDetails!.id ?? '',
                              context: context,
                              paymentGatewaySetting: context
                                  .read<SystemSettingCubit>()
                                  .paymentGatewaysSettings,
                            );

                            if (selectedPaymentMethod == 'razorpay' ||
                                selectedPaymentMethod == 'stripe') {
                              _processDirectPayment(
                                widget.bookingDetails!.totalAdditionalCharge,
                              );
                            } else if (selectedPaymentMethod == 'cod') {
                              _processDirectPayment(
                                widget.bookingDetails!.totalAdditionalCharge,
                              );
                            } else {
                              await context
                                  .read<AddTransactionCubit>()
                                  .addTransaction(
                                    status: "pending",
                                    orderID: widget.bookingDetails!.id ?? '',
                                    isAdditionalCharge: '1',
                                    paymentGatewayName: selectedPaymentMethod,
                                  );
                            }
                          },
                        );
                      },
                    );
                  },
                ),
            ],
          );
        },
      ),
    );
  }

  void _processPayment({
    required AddTransactionSuccess state,
    required String? totalAdditionalCharge,
  }) {
    String? paymentUrl;

    switch (selectedPaymentMethod) {
      case 'paystack':
        paymentUrl = state.paystackUrl;
        break;
      case 'flutterwave':
        paymentUrl = state.flutterwaveUrl;
        break;
      case 'paypal':
        paymentUrl = state.paypalUrl;
        break;
      case 'xendit':
        paymentUrl = state.xenditUrl;
        break;
    }

    if (paymentUrl != null && paymentUrl.isNotEmpty) {
      ClarityService.logAction(
        ClarityActions.paymentGatewayRedirected,
        {
          'order_id': widget.bookingDetails!.id ?? '',
          'payment_method': selectedPaymentMethod,
          'payment_url': paymentUrl,
          'amount': totalAdditionalCharge ?? '',
        },
      );
      _paymentManager?.processPayment(
        paymentMethod: selectedPaymentMethod,
        amount: double.parse(
          totalAdditionalCharge == '' ? '0' : totalAdditionalCharge ?? '0',
        ),
        orderId: widget.bookingDetails!.id ?? '',
        paymentUrl: paymentUrl,
      );
    }
  }

  void _processDirectPayment(String? totalAdditionalCharge) {
    ClarityService.logAction(
      ClarityActions.paymentGatewayRedirected,
      {
        'order_id': widget.bookingDetails!.id ?? '',
        'payment_method': selectedPaymentMethod,
        'amount': totalAdditionalCharge ?? '',
        'flow': 'direct',
      },
    );
    _paymentManager?.processPayment(
      paymentMethod: selectedPaymentMethod,
      amount: double.parse(
          totalAdditionalCharge == '' ? '0' : totalAdditionalCharge ?? '0'),
      orderId: widget.bookingDetails!.id ?? '',
    );
  }

  Widget _buildCustomJobRequestContainer() {
    final BookedService service = widget.bookingDetails!.services![0];

    bool collapsed = true;
    return CustomContainer(
      width: context.screenWidth,
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
      color: context.colorScheme.secondaryColor,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSectionHeadingWidget(context, title: "requestedService"),
          const CustomSizedBox(
            height: 10,
          ),
          StatefulBuilder(builder: (context, innerSetState) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    CustomContainer(
                      borderRadius: UiUtils.borderRadiusOf5,
                      width: 62,
                      height: 68,
                      border: Border.all(
                          color: context.colorScheme.lightGreyColor,
                          width: 0.5),
                      child: ClipRRect(
                        borderRadius:
                            BorderRadius.circular(UiUtils.borderRadiusOf5),
                        child: CustomCachedNetworkImage(
                          networkImageUrl: service.image ?? '',
                          height: 68,
                          width: 62,
                          fit: BoxFit.fill,
                        ),
                      ),
                    ),
                    const CustomSizedBox(
                      width: 12,
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CustomText(
                            '${service.translatedTitle} ',
                            fontWeight: FontWeight.w500,
                            color: context.colorScheme.blackColor,
                            fontSize: 16,
                            maxLines: 2,
                          ),
                          const CustomSizedBox(height: 8),
                          Row(
                            children: [
                              Row(
                                children: [
                                  CustomText(
                                    service.priceWithTax!
                                        .toString()
                                        .priceFormat(context),
                                    color: context.colorScheme.accentColor,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 16,
                                  ),
                                  const CustomSizedBox(
                                    width: 12,
                                  ),
                                  CustomText(
                                    "x${service.quantity}"
                                        .translate(context: context),
                                    fontWeight: FontWeight.w400,
                                    fontSize: 12,
                                    color: context.colorScheme.blackColor,
                                  ),
                                ],
                              ),
                              const Spacer(),
                              _buildRatingWidget(context,
                                  service: service, index: 0, onTap: () {
                                if ((service.comment ?? '').isEmpty &&
                                    (service.reviewImages ?? []).isEmpty) {
                                  return;
                                }
                                innerSetState(() {
                                  collapsed = !collapsed;
                                });
                              }, isCollapsed: collapsed),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (_isValueGreaterThanZero(service.rating)) ...[
                  AnimatedSize(
                    duration: const Duration(milliseconds: 300),
                    child: CustomContainer(
                      constraints: collapsed
                          ? const BoxConstraints(maxHeight: 0.0)
                          : const BoxConstraints(
                              maxHeight: double.infinity,
                              maxWidth: double.maxFinite,
                            ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const CustomSizedBox(
                            height: 10,
                          ),
                          CustomText(
                            service.comment ?? '',
                            fontSize: 14,
                            fontWeight: FontWeight.w400,
                            color: context.colorScheme.lightGreyColor,
                          ),
                          const CustomSizedBox(
                            height: 10,
                          ),
                          if (service.reviewImages?.isNotEmpty ?? false)
                            CustomSizedBox(
                              height: 44,
                              child: ListView.separated(
                                  physics: const NeverScrollableScrollPhysics(),
                                  shrinkWrap: true,
                                  scrollDirection: Axis.horizontal,
                                  itemCount: service.reviewImages?.length ?? 0,
                                  separatorBuilder: (final BuildContext context,
                                          final int index) =>
                                      const CustomSizedBox(
                                        width: 12,
                                      ),
                                  itemBuilder: (context, index) =>
                                      CustomInkWellContainer(
                                        onTap: () {
                                          Navigator.pushNamed(
                                            context,
                                            imagePreview,
                                            arguments: {
                                              "startFrom": index,
                                              "isReviewType": true,
                                              "dataURL": service.reviewImages,
                                              "reviewDetails": Reviews(
                                                id: service.id ?? '',
                                                rating: service.rating ?? '',
                                                profileImage: HiveRepository
                                                        .getUserProfilePictureURL ??
                                                    '',
                                                userName: HiveRepository
                                                        .getUsername ??
                                                    '',
                                                serviceId:
                                                    service.serviceId ?? '',
                                                comment: service.comment ?? '',
                                                images:
                                                    service.reviewImages ?? [],
                                                ratedOn: '',
                                              ),
                                            },
                                          );
                                        },
                                        child: CustomContainer(
                                            height: 44,
                                            width: 44,
                                            borderRadius:
                                                UiUtils.borderRadiusOf5,
                                            border: Border.all(
                                                color: context
                                                    .colorScheme.lightGreyColor,
                                                width: 0.5),
                                            child: ClipRRect(
                                                borderRadius:
                                                    BorderRadius.circular(
                                                        UiUtils
                                                            .borderRadiusOf5),
                                                child: CustomCachedNetworkImage(
                                                  networkImageUrl:
                                                      service.reviewImages?[
                                                              index] ??
                                                          '',
                                                  height: 44,
                                                  width: 44,
                                                  fit: BoxFit.fill,
                                                ))),
                                      )),
                            )
                        ],
                      ),
                    ),
                  ),
                ],
                if (widget.bookingDetails!.services?[0].customJobServiceNote !=
                        null &&
                    widget.bookingDetails!.services?[0].customJobServiceNote !=
                        '') ...[
                  const CustomSizedBox(
                    height: 10,
                  ),
                  CustomReadMoreTextContainer(
                      text: widget.bookingDetails!.services?[0]
                              .customJobServiceNote ??
                          '',
                      textStyle: TextStyle(
                        color: context.colorScheme.blackColor,
                        fontSize: 16,
                        fontWeight: FontWeight.w400,
                      )),
                ],
              ],
            );
          })
        ],
      ),
    );
  }

  Widget _buildCustomJobProviderNoteContainer() {
    return CustomContainer(
      width: context.screenWidth,
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
      color: context.colorScheme.secondaryColor,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSectionHeadingWidget(context,
              title: "providerNote".translate(context: context)),
          const CustomSizedBox(
            height: 10,
          ),
          CustomReadMoreTextContainer(
            text: widget.bookingDetails!.services?[0]
                    .customJobServiceProviderNote ??
                '',
            textStyle: TextStyle(
              color: context.colorScheme.blackColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAdditionalCharges() {
    return CustomContainer(
      width: context.screenWidth,
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
      color: context.colorScheme.secondaryColor,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSectionHeadingWidget(context, title: "additionalCharges"),
          const CustomSizedBox(
            height: 10,
          ),
          for (var i = 0;
              i < widget.bookingDetails!.additionalCharges!.length;
              i++) ...[
            _getPriceSectionTile(
              context: context,
              fontSize: 14,
              heading: widget.bookingDetails!.additionalCharges![i].name!,
              subHeading: widget.bookingDetails!.additionalCharges![i].charge!
                  .priceFormat(context),
              textColor: context.colorScheme.blackColor,
            ),
            const CustomSizedBox(
              height: 4,
            ),
          ],
        ],
      ),
    );
  }

// !Additional Payment END
}
