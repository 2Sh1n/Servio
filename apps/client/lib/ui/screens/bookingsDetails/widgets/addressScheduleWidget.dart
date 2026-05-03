import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class AddressScheduleWidget extends StatelessWidget {
  final Booking bookingDetails;
  final bool isAtStoreBooking;
  final bool isOTPSystemEnabled;
  final bool isBookingCancelled;
  final bool isBookingCompleted;

  const AddressScheduleWidget({
    super.key,
    required this.bookingDetails,
    required this.isAtStoreBooking,
    required this.isOTPSystemEnabled,
    required this.isBookingCancelled,
    required this.isBookingCompleted,
  });

  bool get hasMultipleDaysBooking =>
      bookingDetails.multipleDaysBooking?.isNotEmpty ?? false;

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      padding: const EdgeInsets.all(15),
      child: Column(
        children: [
          _buildAddressHeader(context),
          const CustomSizedBox(height: 10),
          _buildAddressSection(context),
          const CustomSizedBox(height: 10),
          _buildPhoneSection(context),
          const CustomSizedBox(height: 10),
          _buildScheduleSection(context),
          if (_shouldShowOTP()) ...[
            const CustomSizedBox(height: 10),
            _buildOTPWidget(context),
          ]
        ],
      ),
    );
  }

  Widget _buildAddressHeader(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _buildSectionHeading(context, "bookedAt"),
        ),
        _buildAddressTypeButton(context),
      ],
    );
  }

  Widget _buildSectionHeading(BuildContext context, String title) {
    return CustomText(
      title.translate(context: context),
      maxLines: 1,
      fontWeight: FontWeight.w400,
      fontSize: 14,
      color: context.colorScheme.lightGreyColor,
    );
  }

  Widget _buildAddressTypeButton(BuildContext context) {
    final String addressType =
        isAtStoreBooking ? "storeAddress" : "yourAddress";

    return CustomInkWellContainer(
      showRippleEffect: false,
      onTap: () => _handleAddressTap(context),
      child: CustomText(
        addressType.translate(context: context),
        fontWeight: FontWeight.w400,
        fontSize: 14,
        color: context.colorScheme.accentColor,
      ),
    );
  }

  Widget _buildAddressSection(BuildContext context) {
    final String addressText = isAtStoreBooking
        ? bookingDetails.providerAddress ?? ''
        : filterAddressString(bookingDetails.address ?? '');

    return CustomInkWellContainer(
      showRippleEffect: false,
      onTap: () => _handleAddressTap(context),
      child: _buildImageAndTitleWidget(
        context,
        imageName: AppAssets.icLocation,
        title: addressText,
      ),
    );
  }

  Widget _buildPhoneSection(BuildContext context) {
    final String phoneNumber = isAtStoreBooking
        ? bookingDetails.providerNumber ?? ''
        : filterAddressString(bookingDetails.customerNo ?? '');

    return CustomInkWellContainer(
      showRippleEffect: false,
      onTap: () => _handleNumberTap(context, phoneNumber),
      child: _buildImageAndTitleWidget(
        context,
        imageName: AppAssets.phone,
        title: phoneNumber,
      ),
    );
  }

  Widget _buildScheduleSection(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildSingleDaySchedule(context),
        if (hasMultipleDaysBooking) _buildMultipleDaysSchedule(context)
      ],
    );
  }

  Widget _buildSingleDaySchedule(BuildContext context) {
    return Row(
      children: [
        Expanded(
          flex: 4,
          child: _buildImageAndTitleWidget(
            context,
            imageName: AppAssets.icCalendar,
            title: bookingDetails.dateOfService.toString().formatDate(),
          ),
        ),
        const CustomSizedBox(width: 5),
        Expanded(
          flex: 6,
          child: _buildImageAndTitleWidget(
            context,
            imageName: AppAssets.icClock,
            title: bookingDetails.startingTime.toString().formatTime(),
            secondTitle: bookingDetails.endingTime.toString().formatTime(),
          ),
        ),
        const CustomSizedBox(width: 10),
      ],
    );
  }

  Widget _buildMultipleDaysSchedule(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: List.generate(
        bookingDetails.multipleDaysBooking!.length,
        (index) => _buildMultipleDayScheduleItem(context, index),
      ),
    );
  }

  Widget _buildMultipleDayScheduleItem(BuildContext context, int index) {
    final multipleDayBooking = bookingDetails.multipleDaysBooking![index];
    final String scheduleDate =
        multipleDayBooking.multipleDayDateOfService.toString().formatDate();
    final String scheduleStartTime =
        multipleDayBooking.multipleDayStartingTime.toString().formatTime();
    final String scheduleEndTime =
        multipleDayBooking.multipleEndingTime.toString().formatTime();

    return Row(
      children: [
        Expanded(
          flex: 4,
          child: _buildImageAndTitleWidget(
            context,
            imageName: AppAssets.icCalendar,
            title: scheduleDate,
          ),
        ),
        const CustomSizedBox(width: 5),
        Expanded(
          flex: 6,
          child: _buildImageAndTitleWidget(
            context,
            imageName: AppAssets.icClock,
            title: scheduleStartTime,
            secondTitle: scheduleEndTime,
          ),
        ),
        const CustomSizedBox(width: 10),
      ],
    );
  }

  Widget _buildImageAndTitleWidget(
    BuildContext context, {
    required String imageName,
    required String title,
    String secondTitle = '',
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        CustomSvgPicture(
          avoideResponsive: true,
          svgImage: imageName,
          height: 20,
          width: 20,
          color: context.colorScheme.accentColor,
        ),
        const CustomSizedBox(width: 5),
        secondTitle.isNotEmpty
            ? _buildTimeRangeText(context, title, secondTitle)
            : _buildSingleText(context, title),
      ],
    );
  }

  Widget _buildTimeRangeText(
      BuildContext context, String startTime, String endTime) {
    return Row(
      children: [
        CustomText(
          startTime,
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: context.colorScheme.blackColor,
          maxLines: 2,
        ),
        CustomText(
          ' ${'to'.translate(context: context)} $endTime',
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: context.colorScheme.blackColor,
          maxLines: 1,
        ),
      ],
    );
  }

  Widget _buildSingleText(BuildContext context, String title) {
    return Expanded(
      child: CustomText(
        title,
        fontSize: 14,
        fontWeight: FontWeight.w500,
        color: context.colorScheme.blackColor,
        maxLines: 2,
      ),
    );
  }

  Widget _buildOTPWidget(BuildContext context) {
    return Row(
      children: [
        CustomSvgPicture(
          svgImage: AppAssets.icOtp,
          height: 20,
          width: 20,
          color: context.colorScheme.accentColor,
        ),
        const CustomSizedBox(width: 10),
        CustomText(
          "otp".translate(context: context),
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: context.colorScheme.blackColor,
          maxLines: 1,
        ),
        const CustomSizedBox(width: 10),
        _buildOTPContainer(context),
      ],
    );
  }

  Widget _buildOTPContainer(BuildContext context) {
    return CustomInkWellContainer(
      onTap: () => _copyOTPToClipboard(),
      borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf5),
      child: CustomContainer(
        height: 30,
        width: 110,
        color: context.colorScheme.accentColor.withAlpha(15),
        borderRadius: UiUtils.borderRadiusOf5,
        child: Stack(
          children: [
            CustomSizedBox(
              avoideResponsive: false,
              height: 30,
              width: 110,
              child: DashedRect(
                color: context.colorScheme.accentColor,
                strokeWidth: 1,
                gap: 5,
              ),
            ),
            Center(
              child: CustomText(
                bookingDetails.otp ?? '',
                fontSize: 16,
                fontWeight: FontWeight.w500,
                color: context.colorScheme.accentColor,
                maxLines: 1,
                letterSpacing: 5,
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _shouldShowOTP() {
    return isOTPSystemEnabled && !isBookingCancelled && !isBookingCompleted;
  }

  Future<void> _handleAddressTap(BuildContext context) async {
    if (isAtStoreBooking) {
      UiUtils.openMap(context,
          latitude: bookingDetails.providerLatitude,
          longitude: bookingDetails.providerLongitude);
    }
  }

  Future<void> _handleNumberTap(
      BuildContext context, String phoneNumber) async {
    final Uri url = Uri.parse("tel:$phoneNumber");
    if (await canLaunchUrl(url)) {
      await launchUrl(url);
    } else {
      throw "Could not launch $url";
    }
  }

  Future<void> _copyOTPToClipboard() async {
    await Clipboard.setData(ClipboardData(text: bookingDetails.otp ?? ''));
  }
}
