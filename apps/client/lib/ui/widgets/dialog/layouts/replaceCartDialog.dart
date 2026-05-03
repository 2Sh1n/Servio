import 'package:flutter/material.dart';
import '../../../../app/generalImports.dart';

class ReplaceCartDialog extends StatelessWidget {
  final String currentProviderName;
  final String newProviderName;
  final VoidCallback confirmCallback;

  const ReplaceCartDialog({
    super.key,
    required this.currentProviderName,
    required this.newProviderName,
    required this.confirmCallback,
  });

  @override
  Widget build(BuildContext context) {
    return CustomDialogLayout(
      showProgressIndicator: false,
      icon: CustomContainer(
          height: 70,
          width: 70,
          color: context.colorScheme.secondaryColor,
          borderRadius: UiUtils.borderRadiusOf50,
          child: Icon(Icons.info,
              color: context.colorScheme.accentColor, size: 70)),
      title: "replaceCartItem",
      description: "replaceCartItemMessage",
      cancelButtonName: "no",
      cancelButtonBackgroundColor: context.colorScheme.secondaryColor,
      cancelButtonPressed: () {
        Navigator.of(context).pop();
      },
      confirmButtonName: "yes",
      confirmButtonBackgroundColor: context.colorScheme.accentColor,
      confirmButtonPressed: () {
        Navigator.pop(context);
        confirmCallback();
      },
    );
  }
}
