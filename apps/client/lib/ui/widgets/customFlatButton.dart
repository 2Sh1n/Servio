import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomFlatButton extends StatelessWidget {
  const CustomFlatButton({
    final Key? key,
    this.text,
    this.onPressed,
    this.width,
    this.height,
    this.backgroundColor,
    this.fontColor,
    this.fontSize,
    this.innerPadding,
    this.radius,
    this.showBorder,
    this.borderColor,
  }) : super(key: key);
  final double? width;
  final double? height;
  final Color? backgroundColor;
  final String? text;
  final Color? fontColor;
  final VoidCallback? onPressed;
  final double? fontSize;
  final double? innerPadding;
  final double? radius;
  final bool? showBorder;
  final Color? borderColor;

  @override
  Widget build(final BuildContext context) => CustomInkWellContainer(
        onTap: onPressed,
        child: CustomContainer(
          width: width?.rw(context),
          height: height?.rh(context),
          constraints: const BoxConstraints(minWidth: 10, minHeight: 10),
          color: backgroundColor ?? context.colorScheme.secondaryColor,
          borderRadius: radius ?? UiUtils.borderRadiusOf20,
          border: showBorder ?? false
              ? Border.all(
                  color: borderColor ?? context.colorScheme.secondaryColor,
                )
              : null,
          child: Padding(
            padding: EdgeInsetsDirectional.all(innerPadding ?? 8.0),
            child: Center(
              child: CustomText(
                text ?? '',
                color: fontColor ?? context.colorScheme.secondaryColor,
                fontSize: fontSize ?? 14,
              ),
            ),
          ),
        ),
      );
}
