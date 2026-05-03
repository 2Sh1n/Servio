import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/dialog/layouts/replaceCartDialog.dart';
import 'package:flutter/material.dart';

class AddButton extends StatelessWidget {
  const AddButton(
      {required this.alreadyAddedQuantity,
      required this.serviceId,
      required this.maximumAllowedQuantity,
      final Key? key,
      this.height,
      this.width,
      this.isProviderAvailableAtLocation,
      this.addButtonWithIcon,
      this.padding,
      this.backgroundColor,
      this.serviceProviderName,
      required this.providerId})
      : super(key: key);

  final String serviceId;
  final String providerId;
  final String? isProviderAvailableAtLocation;
  final int maximumAllowedQuantity;
  final int alreadyAddedQuantity;
  final double? height;
  final double? width;
  final bool? addButtonWithIcon;
  final Color? backgroundColor;
  final double? padding;
  final String? serviceProviderName;

  @override
  Widget build(final BuildContext context) {
    return BlocConsumer<AddServiceIntoCartCubit, AddServiceIntoCartState>(
      listener: (final BuildContext context,
          final AddServiceIntoCartState addServiceIntoCartState) {
        if (addServiceIntoCartState is AddServiceIntoCartFailure) {
          UiUtils.showMessage(context, addServiceIntoCartState.errorMessage,
              ToastificationType.error);
        }
        if (addServiceIntoCartState is AddServiceIntoCartProviderMismatch) {
          UiUtils.showAnimatedDialog(
            context: context,
            child: ReplaceCartDialog(
              currentProviderName: addServiceIntoCartState.currentProviderName,
              newProviderName: addServiceIntoCartState.newProviderName,
              confirmCallback: () async {
                await context
                    .read<AddServiceIntoCartCubit>()
                    .addServiceIntoCart(
                      serviceId: addServiceIntoCartState.serviceId,
                      quantity: addServiceIntoCartState.quantity,
                      serviceProviderId: null,
                      serviceProviderName: null,
                      cartCubit: null,
                    );
              },
            ),
          );
        }
        if (addServiceIntoCartState is AddServiceIntoCartSuccess) {
          if (addServiceIntoCartState.error == false) {
            context
                .read<CartCubit>()
                .updateCartDetails(addServiceIntoCartState.cartDetails);
          } else {
            UiUtils.showMessage(context, addServiceIntoCartState.successMessage,
                ToastificationType.error);
          }
        }
      },
      builder: (final BuildContext context,
              final AddServiceIntoCartState addServiceIntoCartState) =>
          BlocConsumer<RemoveServiceFromCartCubit, RemoveServiceFromCartState>(
        listener:
            (context, RemoveServiceFromCartState removeServiceFromCartState) {
          if (removeServiceFromCartState is RemoveServiceFromCartFailure) {
            UiUtils.showMessage(
              context,
              removeServiceFromCartState.errorMessage,
              ToastificationType.error,
            );
          }
          if (removeServiceFromCartState is RemoveServiceFromCartSuccess) {
            if (removeServiceFromCartState.error == false) {
              context
                  .read<CartCubit>()
                  .updateCartDetails(removeServiceFromCartState.cartDetails!);
            }
          }
        },
        builder: (final BuildContext context,
            RemoveServiceFromCartState removeServiceFromCartState) {
          return alreadyAddedQuantity <= 0
              ? Stack(
                  alignment: Alignment.center,
                  children: [
                    CustomInkWellContainer(
                        onTap: () async {
                          if (isProviderAvailableAtLocation == "0") {
                            UiUtils.showMessage(
                                context,
                                'currentlyNotAvailableAtSelectedAddress'
                                    .translate(context: context),
                                ToastificationType.warning);
                            return;
                          }
                          final authStatus =
                              context.read<AuthenticationCubit>().state;
                          if (authStatus is UnAuthenticatedState) {
                            await UiUtils.showAnimatedDialog(
                                context: context,
                                child: const LogInAccountDialog());

                            return;
                          }
                          if (alreadyAddedQuantity < maximumAllowedQuantity) {
                            await context
                                .read<AddServiceIntoCartCubit>()
                                .addServiceIntoCart(
                                  serviceId: int.parse(serviceId),
                                  quantity: 1,
                                  serviceProviderId: providerId,
                                  serviceProviderName: serviceProviderName,
                                  cartCubit: context.read<CartCubit>(),
                                );
                          } else {
                            UiUtils.showMessage(
                              context,
                              'maximumLimitReached'.translate(context: context),
                              ToastificationType.warning,
                            );
                          }
                        },
                        child: addButtonWithIcon == true
                            ? Center(
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  crossAxisAlignment: CrossAxisAlignment.center,
                                  children: [
                                    CustomText(
                                      'add'.translate(context: context),
                                      textAlign: TextAlign.left,
                                      color: isProviderAvailableAtLocation ==
                                              "0"
                                          ? context.colorScheme.lightGreyColor
                                          : context.colorScheme.accentColor,
                                      fontStyle: FontStyle.normal,
                                      fontSize: 14,
                                    ),
                                    const CustomSizedBox(
                                      width: 10,
                                    ),
                                    Icon(
                                      Icons.add_circle_rounded,
                                      color: context.colorScheme.accentColor,
                                      size: 20,
                                    ),
                                  ],
                                ),
                              )
                            : CustomContainer(
                                borderRadius: UiUtils.borderRadiusOf6,
                                color: backgroundColor,
                                height: height ?? 35,
                                width: width ?? 80,
                                child: Center(
                                  child: CustomText(
                                    'add'.translate(context: context),
                                    textAlign: TextAlign.center,
                                    color: isProviderAvailableAtLocation == "0"
                                        ? context.colorScheme.lightGreyColor
                                        : context.colorScheme.accentColor,
                                    fontStyle: FontStyle.normal,
                                    fontSize: 14,
                                  ),
                                ),
                              )),
                    if (addServiceIntoCartState
                            is AddServiceIntoCartInProgress ||
                        removeServiceFromCartState
                            is RemoveServiceFromCartInProgress)
                      CustomShimmerLoadingContainer(
                        borderRadius: UiUtils.borderRadiusOf5,
                        height: height ?? 35,
                        width: width ?? 80,
                      ),
                  ],
                )
              : Stack(
                  alignment: Alignment.center,
                  children: [
                    CustomContainer(
                      height: height ?? 35,
                      width: width ?? 80,
                      color: backgroundColor,
                      borderRadius: UiUtils.borderRadiusOf6,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: [
                          CustomInkWellContainer(
                            onTap: () {
                              final authStatus =
                                  context.read<AuthenticationCubit>().state;
                              if (authStatus is UnAuthenticatedState) {
                                UiUtils.showAnimatedDialog(
                                    context: context,
                                    child: const LogInAccountDialog());
                                return;
                              }

                              if (alreadyAddedQuantity > 1) {
                                // Add service into cart
                                context
                                    .read<AddServiceIntoCartCubit>()
                                    .addServiceIntoCart(
                                      serviceId: int.parse(serviceId),
                                      quantity: alreadyAddedQuantity - 1,
                                      serviceProviderId: providerId,
                                      serviceProviderName: serviceProviderName,
                                      cartCubit: context.read<CartCubit>(),
                                    );
                              } else {
                                // remove service from cart
                                context
                                    .read<RemoveServiceFromCartCubit>()
                                    .removeServiceFromCart(
                                      serviceId: int.parse(serviceId),
                                    );
                              }
                            },
                            child: alreadyAddedQuantity == 1
                                ? Icon(
                                    Icons.delete_outline,
                                    size: 18,
                                    color: context.colorScheme.accentColor,
                                  )
                                : Icon(
                                    Icons.remove_circle_outline,
                                    size: 18,
                                    color: context.colorScheme.accentColor,
                                  ),
                          ),
                          CustomText(
                            alreadyAddedQuantity.toString(),
                            color: context.colorScheme.accentColor,
                            fontSize: 14,
                          ),
                          CustomInkWellContainer(
                            onTap: () {
                              if (alreadyAddedQuantity <
                                  maximumAllowedQuantity) {
                                context
                                    .read<AddServiceIntoCartCubit>()
                                    .addServiceIntoCart(
                                      serviceId: int.parse(serviceId),
                                      quantity: alreadyAddedQuantity + 1,
                                      serviceProviderId: providerId,
                                      serviceProviderName: serviceProviderName,
                                      cartCubit: context.read<CartCubit>(),
                                    );
                              } else {
                                UiUtils.showMessage(
                                  context,
                                  'maximumLimitReached'
                                      .translate(context: context),
                                  ToastificationType.warning,
                                );
                              }
                            },
                            child: Icon(
                              Icons.add_circle_outline,
                              size: 18,
                              color: context.colorScheme.accentColor,
                            ),
                          )
                        ],
                      ),
                    ),
                    if (addServiceIntoCartState
                            is AddServiceIntoCartInProgress ||
                        removeServiceFromCartState
                            is RemoveServiceFromCartInProgress)
                      CustomShimmerLoadingContainer(
                        borderRadius: UiUtils.borderRadiusOf5,
                        height: height ?? 35,
                        width: width ?? 80,
                      ),
                  ],
                );
        },
      ),
    );
  }
}
