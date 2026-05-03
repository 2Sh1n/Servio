import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomRadioOptionsWidget extends StatelessWidget {
  final String image;
  final String title;
  final String value;
  final String groupValue;
  final Function(Object?)? onChanged;
  final bool? applyAccentColor;

  const CustomRadioOptionsWidget({
    super.key,
    required this.image,
    required this.title,
    required this.value,
    required this.groupValue,
    this.onChanged,
    this.applyAccentColor = true,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        onChanged
            ?.call(value); // Trigger the onChanged callback with the new value
      },
      child: CustomContainer(
          padding: const EdgeInsetsDirectional.symmetric(vertical: 12),
          border: Border.all(
              color: value == groupValue // Highlight the border if selected
                  ? context.colorScheme.accentColor
                  : context.colorScheme.primaryColor),
          borderRadius: UiUtils.borderRadiusOf10,
          child: Row(
            children: [
              Expanded(
                child: CustomSizedBox(
                  height: 25,
                  width: 25,
                  child: CustomSvgPicture(
                    svgImage: image,
                    color: applyAccentColor!
                        ? context.colorScheme.accentColor
                        : null,
                  ),
                ),
              ),
              Expanded(
                flex: 4,
                child: CustomText(
                  title,
                  fontSize: 16,
                  fontWeight: FontWeight.w500,
                ),
              ),
              Expanded(
                child: CustomSizedBox(
                  height: 25,
                  width: 25,
                  child: value == groupValue
                      ? CustomSvgPicture(
                          svgImage: AppAssets.icCheck,
                          color: context.colorScheme.accentColor)
                      : null,
                ),
              ),
            ],
          )),
    );
  }
}
