import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomLoadingMoreContainer extends StatelessWidget {
  final bool isError;
  final Function()? onErrorButtonPressed;

  const CustomLoadingMoreContainer(
      {super.key, required this.isError, this.onErrorButtonPressed});

  @override
  Widget build(BuildContext context) {
    return isError
        ? MaterialButton(
            onPressed: () {
              onErrorButtonPressed?.call();
            },
            textColor: context.colorScheme.accentColor,
            child: CustomText(
              "retry".translate(context: context),
            ),
          )
        : CustomCircularProgressIndicator(
            color: context.colorScheme.accentColor,
          );
  }
}
