import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class ImagePreview extends StatefulWidget {
  const ImagePreview({
    required this.isReviewType,
    required this.dataURL,
    required this.reviewDetails,
    required this.startFrom,
    final Key? key,
  }) : super(key: key);
  final Reviews? reviewDetails;
  final int startFrom;
  final bool isReviewType;
  final List<dynamic> dataURL;

  @override
  State<ImagePreview> createState() => _ImagePreviewState();

  static Route route(final RouteSettings settings) {
    final arguments = settings.arguments as Map;
    return CupertinoPageRoute(
      builder: (final context) => ImagePreview(
        reviewDetails: arguments["reviewDetails"] ?? Reviews(),
        startFrom: arguments["startFrom"],
        isReviewType: arguments["isReviewType"],
        dataURL: arguments["dataURL"],
      ),
    );
  }
}

class _ImagePreviewState extends State<ImagePreview>
    with TickerProviderStateMixin {
  ValueNotifier<bool> isShowData = ValueNotifier(true);

  late final AnimationController animationController = AnimationController(
      vsync: this, duration: const Duration(milliseconds: 100));
  late final Animation<double> opacityAnimation = Tween<double>(
    begin: 1,
    end: 0,
  ).animate(
    CurvedAnimation(
      parent: animationController,
      curve: Curves.linear,
    ),
  );

  late final PageController _pageController =
      PageController(initialPage: widget.startFrom);

  @override
  void dispose() {
    isShowData.dispose();
    animationController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    if (!widget.isReviewType) {
      isShowData.value = false;
    }
    super.initState();
  }

  @override
  Widget build(final BuildContext context) {
    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          ValueListenableBuilder(
              valueListenable: isShowData,
              builder: (context, value, child) {
                return PageView.builder(
                  controller: _pageController,
                  itemCount: widget.dataURL.length,
                  physics: value ? const NeverScrollableScrollPhysics() : null,
                  itemBuilder: (final BuildContext context, int index) =>
                      CustomInkWellContainer(
                    onTap: () {
                      if (widget.isReviewType) {
                        isShowData.value = !isShowData.value;
                      }

                      if (isShowData.value) {
                        animationController.forward();
                      } else {
                        animationController.reverse();
                      }
                    },
                    child: UrlTypeHelper.getType(widget.dataURL[index]) ==
                            UrlType.image
                        ? InteractiveViewer(
                            minScale: 0.5,
                            maxScale: 4,
                            child: CustomContainer(
                              height: double
                                  .maxFinite, //// USE THIS FOR THE MATCH WIDTH AND HEIGHT
                              width: double.maxFinite,
                              child: CustomCachedNetworkImage(
                                networkImageUrl: widget.dataURL[index],
                                // fit: BoxFit.fill,
                              ),
                            ),
                          )
                        : PlayVideoScreen(
                            videoURL: widget.dataURL[index],
                          ),
                  ),
                );
              }),
          PositionedDirectional(
            start: 15,
            top: 40,
            child: AnimatedBuilder(
              animation: animationController,
              builder: (final BuildContext context, Widget? child) => Opacity(
                opacity: opacityAnimation.value,
                child: CustomContainer(
                  height: 35,
                  width: 35,
                  color: context.colorScheme.accentColor.withValues(alpha: 0.3),
                  borderRadius: UiUtils.borderRadiusOf5,
                  child: CustomInkWellContainer(
                    onTap: () {
                      Navigator.pop(context);
                    },
                    showRippleEffect: true,
                    borderRadius:
                        BorderRadius.circular(UiUtils.borderRadiusOf5),
                    child: Padding(
                      padding: const EdgeInsets.all(5),
                      child: Center(
                        child: CustomSvgPicture(
                          svgImage: Directionality.of(context)
                                  .toString()
                                  .contains(
                                      TextDirection.RTL.value.toLowerCase())
                              ? AppAssets.backArrowLtr
                              : AppAssets.backArrow,
                          color: context.colorScheme.accentColor,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          if (widget.isReviewType) ...[
            Align(
              alignment: AlignmentDirectional.bottomCenter,
              child: AnimatedBuilder(
                animation: animationController,
                builder: (final BuildContext context, Widget? child) => Opacity(
                  opacity: opacityAnimation.value,
                  child: CustomContainer(
                    constraints:
                        BoxConstraints(maxHeight: context.screenHeight * 0.3),
                    width: context.screenWidth,
                    padding: const EdgeInsets.all(15),
                    boxShadow: [
                      BoxShadow(
                        color: context.colorScheme.secondaryColor
                            .withValues(alpha: 0.35),
                        offset: const Offset(0, 0.75),
                        spreadRadius: 5,
                        blurRadius: 25,
                      )
                    ],
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        CustomContainer(
                          height: 40,
                          width: 40,
                          borderRadius: UiUtils.borderRadiusOf50,
                          child: ClipRRect(
                            borderRadius:
                                BorderRadius.circular(UiUtils.borderRadiusOf50),
                            child: CustomCachedNetworkImage(
                              networkImageUrl:
                                  widget.reviewDetails!.profileImage ?? '',
                            ),
                          ),
                        ),
                        const CustomSizedBox(
                          width: 15,
                        ),
                        Expanded(
                          child: SingleChildScrollView(
                            clipBehavior: Clip.none,
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                CustomReadMoreTextContainer(
                                  text: widget.reviewDetails!.comment ?? '',
                                  textStyle: TextStyle(
                                    color: context.colorScheme.blackColor,
                                    fontSize: 12,
                                  ),
                                ),
                                StarRating(
                                  rating: double.parse(
                                      widget.reviewDetails!.rating!),
                                  onRatingChanged: (final double rating) =>
                                      rating,
                                ),
                                CustomText(
                                  "${widget.reviewDetails!.userName ?? ''}, ${UiUtils.getTimeAgo(context, date: DateTime.tryParse(widget.reviewDetails!.ratedOn ?? ''))}",
                                  color: context.colorScheme.blackColor,
                                  fontSize: 12,
                                  maxLines: 3,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            )
          ],
        ],
      ),
    );
  }
}
