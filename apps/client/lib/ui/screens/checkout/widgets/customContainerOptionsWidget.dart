import 'package:e_demand/app/generalImports.dart';

import 'package:flutter/material.dart'; // ignore_for_file: prefer_typing_uninitialized_variables

class CustomContainerOptionsWidget extends StatelessWidget {
  final bool? checkSelected;
  final String image;
  final String title;
  final String subTitle;
  final String value;
  final String groupValue;
  final bool? isFirst;
  final bool? isLast;
  final Function(Object?)? onChanged;
  final bool? applyAccentColor;
  const CustomContainerOptionsWidget({
    super.key,
    required this.image,
    required this.title,
    required this.subTitle,
    required this.value,
    required this.groupValue,
    this.onChanged,
    this.isFirst,
    this.isLast,
    this.applyAccentColor = true,
    this.checkSelected,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        onChanged?.call(value);
      },
      child: CustomContainer(
        color: context.colorScheme.secondaryColor,
        borderRadius: UiUtils.borderRadiusOf10,
        border: Border.all(
          color: value == groupValue
              ? context.colorScheme.accentColor
              : Colors.transparent,
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.start,
            children: [
              CustomSizedBox(
                height: 24,
                width: 24,
                child: CustomSvgPicture(
                  svgImage: image,
                  color: value == groupValue
                      ? context.colorScheme.accentColor
                      : context.colorScheme.blackColor,
                ),
              ),
              const CustomSizedBox(
                width: 5,
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CustomText(
                    title.translate(context: context),
                    fontSize: 14,
                    color: value == groupValue
                        ? context.colorScheme.accentColor
                        : context.colorScheme.blackColor,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
