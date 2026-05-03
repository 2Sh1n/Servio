import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/cubits/fetchMapProviderCubit.dart';
import 'package:e_demand/cubits/fetchUserCurrentLocationCubit.dart';
import 'package:e_demand/data/model/providerMapModel.dart';
import 'package:e_demand/ui/screens/map/widgets/mapSearchContainer.dart';
import 'package:e_demand/ui/screens/map/widgets/providerSlider.dart';
import 'package:e_demand/ui/screens/map/widgets/mapTitleWidget.dart';
import 'package:e_demand/ui/screens/map/utils/map_utils.dart';
import 'package:e_demand/ui/widgets/tooltipShapeBorder.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:carousel_slider/carousel_slider.dart';

enum GoogleMapScreenType {
  providerOnMap,
  selectLocationOnMap;

  String get titleKey {
    switch (this) {
      case providerOnMap:
        return 'providerOnMap';
      case selectLocationOnMap:
        return 'selectLocationOnMap';
    }
  }
}

class GoogleMapScreen extends StatefulWidget {
  const GoogleMapScreen({
    final Key? key,
    required this.showAddressForm,
    this.addressDetails,
    required this.defaultLatitude,
    required this.defaultLongitude,
    this.screenType = GoogleMapScreenType.selectLocationOnMap,
  }) : super(key: key);

  final bool showAddressForm;
  final GetAddressModel? addressDetails;
  final String defaultLatitude;
  final String defaultLongitude;
  final GoogleMapScreenType screenType;

  @override
  State<GoogleMapScreen> createState() => _GoogleMapScreenState();

  static Route route(final RouteSettings routeSettings) {
    final Map argument = routeSettings.arguments as Map;
    return CupertinoPageRoute(
      builder: (final context) => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => AddAddressCubit(),
          ),
          BlocProvider(
            create: (context) => FetchUserCurrentLocationCubit(),
          ),
          BlocProvider(
            create: (final context) => CheckProviderAvailabilityCubit(
                providerRepository: ProviderRepository()),
          ),
          BlocProvider(
            create: (final context) => FetchMapProviderCubit(
              ProviderRepository(),
            ),
          ),
        ],
        child: Builder(
          builder: (context) => GoogleMapScreen(
            showAddressForm: argument['showAddressForm'] ?? false,
            addressDetails: argument['details'],
            defaultLatitude: argument["defaultLatitude"] ?? "0.0",
            defaultLongitude: argument["defaultLongitude"] ?? "0.0",
            screenType: argument["screenType"] ??
                GoogleMapScreenType.selectLocationOnMap,
          ),
        ),
      ),
    );
  }
}

class _GoogleMapScreenState extends State<GoogleMapScreen> {
  //String lineOneAddress = '', lineTwoAddress = '';
  late CameraPosition initialCameraPosition;

  ValueNotifier selectedAddress = ValueNotifier(
      {"lineOneAddress": '', "lineTwoAddress": '', "selectedCity": ''});

  double? selectedLatitude, selectedLongitude;

  late Marker initialLocation;

  final ValueNotifier<Set<Marker>> mapMarkers = ValueNotifier(Set());
  Completer<GoogleMapController> _controller = Completer();
  final CustomInfoWindowController _customInfoWindowController =
      CustomInfoWindowController();
  String? mapStyle;

  final ValueNotifier<String> searchText = ValueNotifier('');

  // New ValueNotifiers for provider map functionality
  final ValueNotifier<GoogleMapScreenType> currentScreenType =
      ValueNotifier(GoogleMapScreenType.selectLocationOnMap);
  final ValueNotifier<int> selectedProviderIndex = ValueNotifier(0);
  final CarouselSliderController providerCarouselController =
      CarouselSliderController();

  @override
  void initState() {
    super.initState();
    currentScreenType.value = widget.screenType;
    setMapStyle();

    if (currentScreenType.value == GoogleMapScreenType.providerOnMap) {
      _initializeProvidersMap();
    } else {
      _initializeLocationMap();
    }

    searchText.addListener(() {
      context
          .read<FetchMapProviderCubit>()
          .applySearchText(searchText.value.trim());
    });
  }

  void _initializeProvidersMap() {
    context.read<FetchMapProviderCubit>().fetchMapProviders(
          latitude: widget.defaultLatitude,
          longitude: widget.defaultLongitude,
        );

    selectedLatitude = double.parse(widget.defaultLatitude);
    selectedLongitude = double.parse(widget.defaultLongitude);

    // Set initial camera position to selected location
    initialCameraPosition = CameraPosition(
        zoom: 14, target: LatLng(selectedLatitude!, selectedLongitude!));

    // Add listener to zoom to first provider when data loads
    context.read<FetchMapProviderCubit>().stream.listen((state) {
      if (state is FetchMapProviderFetchSuccess &&
          state.filteredProviderList.isNotEmpty) {
        // Zoom to first provider location
        final firstProvider = state.filteredProviderList.first;
        _zoomToProviderLocation(
            firstProvider.latitude, firstProvider.longitude);
      }
    });
  }

  void _initializeLocationMap() {
    //when user adding the address then load user current location
    if (widget.showAddressForm && widget.addressDetails == null) {
      Future.delayed(
        Duration.zero,
        () {
          context
              .read<FetchUserCurrentLocationCubit>()
              .fetchUserCurrentLocation();
        },
      );
    }
    selectedLatitude = double.parse(widget.defaultLatitude);
    selectedLongitude = double.parse(widget.defaultLongitude);

    initialLocation = Marker(
      markerId: const MarkerId("1"),
      position: LatLng(double.parse(selectedLatitude.toString()),
          double.parse(selectedLongitude.toString())),
      // Always use red marker for location selection
      icon: BitmapDescriptor.defaultMarkerWithHue(
        BitmapDescriptor.hueRed,
      ),
      onTap: () {},
    );

    checkProviderAvailability(
      latitude: widget.defaultLatitude,
      longitude: widget.defaultLongitude,
    );

    mapMarkers.value = {
      ...mapMarkers.value,
      Marker(
        markerId: const MarkerId("1"),
        position: LatLng(selectedLatitude!, selectedLongitude!),
        // Always use red marker for location selection
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
        onTap: () {},
      )
    };

    initialCameraPosition = CameraPosition(
        zoom: 16, target: LatLng(selectedLatitude!, selectedLongitude!));

    createAddressFromCoOrdinates(
      selectedLatitude!,
      selectedLongitude!,
    );

    // Ensure we zoom to the marker location after initialization
    Future.delayed(const Duration(milliseconds: 100), () {
      _zoomToMarkerLocation();
    });
  }

  void _updateProviderMarkers({required List<ProviderMapModel> providers}) {
    mapMarkers.value = MapUtils.updateProviderMarkers(
      providers,
      mapMarkers.value,
      selectedProviderIndex,
      providerCarouselController,
    );
  }

  void _resetMapController() => _controller = MapUtils.resetMapController();

  Future<void> _zoomToMarkerLocation() async => MapUtils.zoomToMarkerLocation(
      _controller, selectedLatitude, selectedLongitude);

  Future<void> _zoomToProviderLocation(
          double latitude, double longitude) async =>
      MapUtils.zoomToProviderLocation(_controller, latitude, longitude);

  Future<void> setMapStyle() async {
    if (context.read<AppThemeCubit>().state.appTheme == AppTheme.dark) {
      mapStyle = await rootBundle.loadString('assets/mapTheme/darkMap.json');
    } else {
      mapStyle = await rootBundle.loadString('assets/mapTheme/lightMap.json');
    }
  }

  Future<void> createAddressFromCoOrdinates(
      final double latitude, final double longitude) async {
    _customInfoWindowController.hideInfoWindow?.call();
    final newAddress =
        await MapUtils.createAddressFromCoordinates(latitude, longitude);
    selectedAddress.value = newAddress;
  }

  Future<void> checkProviderAvailability({
    required final String latitude,
    required final String longitude,
  }) async {
    context.read<CheckProviderAvailabilityCubit>().checkProviderAvailability(
          isAuthTokenRequired: false,
          checkingAtCheckOut: "0",
          latitude: latitude,
          longitude: longitude,
        );
  }

  Future<void> _placeMarkerOnLatitudeAndLongitude(
      {required final double latitude, required final double longitude}) async {
    selectedLatitude = latitude;
    selectedLongitude = longitude;

    final latLong = LatLng(latitude, longitude);
    final Marker marker = Marker(
      markerId: const MarkerId("1"),
      position: latLong,
      // Always use red marker for location selection
      icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
      onTap: () {
        _customInfoWindowController.addInfoWindow!(
            _infoWindowContainer(), latLong);
      },
    );

    // Clear existing location markers first
    final Set<Marker> otherMarkers = {};
    for (Marker existingMarker in mapMarkers.value) {
      if (existingMarker.markerId.value != "1") {
        otherMarkers.add(existingMarker);
      }
    }

    // Add the new red marker
    mapMarkers.value = {...otherMarkers, marker};

    // Always try to zoom to red marker location
    try {
      // Wait for map controller to be ready
      await Future.delayed(const Duration(milliseconds: 300));

      // Keep trying to get controller and zoom for up to 2 seconds
      for (int i = 0; i < 10; i++) {
        try {
          if (_controller.isCompleted) {
            final GoogleMapController controller = await _controller.future;

            // Zoom to red marker location
            final newCameraPosition = CameraUpdate.newCameraPosition(
                CameraPosition(zoom: 17, target: latLong));
            await controller.animateCamera(newCameraPosition);
            break; // Successfully zoomed, exit loop
          }
        } catch (e) {
          // Controller not ready yet, wait and try again
          await Future.delayed(const Duration(milliseconds: 200));
        }
      }

      // Update address and provider availability
      await checkProviderAvailability(
          latitude: selectedLatitude.toString(),
          longitude: selectedLongitude.toString());
      await createAddressFromCoOrdinates(selectedLatitude!, selectedLongitude!);
    } catch (e) {
      // Even if zoom fails, still update address
      await checkProviderAvailability(
          latitude: selectedLatitude.toString(),
          longitude: selectedLongitude.toString());
      await createAddressFromCoOrdinates(selectedLatitude!, selectedLongitude!);
    }
  }

  void _switchToLocationSelectionMode() {
    _resetMapController();
    currentScreenType.value = GoogleMapScreenType.selectLocationOnMap;
    _initializeLocationMap();

    // Zoom to marker location after switching views
    Future.delayed(const Duration(milliseconds: 300), () {
      _zoomToMarkerLocation();
    });
  }

  Container _infoWindowContainer() => Container(
        decoration: ShapeDecoration(
          color: context.colorScheme.blackColor,
          shape: const TooltipShapeBorder(radius: 10),
          shadows: const [
            BoxShadow(
                color: Colors.black26, blurRadius: 1, offset: Offset(2, 2))
          ],
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CustomText(
              'yourServiceWillHere'.translate(context: context),
              fontWeight: FontWeight.w700,
              fontSize: 14,
              color: context.colorScheme.secondaryColor,
            ),
            CustomText(
              'movePinToLocation'.translate(context: context),
              fontSize: 10,
              color: context.colorScheme.lightGreyColor,
            )
          ],
        ),
      );

  void _showAddressSheet(
    final BuildContext context,
  ) {
    UiUtils.showBottomSheet(
        context: context,
        enableDrag: true,
        isScrollControlled: true,
        useSafeArea: true,
        child: CustomContainer(
          padding: EdgeInsetsDirectional.only(
              bottom: MediaQuery.of(context).viewInsets.bottom),
          child: BlocProvider.value(
            value: context.read<AddAddressCubit>(),
            child: Builder(
              builder: (final context) {
                final String userMobileNumber =
                    "${HiveRepository.getUserMobileNumber}";
                final String selectedCity =
                    selectedAddress.value["selectedCity"];
                return AddressSheet(
                  addressDataModel: AddressModel(
                      latitude: selectedLatitude.toString(),
                      longitude: selectedLongitude.toString(),
                      addressId: widget.addressDetails?.id,
                      address: widget.addressDetails?.address,
                      mobile: widget.addressDetails?.mobile ?? userMobileNumber,
                      area: widget.addressDetails?.area,
                      cityName: widget.addressDetails?.cityName ?? selectedCity,
                      type: widget.addressDetails?.type ?? '',
                      isDefault: widget.addressDetails?.isDefault ?? "0"),
                  isUpdateAddress: widget.addressDetails == null ? false : true,
                  addressId: widget.addressDetails == null
                      ? null
                      : widget.addressDetails!.id,
                );
              },
            ),
          ),
        ));
  }

  @override
  Widget build(final BuildContext context) => AnnotatedSafeArea(
        isAnnotated: true,
        child: Scaffold(
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            titleWidget: MapTitleWidget(
              currentScreenType: currentScreenType,
              showAddressForm: widget.showAddressForm,
            ),
            actions: currentScreenType.value ==
                    GoogleMapScreenType.providerOnMap
                ? [
                    CustomInkWellContainer(
                      onTap: () {
                        UiUtils.showBottomSheet(
                          enableDrag: true,
                          isScrollControlled: true,
                          useSafeArea: true,
                          child: CustomContainer(
                            padding: EdgeInsetsDirectional.only(
                                bottom:
                                    MediaQuery.of(context).viewInsets.bottom),
                            child: const LocationBottomSheet(),
                          ),
                          context: context,
                        ).then((final value) {
                          if (value != null) {
                            if (currentScreenType.value ==
                                GoogleMapScreenType.providerOnMap) {
                              _switchToLocationSelectionMode();
                            }
                            setState(() {
                              currentScreenType.value =
                                  GoogleMapScreenType.selectLocationOnMap;
                            });

                            // Place marker at the new location and update address
                            final newLatitude =
                                double.parse(value['latitude'].toString());
                            final newLongitude =
                                double.parse(value['longitude'].toString());
                            _placeMarkerOnLatitudeAndLongitude(
                              latitude: newLatitude,
                              longitude: newLongitude,
                            );
                          }
                        });
                      },
                      child: Padding(
                        padding: const EdgeInsetsDirectional.only(end: 10),
                        child: CustomText(
                          'change'.translate(context: context),
                          color: context.colorScheme.accentColor,
                          fontWeight: FontWeight.w500,
                          fontStyle: FontStyle.normal,
                          fontSize: 14,
                        ),
                      ),
                    ),
                  ]
                : [],
            backgroundColor: context.colorScheme.secondaryColor,
          ),
          body: PopScope(
            canPop: false,
            onPopInvokedWithResult: (didPop, _) {
              if (didPop) {
                return;
              } else {
                Future.delayed(const Duration(milliseconds: 1000))
                    .then((final value) => Navigator.pop(context));
              }
            },
            child: ValueListenableBuilder<GoogleMapScreenType>(
              valueListenable: currentScreenType,
              builder: (context, screenType, _) {
                if (screenType == GoogleMapScreenType.providerOnMap) {
                  return _buildProviderMapView();
                } else {
                  return _buildLocationSelectionView();
                }
              },
            ),
          ),
        ),
      );

  Widget _buildProviderMapView() {
    return ValueListenableBuilder<Set<Marker>>(
      valueListenable: mapMarkers,
      builder: (context, data, _) => Stack(
        children: [
          GoogleMap(
            style: mapStyle,
            zoomControlsEnabled: false,
            markers: Set.of(data),
            onMapCreated: (GoogleMapController controller) async {
              try {
                if (!_controller.isCompleted) {
                  _controller.complete(controller);
                }
              } catch (e) {
                // If already completed, create a new completer and complete it
                _controller = Completer<GoogleMapController>();
                _controller.complete(controller);
              }
              _customInfoWindowController.googleMapController = controller;
            },
            minMaxZoomPreference: const MinMaxZoomPreference(0, 16),
            initialCameraPosition: initialCameraPosition,
          ),
          Positioned.fill(
            child: Align(
              alignment: Alignment.bottomCenter,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (currentScreenType.value ==
                      GoogleMapScreenType.selectLocationOnMap)
                    Align(
                      alignment: AlignmentDirectional.bottomEnd,
                      child: CustomContainer(
                        margin: const EdgeInsets.all(20),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            blurRadius: 5,
                            color: context.colorScheme.blackColor
                                .withValues(alpha: 0.2),
                          )
                        ],
                        child: CustomInkWellContainer(
                          onTap: () {
                            context
                                .read<FetchUserCurrentLocationCubit>()
                                .fetchUserCurrentLocation();
                          },
                          child: BlocConsumer<FetchUserCurrentLocationCubit,
                              FetchUserCurrentLocationState>(
                            listener: (context, state) {
                              if (state is FetchUserCurrentLocationSuccess) {
                                // Update camera position and regenerate providers
                                selectedLatitude = state.position.latitude;
                                selectedLongitude = state.position.longitude;
                                context
                                    .read<FetchMapProviderCubit>()
                                    .fetchMapProviders(
                                      latitude: selectedLatitude?.toString() ??
                                          widget.defaultLatitude,
                                      longitude:
                                          selectedLongitude?.toString() ??
                                              widget.defaultLongitude,
                                    );

                                _controller.future.then((controller) {
                                  controller.animateCamera(
                                    CameraUpdate.newCameraPosition(
                                      CameraPosition(
                                        zoom: 14,
                                        target: LatLng(selectedLatitude!,
                                            selectedLongitude!),
                                      ),
                                    ),
                                  );
                                });
                              } else if (state
                                  is FetchUserCurrentLocationFailure) {
                                UiUtils.showMessage(context, state.errorMessage,
                                    ToastificationType.error);
                              }
                            },
                            builder: (context, state) {
                              Widget? child;
                              if (state is FetchUserCurrentLocationInProgress) {
                                child = CustomCircularProgressIndicator(
                                  color: context.colorScheme.blackColor,
                                );
                              }
                              return CustomContainer(
                                color: context.colorScheme.secondaryColor,
                                borderRadius: UiUtils.borderRadiusOf50,
                                width: 60,
                                height: 60,
                                child: child ??
                                    Icon(
                                      Icons.my_location_outlined,
                                      size: 35,
                                      color: context.colorScheme.blackColor,
                                    ),
                              );
                            },
                          ),
                        ),
                      ),
                    ),
                  ProviderSlider(
                    providerCarouselController: providerCarouselController,
                    selectedProviderIndex: selectedProviderIndex,
                    controller: _controller,
                    onProvidersUpdate: (providers) =>
                        _updateProviderMarkers(providers: providers),
                  ),
                  const CustomSizedBox(height: 16),
                ],
              ),
            ),
          ),
          Align(
            alignment: Alignment.topCenter,
            child: MapSearchContainer(
              hint: 'searchProviders'.translate(context: context),
              onTap: context.watch<FetchMapProviderCubit>().state
                      is FetchMapProviderFetchSuccess
                  ? null
                  : () {},
              onSearchTextChanged: (value) {
                searchText.value = value;
              },
            ),
          )
        ],
      ),
    );
  }

  Widget _buildLocationSelectionView() {
    return ValueListenableBuilder<Set<Marker>>(
      valueListenable: mapMarkers,
      builder: (context, data, _) =>
          BlocListener<AddAddressCubit, AddAddressState>(
        listener: (final BuildContext context, AddAddressState state) {
          if (state is AddAddressSuccess) {
            if (data.isNotEmpty) {
              final position = data.elementAt(0).position;

              HiveRepository.setLongitude = position.longitude;
              HiveRepository.setLatitude = position.latitude;
            }
            Navigator.of(context).pop();
            Navigator.of(context)
                .pop(widget.addressDetails == null ? null : true);
          }
        },
        child: Stack(
          children: [
            GoogleMap(
              style: mapStyle,
              zoomControlsEnabled: false,
              markers: data.isEmpty ? {initialLocation} : Set.of(data),
              onTap: (final LatLng position) async {
                _placeMarkerOnLatitudeAndLongitude(
                    longitude: position.longitude, latitude: position.latitude);
              },
              onCameraMove: (final position) {
                _customInfoWindowController.onCameraMove!();
              },
              onMapCreated: (GoogleMapController controller) async {
                try {
                  if (!_controller.isCompleted) {
                    _controller.complete(controller);
                  }
                } catch (e) {
                  // If already completed, create a new completer and complete it
                  _controller = Completer<GoogleMapController>();
                  _controller.complete(controller);
                }
                _customInfoWindowController.googleMapController = controller;

                // Auto-zoom to marker location when map is created
                _zoomToMarkerLocation();
              },
              minMaxZoomPreference: const MinMaxZoomPreference(0, 16),
              initialCameraPosition: initialCameraPosition,
            ),
            CustomInfoWindow(
              controller: _customInfoWindowController,
              height: 60,
              width: 185,
              offset: 48,
            ),
            Positioned.fill(
              child: Align(
                alignment: Alignment.bottomCenter,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Align(
                      alignment: AlignmentDirectional.bottomEnd,
                      child: CustomContainer(
                        margin: const EdgeInsets.all(20),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            blurRadius: 5,
                            color: context.colorScheme.blackColor
                                .withValues(alpha: 0.2),
                          )
                        ],
                        child: CustomInkWellContainer(
                          onTap: () {
                            context
                                .read<FetchUserCurrentLocationCubit>()
                                .fetchUserCurrentLocation();
                          },
                          child: BlocConsumer<FetchUserCurrentLocationCubit,
                              FetchUserCurrentLocationState>(
                            listener: (context, state) {
                              if (state is FetchUserCurrentLocationSuccess) {
                                _placeMarkerOnLatitudeAndLongitude(
                                  latitude: state.position.latitude,
                                  longitude: state.position.longitude,
                                );
                              } else if (state
                                  is FetchUserCurrentLocationFailure) {
                                UiUtils.showMessage(context, state.errorMessage,
                                    ToastificationType.error);
                              }
                            },
                            builder: (context, state) {
                              Widget? child;
                              if (state is FetchUserCurrentLocationInProgress) {
                                child = CustomCircularProgressIndicator(
                                  color: context.colorScheme.blackColor,
                                );
                              }
                              return CustomContainer(
                                color: context.colorScheme.secondaryColor,
                                borderRadius: UiUtils.borderRadiusOf50,
                                width: 60,
                                height: 60,
                                child: child ??
                                    Icon(
                                      Icons.my_location_outlined,
                                      size: 35,
                                      color: context.colorScheme.blackColor,
                                    ),
                              );
                            },
                          ),
                        ),
                      ),
                    ),
                    BlocBuilder<CheckProviderAvailabilityCubit,
                        CheckProviderAvailabilityState>(
                      builder: (final context,
                          final checkProviderAvailabilityState) {
                        if (checkProviderAvailabilityState
                            is CheckProviderAvailabilityFetchSuccess) {
                          return BlocBuilder<AddAddressCubit, AddAddressState>(
                            builder: (final BuildContext context,
                                    final AddAddressState state) =>
                                CustomContainer(
                              height: checkProviderAvailabilityState.error
                                  ? 80
                                  : 150,
                              width: context.screenWidth,
                              margin: const EdgeInsets.all(10),
                              color: context.colorScheme.secondaryColor,
                              borderRadius: UiUtils.borderRadiusOf20,
                              child: ValueListenableBuilder(
                                valueListenable: selectedAddress,
                                builder: (context, value, child) {
                                  value as Map;
                                  return Column(
                                    children: [
                                      Expanded(
                                        child: Padding(
                                          padding: const EdgeInsets.fromLTRB(
                                              16, 10, 16, 10),
                                          child: Row(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: <Widget>[
                                              CustomContainer(
                                                borderRadius:
                                                    UiUtils.borderRadiusOf10,
                                                color: context
                                                    .colorScheme.accentColor
                                                    .withAlpha(20),
                                                padding:
                                                    const EdgeInsets.all(10),
                                                child: CustomSvgPicture(
                                                  svgImage:
                                                      AppAssets.locationMark,
                                                  height: 20,
                                                  width: 20,
                                                  color: context
                                                      .colorScheme.accentColor,
                                                ),
                                              ),
                                              const CustomSizedBox(
                                                width: 10,
                                              ),
                                              Expanded(
                                                child: Column(
                                                  crossAxisAlignment:
                                                      CrossAxisAlignment.start,
                                                  children: <Widget>[
                                                    if (checkProviderAvailabilityState
                                                        .error) ...[
                                                      CustomText(
                                                        "serviceNotAvailableAtSelectedLocation"
                                                            .translate(
                                                                context:
                                                                    context),
                                                        maxLines: 2,
                                                        fontSize: 14,
                                                        fontWeight:
                                                            FontWeight.normal,
                                                        color: Theme.of(context)
                                                            .colorScheme
                                                            .blackColor,
                                                      )
                                                    ] else ...[
                                                      CustomText(
                                                        value["lineOneAddress"] ??
                                                            '',
                                                        maxLines: 1,
                                                        fontSize: 18,
                                                        fontWeight:
                                                            FontWeight.bold,
                                                        color: Theme.of(context)
                                                            .colorScheme
                                                            .blackColor,
                                                      ),
                                                      const CustomSizedBox(
                                                        height: 5,
                                                      ),
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
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                      if (!checkProviderAvailabilityState.error)
                                        Padding(
                                          padding:
                                              const EdgeInsetsDirectional.only(
                                            start: 16,
                                            end: 16,
                                            bottom: 12,
                                          ),
                                          child: CustomRoundedButton(
                                            onTap: () {
                                              if (context
                                                      .read<
                                                          FetchUserCurrentLocationCubit>()
                                                      .state
                                                  is FetchUserCurrentLocationInProgress) {
                                                return;
                                              }
                                              if (widget.showAddressForm) {
                                                //complete address button click
                                                _showAddressSheet(
                                                  context,
                                                );
                                              } else {
                                                //confirm address button click
                                                HiveRepository.setLocationName =
                                                    value["lineOneAddress"];
                                                HiveRepository.setLongitude =
                                                    selectedLongitude;
                                                HiveRepository.setLatitude =
                                                    selectedLatitude;

                                                {
                                                  // Original navigation logic
                                                  Future.delayed(Duration.zero,
                                                      () {
                                                    context
                                                        .read<HomeScreenCubit>()
                                                        .fetchHomeScreenData();

                                                    if (Routes.previousRoute ==
                                                        allowLocationScreenRoute) {
                                                      Navigator.popUntil(
                                                        context,
                                                        (final Route route) =>
                                                            route.isFirst,
                                                      );
                                                    } else {
                                                      Navigator.of(context)
                                                          .pushNamedAndRemoveUntil(
                                                        navigationRoute,
                                                        (final route) => false,
                                                      );
                                                    }
                                                  });
                                                }
                                              }
                                            },
                                            widthPercentage: 1,
                                            backgroundColor: (context
                                                        .watch<
                                                            FetchUserCurrentLocationCubit>()
                                                        .state
                                                    is FetchUserCurrentLocationInProgress)
                                                ? context
                                                    .colorScheme.lightGreyColor
                                                : context
                                                    .colorScheme.accentColor,
                                            buttonTitle: (widget.showAddressForm
                                                    ? 'completeAddress'
                                                    : "confirmAddress")
                                                .translate(context: context),
                                            showBorder: false,
                                          ),
                                        ),
                                    ],
                                  );
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
                              color: context.colorScheme.blackColor
                                  .withValues(alpha: 0.2),
                            )
                          ],
                          borderRadiusStyle: const BorderRadius.only(
                            topLeft: Radius.circular(UiUtils.borderRadiusOf20),
                            topRight: Radius.circular(UiUtils.borderRadiusOf20),
                          ),
                          child: Center(
                            child: (checkProviderAvailabilityState
                                    is CheckProviderAvailabilityFetchFailure)
                                ? CustomText(
                                    checkProviderAvailabilityState.errorMessage)
                                : Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      CustomShimmerLoadingContainer(
                                        height: 10,
                                        width: context.screenWidth * 0.9,
                                        borderRadius: UiUtils.borderRadiusOf10,
                                      ),
                                      const CustomSizedBox(
                                        height: 10,
                                      ),
                                      CustomShimmerLoadingContainer(
                                        height: 10,
                                        width: context.screenWidth * 0.9,
                                        borderRadius: UiUtils.borderRadiusOf10,
                                      ),
                                      const CustomSizedBox(
                                        height: 10,
                                      ),
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
                    ),
                  ],
                ),
              ),
            ),
            Align(
              alignment: Alignment.topCenter,
              child: MapSearchContainer(
                hint: 'searchLocation'.translate(context: context),
                onTap: () {
                  UiUtils.showBottomSheet(
                    enableDrag: true,
                    isScrollControlled: true,
                    useSafeArea: true,
                    child: CustomContainer(
                      padding: EdgeInsetsDirectional.only(
                          bottom: MediaQuery.of(context).viewInsets.bottom),
                      child: const LocationBottomSheet(),
                    ),
                    context: context,
                  ).then((final value) {
                    if (value != null) {
                      if (currentScreenType.value ==
                          GoogleMapScreenType.providerOnMap) {
                        _switchToLocationSelectionMode();
                      }

                      // Don't reset map controller, just update the current one
                      setState(() {
                        currentScreenType.value =
                            GoogleMapScreenType.selectLocationOnMap;
                      });

                      // Place marker at the new location and update address
                      final newLatitude =
                          double.parse(value['latitude'].toString());
                      final newLongitude =
                          double.parse(value['longitude'].toString());
                      _placeMarkerOnLatitudeAndLongitude(
                        latitude: newLatitude,
                        longitude: newLongitude,
                      );
                    }
                  });
                },
              ),
            )
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    mapMarkers.dispose();
    searchText.dispose();
    currentScreenType.dispose();
    selectedProviderIndex.dispose();
    // CarouselSliderController doesn't need to be disposed
    _customInfoWindowController.dispose();
    super.dispose();
  }
}
