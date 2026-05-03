import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class ServiceListWidget extends StatelessWidget {
  final List<BookedService> servicesList;
  final bool isBookingCompleted;

  const ServiceListWidget({
    super.key,
    required this.servicesList,
    required this.isBookingCompleted,
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
          _buildServicesList(context),
        ],
      ),
    );
  }

  Widget _buildSectionHeading(BuildContext context) {
    return CustomText(
      "services".translate(context: context),
      maxLines: 1,
      fontWeight: FontWeight.w400,
      fontSize: 14,
      color: context.colorScheme.lightGreyColor,
    );
  }

  Widget _buildServicesList(BuildContext context) {
    return ListView.separated(
      physics: const NeverScrollableScrollPhysics(),
      separatorBuilder: (context, index) => _buildServiceSeparator(),
      itemCount: servicesList.length,
      shrinkWrap: true,
      itemBuilder: (context, index) => ServiceItem(
        service: servicesList[index],
        index: index,
        isBookingCompleted: isBookingCompleted,
      ),
    );
  }

  Widget _buildServiceSeparator() {
    return Column(
      children: [
        const CustomSizedBox(height: 12),
        LinearDivider(),
        const CustomSizedBox(height: 12),
      ],
    );
  }
}

class ServiceItem extends StatefulWidget {
  final BookedService service;
  final int index;
  final bool isBookingCompleted;

  const ServiceItem({
    super.key,
    required this.service,
    required this.index,
    required this.isBookingCompleted,
  });

  @override
  State<ServiceItem> createState() => _ServiceItemState();
}

class _ServiceItemState extends State<ServiceItem> {
  bool collapsed = true;

  bool get shouldShowReviewDetails => widget.service.rating != "0";

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _buildServiceMainRow(),
        if (shouldShowReviewDetails) _buildServiceReviewDetails(),
      ],
    );
  }

  Widget _buildServiceMainRow() {
    return Row(
      children: [
        _buildServiceImage(),
        const CustomSizedBox(width: 12),
        _buildServiceDetails(),
      ],
    );
  }

  Widget _buildServiceImage() {
    return CustomContainer(
      borderRadius: UiUtils.borderRadiusOf5,
      width: 62,
      height: 68,
      border: Border.all(color: context.colorScheme.lightGreyColor, width: 0.5),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf5),
        child: CustomCachedNetworkImage(
          networkImageUrl: widget.service.image ?? '',
          height: 68,
          width: 62,
          fit: BoxFit.fill,
        ),
      ),
    );
  }

  Widget _buildServiceDetails() {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomText(
            '${widget.service.translatedTitle} ',
            fontWeight: FontWeight.w500,
            color: context.colorScheme.blackColor,
            fontSize: 16,
            maxLines: 2,
          ),
          const CustomSizedBox(height: 8),
          _buildServicePriceAndRatingRow(),
        ],
      ),
    );
  }

  Widget _buildServicePriceAndRatingRow() {
    return Row(
      children: [
        _buildServicePriceSection(),
        const Spacer(),
        ServiceRatingWidget(
          service: widget.service,
          index: widget.index,
          isBookingCompleted: widget.isBookingCompleted,
          onTap: _handleRatingTap,
          isCollapsed: collapsed,
          reBuilder: () {
            setState(() {});
          },
        ),
      ],
    );
  }

  Widget _buildServicePriceSection() {
    return Row(
      children: [
        CustomText(
          widget.service.priceWithTax!.toString().priceFormat(context),
          color: context.colorScheme.accentColor,
          fontWeight: FontWeight.w700,
          fontSize: 16,
        ),
        const CustomSizedBox(width: 12),
        CustomText(
          "x${widget.service.quantity}".translate(context: context),
          fontWeight: FontWeight.w400,
          fontSize: 12,
          color: context.colorScheme.blackColor,
        ),
      ],
    );
  }

  void _handleRatingTap() {
    final bool hasReviewContent = (widget.service.comment ?? '').isNotEmpty ||
        (widget.service.reviewImages ?? []).isNotEmpty;
    if (!hasReviewContent) return;

    setState(() {
      collapsed = !collapsed;
    });
  }

  Widget _buildServiceReviewDetails() {
    return AnimatedSize(
      duration: const Duration(milliseconds: 300),
      child: CustomContainer(
        constraints: collapsed
            ? const BoxConstraints(maxHeight: 0.0)
            : const BoxConstraints(
                maxHeight: double.infinity,
                maxWidth: double.maxFinite,
              ),
        child: _buildReviewDetailsContent(),
      ),
    );
  }

  Widget _buildReviewDetailsContent() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const CustomSizedBox(height: 10),
        _buildReviewComment(),
        const CustomSizedBox(height: 10),
        if (_hasReviewImages()) _buildReviewImagesSection(),
      ],
    );
  }

  bool _hasReviewImages() {
    return widget.service.reviewImages?.isNotEmpty ?? false;
  }

  Widget _buildReviewComment() {
    return CustomText(
      widget.service.comment ?? '',
      fontSize: 14,
      fontWeight: FontWeight.w400,
      color: context.colorScheme.lightGreyColor,
    );
  }

  Widget _buildReviewImagesSection() {
    return CustomSizedBox(
      height: 44,
      child: ListView.separated(
        physics: const NeverScrollableScrollPhysics(),
        shrinkWrap: true,
        scrollDirection: Axis.horizontal,
        itemCount: widget.service.reviewImages?.length ?? 0,
        separatorBuilder: (context, index) => const CustomSizedBox(width: 12),
        itemBuilder: (context, index) => ReviewImageItem(
          service: widget.service,
          imageIndex: index,
        ),
      ),
    );
  }
}

class ReviewImageItem extends StatelessWidget {
  final BookedService service;
  final int imageIndex;

  const ReviewImageItem({
    super.key,
    required this.service,
    required this.imageIndex,
  });

  @override
  Widget build(BuildContext context) {
    return CustomInkWellContainer(
      onTap: () => _navigateToImagePreview(context),
      child: CustomContainer(
        height: 44,
        width: 44,
        borderRadius: UiUtils.borderRadiusOf5,
        border:
            Border.all(color: context.colorScheme.lightGreyColor, width: 0.5),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf5),
          child: CustomCachedNetworkImage(
            networkImageUrl: service.reviewImages?[imageIndex] ?? '',
            height: 44,
            width: 44,
            fit: BoxFit.fill,
          ),
        ),
      ),
    );
  }

  void _navigateToImagePreview(BuildContext context) {
    Navigator.pushNamed(
      context,
      imagePreview,
      arguments: {
        "startFrom": imageIndex,
        "isReviewType": true,
        "dataURL": service.reviewImages,
        "reviewDetails": Reviews(
          id: service.id ?? '',
          rating: service.rating ?? '',
          profileImage: HiveRepository.getUserProfilePictureURL ?? '',
          userName: HiveRepository.getUsername ?? '',
          serviceId: service.serviceId ?? '',
          comment: service.comment ?? '',
          images: service.reviewImages ?? [],
          ratedOn: '',
        ),
      },
    );
  }
}

class ServiceRatingWidget extends StatelessWidget {
  final BookedService service;
  final int index;
  final bool isBookingCompleted;
  final VoidCallback onTap;
  final bool isCollapsed;
  final VoidCallback reBuilder;

  const ServiceRatingWidget({
    super.key,
    required this.service,
    required this.index,
    required this.isBookingCompleted,
    required this.onTap,
    required this.isCollapsed,
    required this.reBuilder,
  });

  @override
  Widget build(BuildContext context) {
    final int serviceRating = int.parse(
      (service.rating ?? 0).toString().isEmpty ? '0' : service.rating ?? '0',
    );

    final bool hasRating = serviceRating != 0;
    final bool canRate = isBookingCompleted && serviceRating == 0;
    final bool canEdit = isBookingCompleted && hasRating;

    if (canRate) {
      return _buildRatingButton(context);
    } else if (canEdit) {
      return _buildRatingDisplay(context, serviceRating);
    } else {
      return const SizedBox.shrink();
    }
  }

  Widget _buildRatingButton(BuildContext context) {
    return CustomInkWellContainer(
      onTap: () => _showRatingBottomSheet(context, "rate"),
      child: CustomText(
        "rate".translate(context: context),
        fontWeight: FontWeight.w500,
        fontSize: 14,
        color: context.colorScheme.accentColor,
      ),
    );
  }

  Widget _buildRatingDisplay(BuildContext context, int serviceRating) {
    final bool hasReviewContent = (service.comment ?? '').isNotEmpty ||
        (service.reviewImages ?? []).isNotEmpty;

    return CustomInkWellContainer(
      showRippleEffect: false,
      onTap: onTap,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildStarIcon(context),
          const CustomSizedBox(width: 6),
          _buildRatingText(context, serviceRating),
          if (hasReviewContent) _buildCollapseIcon(context),
          const CustomSizedBox(width: 6),
          _buildDivider(context),
          const CustomSizedBox(width: 6),
          _buildEditButton(context),
        ],
      ),
    );
  }

  Widget _buildStarIcon(BuildContext context) {
    return CustomSvgPicture(
      avoideResponsive: true,
      svgImage: AppAssets.icStar,
      height: 16,
      width: 16,
      color: context.colorScheme.accentColor,
    );
  }

  Widget _buildRatingText(BuildContext context, int serviceRating) {
    return CustomText(
      serviceRating.toString(),
      fontSize: 14,
      fontWeight: FontWeight.w500,
      color: context.colorScheme.accentColor,
    );
  }

  Widget _buildCollapseIcon(BuildContext context) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 300),
      child: isCollapsed
          ? Icon(Icons.arrow_drop_down_sharp,
              color: context.colorScheme.accentColor, size: 12)
          : Icon(Icons.arrow_drop_up_sharp,
              color: context.colorScheme.accentColor, size: 12),
    );
  }

  Widget _buildDivider(BuildContext context) {
    return CustomSizedBox(
      height: 12,
      child: VerticalDivider(
        color: context.colorScheme.lightGreyColor,
        thickness: 0.5,
        width: 0.5,
      ),
    );
  }

  Widget _buildEditButton(BuildContext context) {
    return CustomInkWellContainer(
      onTap: () => _showRatingBottomSheet(context, "edit"),
      child: CustomText(
        "edit".translate(context: context),
        fontWeight: FontWeight.w500,
        fontSize: 14,
        color: context.colorScheme.accentColor,
      ),
    );
  }

  void _showRatingBottomSheet(BuildContext context, String title) {
    UiUtils.showBottomSheet(
      enableDrag: true,
      context: context,
      child: BlocProvider<SubmitReviewCubit>(
        create: (final BuildContext context) =>
            SubmitReviewCubit(bookingRepository: BookingRepository()),
        child: RatingBottomSheet(
          reviewComment: service.comment ?? '',
          ratingStar: int.tryParse(service.rating ?? '0') ?? 0,
          serviceID: service.serviceId ?? '',
          serviceName: service.translatedTitle ?? '',
          serviceReviewImages: service.reviewImages ?? [],
          customJobRequestId: service.customJobRequestId,
        ),
      ),
    ).then((value) {
      if (value != null) {
        service.comment = value["comment"];
        service.rating = value["rating"];
        service.reviewImages = (value["images"]?.isNotEmpty ?? false)
            ? (value["images"] as List).cast<String>()
            : [];
        reBuilder();
      }
    });
  }
}
