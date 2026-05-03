import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class AuthenticationScreenBackground extends StatelessWidget {
  final Widget child;

  const AuthenticationScreenBackground({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned.directional(
          textDirection: Directionality.of(context),
          start: -25,
          top: context.screenHeight * 0.05,
          child: CustomContainer(
            height: 120,
            width: 120,
            shape: BoxShape.circle,
            border: Border.all(
              color: context.colorScheme.accentColor,
              width: 0.5,
            ),
          ),
        ),
        Positioned.directional(
          textDirection: Directionality.of(context),
          start: -50,
          top: context.screenHeight * 0.3,
          child: CustomContainer(
            height: 100,
            width: 100,
            shape: BoxShape.circle,
            border: Border.all(
              color: context.colorScheme.accentColor,
              width: 0.5,
            ),
          ),
        ),
        Positioned.directional(
          textDirection: Directionality.of(context),
          end: -45,
          top: context.screenHeight * 0.2,
          child: CustomContainer(
            height: 90,
            width: 90,
            shape: BoxShape.circle,
            border: Border.all(
              color: context.colorScheme.accentColor,
              width: 0.5,
            ),
          ),
        ),
        Positioned.directional(
          textDirection: Directionality.of(context),
          start: 22,
          top: context.screenHeight * 0.27,
          child: CustomContainer(
            height: 16,
            width: 16,
            shape: BoxShape.circle,
            color: context.colorScheme.accentColor.withAlpha(30),
          ),
        ),
        Positioned.directional(
          textDirection: Directionality.of(context),
          start: 115,
          top: context.screenHeight * 0.08,
          child: CustomContainer(
            height: 21,
            width: 21,
            shape: BoxShape.circle,
            color: context.colorScheme.accentColor.withAlpha(30),
          ),
        ),
        Positioned.directional(
          textDirection: Directionality.of(context),
          end: 5,
          top: context.screenHeight * 0.15,
          child: CustomContainer(
            height: 33,
            width: 33,
            child: CustomSvgPicture(
              svgImage: AppAssets.polygon,
              height: 33,
              width: 33,
              color: context.colorScheme.accentColor,
            ),
          ),
        ),
        child,
      ],
    );
  }
}
