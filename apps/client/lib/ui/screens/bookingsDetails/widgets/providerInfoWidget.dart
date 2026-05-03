import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class ProviderInfoWidget extends StatelessWidget {
  final Booking bookingDetails;
  final bool isBookingCompletedOrCancelled;

  const ProviderInfoWidget({
    super.key,
    required this.bookingDetails,
    required this.isBookingCompletedOrCancelled,
  });

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSectionHeading(context),
          const CustomSizedBox(height: 10),
          _buildProviderInfoRow(context),
        ],
      ),
    );
  }

  Widget _buildSectionHeading(BuildContext context) {
    return CustomText(
      "provider".translate(context: context),
      maxLines: 1,
      fontWeight: FontWeight.w400,
      fontSize: 14,
      color: context.colorScheme.lightGreyColor,
    );
  }

  Widget _buildProviderInfoRow(BuildContext context) {
    return CustomInkWellContainer(
      showRippleEffect: false,
      onTap: () => _navigateToProviderDetails(context),
      child: Row(
        children: [
          _buildProviderImage(context),
          const CustomSizedBox(width: 12),
          _buildProviderNameSection(context),
          if (_isChatAllowed()) _buildChatButton(context),
        ],
      ),
    );
  }

  void _navigateToProviderDetails(BuildContext context) {
    Navigator.pushNamed(
      context,
      providerRoute,
      arguments: {"providerId": bookingDetails.partnerId ?? "0"},
    );
  }

  Widget _buildProviderImage(BuildContext context) {
    return CustomContainer(
      border: Border.all(color: context.colorScheme.lightGreyColor, width: 0.5),
      borderRadius: UiUtils.borderRadiusOf5,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf5),
        child: CustomContainer(
          height: 44,
          width: 44,
          borderRadius: UiUtils.borderRadiusOf5,
          child: CustomCachedNetworkImage(
              height: 44,
              width: 44,
              networkImageUrl: bookingDetails.profileImage ?? '',
              fit: BoxFit.cover),
        ),
      ),
    );
  }

  Widget _buildProviderNameSection(BuildContext context) {
    return Expanded(
      child: CustomText(
        bookingDetails.translatedCompanyName ?? '',
        fontWeight: FontWeight.w500,
        fontSize: 14,
        color: context.colorScheme.blackColor,
        maxLines: 2,
      ),
    );
  }

  bool _isChatAllowed() {
    return bookingDetails.isPostBookingChatAllowed == "1";
  }

  Widget _buildChatButton(BuildContext context) {
    final Color backgroundColor = isBookingCompletedOrCancelled
        ? context.colorScheme.lightGreyColor.withValues(alpha: 0.1)
        : context.colorScheme.accentColor.withValues(alpha: 0.1);

    final Color iconColor = isBookingCompletedOrCancelled
        ? context.colorScheme.lightGreyColor
        : context.colorScheme.accentColor;

    return CustomContainer(
      padding: const EdgeInsetsDirectional.all(12),
      color: backgroundColor,
      borderRadius: UiUtils.borderRadiusOf10,
      child: CustomToolTip(
        toolTipMessage: "chat".translate(context: context),
        child: CustomInkWellContainer(
          borderRadius: BorderRadius.circular(5),
          onTap: () => _navigateToChatMessages(context),
          child: CustomSvgPicture(
            svgImage: AppAssets.drChat,
            color: iconColor,
          ),
        ),
      ),
    );
  }

  void _navigateToChatMessages(BuildContext context) {
    Navigator.pushNamed(context, chatMessages, arguments: {
      "chatUser": ChatUser(
        id: bookingDetails.partnerId ?? "-",
        bookingId: bookingDetails.id.toString(),
        bookingStatus: bookingDetails.status.toString(),
        name: bookingDetails.translatedCompanyName.toString(),
        translatedName: bookingDetails.translatedCompanyName.toString(),
        receiverType: "1",
        providerId: bookingDetails.partnerId ?? "0",
        unReadChats: 0,
        profile: bookingDetails.profileImage,
        senderId: context.read<UserDetailsCubit>().getUserDetails().id ?? "0",
      ),
    });
  }
}
