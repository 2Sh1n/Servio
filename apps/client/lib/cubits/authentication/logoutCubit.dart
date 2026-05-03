import 'package:e_demand/cubits/authentication/authenticationCubit.dart';
import 'package:e_demand/cubits/bookmarkCubits/bookmarkCubit.dart';
import 'package:e_demand/cubits/cart/getCartCubit.dart';
import 'package:e_demand/cubits/userDetailsCubit.dart' show UserDetailsCubit;
import 'package:e_demand/data/repository/authenticationRepository.dart';
import 'package:e_demand/core/analytics/analytics_events.dart';
import 'package:e_demand/core/analytics/clarity_service.dart';
import 'package:e_demand/utils/appQuickActions.dart';
import 'package:e_demand/utils/uiUtils.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class LogoutState {}

class LogoutInitial extends LogoutState {}

class LogoutLoading extends LogoutState {}

class LogoutSuccess extends LogoutState {}

class LogoutFailure extends LogoutState {}

class LogoutCubit extends Cubit<LogoutState> {
  final AuthenticationRepository authRepository;

  LogoutCubit(this.authRepository) : super(LogoutInitial());

  Future<void> logout(BuildContext context) async {
    try {
      emit(LogoutLoading());
      String fcmId = '';
      try {
        fcmId = await FirebaseMessaging.instance.getToken() ?? '';
      } catch (_) {}

      // Store cubit references before they might become unavailable
      late AuthenticationCubit authCubit;
      late UserDetailsCubit userDetailsCubit;
      late CartCubit cartCubit;
      late BookmarkCubit bookmarkCubit;

      try {
        authCubit = context.read<AuthenticationCubit>();
        userDetailsCubit = context.read<UserDetailsCubit>();
        cartCubit = context.read<CartCubit>();
        bookmarkCubit = context.read<BookmarkCubit>();
      } catch (e) {
        debugPrint('Error reading cubit references in logout: $e');
        emit(LogoutFailure());
        return;
      }

      final error = await authRepository.logoutUser(
        fcmId: fcmId,
      );

      if (!error) {
        ClarityService.logAction(
          ClarityActions.logout,
          {
            'fcm_id': fcmId,
          },
        );
        // Clear Cubits using stored references
        await UiUtils.clearUserData();

        // Use stored references safely
        authCubit.checkStatus();
        userDetailsCubit.clearCubit();
        cartCubit.clearCartCubit();
        bookmarkCubit.clearBookMarkCubit();

        AppQuickActions.createAppQuickActions();

        emit(LogoutSuccess());
      } else {
        emit(LogoutFailure());
      }
    } catch (e) {
      debugPrint('Error in logout method: $e');
      emit(LogoutFailure());
    }
  }
}
