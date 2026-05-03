import 'package:flutter/material.dart';

import '../../../../app/generalImports.dart';

class GeneralCardContainer extends StatelessWidget {
  final String imageName;
  final String title;
  final String description;
  final bool? showNextIcon;
  final VoidCallback? onTap;
  final Widget? extraWidgetWithDescription;
  final Color? descriptionColor;
  const GeneralCardContainer(
      {super.key,
      required this.imageName,
      required this.title,
      required this.description,
      this.showNextIcon,
      this.extraWidgetWithDescription,
      this.onTap,
      this.descriptionColor});

  @override
  Widget build(BuildContext context) {
    return CustomInkWellContainer(
      onTap: () {
        onTap?.call();
      },
      child: Ink(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(
          color: context.colorScheme.secondaryColor,
          // borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
        ),
        child: Row(
          children: [
            CustomContainer(
              borderRadius: UiUtils.borderRadiusOf6,
              color: context.colorScheme.accentColor.withAlpha(20),
              padding: const EdgeInsets.all(12),
              // width: 24,
              // height: 24,
              child: CustomSvgPicture(
                svgImage: imageName,
                width: 24,
                height: 24,
                color: context.colorScheme.accentColor,
              ),
            ),
            const CustomSizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.start,
                children: [
                  CustomText(
                    title.translate(context: context),
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                  if (description != '') ...[
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: CustomText(
                            description.translate(context: context),
                            fontSize: 14,
                            color: descriptionColor ??
                                context.colorScheme.lightGreyColor,
                            maxLines: 2,
                          ),
                        ),
                        extraWidgetWithDescription ?? const SizedBox.shrink(),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            if (showNextIcon ?? false) ...[
              Icon(
                Icons.arrow_forward_ios,
                color: context.colorScheme.blackColor,
              ),
            ]
          ],
        ),
      ),
    );
  }
}
