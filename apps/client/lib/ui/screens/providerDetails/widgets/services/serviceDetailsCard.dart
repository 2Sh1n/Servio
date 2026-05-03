import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:e_demand/ui/widgets/bannerPainter.dart' as BannerPainter;

class ServiceDetailsCard extends StatelessWidget {
  const ServiceDetailsCard({
    required this.services,
    final Key? key,
    this.onTap,
    this.showDescription,
    this.showAddButton,
    this.isProviderAvailableAtLocation,
    required this.providerId,
  }) : super(key: key);
  final Services services;
  final String providerId;
  final String? isProviderAvailableAtLocation;
  final VoidCallback? onTap;
  final bool? showDescription;
  final bool? showAddButton;

  @override
  Widget build(final BuildContext context) {
    final saveAmount = (services.originalPriceWithTax != ''
            ? double.parse(services.originalPriceWithTax!)
            : 0.0) -
        (services.priceWithTax != ''
            ? double.parse(services.priceWithTax!)
            : 0.0);
    return CustomInkWellContainer(
      onTap: onTap,
      child: CustomContainer(
        border: Border.all(color: context.colorScheme.accentColor, width: 0.5),
        margin: const EdgeInsets.symmetric(vertical: 7),
        color: context.colorScheme.secondaryColor,
        borderRadius: UiUtils.borderRadiusOf10,
        gradient: LinearGradient(
          colors: [
            context.colorScheme.accentColor.withAlpha(10),
            context.colorScheme.accentColor.withAlpha(5),
            context.colorScheme.accentColor.withAlpha(1),
            context.colorScheme.accentColor.withAlpha(0),
            context.colorScheme.secondaryColor.withAlpha(0),
            context.colorScheme.secondaryColor,
          ],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
          stops: const [0.0, 0.5, 0.6, 0.7, 0.8, 0.9],
        ),
        height: 175,
        child: Stack(
          alignment: Alignment.topCenter,
          children: [
            if (saveAmount > 0)
              PositionedDirectional(
                end: 118.rw(context),
                child: Container(
                  alignment: Alignment.topCenter,
                  height: 30,
                  width: 110.rw(context),
                  child: CustomPaint(
                    size: Size(
                        110.rw(context), 30), // Width and height of the banner
                    foregroundPainter: BannerPainter.BannerPainter(),
                    child: CustomContainer(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      alignment: Alignment.center,
                      child: MarqueeWidget(
                        key: ValueKey(services.id),
                        child: CustomText(
                          "${'save'.translate(context: context)} ${saveAmount.toString().priceFormat(context)}",
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: context.colorScheme.accentColor,
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.max,
                    children: [
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsetsDirectional.all(8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                            children: [
                              double.parse(services.rating!)
                                          .toStringAsFixed(1) !=
                                      '0.0'
                                  ? Row(
                                      children: [
                                        const CustomSvgPicture(
                                          avoideResponsive: true,
                                          svgImage: AppAssets.icStar,
                                          color: AppColors.ratingStarColor,
                                          height: 20,
                                          width: 20,
                                        ),
                                        CustomText(
                                          ' ${double.parse(services.rating!).toStringAsFixed(1)}',
                                          color: Theme.of(context)
                                              .colorScheme
                                              .lightGreyColor,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ],
                                    )
                                  : const CustomSizedBox(
                                      height: 20,
                                    ),
                              const CustomSizedBox(
                                height: 2,
                              ),
                              CustomText(
                                services.translatedTitle!,
                                maxLines: 2,
                                fontWeight: FontWeight.w600,
                                fontSize: 16,
                                color: context.colorScheme.blackColor,
                              ),
                              if (showDescription ?? true)
                                CustomText(
                                  '${services.translatedDescription}',
                                  maxLines: 2,
                                  fontWeight: FontWeight.w400,
                                  fontSize: 11,
                                  color: context.colorScheme.lightGreyColor,
                                ),
                              Row(
                                children: [
                                  CustomSvgPicture(
                                    svgImage: AppAssets.icGroup,
                                    height: 14,
                                    width: 14,
                                    color: context.colorScheme.accentColor,
                                  ),
                                  CustomText(
                                    " ${services.numberOfMembersRequired} ${"person".translate(context: context)} ",
                                    fontSize: 12,
                                    color: context.colorScheme.blackColor,
                                    fontWeight: FontWeight.w600,
                                    maxLines: 1,
                                  ),
                                  CustomSvgPicture(
                                    avoideResponsive: true,
                                    svgImage: AppAssets.icClock,
                                    height: 14,
                                    width: 14,
                                    color: context.colorScheme.accentColor,
                                  ),
                                  CustomText(
                                    " ${services.duration} ${"minutes".translate(context: context)}",
                                    fontSize: 12,
                                    color: context.colorScheme.blackColor,
                                    fontWeight: FontWeight.w600,
                                    maxLines: 1,
                                  ),
                                ],
                              ),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  CustomText(
                                    (services.priceWithTax != ''
                                            ? services.priceWithTax!
                                            : '0.0')
                                        .priceFormat(context),
                                    fontWeight: FontWeight.w700,
                                    fontSize: 16,
                                    color: context.colorScheme.blackColor,
                                  ),
                                  if (services.discountedPrice != '0')
                                    const CustomSizedBox(
                                      width: 5,
                                    ),
                                  if (services.discountedPrice != '0')
                                    Expanded(
                                      child: CustomText(
                                        (services.originalPriceWithTax != ''
                                                ? services.originalPriceWithTax!
                                                : '0.0')
                                            .priceFormat(context),
                                        fontWeight: FontWeight.w500,
                                        fontSize: 12,
                                        underlineOrLineColor:
                                            context.colorScheme.blackColor,
                                        color: context.colorScheme.accentColor,
                                        showLineThrough: true,
                                        maxLines: 1,
                                      ),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const CustomSizedBox(
                  width: 8,
                ),
                CustomContainer(
                  border: Border.all(
                      color: context.colorScheme.lightGreyColor, width: 0.2),
                  borderRadius: UiUtils.borderRadiusOf10,
                  margin: const EdgeInsetsDirectional.all(5),
                  height: 125,
                  width: 110,
                  child: Stack(
                    children: [
                      CustomImageContainer(
                        height: 125,
                        width: 110,
                        borderRadiusStyle: const BorderRadius.only(
                          topLeft: Radius.circular(UiUtils.borderRadiusOf8),
                          topRight: Radius.circular(UiUtils.borderRadiusOf8),
                        ),
                        imageURL: services.imageOfTheService!,
                        boxFit: BoxFit.cover,
                      ),
                      if (showAddButton ?? true)
                        Align(
                          alignment: Alignment.bottomCenter,
                          child: CustomContainer(
                              height: 40,
                              width: double.infinity,
                              color: context.colorScheme.secondaryColor,
                              borderRadiusStyle: const BorderRadius.vertical(
                                bottom:
                                    Radius.circular(UiUtils.borderRadiusOf10),
                              ),
                              child: Align(
                                alignment: AlignmentDirectional.center,
                                child: MultiBlocProvider(
                                  providers: [
                                    BlocProvider<AddServiceIntoCartCubit>(
                                      create: (final BuildContext context) =>
                                          AddServiceIntoCartCubit(
                                              CartRepository()),
                                    ),
                                    BlocProvider<RemoveServiceFromCartCubit>(
                                      create: (final BuildContext context) =>
                                          RemoveServiceFromCartCubit(
                                              CartRepository()),
                                    ),
                                  ],
                                  child: BlocConsumer<CartCubit, CartState>(
                                    listener: (final BuildContext context,
                                        final CartState state) {
                                      if (state is CartFetchSuccess) {
                                        try {
                                          UiUtils.getVibrationEffect();
                                        } catch (_) {}
                                      }
                                    },
                                    builder: (final BuildContext context,
                                            final CartState state) =>
                                        AddButton(
                                      height: 40,
                                      width: double.infinity,
                                      serviceId: services.id!,
                                      providerId: providerId,
                                      isProviderAvailableAtLocation:
                                          isProviderAvailableAtLocation,
                                      maximumAllowedQuantity: int.parse(
                                          services.maxQuantityAllowed!),
                                      alreadyAddedQuantity:
                                          isProviderAvailableAtLocation == "0"
                                              ? 0
                                              : int.parse(
                                                  context
                                                      .read<CartCubit>()
                                                      .getServiceCartQuantity(
                                                        serviceId: services.id!,
                                                      ),
                                                ),
                                      serviceProviderName:
                                          services.translatedPartnerName ??
                                              services.partnerName,
                                    ),
                                  ),
                                ),
                              )),
                        )
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
