import 'package:flutter/material.dart';

class CustomInkWellContainer extends StatelessWidget {
  final Widget child;
  final VoidCallback? onTap;
  final BorderRadius? borderRadius;
  final bool? showRippleEffect;

  const CustomInkWellContainer({
    super.key,
    required this.child,
    this.onTap,
    this.borderRadius,
    this.showRippleEffect,
  });

  @override
  Widget build(final BuildContext context) => InkWell(
        borderRadius: borderRadius,
        highlightColor: Colors.transparent,
        hoverColor: Colors.transparent,
        splashFactory: showRippleEffect ?? true ? null : NoSplash.splashFactory,
        onTap: onTap,
        child: child,
      );
}
