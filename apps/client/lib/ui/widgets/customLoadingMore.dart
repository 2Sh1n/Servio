import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomLoadingMore extends StatelessWidget {
  const CustomLoadingMore({
    required this.isError,
    super.key,
    this.onErrorButtonPressed,
  });

  final bool isError;
  final VoidCallback? onErrorButtonPressed;

  @override
  Widget build(BuildContext context) {
    return isError
        ? MaterialButton(
            onPressed: () {
              onErrorButtonPressed?.call();
            },
            textColor: context.colorScheme.primary,
            child: const CustomText(
              'retry',
            ),
          )
        : const CustomCircularProgressIndicator();
  }
}
