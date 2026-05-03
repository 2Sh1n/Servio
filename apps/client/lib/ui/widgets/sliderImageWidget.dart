import 'dart:ui';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:carousel_slider/carousel_slider.dart';
import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class SliderImageWidget extends StatefulWidget {
  final List<SliderImage> sliderImages;

  const SliderImageWidget({Key? key, required this.sliderImages})
      : super(key: key);

  @override
  State<SliderImageWidget> createState() => _SliderImageWidgetState();
}

class _SliderImageWidgetState extends State<SliderImageWidget> {
  int currentSliderImage = 0;

  final ScrollController _scrollController = ScrollController();
  var totalScroll;
  var currentScroll;
  Timer? timer;

  @override
  void initState() {
    super.initState();
  }

  @override
  void dispose() {
    timer?.cancel();
    _scrollController.dispose();

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return CustomSizedBox(
      height: 200.rs(context),
      child: Stack(
        fit: StackFit.expand,
        children: [
          CarouselSlider(
              options: CarouselOptions(
                height: 200.rs(context),
                viewportFraction: 1,
                initialPage: 0,
                enableInfiniteScroll: widget.sliderImages.length > 1,
                reverse: false,
                autoPlay: widget.sliderImages.length > 1,
                autoPlayInterval: Duration(
                    milliseconds: sliderAnimationDurationSettings[
                        "sliderAnimationDuration"]),
                autoPlayAnimationDuration: Duration(
                    milliseconds: sliderAnimationDurationSettings[
                        "changeSliderAnimationDuration"]),
                autoPlayCurve: Curves.fastOutSlowIn,
                enlargeCenterPage: false,
                pauseAutoPlayOnTouch: false,
                enlargeFactor: 1,
                aspectRatio: 1,
                scrollDirection: Axis.horizontal,
                onPageChanged: (index, reason) {
                  setState(() {
                    currentSliderImage = index;
                  });
                },
              ),
              items: List.generate(widget.sliderImages.length, (index) {
                final SliderImage sliderImage = widget.sliderImages[index];
                return Padding(
                  padding: EdgeInsets.symmetric(horizontal: 10.rs(context)),
                  child: SingleSliderImageWidget(sliderImage: sliderImage),
                );
              })),
          if (widget.sliderImages.length > 1)
            Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 15,
                ),
                child: CustomContainer(
                  height: 24,
                  child: LayoutBuilder(
                    builder: (p0, p1) {
                      return Center(
                        child: SingleChildScrollView(
                          controller: _scrollController,
                          scrollDirection: Axis.horizontal,
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(50),
                            child: BackdropFilter(
                              filter: ImageFilter.blur(sigmaX: 5, sigmaY: 5),
                              child: CustomContainer(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 10, vertical: 5),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.center,
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: List.generate(
                                    widget.sliderImages.length,
                                    (index) {
                                      return getIndicator(index: index, p1: p1);
                                    },
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget getIndicator({required int index, required BoxConstraints p1}) {
    return Stack(
      children: [
        AnimatedContainer(
          alignment: AlignmentDirectional.centerStart,
          curve: Curves.easeIn,
          duration: Duration(
              milliseconds: sliderAnimationDurationSettings[
                  "changeSliderAnimationDuration"]),
          margin: const EdgeInsets.symmetric(horizontal: 5),
          height: 8,
          width: currentSliderImage == index ? 30 : 8,
          decoration: BoxDecoration(
            color: context.colorScheme.secondaryColor,
            borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf50),
          ),
          child: currentSliderImage == index
              ? LayoutBuilder(
                  builder: (context, constraints) {
                    //we will get total available scroll with (padding between indicator + indicator size)
                    // + added 22, because active indicator extra width of 22
                    totalScroll = (widget.sliderImages.length * 18) + 22;
                    currentScroll = (index * 18) + 22;
                    if (currentScroll >= p1.maxWidth) {
                      final currentScrolls = _scrollController.position.pixels;
                      _scrollController.animateTo(
                        currentScrolls + 5,
                        duration: const Duration(milliseconds: 10),
                        curve: Curves.easeInOut,
                      );
                    } else if (index == 0 &&
                        _scrollController.position.pixels != 0) {
                      _scrollController.animateTo(
                        0,
                        duration: const Duration(milliseconds: 10),
                        curve: Curves.easeInOut,
                      );
                    }
                    return const CustomSizedBox();
                  },
                )
              : const CustomSizedBox(),
        ),
        AnimatedContainer(
          duration: currentSliderImage == index
              ? Duration(
                  milliseconds: sliderAnimationDurationSettings[
                      "sliderAnimationDuration"])
              : Duration.zero,
          width: currentSliderImage == index ? 30 : 8,
          curve: Curves.easeIn,
          margin: const EdgeInsets.symmetric(horizontal: 5),
          height: 8,
          decoration: BoxDecoration(
            color: currentSliderImage == index
                ? context.colorScheme.accentColor
                : context.colorScheme.secondaryColor,
            borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf50),
          ),
        )
      ],
    );
  }
}

class SingleSliderImageWidget extends StatelessWidget {
  const SingleSliderImageWidget({required this.sliderImage, super.key});

  final SliderImage sliderImage;

  @override
  Widget build(BuildContext context) {
    return CustomInkWellContainer(
      showRippleEffect: false,
      onTap: () async {
        ClarityService.logAction(
          ClarityActions.homeBannerTapped,
          {
            'banner_id': sliderImage.id ?? '',
            'banner_type': sliderImage.type?.name ?? '',
            'type_id': sliderImage.typeId ?? '',
            'category_name': sliderImage.categoryName ?? '',
            'provider_name': sliderImage.providerName ?? '',
          },
        );
        if (sliderImage.type == SliderImagesType.category ||
            sliderImage.type == SliderImagesType.subCategory) {
          final Map<String, dynamic> arguments = {
            'appBarTitle': sliderImage.categoryName,
          };
          if (sliderImage.type == SliderImagesType.category) {
            arguments['categoryId'] = sliderImage.typeId;
            arguments['type'] = CategoryType.category;
            arguments['subCategoryId'] = '';
          } else {
            arguments['categoryId'] = '';
            arguments['subCategoryId'] = sliderImage.typeId;
            arguments['type'] = CategoryType.subcategory;
          }
          Navigator.pushNamed(
            context,
            subCategoryRoute,
            arguments: arguments,
          );
        } else if (sliderImage.type == SliderImagesType.provider) {
          Navigator.pushNamed(
            context,
            providerRoute,
            arguments: {"providerId": sliderImage.typeId},
          );
        } else if (sliderImage.type == SliderImagesType.url) {
          try {
            final String url = sliderImage.url ?? '';
            if (await canLaunchUrl(Uri.parse(url))) {
              await launchUrl(Uri.parse(url),
                  mode: LaunchMode.externalApplication);
            } else {
              throw 'Could not launch $url';
            }
          } catch (e) {
            throw 'Something went wrong';
          }
        }
      },
      child: Container(
        width: double.infinity,
        height: 200.rs(context),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.1),
              blurRadius: 10,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
          child: sliderImage.sliderImage!.endsWith('.svg')
              ? Container(
                  width: double.infinity,
                  height: double.infinity,
                  color: context.colorScheme.accentColor.withValues(alpha: 0.1),
                  child: SvgPicture.network(
                    sliderImage.sliderImage ?? '',
                    fit: BoxFit.contain,
                    colorFilter: ColorFilter.mode(
                        context.colorScheme.accentColor, BlendMode.srcIn),
                    placeholderBuilder: (final BuildContext context) =>
                        const Center(
                      child: CustomSvgPicture(
                        svgImage: AppAssets.placeHolder,
                        boxFit: BoxFit.contain,
                      ),
                    ),
                  ),
                )
              : CachedNetworkImage(
                  imageUrl: "${sliderImage.sliderImage}",
                  width: double.infinity,
                  height: double.infinity,
                  fit: BoxFit.cover,
                  memCacheWidth: 800,
                  memCacheHeight: 600,
                  maxWidthDiskCache: 800,
                  maxHeightDiskCache: 600,
                  errorWidget:
                      (BuildContext context, String url, final error) =>
                          const Center(
                    child: CustomSvgPicture(
                      svgImage: AppAssets.noImageFound,
                      width: 100,
                      height: 100,
                      boxFit: BoxFit.contain,
                    ),
                  ),
                  placeholder: (final BuildContext context, final String url) =>
                      const Center(
                    child: CustomSvgPicture(
                      svgImage: AppAssets.placeHolder,
                      width: 100,
                      height: 100,
                      boxFit: BoxFit.contain,
                    ),
                  ),
                ),
        ),
      ),
    );
  }
}
