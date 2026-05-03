import 'package:flutter/material.dart';

import '../../../../app/generalImports.dart';

class DeleteUserAccountDialog extends StatelessWidget {
  const DeleteUserAccountDialog({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<DeleteUserAccountCubit, DeleteUserAccountState>(
      listener: (final BuildContext context,
          final DeleteUserAccountState state) async {
        if (state is DeleteUserAccountSuccess) {
          final bool response = await UiUtils.clearUserData();

          if (response) {
            UiUtils.showMessage(
              context,
              'accountDeletedSuccessfully'.translate(context: context),
              ToastificationType.success,
            );

            Future.delayed(Duration.zero, () {
              context.read<AuthenticationCubit>().checkStatus();
              context.read<UserDetailsCubit>().clearCubit();
              context.read<CartCubit>().clearCartCubit();
              context.read<BookmarkCubit>().clearBookMarkCubit();

              AppQuickActions.createAppQuickActions();

              Navigator.pop(context, true);
            });
          } else {
            UiUtils.showMessage(
              context,
              'somethingWentWrong'.translate(context: context),
              ToastificationType.error,
            );
            Navigator.pop(context, true);
          }
        } else if (state is DeleteUserAccountFailure) {
          UiUtils.showMessage(
            context,
            state.errorMessage,
            ToastificationType.error,
          );
          Navigator.pop(context, true);
        }
      },
      builder:
          (final BuildContext context, final DeleteUserAccountState state) {
        Widget? child;

        if (state is DeleteUserAccountInProgress) {
          child = Padding(
            padding: const EdgeInsets.all(5),
            child: CustomSizedBox(
              height: 30,
              width: 25,
              child: CustomCircularProgressIndicator(
                color: context.colorScheme.secondaryColor,
              ),
            ),
          );
        }

        return CustomDialogLayout(
            showProgressIndicator: false,
            title: "deleteAccount",
            description: "deleteAccountWarning",
            confirmButtonName: "delete",
            cancelButtonName: "cancel",
            cancelButtonPressed: () {
              if (state is DeleteUserAccountInProgress) {
                return;
              }
              Navigator.pop(context);
            },
            confirmButtonPressed: () {
              context.read<DeleteUserAccountCubit>().deleteUserAccount();
            },
            confirmButtonBackgroundColor: AppColors.redColor,
            cancelButtonBackgroundColor: context.colorScheme.secondaryColor,
            confirmButtonChild: child);
      },
    );
  }
}
