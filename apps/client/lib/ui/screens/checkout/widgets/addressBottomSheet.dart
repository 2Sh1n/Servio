import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class AddressBotoomSheet extends StatefulWidget {
  const AddressBotoomSheet(
      {super.key, required this.addresses, required this.defaultAddress});
  final List<GetAddressModel> addresses;
  final GetAddressModel defaultAddress;
  @override
  State<AddressBotoomSheet> createState() => _AddressBotoomSheetState();
}

class _AddressBotoomSheetState extends State<AddressBotoomSheet> {
  String? selectedAddressId;

  @override
  void initState() {
    selectedAddressId = widget.defaultAddress.id;
    super.initState();
  }

  @override
  Widget build(final BuildContext context) {
    return BlocListener<AddAddressCubit, AddAddressState>(
      listener: (context, addAddressState) {
        // Refresh addresses when an address is successfully added or updated
        if (addAddressState is AddAddressSuccess) {
          context.read<GetAddressCubit>().fetchAddress();
        }
      },
      child: BottomSheetLayout(
        title: 'selectAddress',
        bottomWidget: CustomContainer(
          color: context.colorScheme.secondaryColor,
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 10),
          child: AddAddressContainer(
            onTap: () {
              Navigator.pushNamed(
                context,
                googleMapRoute,
                arguments: {
                  "defaultLatitude": HiveRepository.getLatitude,
                  "defaultLongitude": HiveRepository.getLongitude,
                  'showAddressForm': true,
                  'screenType': GoogleMapScreenType.selectLocationOnMap,
                },
              ).then((final Object? value) {
                context.read<GetAddressCubit>().fetchAddress();
                Navigator.pop(context);
              });
            },
          ),
        ),
        child: BlocBuilder<GetAddressCubit, GetAddressState>(
          builder: (context, getAddressState) {
            // Use updated addresses from cubit if available, otherwise use initial addresses
            List<GetAddressModel> addresses = widget.addresses;
            GetAddressModel defaultAddress = widget.defaultAddress;

            if (getAddressState is GetAddressSuccess) {
              addresses = getAddressState.data;
              // Update default address if available
              if (addresses.isNotEmpty) {
                final defaultAddr = addresses.firstWhere(
                  (addr) => addr.isDefault == '1',
                  orElse: () => addresses.first,
                );
                defaultAddress = defaultAddr;
                // Update selected address ID if current default changed
                if (selectedAddressId != defaultAddress.id) {
                  WidgetsBinding.instance.addPostFrameCallback((_) {
                    if (mounted) {
                      setState(() {
                        selectedAddressId = defaultAddress.id;
                      });
                    }
                  });
                }
              }
            }

            if (addresses.isEmpty) {
              return const SizedBox.shrink();
            }

            return Column(
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: List.generate(
                    addresses.length,
                    (final int index) {
                      final addressData = addresses[index];

                      return CustomContainer(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 15, vertical: 0),
                        color: context.colorScheme.secondaryColor,
                        borderRadius: UiUtils.borderRadiusOf10,
                        child: Padding(
                          padding:
                              const EdgeInsetsDirectional.only(bottom: 12.0),
                          child: AddressRadioOptionsWidget(
                            title: addressData.type!,
                            subTitle:
                                '${addressData.address}, ${addressData.area}, ${addressData.cityName}',
                            value: addressData.id!,
                            groupValue: selectedAddressId ?? defaultAddress.id!,
                            applyAccentColor: true,
                            addressData: addressData,
                            onChanged: (final Object? selectedValue) {
                              Navigator.pop(
                                  context, {'selectedAddress': addressData});
                            },
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
