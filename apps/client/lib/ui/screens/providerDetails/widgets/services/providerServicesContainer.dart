import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class ProviderServicesContainer extends StatelessWidget {
  final List<Services> servicesList;
  final String providerID;
  final String isProviderAvailableAtLocation;
  final bool isLoadingMoreData;

  const ProviderServicesContainer(
      {super.key,
      required this.servicesList,
      required this.providerID,
      required this.isLoadingMoreData,
      required this.isProviderAvailableAtLocation});

  @override
  Widget build(BuildContext context) {
    return servicesList.isEmpty
        ? NoDataFoundWidget(
            titleKey: "noServicesAvailable".translate(context: context),
            subtitleKey: "noServicesFoundSubTitle".translate(context: context),
          )
        : NotificationListener(
            onNotification: (ScrollNotification scrollInfo) {
              if (scrollInfo.metrics.pixels ==
                  scrollInfo.metrics.maxScrollExtent) {
                if (!context
                    .read<ProviderDetailsAndServiceCubit>()
                    .hasMoreServices()) {
                  return false;
                }
                context
                    .read<ProviderDetailsAndServiceCubit>()
                    .fetchMoreServices(providerId: providerID);
              }
              return true;
            },
            child: CustomContainer(
              color: context.colorScheme.secondaryColor,
              child: ListView.builder(
                key: const PageStorageKey("services"),
                padding: EdgeInsetsDirectional.only(
                  top: 5.rh(context),
                  start: 15.rw(context),
                  end: 15.rw(context),
                  bottom: 15.rh(context) +
                      (context.read<CartCubit>().getProviderIDFromCartData() ==
                              '0'
                          ? 0
                          : kBottomNavigationBarHeight.rh(context) +
                              10.rh(context)),
                ),
                shrinkWrap: true,
                itemCount: servicesList.length + (isLoadingMoreData ? 1 : 0),
                itemBuilder: (final BuildContext context, final index) {
                  if (index >= servicesList.length) {
                    return Center(
                      child: CustomCircularProgressIndicator(
                          color: context.colorScheme.accentColor),
                    );
                  }
                  return ServiceDetailsCard(
                    isProviderAvailableAtLocation:
                        isProviderAvailableAtLocation,
                    services: servicesList[index],
                    providerId: providerID,
                    onTap: () {
                      Navigator.pushNamed(
                        context,
                        providerServiceDetails,
                        arguments: {
                          'serviceDetails': servicesList[index],
                          'serviceId': servicesList[index].id ?? '',
                          'providerId': providerID,
                          'isProviderAvailableAtLocation':
                              isProviderAvailableAtLocation
                        },
                      );
                    },
                  );
                },
              ),
            ),
          );
  }
}
