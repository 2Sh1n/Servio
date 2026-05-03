import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class GalleryImagesStyles extends StatelessWidget {
  final List<String> imagesList;

  const GalleryImagesStyles({super.key, required this.imagesList});

  Widget getGalleryImage(
      {required BuildContext context,
      required int imageIndex,
      required bool navigateToPreviewOnTap}) {
    return CustomInkWellContainer(
      onTap: navigateToPreviewOnTap
          ? () {
              Navigator.pushNamed(
                context,
                imagePreview,
                arguments: {
                  "startFrom": imageIndex,
                  "isReviewType": false,
                  "dataURL": imagesList,
                },
              );
            }
          : () {},
      child: ClipRRect(
        borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
        child: CustomCachedNetworkImage(
          networkImageUrl: imagesList[imageIndex],
          fit: BoxFit.cover,
        ),
      ),
    );
  }

  Widget gallerySectionStyle1({required BuildContext context}) {
    return Row(
      children: List.generate(
        imagesList.length,
        (index) => Expanded(
          child: CustomContainer(
            height: 180,
            margin: EdgeInsetsDirectional.only(
              end: imagesList.length - 1 != index ? 10 : 0,
            ),
            child: getGalleryImage(
              context: context,
              imageIndex: index,
              navigateToPreviewOnTap: true,
            ),
          ),
        ),
      ),
    );
  }

  Widget gallerySectionStyle2({required BuildContext context}) {
    return Row(
      children: [
        Expanded(
          child: CustomSizedBox(
            height: 180,
            child: getGalleryImage(
              context: context,
              imageIndex: 0,
              navigateToPreviewOnTap: true,
            ),
          ),
        ),
        const CustomSizedBox(
          width: 10,
        ),
        Expanded(
          child: Column(
            children: [
              CustomSizedBox(
                height: 85,
                width: double.infinity,
                child: getGalleryImage(
                  context: context,
                  imageIndex: 1,
                  navigateToPreviewOnTap: true,
                ),
              ),
              const CustomSizedBox(
                height: 10,
              ),
              CustomSizedBox(
                width: double.infinity,
                height: 85,
                child: getGalleryImage(
                  context: context,
                  imageIndex: 2,
                  navigateToPreviewOnTap: true,
                ),
              ),
            ],
          ),
        )
      ],
    );
  }

  Widget gallerySectionStyle3({required BuildContext context}) {
    return Row(
      children: [
        Expanded(
          child: Column(
            children: [
              CustomSizedBox(
                height: 175,
                width: double.infinity,
                child: getGalleryImage(
                  context: context,
                  imageIndex: 0,
                  navigateToPreviewOnTap: true,
                ),
              ),
              const CustomSizedBox(
                height: 10,
              ),
              CustomSizedBox(
                width: double.infinity,
                height: 90,
                child: getGalleryImage(
                  context: context,
                  imageIndex: 1,
                  navigateToPreviewOnTap: true,
                ),
              ),
            ],
          ),
        ),
        const CustomSizedBox(
          width: 10,
        ),
        Expanded(
          child: Column(
            children: [
              CustomSizedBox(
                height: 90,
                width: double.infinity,
                child: getGalleryImage(
                  context: context,
                  imageIndex: 2,
                  navigateToPreviewOnTap: true,
                ),
              ),
              const CustomSizedBox(
                height: 10,
              ),
              Stack(
                children: [
                  CustomSizedBox(
                    width: double.infinity,
                    height: 175,
                    child: getGalleryImage(
                      context: context,
                      imageIndex: 3,
                      navigateToPreviewOnTap:
                          imagesList.length > 4 ? false : true,
                    ),
                  ),
                  if (imagesList.length > 4)
                    CustomInkWellContainer(
                      onTap: () {
                        UiUtils.showBottomSheet(
                          context: context,
                          enableDrag: true,
                          isScrollControlled: true,
                          useSafeArea: true,
                          child: ServiceImagesList(
                            imageList: imagesList,
                          ),
                        );
                      },
                      child: CustomContainer(
                        color: context.colorScheme.secondaryColor
                            .withValues(alpha: 0.7),
                        height: 175,
                        child: Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            mainAxisSize: MainAxisSize.max,
                            children: [
                              Icon(
                                Icons.panorama,
                                color: context.colorScheme.blackColor,
                                size: 25,
                              ),
                              CustomText(
                                'seeAll'.translate(context: context),
                                color: context.colorScheme.blackColor,
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),
        )
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return imagesList.length <= 2
        ? gallerySectionStyle1(context: context)
        : imagesList.length <= 3
            ? gallerySectionStyle2(context: context)
            : gallerySectionStyle3(context: context);
  }
}
