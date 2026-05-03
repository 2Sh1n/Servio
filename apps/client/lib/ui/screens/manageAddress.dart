import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class ManageAddress extends StatefulWidget {
  const ManageAddress({required this.appBarTitle, final Key? key})
      : super(key: key);
  final String appBarTitle;

  @override
  State<ManageAddress> createState() => _ManageAddressState();

  static Route<ManageAddress> route(final RouteSettings routeSettings) {
    final arguments = routeSettings.arguments as Map<String, String>;
    return CupertinoPageRoute(
      builder: (final _) =>
          ManageAddress(appBarTitle: arguments["appBarTitle"]!),
    );
  }
}

class _ManageAddressState extends State<ManageAddress> {
  Widget buildAddressSelector() => MultiBlocListener(
        listeners: [
          BlocListener<AddAddressCubit, AddAddressState>(
            listener:
                (final BuildContext context, AddAddressState addAddressState) {
              if (addAddressState is AddAddressSuccess) {
                final GetAddressModel getAddressModel =
                    GetAddressModel.fromJson(addAddressState.result["data"][0]);
                context.read<AddressesCubit>().addAddress(getAddressModel);
              }
            },
          ),
          BlocListener<DeleteAddressCubit, DeleteAddressState>(
            listener:
                (final BuildContext context, final DeleteAddressState state) {
              if (state is DeleteAddressSuccess) {
                context.read<AddressesCubit>().removeAddress(state.id);
              }
            },
          ),
        ],
        child: BlocConsumer<GetAddressCubit, GetAddressState>(
          listener: (final BuildContext context,
              final GetAddressState getAddressState) {
            if (getAddressState is GetAddressSuccess) {
              context.read<AddressesCubit>().load(getAddressState.data);
            }
          },
          builder: (final BuildContext context,
              final GetAddressState getAddressState) {
            if (getAddressState is GetAddressFail) {
              return ErrorContainer(
                errorMessage: getAddressState.error.translate(context: context),
                showRetryButton: true,
                onTapRetry: () {
                  context.read<GetAddressCubit>().fetchAddress();
                },
              );
            }

            if (getAddressState is GetAddressSuccess) {
              return BlocBuilder<AddressesCubit, AddressesState>(
                builder: (final context, final AddressesState addressesState) {
                  if (addressesState is Addresses) {
                    if (addressesState.addresses.isEmpty) {
                      return Center(
                        child: NoDataFoundWidget(
                          titleKey:
                              "noAddressFound".translate(context: context),
                          subtitleKey: 'noAddressFoundSubTitle'
                              .translate(context: context),
                        ),
                      );
                    }
                    return SingleChildScrollView(
                      child: Padding(
                        padding: const EdgeInsetsDirectional.only(
                            top: 8.0, bottom: 70),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children:
                              List.generate(addressesState.addresses.length,
                                  (final int index) {
                            late GetAddressModel addressData;
                            if (getAddressState.data.isNotEmpty) {
                              addressData = addressesState.addresses[index];
                            }

                            return CustomContainer(
                              margin: const EdgeInsets.symmetric(
                                  horizontal: 15, vertical: 5),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 20, vertical: 10),
                              color: context.colorScheme.secondaryColor,
                              borderRadius: UiUtils.borderRadiusOf10,
                              child: Column(
                                children: [
                                  Row(
                                    children: [
                                      CustomContainer(
                                        child: CustomSvgPicture(
                                          svgImage: addressData.type == 'home'
                                              ? AppAssets.homeAdd
                                              : addressData.type == 'Other'
                                                  ? AppAssets.locationMark
                                                  : AppAssets.officeAdd,
                                          color:
                                              context.colorScheme.accentColor,
                                        ),
                                      ),
                                      const CustomSizedBox(
                                        width: 10,
                                      ),
                                      Expanded(
                                        child: CustomText(
                                          addressData.type
                                              .toString()
                                              .toLowerCase()
                                              .translate(context: context),
                                          fontWeight: FontWeight.bold,
                                          color: context.colorScheme.blackColor,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const CustomSizedBox(
                                    height: 5,
                                  ),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            CustomText(
                                              filterAddressString(
                                                  '${addressData.address},${addressData.area},${addressData.state}, ${addressData.country}'),
                                              fontSize: 12,
                                              color: context
                                                  .colorScheme.blackColor,
                                              maxLines: 3,
                                              textAlign: TextAlign.start,
                                            ),
                                            const CustomSizedBox(
                                              height: 5,
                                            ),
                                            Row(
                                              mainAxisAlignment:
                                                  MainAxisAlignment
                                                      .spaceBetween,
                                              children: [
                                                CustomText(
                                                  '${addressData.mobile}',
                                                  fontSize: 12,
                                                  color: context
                                                      .colorScheme.blackColor,
                                                  maxLines: 1,
                                                  textAlign: TextAlign.start,
                                                ),
                                                IntrinsicHeight(
                                                  child: Row(
                                                    children: [
                                                      GestureDetector(
                                                        onTap: () {
                                                          Navigator.pushNamed(
                                                            context,
                                                            googleMapRoute,
                                                            arguments: {
                                                              'screenType':
                                                                  GoogleMapScreenType
                                                                      .selectLocationOnMap,
                                                              "defaultLatitude":
                                                                  addressData
                                                                      .lattitude
                                                                      .toString(),
                                                              "defaultLongitude":
                                                                  addressData
                                                                      .longitude
                                                                      .toString(),
                                                              'details':
                                                                  addressData,
                                                              'showAddressForm':
                                                                  true
                                                            },
                                                          ).then((final Object?
                                                              value) {
                                                            if (value == true) {
                                                              context
                                                                  .read<
                                                                      GetAddressCubit>()
                                                                  .fetchAddress();
                                                            }
                                                          });
                                                        },
                                                        child: CustomSvgPicture(
                                                          svgImage:
                                                              AppAssets.edit,
                                                          color: context
                                                              .colorScheme
                                                              .accentColor,
                                                        ),
                                                      ),
                                                      VerticalDivider(
                                                        color: context
                                                            .colorScheme
                                                            .accentColor,
                                                        width: 20,
                                                      ),
                                                      GestureDetector(
                                                        onTap: () {
                                                          UiUtils
                                                              .showAnimatedDialog(
                                                            context: context,
                                                            child:
                                                                DeleteAddressDialog(
                                                              onConfirmButtonPressed:
                                                                  () {
                                                                try {
                                                                  context
                                                                      .read<
                                                                          DeleteAddressCubit>()
                                                                      .deleteAddress(
                                                                          addressData.id
                                                                              as String);
                                                                  Navigator.pop(
                                                                      context);
                                                                } catch (_) {}
                                                              },
                                                            ),
                                                          );
                                                        },
                                                        child: CustomSvgPicture(
                                                          svgImage:
                                                              AppAssets.delete,
                                                          color: context
                                                              .colorScheme
                                                              .accentColor,
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                )
                                              ],
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            );
                          }),
                        ),
                      ),
                    );
                  }

                  return const CustomSizedBox();
                },
              );
            }
            return ListView.builder(
              itemCount: UiUtils.numberOfShimmerContainer,
              shrinkWrap: true,
              itemBuilder: (context, int index) =>
                  const CustomShimmerLoadingContainer(
                margin: EdgeInsets.symmetric(horizontal: 4, vertical: 5),
                height: 140,
                width: 24,
              ),
            );
          },
        ),
      );

  @override
  void initState() {
    super.initState();
    context.read<GetAddressCubit>().fetchAddress();
  }

  @override
  Widget build(final BuildContext context) => InterstitialAdWidget(
        child: Scaffold(
          backgroundColor: context.colorScheme.primaryColor,
          appBar: UiUtils.getSimpleAppBar(
              context: context,
              // title: widget.appBarTitle.translate(context: context),
              titleWidget: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  CustomText(
                    widget.appBarTitle.translate(context: context).capitalize(),
                    color: context.colorScheme.blackColor,
                    fontWeight: FontWeight.w600,
                    fontSize: 18,
                  ),
                  context.watch<GetAddressCubit>().state is GetAddressSuccess
                      ? BlocBuilder<AddressesCubit, AddressesState>(
                          builder: (context, state) {
                            if (state is Addresses) {
                              return HeadingAmountAnimation(
                                key: ValueKey(state.addresses.length),
                                text:
                                    '${state.addresses.length} ${'addressesSaved'.translate(context: context)}',
                                textStyle: TextStyle(
                                  color: context.colorScheme.lightGreyColor,
                                  fontWeight: FontWeight.w400,
                                  fontSize: 14,
                                ),
                              );
                            }
                            return Container();
                          },
                        )
                      : CustomSizedBox(
                          width: context.screenWidth * 0.7,
                          height: context.screenHeight * 0.02,
                        )
                ],
              )),
          bottomNavigationBar: CustomContainer(
            padding: EdgeInsets.only(
                right: 15,
                left: 15,
                top: 10,
                bottom: MediaQuery.of(context).viewPadding.bottom + 10),
            child: AddAddressContainer(
              onTap: () {
                Navigator.pushNamed(
                  context,
                  googleMapRoute,
                  arguments: {
                    'screenType': GoogleMapScreenType.selectLocationOnMap,
                    "defaultLatitude": HiveRepository.getLatitude,
                    "defaultLongitude": HiveRepository.getLongitude,
                    'showAddressForm': true
                  },
                ).then((final Object? value) {
                  context.read<GetAddressCubit>().fetchAddress();
                });
              },
            ),
          ),
          body: CustomRefreshIndicator(
              displacment: 12,
              onRefreshCallback: () {
                context.read<GetAddressCubit>().fetchAddress();
              },
              child: buildAddressSelector()),
        ),
      );
}
