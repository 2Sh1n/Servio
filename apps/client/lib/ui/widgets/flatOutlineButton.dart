import 'package:flutter/material.dart';

import "../../app/generalImports.dart";

class FlatOutlinedButton extends StatelessWidget {
  const FlatOutlinedButton({
    required this.name,
    required this.onTap,
    required this.isSelected,
    final Key? key,
  }) : super(key: key);

  final bool isSelected;
  final String name;
  final VoidCallback onTap;

  @override
  Widget build(final BuildContext context) => Material(
        color:
            isSelected ? context.colorScheme.accentColor : Colors.transparent,
        borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
        child: CustomInkWellContainer(
          onTap: onTap,
          child: CustomContainer(
            width: 80,
            height: 34,
            borderRadius: UiUtils.borderRadiusOf10,
            border: isSelected
                ? null
                : Border.all(
                    color: context.colorScheme.lightGreyColor,
                  ),
            child: Center(
              child: CustomText(
                name,
                color: isSelected
                    ? AppColors.whiteColors
                    : context.colorScheme.lightGreyColor,
                fontWeight: FontWeight.w600,
                fontStyle: FontStyle.normal,
                fontSize: 12,
              ),
            ),
          ),
        ),
      );
}
