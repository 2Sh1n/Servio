import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class LinearDivider extends StatelessWidget {
  final double? height;
  LinearDivider({super.key, this.height});

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      height: height ?? 0.5,
      gradient: LinearGradient(
        colors: [
          context.colorScheme.secondaryColor,
          context.colorScheme.lightGreyColor.withAlpha(80),
          context.colorScheme.lightGreyColor,
          context.colorScheme.lightGreyColor.withAlpha(80),
          context.colorScheme.secondaryColor,
        ],
        stops: [0.0, 0.2, 0.5, 0.8, 1],
      ),
    );
  }
}
