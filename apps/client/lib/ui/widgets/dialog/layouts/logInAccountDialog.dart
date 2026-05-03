import 'package:flutter/material.dart';

import '../../../../app/generalImports.dart';

class LogInAccountDialog extends StatelessWidget {
  const LogInAccountDialog({super.key});

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
      title: "loginRequired",
      description: "pleaseLogin",
      cancelButtonName: "notNow",
      cancelButtonBackgroundColor: context.colorScheme.secondaryColor,
      cancelButtonPressed: () {
        Navigator.of(context).pop();
      },
      confirmButtonName: "logIn",
      confirmButtonBackgroundColor: context.colorScheme.accentColor,
      confirmButtonPressed: () {
        Navigator.pop(context);
        Navigator.pushNamed(context, loginRoute,
            arguments: {'source': 'dialog'});
      },
    );
  }
}
