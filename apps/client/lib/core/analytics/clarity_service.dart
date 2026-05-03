import 'package:clarity_flutter/clarity_flutter.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../../app/routes.dart';
import 'google_analytics_service.dart';

class ClarityService {
  const ClarityService._();

  static void logAction(
    String action, [
    Map<String, Object?>? parameters,
  ]) {
    final trimmed = action.trim();
    if (trimmed.isEmpty) return;

    GoogleAnalyticsService.logEvent(trimmed, parameters: parameters);

    try {
      Clarity.sendCustomEvent(trimmed);
    } catch (error, stackTrace) {
      _debugPrint('logAction', error, stackTrace);
    }
  }

  static void setUserId(String userId) {
    final trimmed = userId.trim();
    if (trimmed.isEmpty) return;

    GoogleAnalyticsService.setUserId(trimmed);

    try {
      Clarity.setCustomUserId(trimmed);
    } catch (error, stackTrace) {
      _debugPrint('setUserId', error, stackTrace);
    }
  }

  static void setTag(String key, String value) {
    final trimmedKey = key.trim();
    final trimmedValue = value.trim();
    if (trimmedKey.isEmpty || trimmedValue.isEmpty) return;

    GoogleAnalyticsService.setUserProperty(trimmedKey, trimmedValue);

    try {
      Clarity.setCustomTag(trimmedKey, trimmedValue);
    } catch (error, stackTrace) {
      _debugPrint('setTag', error, stackTrace);
    }
  }

  static void setScreenName(String name) {
    final trimmed = name.trim();
    if (trimmed.isEmpty) return;

    GoogleAnalyticsService.logScreenView(trimmed);

    try {
      Clarity.setCurrentScreenName(trimmed);
    } catch (error, stackTrace) {
      _debugPrint('setScreenName', error, stackTrace);
    }
  }

  static String? get currentSessionUrl {
    try {
      return Clarity.getCurrentSessionUrl();
    } catch (error, stackTrace) {
      _debugPrint('currentSessionUrl', error, stackTrace);
      return null;
    }
  }

  static void _debugPrint(
    String method,
    Object error,
    StackTrace stackTrace,
  ) {
    if (kDebugMode) {
      debugPrint(
        '[ClarityService] $method failed: $error\n$stackTrace',
      );
    }
  }
}

class ClarityRouteObserver extends RouteObserver<PageRoute<dynamic>> {
  void _sendScreenView(Route<dynamic>? route) {
    if (route is PageRoute<dynamic>) {
      final screenName = _getScreenName(route);
      ClarityService.setScreenName(screenName);
    }
  }

  String _getScreenName(PageRoute<dynamic> route) {
    var routeName = route.settings.name;

    if (routeName != null && routeName.isNotEmpty) {
      return _formatRouteName(routeName);
    }

    if (Routes.currentRoute.isNotEmpty) {
      return _formatRouteName(Routes.currentRoute);
    }

    final routeType = route.runtimeType.toString();
    if (routeType.contains('CupertinoPageRoute') ||
        routeType.contains('MaterialPageRoute')) {
      return 'Page';
    }

    return _formatRouteName(routeType);
  }

  String _formatRouteName(String routeName) {
    final routeNameMap = {
      '/': 'Splash',
      '/navigationBar': 'Home',
      '/onboarding': 'Onboarding',
      '/login': 'Login',
      '/login/OtpVerification': 'OTP Verification',
      '/allowLocationScreenRoute': 'Allow Location',
      '/countryCodePicker': 'Country Code',
      '/subCategoryScreen': 'Sub Category',
      '/appSettings': 'App Settings',
      '/contactUs': 'Contact Us',
      '/notifications': 'Notifications',
      '/faqs': 'FAQs',
      '/profile': 'Profile',
      '/profile/editProfile': 'Edit Profile',
      '/googleMapScreen': 'Map',
      '/scheduleScreen': 'Schedule Booking',
      '/providerRoute': 'Provider Details',
      '/notAvailable': 'Not Available',
      '/bookmarkRoute': 'Bookmarks',
      '/orderConfirmation': 'Order Confirmation',
      '/promocodeScreen': 'Promo Code',
      '/searchScreen': 'Search',
      '/bookingDetails': 'Booking Details',
      '/paymentDetailsScreen': 'Payment History',
      '/manageAddressScreen': 'Manage Address',
      '/maintenanceModeScreen': 'Maintenance',
      '/appUpdateScreen': 'App Update',
      '/imagePreview': 'Image Preview',
      '/paypalPaymentScreen': 'Payment',
      '/reviewScreen': 'Reviews',
      '/providerServiceDetails': 'Service Details',
      '/chatMessages': 'Chat Messages',
      '/chatUsersList': 'Chat Users',
      '/imagesFullScreenView': 'Image Viewer',
      '/myRequestDetailsScreen': 'My Request Details',
      '/requestServiceFormScreen': 'Request Service',
      '/blockUserScreen': 'Blocked Users',
      '/blogsRoute': 'Blogs',
      '/blogDetailsScreen': 'Blog Details',
    };

    if (routeNameMap.containsKey(routeName)) {
      return routeNameMap[routeName]!;
    }

    //deep link events
    if (routeName.contains('/provider-details/')) {
      return 'Provider Details';
    }

    if (routeName.contains('CupertinoPageRoute') ||
        routeName.contains('MaterialPageRoute')) {
      return 'Page';
    }

    if (routeName.startsWith('/')) {
      routeName = routeName.substring(1);
    }

    routeName = routeName.replaceAll('/', ' ').trim();
    if (routeName.isEmpty) {
      return 'Page';
    }

    final words = routeName.split(RegExp(r'[_\s<>]+'));
    final formatted =
        words.where((w) => w.isNotEmpty && w != 'dynamic').map((word) {
      if (word.isEmpty) return '';
      return word[0].toUpperCase() + word.substring(1).toLowerCase();
    }).join(' ');

    return formatted.isEmpty ? 'Page' : formatted;
  }

  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    super.didPush(route, previousRoute);
    _sendScreenView(route);
  }

  @override
  void didReplace({Route<dynamic>? newRoute, Route<dynamic>? oldRoute}) {
    super.didReplace(newRoute: newRoute, oldRoute: oldRoute);
    _sendScreenView(newRoute);
  }

  @override
  void didPop(Route<dynamic> route, Route<dynamic>? previousRoute) {
    super.didPop(route, previousRoute);
    _sendScreenView(previousRoute);
  }
}

final clarityRouteObserver = ClarityRouteObserver();
