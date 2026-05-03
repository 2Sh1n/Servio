import 'package:flutter/material.dart';
import '../../../../app/generalImports.dart';

class MapTitleWidget extends StatelessWidget {
  final ValueNotifier<GoogleMapScreenType> currentScreenType;
  final bool showAddressForm;

  const MapTitleWidget(
      {Key? key,
      required this.currentScreenType,
      required this.showAddressForm})
      : super(key: key);

  @override
  Widget build(BuildContext context) =>
      ValueListenableBuilder<GoogleMapScreenType>(
        valueListenable: currentScreenType,
        builder: (context, screenType, _) {
          if (screenType == GoogleMapScreenType.providerOnMap) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomText(
                  'providersNearby'.translate(context: context),
                  fontSize: 14,
                  fontWeight: FontWeight.w400,
                  color: context.colorScheme.lightGreyColor,
                ),
                MarqueeWidget(
                  direction: Axis.horizontal,
                  child: ValueListenableBuilder(
                    valueListenable:
                        Hive.box(HiveRepository.userDetailBoxKey).listenable(),
                    builder: (BuildContext context, Box box, _) => CustomText(
                      " ${HiveRepository.getLocationName ?? "selectYourLocation".translate(context: context)} ",
                      color: context.colorScheme.blackColor,
                      fontWeight: FontWeight.w500,
                      fontStyle: FontStyle.normal,
                      fontSize: 14,
                      maxLines: 1,
                    ),
                  ),
                ),
              ],
            );
          } else {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                showAddressForm
                    ? CustomText(
                        'selectLocation'.translate(context: context),
                        fontSize: 18,
                        fontWeight: FontWeight.w400,
                        color: context.colorScheme.blackColor,
                      )
                    : CustomText(
                        'yourLocation'.translate(context: context),
                        fontSize: 14,
                        fontWeight: FontWeight.w400,
                        color: context.colorScheme.lightGreyColor,
                      ),
                if (!showAddressForm)
                  MarqueeWidget(
                    direction: Axis.horizontal,
                    child: ValueListenableBuilder(
                      valueListenable: Hive.box(HiveRepository.userDetailBoxKey)
                          .listenable(),
                      builder: (BuildContext context, Box box, _) => CustomText(
                        " ${HiveRepository.getLocationName ?? "selectYourLocation".translate(context: context)} ",
                        color: context.colorScheme.blackColor,
                        fontWeight: FontWeight.w500,
                        fontStyle: FontStyle.normal,
                        fontSize: 14,
                        maxLines: 1,
                      ),
                    ),
                  ),
              ],
            );
          }
        },
      );
}
