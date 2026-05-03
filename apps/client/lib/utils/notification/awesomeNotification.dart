import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class LocalAwesomeNotification {
  static AwesomeNotifications notification = AwesomeNotifications();

  static String notificationChannel = "basic_channel";
  static String chatNotificationChannel = "chat_notification";

  static Future<void> init(final BuildContext context) async {
    await requestPermission();
    await NotificationService.init(context);

    notification.initialize(
      'resource://drawable/ic_launcher_foreground',
      [
        NotificationChannel(
          playSound: true,
          channelKey: notificationChannel,
          channelName: 'Basic notifications',
          channelDescription: 'Notification channel',
          importance: NotificationImportance.High,
          ledColor: Colors.white,
        ),
        NotificationChannel(
            channelKey: chatNotificationChannel,
            channelName: "Chat notifications",
            channelDescription: "Notification related to chat",
            vibrationPattern: mediumVibrationPattern,
            importance: NotificationImportance.High)
      ],
      channelGroups: [],
    );
    await setupAwesomeNotificationListeners();
  }

  /// Use this method to detect when the user taps on a notification or action button
  @pragma("vm:entry-point")
  static Future<void> onActionReceivedMethod(
    ReceivedAction event,
  ) async {
    if (Platform.isAndroid) {
      final data = event.payload;
      if (data != null) {
        await NotificationService.handleNotificationRedirection(data);
      }
    }
  }

  /// Use this method to detect when a new notification or a schedule is created
  @pragma("vm:entry-point")
  static Future<void> onNotificationCreatedMethod(
      ReceivedNotification receivedNotification) async {
    // Your code goes here
  }

  /// Use this method to detect every time that a new notification is displayed
  @pragma("vm:entry-point")
  static Future<void> onNotificationDisplayedMethod(
      ReceivedNotification receivedNotification) async {
    // Your code goes here
  }

  /// Use this method to detect if the user dismissed a notification
  @pragma("vm:entry-point")
  static Future<void> onDismissActionReceivedMethod(
      ReceivedAction receivedAction) async {
    // Your code goes here
  }

  @pragma('vm:entry-point')
  static Future<void> setupAwesomeNotificationListeners() async {
    // Only after at least the action method is set, the notification events are delivered
    notification.setListeners(
        onActionReceivedMethod: LocalAwesomeNotification.onActionReceivedMethod,
        onNotificationCreatedMethod:
            LocalAwesomeNotification.onNotificationCreatedMethod,
        onNotificationDisplayedMethod:
            LocalAwesomeNotification.onNotificationDisplayedMethod,
        onDismissActionReceivedMethod:
            LocalAwesomeNotification.onDismissActionReceivedMethod);
  }

  Future<void> createNotification({
    required final RemoteMessage notificationData,
    required final bool isLocked,
  }) async {
    try {
      await notification
          .createNotification(
            content: NotificationContent(
              id: Random().nextInt(5000),
              title: notificationData.data["title"] ?? '',
              locked: isLocked,
              payload: Map.from(notificationData.data),
              autoDismissible: true,
              body: notificationData.data["body"] ?? '',
              color: const Color.fromARGB(255, 79, 54, 244),
              wakeUpScreen: true,
              channelKey: notificationChannel,
              notificationLayout: NotificationLayout.BigText,
            ),
          )
          .then((final bool value) {})
          .onError((final error, stackTrace) {});
    } catch (_) {}
  }

  Future<void> createImageNotification({
    required final RemoteMessage notificationData,
    required final bool isLocked,
  }) async {
    try {
      await notification
          .createNotification(
            content: NotificationContent(
              id: Random().nextInt(5000),
              title: notificationData.data["title"] ?? '',
              locked: isLocked,
              payload: Map.from(notificationData.data),
              autoDismissible: true,
              body: notificationData.data["body"] ?? '',
              color: const Color.fromARGB(255, 79, 54, 244),
              wakeUpScreen: true,
              channelKey: notificationChannel,
              largeIcon: notificationData.data["image"] ?? '',
              bigPicture: notificationData.data["image"] ?? '',
              notificationLayout: NotificationLayout.BigPicture,
            ),
          )
          .then((final value) {})
          .onError((final error, final stackTrace) {});
    } catch (_) {}
  }

  static Future<void> requestPermission() async {
    final NotificationSettings notificationSettings =
        await FirebaseMessaging.instance.getNotificationSettings();

    if (notificationSettings.authorizationStatus ==
        AuthorizationStatus.notDetermined) {
      await FirebaseMessaging.instance.requestPermission();

      if (notificationSettings.authorizationStatus ==
              AuthorizationStatus.authorized ||
          notificationSettings.authorizationStatus ==
              AuthorizationStatus.provisional) {}
    } else if (notificationSettings.authorizationStatus ==
        AuthorizationStatus.denied) {
      return;
    }
  }
}
