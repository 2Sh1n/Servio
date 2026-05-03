import 'package:flutter/material.dart';

import '../../../../app/generalImports.dart';

class DeleteAddressDialog extends StatelessWidget {
  final VoidCallback onConfirmButtonPressed;

  const DeleteAddressDialog({super.key, required this.onConfirmButtonPressed});

  @override
  Widget build(BuildContext context) {
    return CustomDialogLayout(
      showProgressIndicator: false,
      icon: CustomContainer(
          height: 70,
          width: 70,
          padding: const EdgeInsets.all(10),
          color: context.colorScheme.secondaryColor,
          borderRadius: UiUtils.borderRadiusOf50,
          child: Icon(Icons.help,
              color: context.colorScheme.accentColor, size: 70)),
      title: "deleteAddress",
      description: "doYouReallyWantToDelete",
      cancelButtonName: "cancel",
      cancelButtonBackgroundColor: context.colorScheme.secondaryColor,
      cancelButtonPressed: () {
        Navigator.of(context).pop();
      },
      confirmButtonName: "delete",
      confirmButtonBackgroundColor: AppColors.redColor,
      confirmButtonPressed: () {
        onConfirmButtonPressed.call();
      },
    );
  }
}
