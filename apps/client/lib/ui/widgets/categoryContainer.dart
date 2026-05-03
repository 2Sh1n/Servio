import 'package:flutter/material.dart';
import 'package:e_demand/app/generalImports.dart';

class CategoryContainer extends StatelessWidget {
  const CategoryContainer({
    required this.imageRadius,
    required this.onTap,
    required this.imageURL,
    required this.title,
    this.cardWidth,
    required this.providers,
    final Key? key,
    this.maxLines,
    this.fontWeight,
    this.darkModeBackgroundColor,
    this.lightModeBackgroundColor,
    this.backgroundColor,
    this.textAlign,
  }) : super(key: key);

  final String imageURL, title, providers;
  final double imageRadius;
  final double? cardWidth;
  final void Function()? onTap;
  final int? maxLines;
  final FontWeight? fontWeight;
  final String? darkModeBackgroundColor, lightModeBackgroundColor;
  final Color? backgroundColor;
  final TextAlign? textAlign;

  @override
  Widget build(final BuildContext context) {
    final darkModeColor = darkModeBackgroundColor == ''
        ? Colors.transparent
        : darkModeBackgroundColor!.toColor();
    final lightModeColor = lightModeBackgroundColor == ''
        ? Colors.transparent
        : lightModeBackgroundColor!.toColor();
    return CustomInkWellContainer(
      onTap: onTap,
      child: CustomContainer(
        padding: const EdgeInsets.all(5),
        borderRadius: imageRadius,
        color: backgroundColor,
        child: Row(
          children: [
            CustomContainer(
              width: 50,
              height: 50,
              borderRadius: imageRadius,
              color:
                  Theme.of(context).colorScheme.brightness == Brightness.light
                      ? lightModeColor
                      : darkModeColor,
              child: ClipRRect(
                borderRadius: BorderRadius.all(
                  Radius.circular(imageRadius),
                ),
                child: CustomCachedNetworkImage(
                  defaultColourFilter: false,
                  networkImageUrl: imageURL,
                  width: 50,
                  height: 50,
                  fit: BoxFit.fill,
                ),
              ),
            ),
            const CustomSizedBox(
              width: 5,
            ),
            CustomContainer(
              width: cardWidth,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  CustomText(
                    title,
                    color: context.colorScheme.blackColor,
                    fontWeight: FontWeight.w400,
                    fontStyle: FontStyle.normal,
                    fontSize: 14,
                    textAlign: TextAlign.start,
                    maxLines: maxLines,
                  ),
                  CustomText(
                    '$providers ${providers.convertToInt() > 1 ? 'providers'.translate(context: context) : 'provider'.translate(context: context)}',
                    color: Theme.of(context).colorScheme.lightGreyColor,
                    fontWeight: FontWeight.w400,
                    fontStyle: FontStyle.normal,
                    fontSize: 12,
                    maxLines: 1,
                    textAlign: TextAlign.start,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
