import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class SectionCardForCategoryContainer extends StatelessWidget {
  const SectionCardForCategoryContainer({
    required this.discount,
    required this.onTap,
    required this.imageHeight,
    required this.imageWidth,
    required this.cardHeight,
    required this.title,
    required this.image,
    required this.providerCounter,
    final Key? key,
  }) : super(key: key);
  final String title, image, discount, providerCounter;
  final double imageHeight, imageWidth, cardHeight;
  final VoidCallback onTap;

  @override
  Widget build(final BuildContext context) => CustomInkWellContainer(
        onTap: onTap,
        child: CustomContainer(
          padding: const EdgeInsetsDirectional.only(top: 10, start: 10),
          height: cardHeight,
          width: imageWidth,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CustomContainer(
                height: imageHeight,
                width: imageWidth,
                color: context.colorScheme.secondaryColor,
                borderRadius: UiUtils.borderRadiusOf8,
                child: Stack(
                  children: [
                    ClipRRect(
                      borderRadius:
                          BorderRadius.circular(UiUtils.borderRadiusOf10),
                      child: CustomCachedNetworkImage(
                        networkImageUrl: image,
                        fit: BoxFit.fill,
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsetsDirectional.only(top: 5),
                child: CustomText(
                  title,
                  maxLines: 1,
                  color: context.colorScheme.blackColor,
                  fontStyle: FontStyle.normal,
                  fontSize: 14,
                  textAlign: TextAlign.left,
                  fontWeight: FontWeight.w500,
                ),
              ),
              Padding(
                padding: const EdgeInsetsDirectional.only(top: 5),
                child: CustomText(
                  "$providerCounter ${providerCounter.convertToInt() > 1 ? 'providers'.translate(context: context) : 'provider'.translate(context: context)}",
                  maxLines: 1,
                  color: context.colorScheme.lightGreyColor,
                  fontStyle: FontStyle.normal,
                  fontSize: 12,
                  textAlign: TextAlign.left,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      );
}
