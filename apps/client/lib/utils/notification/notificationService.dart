import 'package:e_demand/app/generalImports.dart';
import 'dart:developer' as developer;

import 'package:e_demand/cubits/fetchBookingDetailsCubit.dart';
import 'package:e_demand/cubits/authentication/logoutCubit.dart';
import 'package:e_demand/cubits/blogs/blogsCubit.dart';
import 'package:flutter/material.dart';

@pragma('vm:entry-point')
Future<void> onBackgroundMessageHandler(final RemoteMessage message) async {
  // Handle user_account_deactive automatically - logout and redirect
  if (message.data["type"] == "user_account_deactive") {
    await NotificationService.handleUserAccountDeactive(
      title:
          message.data["title"] ??
          message.notification?.title ??
          'Account Deactivated',
      body: message.data["body"] ?? message.notification?.body ?? '',
    );
    return;
  }

  // Handle booking_status and all booking notification types automatically - update status in list
  if (message.data["type"] == "booking_status" ||
      message.data["type"] == "booking_confirmed" ||
      message.data["type"] == "booking_rescheduled" ||
      message.data["type"] == "booking_cancelled" ||
      message.data["type"] == "booking_completed" ||
      message.data["type"] == "booking_started" ||
      message.data["type"] == "booking_ended") {
    await NotificationService.handleBookingStatusUpdate(message.data);
    // Continue to show notification
  }

  if (message.data["type"] == "chat" || message.data["type"] == "new_message") {
    //background chat message storing
    final List<ChatNotificationData> oldList =
        await ChatNotificationsRepository().getBackgroundChatNotificationData();
    final messageChatData = ChatNotificationData.fromRemoteMessage(
      remoteMessage: message,
    );
    oldList.add(messageChatData);
    ChatNotificationsRepository().setBackgroundChatNotificationData(
      data: oldList,
    );
    if (Platform.isAndroid) {
      ChatNotificationsUtils.createChatNotification(
        chatData: messageChatData,
        message: message,
      );
    }
  } else {
    if (message.data["image"] == null) {
      localNotification.createNotification(
        isLocked: false,
        notificationData: message,
      );
    } else {
      localNotification.createImageNotification(
        isLocked: false,
        notificationData: message,
      );
    }
  }
}

LocalAwesomeNotification localNotification = LocalAwesomeNotification();

class NotificationService {
  static FirebaseMessaging messagingInstance = FirebaseMessaging.instance;

  final notification = AwesomeNotifications();
  static late StreamSubscription<RemoteMessage> foregroundStream;
  static late StreamSubscription<RemoteMessage> onMessageOpen;

  static Future<void> requestPermission() async {
    try {
      final notificationSettings = await FirebaseMessaging.instance
          .getNotificationSettings();
      if (notificationSettings.authorizationStatus ==
              AuthorizationStatus.notDetermined ||
          notificationSettings.authorizationStatus ==
              AuthorizationStatus.denied) {
        await FirebaseMessaging.instance.requestPermission(
          alert: true,
          badge: true,
          sound: true,
          provisional: false,
        );
      }
    } catch (e) {
      debugPrint('Error requesting notification permissions: $e');
    }
  }

  static Future<void> init(final context) async {
    try {
      await ChatNotificationsUtils.initialize();
      await requestPermission();
      await registerListeners(context);
    } catch (e) {
      debugPrint('Error initializing notifications: $e');
    }
  }

  static Future<void> handleBookingStatusUpdate(
    Map<String, dynamic> data,
  ) async {
    // Automatically update booking status in BookingCubit when received
    // Fetch full booking details to get translated status
    try {
      final String bookingId =
          data['booking_id']?.toString() ?? data['order_id']?.toString() ?? '';

      if (bookingId.isEmpty) {
        return;
      }

      // Fetch full booking details to get translated status
      final FetchBookingDetailsCubit bookingDetailsCubit =
          FetchBookingDetailsCubit();

      await bookingDetailsCubit.fetchBookingDetails(bookingId: bookingId);

      final state = bookingDetailsCubit.state;
      if (state is FetchBookingDetailsSuccess) {
        final BuildContext? context = UiUtils.rootNavigatorKey.currentContext;
        if (context != null) {
          try {
            final bookingCubit = context.read<BookingCubit>();
            await bookingCubit.updateBookingDataLocally(
              latestBookingData: state.booking,
            );
          } catch (e) {
            debugPrint('Error updating booking data: $e');
          }
        }
      }
    } catch (e) {
      debugPrint('Error handling booking status update: $e');
    }
  }

  static Future<void> handleUserAccountDeactive({
    String? title,
    String? body,
  }) async {
    // Logout user and redirect to contact us page when account is deactivated
    try {
      final BuildContext? context = UiUtils.rootNavigatorKey.currentContext;
      if (context == null) {
        debugPrint('Error: No context available for logout');
        // Fallback: try direct logout without cubit
        try {
          String fcmId = '';
          try {
            fcmId = await FirebaseMessaging.instance.getToken() ?? '';
          } catch (_) {}
          final authRepository = AuthenticationRepository();
          await authRepository.logoutUser(fcmId: fcmId);
          await UiUtils.clearUserData();
        } catch (_) {}
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(contactUsRoute);
        return;
      }

      // Show dialog with notification message first
      await UiUtils.showAnimatedDialog(
        context: context,
        child: _AccountDeactivatedDialog(
          title: title ?? 'Account Deactivated',
          body:
              body ??
              'Your account has been deactivated. Please contact support for assistance.',
          onOkPressed: () {
            Navigator.of(context).pop();
          },
        ),
      );

      // Proceed with logout after dialog is closed
      // Use LogoutCubit for proper logout flow
      final logoutCubit = context.read<LogoutCubit>();
      await logoutCubit.logout(context);

      // Navigate to contact us page after logout
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(contactUsRoute);
    } catch (e) {
      debugPrint('Error handling user_account_deactive notification: $e');
      // Still navigate to contact us page even if logout fails
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(contactUsRoute);
    }
  }

  static Future<void> _handleMaintenanceModeNotification(
    Map<String, dynamic> data,
  ) async {
    // Step 1: Get message from notification data or use empty string
    final String message = data['message']?.toString() ?? '';

    // Step 2: Create instance for this notification
    final SystemSettingCubit systemSettingCubit = SystemSettingCubit(
      settingRepository: SettingRepository(),
    );

    StreamSubscription? subscription;
    try {
      // Step 3: Set up stream listener to wait for state completion
      final completer = Completer<void>();
      bool isCompleted = false;

      subscription = systemSettingCubit.stream.listen((state) {
        if (!isCompleted) {
          if (state is SystemSettingFetchSuccess) {
            isCompleted = true;
            if (!completer.isCompleted) {
              completer.complete();
            }
          } else if (state is SystemSettingFetchFailure) {
            isCompleted = true;
            if (!completer.isCompleted) {
              completer.complete();
            }
          }
        }
      });

      // Step 4: Call settings API first
      await systemSettingCubit.getSystemSettings();

      // Step 5: Wait for state to complete (success or failure)
      await completer.future.timeout(
        const Duration(seconds: 10),
        onTimeout: () {
          isCompleted = true;
          debugPrint('Timeout waiting for system settings');
        },
      );

      // Step 6: Check if settings API call was successful
      final state = systemSettingCubit.state;
      if (state is SystemSettingFetchSuccess) {
        // Step 7: Check if maintenance mode is enabled in settings
        if (systemSettingCubit.appSetting?.customerAppMaintenanceMode == "1") {
          // Step 8: Navigate to maintenance mode screen only if maintenance mode is enabled
          await UiUtils.rootNavigatorKey.currentState?.pushNamedAndRemoveUntil(
            maintenanceModeScreen,
            (route) => false,
            arguments: message,
          );
        }
      }
    } catch (e) {
      debugPrint('Error handling maintenance_mode notification: $e');
    } finally {
      // Cancel subscription and dispose the cubit instance
      await subscription?.cancel();
      systemSettingCubit.close();
    }
  }

  static Future<void> handleNotificationRedirection(
    Map<String, dynamic> data,
  ) async {
    developer.log('HERE: NOTIFICATION TAP DATA: $data');
    ClarityService.logAction(ClarityActions.notificationOpened, {
      'notification_type': data['type']?.toString() ?? 'unknown',
      'campaign': data['campaign']?.toString() ?? '',
      'notification_id': data['notification_id']?.toString() ?? '',
    });
    if (data["type"] == "chat" || data["type"] == "new_message") {
      //get off the route if already on it
      if (Routes.currentRoute == chatMessages) {
        UiUtils.rootNavigatorKey.currentState?.pop();
      }
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(
        chatMessages,
        arguments: {"chatUser": ChatUser.fromNotificationData(data)},
      );
    } else if (data["type"] == "general") {
      // Handle general notifications - redirect to notification screen
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(notificationRoute);
    } else if (data["type"] == "category") {
      // Use parent_id when available (from push notification),
      // otherwise fall back to treating type_id/category_id as main category
      final String parentId = data["parent_id"]?.toString() ?? '';
      final String categoryId = data["category_id"]?.toString() ?? '';

      if (parentId.isEmpty || parentId == "0") {
        // Main category
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          subCategoryRoute,
          arguments: {
            'subCategoryId': '',
            'categoryId': categoryId,
            'appBarTitle': data["category_name"],
            'type': CategoryType.category,
          },
        );
      } else {
        // Subcategory
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          subCategoryRoute,
          arguments: {
            'subCategoryId': categoryId,
            'categoryId': '',
            'appBarTitle': data["category_name"],
            'type': CategoryType.subcategory,
          },
        );
      }
    } else if (data["type"] == "provider") {
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(
        providerRoute,
        arguments: {'providerId': data["provider_id"]},
      );
    } else if (data["type"] == "order") {
      // Pass bookingId directly to let the screen handle loading with shimmer
      try {
        final String orderId = data['order_id']?.toString() ?? '';

        if (orderId.isEmpty) {
          return;
        }

        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          bookingDetails,
          arguments: {'bookingId': orderId},
        );
      } catch (_) {}
    } else if (data["type"] == "url") {
      final url = data["url"].toString();
      try {
        if (await canLaunchUrl(Uri.parse(url))) {
          await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
        } else {
          throw 'Could not launch $url';
        }
      } catch (e) {
        throw 'Something went wrong';
      }
    } else if (data["type"] == "user_account_active") {
      // Redirect to login page when account is activated
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(loginRoute);
    } else if (data["type"] == "user_account_deactive") {
      // Just redirect to contact us page on tap
      // (logout already happened automatically when notification was received)
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(contactUsRoute);
    } else if (data["type"] == "user_blocked" ||
        data["type"] == "user_reported") {
      // Handle user_blocked and user_reported - redirect to chat messages screen
      // Create ChatUser from notification data to navigate to chat
      try {
        final ChatUser chatUser = ChatUser.fromNotificationData(data);
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          chatMessages,
          arguments: {"chatUser": chatUser},
        );
      } catch (e) {
        debugPrint('Error creating ChatUser from notification data: $e');
        // Fallback to chat users list if ChatUser creation fails
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(chatUsersList);
      }
    } else if (data["type"] == "new_blog") {
      // Handle new_blog notification - refresh blog list and redirect to blog details screen
      try {
        final String blogId = data['blog_id']?.toString() ?? '';

        if (blogId.isEmpty) {
          return;
        }

        // Refresh blog list cubit
        final BuildContext? context = UiUtils.rootNavigatorKey.currentContext;
        if (context != null) {
          try {
            context.read<BlogsCubit>().getBlogs();
          } catch (e) {
            debugPrint('Error refreshing blog list: $e');
          }
        }

        // Navigate to blog details screen
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          blogDetailsScreen,
          arguments: {'blogId': blogId},
        );
      } catch (_) {}
    } else if (data["type"] == "terms_and_conditions_changed") {
      // Handle terms_and_conditions_changed notification - redirect to terms and conditions screen
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(
        appSettingsRoute,
        arguments: "termsofservice",
      );
    } else if (data["type"] == "privacy_policy_changed") {
      // Handle privacy_policy_changed notification - redirect to privacy policy screen
      await UiUtils.rootNavigatorKey.currentState?.pushNamed(
        appSettingsRoute,
        arguments: "privacyAndPolicy",
      );
    } else if (data["type"] == "maintenance_mode") {
      // Handle maintenance_mode notification - refresh settings, enable maintenance mode, and redirect
      await _handleMaintenanceModeNotification(data);
    } else if (data["type"] == "bid" ||
        data["type"] == "bid_on_custom_job_request") {
      // Handle bid notification - redirect to custom job request details
      try {
        final String customJobRequestId =
            data['custom_job_request_id']?.toString() ?? '';

        if (customJobRequestId.isEmpty) {
          return;
        }

        // Fetch request details to get status and translatedStatus
        final MyRequestListCubit requestListCubit = MyRequestListCubit();
        await requestListCubit.fetchRequests(id: customJobRequestId);

        String status = data['status']?.toString() ?? '';
        String translatedStatus = data['translated_status']?.toString() ?? '';

        // Find the matching request in the list to get status and translatedStatus
        final state = requestListCubit.state;
        if (state is MyRequestListSuccess) {
          final matchingRequest = state.requestList.first;

          if (matchingRequest.id != null) {
            status = matchingRequest.status ?? status;
            translatedStatus =
                matchingRequest.translatedStatus ?? translatedStatus;
          }
        }

        // Navigate to job details screen
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          myRequestDetailsScreen,
          arguments: {
            'customJobRequestId': customJobRequestId,
            'status': status,
            'translatedStatus': translatedStatus,
          },
        );
      } catch (_) {}
    } else if (data["type"] == "booking_status" ||
        data["type"] == "booking_confirmed" ||
        data["type"] == "booking_rescheduled" ||
        data["type"] == "booking_cancelled" ||
        data["type"] == "booking_completed" ||
        data["type"] == "booking_started" ||
        data["type"] == "booking_ended") {
      // Handle booking status notifications - update status and redirect to booking details
      // Pass bookingId directly to let the screen handle loading with shimmer
      try {
        final String bookingId =
            data['booking_id']?.toString() ??
            data['order_id']?.toString() ??
            '';

        if (bookingId.isEmpty) {
          return;
        }

        // Update booking status automatically (in background)
        handleBookingStatusUpdate(data);

        // Redirect to booking details with bookingId for shimmer loading
        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          bookingDetails,
          arguments: {'bookingId': bookingId},
        );
      } catch (_) {}
    } else if (data["type"] == "payment" ||
        data["type"] == "new_booking_confirmation_to_customer" ||
        data["type"] == "rating_request" ||
        data["type"] == "additional_charges") {
      // Handle payment and booking confirmation - redirect to booking details
      // Pass bookingId directly to let the screen handle loading with shimmer
      try {
        final String bookingId =
            data['booking_id']?.toString() ??
            data['order_id']?.toString() ??
            '';

        if (bookingId.isEmpty) {
          return;
        }

        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          bookingDetails,
          arguments: {'bookingId': bookingId},
        );
      } catch (_) {}
    } else if (data["booking_id"] != null && data["booking_id"] != '') {
      // Fallback for any notification with booking_id
      // Pass bookingId directly to let the screen handle loading with shimmer
      try {
        final String bookingId =
            data['booking_id']?.toString() ??
            data['order_id']?.toString() ??
            '';

        if (bookingId.isEmpty) {
          return;
        }

        await UiUtils.rootNavigatorKey.currentState?.pushNamed(
          bookingDetails,
          arguments: {'bookingId': bookingId},
        );
      } catch (_) {}
    }
  }

  static Future foregroundNotificationHandler() async {
    try {
      foregroundStream = FirebaseMessaging.onMessage.listen(
        (final RemoteMessage message) async {
          debugPrint('Received foreground message: ${message.data}');

          // Handle user_account_deactive automatically - logout and redirect
          if (message.data["type"] == "user_account_deactive") {
            await handleUserAccountDeactive(
              title:
                  message.data["title"] ??
                  message.notification?.title ??
                  'accountDeactivated'.translate(
                    context: UiUtils.rootNavigatorKey.currentContext!,
                  ),
              body: message.data["body"] ?? message.notification?.body ?? '',
            );
            return;
          }

          // Handle booking_status and all booking notification types automatically - update status in list
          if (message.data["type"] == "booking_status" ||
              message.data["type"] == "booking_confirmed" ||
              message.data["type"] == "booking_rescheduled" ||
              message.data["type"] == "booking_cancelled" ||
              message.data["type"] == "booking_completed" ||
              message.data["type"] == "booking_started" ||
              message.data["type"] == "booking_ended") {
            await handleBookingStatusUpdate(message.data);
            // Continue to show notification
          }

          if (message.data["type"] == "chat" ||
              message.data["type"] == "new_message") {
            ChatNotificationsUtils.addChatStreamAndShowNotification(
              message: message,
            );
          } else {
            if (message.data.isEmpty) {
              await localNotification.createNotification(
                isLocked: false,
                notificationData: message,
              );
            } else if (message.data["image"] == null) {
              await localNotification.createNotification(
                isLocked: false,
                notificationData: message,
              );
            } else {
              await localNotification.createImageNotification(
                isLocked: false,
                notificationData: message,
              );
            }
          }
        },
        onError: (error) {
          debugPrint('Error in foreground notification handler: $error');
        },
      );
    } catch (e) {
      debugPrint('Error setting up foreground notification handler: $e');
    }
  }

  static Future terminatedStateNotificationHandler() async {
    FirebaseMessaging.instance.getInitialMessage().then((
      final RemoteMessage? message,
    ) async {
      if (message == null) {
        return;
      }
      // Handle user_account_deactive automatically - logout and redirect
      if (message.data["type"] == "user_account_deactive") {
        await handleUserAccountDeactive(
          title:
              message.data["title"] ??
              message.notification?.title ??
              'accountDeactivated'.translate(
                context: UiUtils.rootNavigatorKey.currentContext!,
              ),
          body: message.data["body"] ?? message.notification?.body ?? '',
        );
      } else if (message.data["type"] == "booking_status" ||
          message.data["type"] == "booking_confirmed" ||
          message.data["type"] == "booking_rescheduled" ||
          message.data["type"] == "booking_cancelled" ||
          message.data["type"] == "booking_completed" ||
          message.data["type"] == "booking_started" ||
          message.data["type"] == "booking_ended") {
        // Update booking status automatically
        await handleBookingStatusUpdate(message.data);
        // Then handle redirection
        await handleNotificationRedirection(message.data);
      } else {
        await handleNotificationRedirection(message.data);
      }
    });
  }

  static Future<void> onTapNotificationHandler() async {
    onMessageOpen = FirebaseMessaging.onMessageOpenedApp.listen((
      final message,
    ) async {
      // Handle user_account_deactive on tap - just redirect to contact us
      // (logout already happened automatically when notification was received)
      if (message.data["type"] == "user_account_deactive") {
        await UiUtils.rootNavigatorKey.currentState?.pushNamedAndRemoveUntil(
          contactUsRoute,
          (route) => false,
        );
        return;
      }
      await handleNotificationRedirection(message.data);
    });
  }

  static Future<void> registerListeners(final context) async {
    try {
      await terminatedStateNotificationHandler();

      await FirebaseMessaging.instance
          .setForegroundNotificationPresentationOptions(
            alert: true,
            badge: true,
            sound: true,
          );

      FirebaseMessaging.onBackgroundMessage(onBackgroundMessageHandler);
      await foregroundNotificationHandler();
      await onTapNotificationHandler();
    } catch (e) {
      debugPrint('Error registering notification listeners: $e');
    }
  }

  static void disposeListeners() {
    ChatNotificationsUtils.dispose();

    onMessageOpen.cancel();
    foregroundStream.cancel();
  }
}

class _AccountDeactivatedDialog extends StatelessWidget {
  final String title;
  final String body;
  final VoidCallback onOkPressed;

  const _AccountDeactivatedDialog({
    required this.title,
    required this.body,
    required this.onOkPressed,
  });

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      avoideResponsive: true,
      height: context.screenHeight * 0.35,
      color: context.colorScheme.secondaryColor,
      borderRadius: UiUtils.borderRadiusOf10,
      alignment: Alignment.center,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: CustomSizedBox(
              height: context.screenHeight * 0.35 - 80,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    const CustomSizedBox(height: 20),
                    CustomContainer(
                      height: 70,
                      width: 70,
                      padding: const EdgeInsets.all(10),
                      color: context.colorScheme.secondaryColor,
                      borderRadius: UiUtils.borderRadiusOf50,
                      child: const Icon(
                        Icons.warning_amber_rounded,
                        color: AppColors.redColor,
                        size: 70,
                      ),
                    ),
                    const CustomSizedBox(height: 20),
                    CustomText(
                      title,
                      color: context.colorScheme.blackColor,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                    const CustomSizedBox(height: 15),
                    CustomText(
                      body,
                      color: context.colorScheme.lightGreyColor,
                      fontSize: 14,
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: CustomInkWellContainer(
              onTap: onOkPressed,
              child: CustomContainer(
                avoideResponsive: true,
                height: 50,
                margin: const EdgeInsets.all(15),
                borderRadius: UiUtils.borderRadiusOf10,
                color: AppColors.redColor,
                child: Center(
                  child: CustomText(
                    'ok'.translate(
                      context: UiUtils.rootNavigatorKey.currentContext!,
                    ),
                    color: AppColors.whiteColors,
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
