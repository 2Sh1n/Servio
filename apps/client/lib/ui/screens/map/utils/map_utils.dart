import 'package:flutter/material.dart';
import 'package:carousel_slider/carousel_slider.dart';
import '../../../../app/generalImports.dart';
import '../../../../data/model/providerMapModel.dart';

class MapUtils {
  // Zoom to marker location with retry logic
  static Future<void> zoomToMarkerLocation(
    Completer<GoogleMapController> controller,
    double? selectedLatitude,
    double? selectedLongitude,
  ) async {
    if (selectedLatitude == null || selectedLongitude == null) return;

    try {
      await Future.delayed(const Duration(milliseconds: 500));
      for (int i = 0; i < 15; i++) {
        try {
          if (controller.isCompleted) {
            final GoogleMapController mapController = await controller.future;
            final newCameraPosition =
                CameraUpdate.newCameraPosition(CameraPosition(
              zoom: 17,
              target: LatLng(selectedLatitude, selectedLongitude),
            ));
            await mapController.animateCamera(newCameraPosition);
            break;
          }
        } catch (e) {
          await Future.delayed(const Duration(milliseconds: 200));
        }
      }
    } catch (e) {
      debugPrint("Error in zoomToMarkerLocation: $e");
    }
  }

  // Zoom to provider location
  static Future<void> zoomToProviderLocation(
    Completer<GoogleMapController> controller,
    double latitude,
    double longitude,
  ) async {
    try {
      await Future.delayed(const Duration(milliseconds: 500));
      for (int i = 0; i < 15; i++) {
        try {
          if (controller.isCompleted) {
            final GoogleMapController mapController = await controller.future;
            final newCameraPosition =
                CameraUpdate.newCameraPosition(CameraPosition(
              zoom: 14,
              target: LatLng(latitude, longitude),
            ));
            await mapController.animateCamera(newCameraPosition);

            break;
          }
        } catch (e) {
          await Future.delayed(const Duration(milliseconds: 200));
        }
      }
    } catch (e) {
      debugPrint("Error in zoomToProviderLocation: $e");
    }
  }

  // Update provider markers on map
  static Set<Marker> updateProviderMarkers(
    List<ProviderMapModel> providers,
    Set<Marker> existingMarkers,
    ValueNotifier<int> selectedProviderIndex,
    CarouselSliderController providerCarouselController,
  ) {
    final Set<Marker> providerMarkers = providers.asMap().entries.map((entry) {
      final index = entry.key;
      final provider = entry.value;
      return Marker(
        markerId: MarkerId("provider_${provider.id}"),
        position: LatLng(provider.latitude, provider.longitude),
        onTap: () {
          selectedProviderIndex.value = index;
          providerCarouselController.animateToPage(
            index,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeInOut,
          );
        },
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueBlue),
      );
    }).toSet();

    final Set<Marker> nonProviderMarkers = existingMarkers
        .where((marker) => !marker.markerId.value.startsWith("provider_"))
        .toSet();

    return {...nonProviderMarkers, ...providerMarkers};
  }

  // Create address from coordinates
  static Future<Map<String, String>> createAddressFromCoordinates(
      double latitude, double longitude) async {
    try {
      final List<Placemark> placeMark =
          await placemarkFromCoordinates(latitude, longitude);
      final name = placeMark[0].name;
      final subLocality = placeMark[0].subLocality;
      final locality = placeMark[0].locality;
      final country = placeMark[0].country;
      final administrativeArea = placeMark[0].administrativeArea;
      final String? postalCode = placeMark[0].postalCode;

      return {
        "lineOneAddress":
            _filterAddressString("$name,$subLocality,$locality,$country"),
        "lineTwoAddress":
            _filterAddressString("$postalCode,$locality,$administrativeArea"),
        "selectedCity": locality ?? ''
      };
    } catch (e) {
      debugPrint("Error in createAddressFromCoordinates: $e");
      return {"lineOneAddress": '', "lineTwoAddress": '', "selectedCity": ''};
    }
  }

  // Helper method to filter address string
  static String _filterAddressString(String address) {
    return address
        .split(',')
        .where((part) => part.trim().isNotEmpty)
        .join(', ');
  }

  // Reset map controller
  static Completer<GoogleMapController> resetMapController() {
    return Completer<GoogleMapController>();
  }

  // Create location marker
  static Marker createLocationMarker(double latitude, double longitude,
      {bool isFromProviderMap = false}) {
    return Marker(
      markerId: const MarkerId("1"),
      position: LatLng(latitude, longitude),
      icon: BitmapDescriptor.defaultMarkerWithHue(isFromProviderMap
          ? BitmapDescriptor.hueBlue
          : BitmapDescriptor.hueRed),
      onTap: () {},
    );
  }
}
