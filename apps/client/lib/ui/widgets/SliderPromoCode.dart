import 'dart:ui';

import 'package:carousel_slider/carousel_slider.dart';
import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/bottomsheets/layouts/promocodeDetailsBottomsheet.dart';
import 'package:flutter/material.dart';

class SliderPromocode extends StatefulWidget {
  final List<Promocode> promocode;
  final double calculatedWidth;
  final double height;

  const SliderPromocode(
      {Key? key,
      required this.promocode,
      required this.calculatedWidth,
      required this.height})
      : super(key: key);

  @override
  State<SliderPromocode> createState() => _SliderPromocodeState();
}

class _SliderPromocodeState extends State<SliderPromocode> {
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
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        CarouselSlider(
          options: CarouselOptions(
            height: widget.height,
            viewportFraction: 1,
            initialPage: 0,
            enableInfiniteScroll: widget.promocode.length > 1,
            reverse: false,
            autoPlay: widget.promocode.length > 1,
            autoPlayInterval: Duration(
                milliseconds:
                    sliderAnimationDurationSettings["sliderAnimationDuration"]),
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
          items: List.generate(
            widget.promocode.length,
            (index) {
              final Promocode promocode = widget.promocode[index];
              return SingleSliderImageWidget(
                  height: widget.height,
                  promocode: promocode,
                  calculatedWidth: widget.calculatedWidth);
            },
          ),
        ),
        if (widget.promocode.length > 1)
          Align(
            alignment: Alignment.bottomCenter,
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: 20,
                vertical: 5,
              ),
              child: CustomContainer(
                height: 24,
                child: LayoutBuilder(
                  builder: (p0, p1) {
                    return Center(
                      child: SingleChildScrollView(
                        // physics: const ClampingScrollPhysics(),
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
                                  widget.promocode.length,
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
                    totalScroll = (widget.promocode.length * 18) + 22;
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
  const SingleSliderImageWidget(
      {required this.promocode,
      required this.calculatedWidth,
      super.key,
      required this.height});

  final Promocode promocode;
  final double calculatedWidth;
  final double height;

  @override
  Widget build(BuildContext context) {
    return CustomInkWellContainer(
      onTap: () {
        UiUtils.showBottomSheet(
          context: context,
          child: Wrap(
            children: [
              PromocodeDetailsBottomSheet(
                promocodeDetails: promocode,
              ),
            ],
          ),
        );
      },
      child: CustomContainer(
        height: height,
        width: calculatedWidth,
        color: context.colorScheme.secondaryColor,
        borderRadius: UiUtils.borderRadiusOf8,
        border: Border.all(color: context.colorScheme.accentColor, width: 0.5),
        child: Row(
          children: [
            Padding(
              padding: const EdgeInsets.all(10.0),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
                child: CustomCachedNetworkImage(
                  networkImageUrl: promocode.image!,
                  height: 50,
                  width: 50,
                  fit: BoxFit.fill,
                ),
              ),
            ),
            Expanded(
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(vertical: 10, horizontal: 5),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CustomText(
                      promocode.promoCode!,
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                      maxLines: 1,
                    ),
                    const CustomSizedBox(height: 5),
                    CustomText(
                      promocode.translatedMessage!,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      maxLines: 1,
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
