import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

class AllowLocationScreen extends StatefulWidget {
  const AllowLocationScreen({final Key? key}) : super(key: key);

  @override
  State<AllowLocationScreen> createState() => _AllowLocationScreenState();

  static Route route(final RouteSettings routeSettings) => CupertinoPageRoute(
        builder: (final BuildContext context) => const AllowLocationScreen(),
      );
}

class _AllowLocationScreenState extends State<AllowLocationScreen>
    with WidgetsBindingObserver {
  bool _isProcessing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  Future<void> _requestLocationPermission() async {
    final bool isLocationOn = await Geolocator.isLocationServiceEnabled();

    if (!isLocationOn) {
      if (!mounted) return;

      UiUtils.showMessage(
        context,
        'pleaseTurnOnYourDeviceLocationToContinue'.translate(context: context),
        ToastificationType.warning,
      );
      return;
    }
    LocationRepository.requestPermission(
      allowed: (final Position position) async {
        if (!mounted || _isProcessing) return;
        _isProcessing = true;

        // Location is already stored by getLocationDataAndStoreIntoHive()
        // Verify location is stored before fetching
        final String lat = HiveRepository.getLatitude ?? "0.0";
        final String lng = HiveRepository.getLongitude ?? "0.0";

        if (mounted && lat != "0.0" && lat != '' && lng != "0.0" && lng != '') {
          await context.read<HomeScreenCubit>().fetchHomeScreenData();
          if (mounted) {
            _isProcessing = false;
            Navigator.pop(context);
          }
        } else {
          _isProcessing = false;
        }
      },
      onRejected: () async {
        if (!mounted) return;
        //open app setting for permission
        UiUtils.showMessage(
            context,
            'permissionRejected'.translate(context: context),
            ToastificationType.warning);
      },
      onGranted: (final Position position) async {
        if (!mounted || _isProcessing) return;
        _isProcessing = true;

        // Location is already stored by getLocationDataAndStoreIntoHive()
        // Verify location is stored before fetching
        final String lat = HiveRepository.getLatitude ?? "0.0";
        final String lng = HiveRepository.getLongitude ?? "0.0";

        if (mounted && lat != "0.0" && lat != '' && lng != "0.0" && lng != '') {
          await context.read<HomeScreenCubit>().fetchHomeScreenData();
          if (mounted) {
            _isProcessing = false;
            Navigator.pop(context);
          }
        } else {
          _isProcessing = false;
        }
      },
    );
  }

  @override
  Future<void> didChangeAppLifecycleState(final AppLifecycleState state) async {
    if (state == AppLifecycleState.resumed) {
      if (!mounted || _isProcessing) return;

      final PermissionStatus permissionStatus =
          await Permission.location.status;
      final bool isLocationServiceEnabled =
          await Geolocator.isLocationServiceEnabled();

      if (permissionStatus == PermissionStatus.granted &&
          isLocationServiceEnabled) {
        if (!mounted) return;
        _isProcessing = true;
        try {
          // First, get and store location data
          await LocationRepository.getLocationDataAndStoreIntoHive();

          // Verify location is stored before fetching home screen data
          final String lat = HiveRepository.getLatitude ?? "0.0";
          final String lng = HiveRepository.getLongitude ?? "0.0";

          if (mounted &&
              lat != "0.0" &&
              lat != '' &&
              lng != "0.0" &&
              lng != '') {
            await context.read<HomeScreenCubit>().fetchHomeScreenData();
            if (mounted) {
              _isProcessing = false;
              Navigator.pop(context);
            }
          } else {
            _isProcessing = false;
          }
        } catch (e) {
          // If getting location fails, don't navigate
          _isProcessing = false;
          debugPrint("didChangeAppLifecycleState - Error getting location: $e");
        }
      }
    }

    super.didChangeAppLifecycleState(state);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(final BuildContext context) => PopScope(
        canPop: false,
        //onWillPop: () async => false,
        child: Scaffold(
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const CustomSvgPicture(svgImage: AppAssets.locationAccess),
                const CustomSizedBox(height: 10),
                CustomText('permissionRequired'.translate(context: context)),
                const CustomSizedBox(height: 10),
                CustomRoundedButton(
                  widthPercentage: 0.8,
                  backgroundColor: context.colorScheme.accentColor,
                  buttonTitle: "useCurrentLocation".translate(context: context),
                  showBorder: true,
                  onTap: () {
                    _requestLocationPermission();
                  },
                ),
                const CustomSizedBox(height: 10),
                CustomRoundedButton(
                  widthPercentage: 0.8,
                  backgroundColor: context.colorScheme.accentColor,
                  buttonTitle: "manualLocation".translate(context: context),
                  showBorder: true,
                  onTap: () {
                    UiUtils.showBottomSheet(
                            child: const LocationBottomSheet(),
                            context: context)
                        .then((final value) {
                      if (value != null) {
                        if (value['navigateToMap']) {
                          Navigator.pushNamed(
                            context,
                            googleMapRoute,
                            arguments: {
                              "defaultLatitude": value['latitude'].toString(),
                              "defaultLongitude": value['longitude'].toString(),
                              'showAddressForm': false,
                              'screenType':
                                  GoogleMapScreenType.selectLocationOnMap,
                            },
                          );
                        }
                      }
                    });
                  },
                ),
              ],
            ),
          ),
        ),
      );
}
