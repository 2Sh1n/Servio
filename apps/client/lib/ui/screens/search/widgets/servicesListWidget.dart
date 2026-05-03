import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class ServicesListWidget extends StatelessWidget {
  final ScrollController servicesScrollController;
  final VoidCallback onTapRetry;
  final VoidCallback onErrorButtonPressed;

  const ServicesListWidget(
      {super.key,
      required this.servicesScrollController,
      required this.onTapRetry,
      required this.onErrorButtonPressed});

  Widget _getServicesList(BuildContext context,
      {required final List<Providers> providerList,
      required final bool isLoadingMoreData,
      required final bool isLoadingMoreError}) {
    final cartButtonHeight =
        context.read<CartCubit>().getProviderIDFromCartData() == '0' ? 0 : 20;
    return ListView.separated(
      separatorBuilder: (context, index) => const CustomSizedBox(
        height: 10,
      ),
      controller: servicesScrollController,
      padding: EdgeInsetsDirectional.only(
        bottom: UiUtils.getScrollViewBottomPadding(context) + cartButtonHeight,
        end: 15,
        start: 15,
        top: 10,
      ),
      physics: const AlwaysScrollableScrollPhysics(),
      shrinkWrap: true,
      itemCount: providerList.length +
          (isLoadingMoreData || isLoadingMoreError ? 1 : 0),
      itemBuilder: (final BuildContext context, final int index) {
        if (index >= providerList.length) {
          return CustomLoadingMoreContainer(
            isError: isLoadingMoreError,
            onErrorButtonPressed: onErrorButtonPressed,
          );
        }
        return CustomContainer(
          width: context.screenWidth,
          color: context.colorScheme.secondaryColor,
          borderRadius: UiUtils.borderRadiusOf10,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.all(10),
                child: CustomInkWellContainer(
                  onTap: () {
                    Navigator.pushNamed(
                      context,
                      providerRoute,
                      arguments: {"providerId": providerList[index].id},
                    );
                  },
                  child: Row(
                    children: [
                      CustomContainer(
                        borderRadius: UiUtils.borderRadiusOf5,
                        child: ClipRRect(
                          borderRadius:
                              BorderRadius.circular(UiUtils.borderRadiusOf5),
                          child: CustomCachedNetworkImage(
                            networkImageUrl: providerList[index].image ?? '',
                            width: 35,
                            height: 35,
                            fit: BoxFit.fill,
                          ),
                        ),
                      ),
                      const CustomSizedBox(
                        width: 10,
                      ),
                      Expanded(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.start,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            CustomText(
                              providerList[index].translatedPartnerName ?? '',
                              color: context.colorScheme.blackColor,
                              fontWeight: FontWeight.w400,
                              fontStyle: FontStyle.normal,
                              fontSize: 12,
                              textAlign: TextAlign.left,
                              maxLines: 1,
                            ),
                            CustomText(
                              providerList[index].translatedCompanyName ?? '',
                              fontWeight: FontWeight.w500,
                              fontSize: 14,
                              maxLines: 1,
                            )
                          ],
                        ),
                      ),
                      Icon(
                        Icons.keyboard_arrow_right,
                        color: context.colorScheme.lightGreyColor,
                      )
                    ],
                  ),
                ),
              ),
              CustomContainer(
                height: 155,
                child: ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  shrinkWrap: false,
                  scrollDirection: Axis.horizontal,
                  itemCount: providerList[index].services?.length ?? 0,
                  itemBuilder: (context, serviceIndex) {
                    if (providerList[index].services == null) {
                      return Container();
                    }
                    return _buildServiceListItem(
                      context,
                      services: providerList[index].services![serviceIndex],
                      providerId: providerList[index].id!,
                      onTap: () {
                        Navigator.pushNamed(
                          context,
                          providerServiceDetails,
                          arguments: {
                            'serviceDetails':
                                providerList[index].services![serviceIndex],
                            'serviceId': providerList[index]
                                    .services![serviceIndex]
                                    .id ??
                                '',
                            'providerId': providerList[index].id!,
                          },
                        );
                      },
                    );
                  },
                  separatorBuilder: (context, index) =>
                      const CustomSizedBox(width: 10),
                ),
              ),
              const CustomSizedBox(
                height: 10,
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        BlocConsumer<SearchServicesCubit, SearchServicesState>(
          listener: (final BuildContext context, final searchState) {
            if (searchState is SearchServicesFailureState) {
              UiUtils.showMessage(
                  context, searchState.errorMessage, ToastificationType.error);
            }
          },
          builder: (final BuildContext context, final searchState) {
            if (searchState is SearchServicesFailureState) {
              return Center(
                child: ErrorContainer(
                  errorMessage:
                      'somethingWentWrong'.translate(context: context),
                  onTapRetry: onTapRetry,
                  showRetryButton: true,
                ),
              );
            } else if (searchState is SearchServicesSuccessState) {
              if (searchState.providersWithServicesList.isEmpty) {
                return NoDataFoundWidget(
                  titleKey: 'noServicesFound'.translate(context: context),
                  subtitleKey:
                      'noServicesFoundSubTitle'.translate(context: context),
                );
              }
              return _getServicesList(context,
                  isLoadingMoreError: searchState.isLoadingMoreError,
                  providerList: searchState.providersWithServicesList,
                  isLoadingMoreData: searchState.isLoadingMore);
            } else if (searchState is SearchServicesProgressState) {
              return const SingleChildScrollView(
                  child: ProviderListShimmerEffect(
                showTotalProviderContainer: false,
              ));
            }
            return NoDataFoundWidget(
              titleKey: "typeToSearch".translate(context: context),
              showRetryButton: false,
            );
          },
        ),
        const Align(
            alignment: Alignment.bottomCenter,
            child: CartSubDetailsContainer()),
      ],
    );
  }

  Widget _buildServiceListItem(BuildContext context,
      {required Services services,
      required String providerId,
      required VoidCallback onTap}) {
    return CustomContainer(
        width: 290,
        padding: const EdgeInsets.all(8),
        color: context.colorScheme.secondaryColor,
        borderRadius: UiUtils.borderRadiusOf10,
        border: Border.all(
            color: context.colorScheme.lightGreyColor.withValues(alpha: 0.3)),
        child: CustomInkWellContainer(
          onTap: onTap,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.start,
                children: [
                  Expanded(
                    flex: 1,
                    child: CustomImageContainer(
                      height: 90,
                      width: 70,
                      borderRadius: UiUtils.borderRadiusOf5,
                      imageURL: services.imageOfTheService!,
                      boxFit: BoxFit.cover,
                      boxShadow: [],
                    ),
                  ),
                  const CustomSizedBox(
                    width: 10,
                  ),
                  Expanded(
                    flex: 3,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (services.rating! != '' &&
                            services.rating! != '0') ...[
                          Row(
                            children: [
                              const CustomSvgPicture(
                                avoideResponsive: true,
                                svgImage: AppAssets.icStar,
                                color: AppColors.ratingStarColor,
                                height: 20,
                                width: 20,
                              ),
                              const CustomSizedBox(
                                width: 5,
                              ),
                              CustomText(
                                (double.tryParse(services.rating ?? '0') ?? 0)
                                    .toStringAsFixed(1),
                                fontSize: 12,
                              )
                            ],
                          ),
                          const CustomSizedBox(
                            height: 5,
                          ),
                        ],
                        CustomText(
                          services.translatedTitle!,
                          maxLines: 1,
                          fontWeight: FontWeight.w500,
                          fontSize: 16,
                          color: context.colorScheme.blackColor,
                        ),
                        const CustomSizedBox(
                          height: 5,
                        ),
                        CustomText(
                          services.translatedDescription!,
                          maxLines: 2,
                          fontSize: 12,
                          color: context.colorScheme.blackColor,
                        ),
                        const CustomSizedBox(
                          height: 5,
                        )
                      ],
                    ),
                  )
                ],
              ),
              const CustomContainer(
                height: 5,
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
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
                        // crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          const CustomSizedBox(
                            height: 10,
                          ),
                          CustomText(
                            (services.priceWithTax != ''
                                    ? services.priceWithTax!
                                    : '0.0')
                                .priceFormat(context),
                            fontWeight: FontWeight.w700,
                            fontSize: 16,
                            color: context.colorScheme.blackColor,
                          ),
                          if (services.discountedPrice != '0') ...[
                            const CustomSizedBox(
                              width: 5,
                            ),
                            CustomText(
                              (services.originalPriceWithTax != ''
                                      ? services.originalPriceWithTax!
                                      : '0.0')
                                  .priceFormat(context),
                              fontWeight: FontWeight.w500,
                              fontSize: 12,
                              color: context.colorScheme.lightGreyColor,
                              showLineThrough: true,
                              maxLines: 1,
                            ),
                          ]
                        ],
                      ),
                    ],
                  ),
                  MultiBlocProvider(
                    providers: [
                      BlocProvider<AddServiceIntoCartCubit>(
                        create: (final BuildContext context) =>
                            AddServiceIntoCartCubit(CartRepository()),
                      ),
                      BlocProvider<RemoveServiceFromCartCubit>(
                        create: (final BuildContext context) =>
                            RemoveServiceFromCartCubit(CartRepository()),
                      ),
                    ],
                    child: Padding(
                      padding: const EdgeInsetsDirectional.only(bottom: 5),
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
                          backgroundColor:
                              context.colorScheme.accentColor.withAlpha(50),
                          serviceId: services.id!,
                          isProviderAvailableAtLocation: "1",
                          maximumAllowedQuantity:
                              int.parse(services.maxQuantityAllowed!),
                          alreadyAddedQuantity: int.parse(
                            context.read<CartCubit>().getServiceCartQuantity(
                                  serviceId: services.id!,
                                ),
                          ),
                          providerId: providerId,
                          serviceProviderName: services.translatedPartnerName ??
                              services.partnerName,
                        ),
                      ),
                    ),
                  )
                ],
              ),
            ],
          ),
        ));
  }
}
