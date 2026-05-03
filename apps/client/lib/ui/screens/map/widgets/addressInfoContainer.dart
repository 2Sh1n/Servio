import 'package:flutter/material.dart';
import '../../../../app/generalImports.dart';
import '../../../../cubits/fetchUserCurrentLocationCubit.dart';

class AddressInfoContainer extends StatelessWidget {
  final ValueNotifier<Map> selectedAddress;
  final double? selectedLatitude;
  final double? selectedLongitude;
  final bool showAddressForm;
  final VoidCallback onCompleteAddress;
  final VoidCallback onConfirmAddress;

  const AddressInfoContainer({
    Key? key,
    required this.selectedAddress,
    required this.selectedLatitude,
    required this.selectedLongitude,
    required this.showAddressForm,
    required this.onCompleteAddress,
    required this.onConfirmAddress,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) => BlocBuilder<
          CheckProviderAvailabilityCubit, CheckProviderAvailabilityState>(
        builder: (context, checkProviderAvailabilityState) {
          if (checkProviderAvailabilityState
              is CheckProviderAvailabilityFetchSuccess) {
            return BlocBuilder<AddAddressCubit, AddAddressState>(
              builder: (context, state) => CustomContainer(
                height: checkProviderAvailabilityState.error ? 80 : 150,
                width: context.screenWidth,
                margin: const EdgeInsets.all(10),
                color: context.colorScheme.secondaryColor,
                borderRadius: UiUtils.borderRadiusOf20,
                child: ValueListenableBuilder<Map>(
                  valueListenable: selectedAddress,
                  builder: (context, value, child) {
                    return Column(children: [
                      Expanded(
                          child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CustomContainer(
                              borderRadius: UiUtils.borderRadiusOf10,
                              color:
                                  context.colorScheme.accentColor.withAlpha(20),
                              padding: const EdgeInsets.all(10),
                              child: CustomSvgPicture(
                                svgImage: AppAssets.locationMark,
                                height: 20,
                                width: 20,
                                color: context.colorScheme.accentColor,
                              ),
                            ),
                            const CustomSizedBox(width: 10),
                            Expanded(
                                child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (checkProviderAvailabilityState.error) ...[
                                  CustomText(
                                    "serviceNotAvailableAtSelectedLocation"
                                        .translate(context: context),
                                    maxLines: 2,
                                    fontSize: 14,
                                    fontWeight: FontWeight.normal,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .blackColor,
                                  )
                                ] else ...[
                                  CustomText(
                                    value["lineOneAddress"] ?? '',
                                    maxLines: 1,
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .blackColor,
                                  ),
                                  const CustomSizedBox(height: 5),
                                  CustomText(
                                    value["lineTwoAddress"],
                                    maxLines: 1,
                                    fontSize: 14,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .lightGreyColor,
                                  ),
                                ],
                              ],
                            )),
                          ],
                        ),
                      )),
                      if (!checkProviderAvailabilityState.error)
                        Padding(
                          padding: const EdgeInsetsDirectional.only(
                              start: 16, end: 16, bottom: 12),
                          child: CustomRoundedButton(
                            onTap: () {
                              if (context
                                      .read<FetchUserCurrentLocationCubit>()
                                      .state
                                  is FetchUserCurrentLocationInProgress) {
                                return;
                              }
                              if (showAddressForm) {
                                onCompleteAddress();
                              } else {
                                onConfirmAddress();
                              }
                            },
                            widthPercentage: 1,
                            backgroundColor: (context
                                        .watch<FetchUserCurrentLocationCubit>()
                                        .state
                                    is FetchUserCurrentLocationInProgress)
                                ? context.colorScheme.lightGreyColor
                                : context.colorScheme.accentColor,
                            buttonTitle: (showAddressForm
                                    ? 'completeAddress'
                                    : "confirmAddress")
                                .translate(context: context),
                            showBorder: false,
                          ),
                        ),
                    ]);
                  },
                ),
              ),
            );
          }
          return CustomContainer(
            height: 150,
            width: context.screenWidth,
            color: context.colorScheme.secondaryColor,
            boxShadow: [
              BoxShadow(
                offset: const Offset(0, -5),
                blurRadius: 4,
                color: context.colorScheme.blackColor.withValues(alpha: 0.2),
              )
            ],
            borderRadiusStyle: const BorderRadius.only(
              topLeft: Radius.circular(UiUtils.borderRadiusOf20),
              topRight: Radius.circular(UiUtils.borderRadiusOf20),
            ),
            child: Center(
              child: (checkProviderAvailabilityState
                      is CheckProviderAvailabilityFetchFailure)
                  ? CustomText("somethingWentWrong".translate(context: context))
                  : Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        CustomShimmerLoadingContainer(
                          height: 10,
                          width: context.screenWidth * 0.9,
                          borderRadius: UiUtils.borderRadiusOf10,
                        ),
                        const CustomSizedBox(height: 10),
                        CustomShimmerLoadingContainer(
                          height: 10,
                          width: context.screenWidth * 0.9,
                          borderRadius: UiUtils.borderRadiusOf10,
                        ),
                        const CustomSizedBox(height: 10),
                        CustomShimmerLoadingContainer(
                          height: 10,
                          width: context.screenWidth * 0.9,
                          borderRadius: UiUtils.borderRadiusOf10,
                        ),
                      ],
                    ),
            ),
          );
        },
      );
}
