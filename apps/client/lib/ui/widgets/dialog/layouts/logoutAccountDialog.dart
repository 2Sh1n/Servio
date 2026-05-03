import 'package:e_demand/cubits/authentication/logoutCubit.dart';
import 'package:flutter/material.dart';

import '../../../../app/generalImports.dart';

class LogoutAccountDialog extends StatelessWidget {
  const LogoutAccountDialog({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<LogoutCubit, LogoutState>(
      listener: (context, state) {
        if (state is LogoutSuccess) {
          Navigator.of(context).pop(true);
          UiUtils.showMessage(
              context,
              'loggedOutSuccessfully'.translate(context: context),
              ToastificationType.success);
        }
        if (state is LogoutFailure) {
          UiUtils.showMessage(
              context,
              'loggedOutFailed'.translate(context: context),
              ToastificationType.error);
        }
      },
      builder: (context, state) {
        return CustomDialogLayout(
          showProgressIndicator: state is LogoutLoading,
          icon: CustomContainer(
              height: 70,
              width: 70,
              padding: const EdgeInsets.all(10),
              color: Theme.of(context).colorScheme.secondaryColor,
              borderRadius: UiUtils.borderRadiusOf50,
              child: Icon(Icons.help,
                  color: Theme.of(context).colorScheme.accentColor, size: 70)),
          title: "confirmLogout",
          description: "areYouSureYouWantToLogout",
          cancelButtonName: "cancel",
          cancelButtonBackgroundColor:
              Theme.of(context).colorScheme.secondaryColor,
          cancelButtonPressed: () {
            Navigator.of(context).pop();
          },
          confirmButtonName: "logout",
          confirmButtonBackgroundColor: AppColors.redColor,
          confirmButtonPressed: () {
            if (state is LogoutLoading) {
              return;
            }
            context.read<LogoutCubit>().logout(context);
          },
        );
      },
    );
  }
}
