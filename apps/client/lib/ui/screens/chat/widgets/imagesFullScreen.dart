import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/screens/chat/widgets/commonImageView.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class ImagesFullScreen extends StatefulWidget {
  final List<String> listOfImages;
  final bool isFile;
  final int? initialPage;
  final String userName;
  final DateTime messageDateTime;

  const ImagesFullScreen(
      {super.key,
      required this.listOfImages,
      required this.isFile,
      this.initialPage,
      required this.userName,
      required this.messageDateTime});

  static Route route(RouteSettings routeSettings) {
    final List<String> multipleFiles =
        (routeSettings.arguments as Map?)?["images"] ?? [];
    final bool isItFile = (routeSettings.arguments as Map?)?["isFile"] ?? false;
    final int? initialPageNumber =
        (routeSettings.arguments as Map?)?["initialPage"];

    return CupertinoPageRoute(
      builder: (_) => ImagesFullScreen(
        listOfImages: multipleFiles,
        isFile: isItFile,
        initialPage: initialPageNumber,
        userName: (routeSettings.arguments as Map?)?["userName"] ?? '',
        messageDateTime:
            (routeSettings.arguments as Map?)?["messageDateTime"] ??
                DateTime.now(),
      ),
    );
  }

  @override
  State<ImagesFullScreen> createState() => _ImagesFullScreenState();
}

class _ImagesFullScreenState extends State<ImagesFullScreen> {
  late final ValueNotifier<int> _currentImageIndex;
  late final PageController _pageController;
  late final ScrollController _bottomItemsScrollController;
  bool isFullScreen = false;
  bool shareDownloadLoading = false;

  double bottomItemSize = 70;

  @override
  void initState() {
    //goto initial page if specified
    final initialPage = widget.initialPage ?? 0;
    _currentImageIndex = ValueNotifier(initialPage);
    _pageController = PageController(initialPage: initialPage);
    _bottomItemsScrollController =
        ScrollController(initialScrollOffset: bottomItemSize * initialPage);
    super.initState();
  }

  @override
  void dispose() {
    _pageController.dispose();
    _currentImageIndex.dispose();
    super.dispose();
  }

  void _adjustBottomItemScroll({required int currentItemIndex}) {
    if (_bottomItemsScrollController.hasClients &&
        _bottomItemsScrollController.positions.isNotEmpty) {
      if (_bottomItemsScrollController.offset <
              ((bottomItemSize + 6) * currentItemIndex - 100) ||
          _bottomItemsScrollController.offset >
              ((bottomItemSize + 6) * currentItemIndex + 100)) {
        _bottomItemsScrollController.animateTo(
            (bottomItemSize + 6) * currentItemIndex,
            duration: const Duration(milliseconds: 250),
            curve: Curves.linear);
      }
    }
  }

  Widget _topWidget() {
    return CustomContainer(
      padding: EdgeInsetsDirectional.only(
          top: MediaQuery.of(context).viewPadding.top,
          bottom: 0,
          start: 0,
          end: 15),
      color: context.colorScheme.secondaryColor,
      child: CustomSizedBox(
        height: 50,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            UiUtils.getBackArrow(context),
            const CustomSizedBox(
              width: 10,
            ),
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  CustomText(
                    widget.userName.capitalize(),
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: context.colorScheme.blackColor,
                    maxLines: 1,
                  ),
                  CustomText(
                    UiUtils.formatTimeWithDateTime(
                      widget.messageDateTime,
                    ),
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: context.colorScheme.blackColor,
                  ),
                ],
              ),
            ),
            const CustomSizedBox(
              width: 10,
            ),
            if (!widget.isFile && !shareDownloadLoading)
              GestureDetector(
                onTap: () async {
                  if (shareDownloadLoading) {
                    return;
                  }
                  shareDownloadLoading = true;
                  setState(() {});
                  try {
                    await UiUtils.downloadOrShareFile(
                        isDownload: true,
                        url: widget.listOfImages[_currentImageIndex.value],
                        customFileName:
                            "${widget.userName}_${widget.messageDateTime.microsecondsSinceEpoch}_${_currentImageIndex.value}");
                  } catch (_) {
                    UiUtils.showMessage(
                        context,
                        'downloadFailed'.translate(context: context),
                        ToastificationType.error);
                  }
                  shareDownloadLoading = false;
                  setState(() {});
                },
                child: CustomContainer(
                  borderRadius: 5,
                  color: context.colorScheme.accentColor.withValues(alpha: 0.3),
                  padding: const EdgeInsets.all(10),
                  height: 40,
                  width: 40,
                  child: CustomSvgPicture(
                      svgImage: AppAssets.downloadIcon,
                      width: 24,
                      height: 30,
                      color: context.colorScheme.accentColor
                          .withValues(alpha: 0.3)),
                ),
              ),
            if (shareDownloadLoading)
              const CustomSizedBox(
                  height: 20,
                  width: 20,
                  child: CustomCircularProgressIndicator())
          ],
        ),
      ),
    );
  }

  Widget _bottomWidget() {
    return widget.listOfImages.length == 1
        ? const SizedBox.shrink()
        : CustomContainer(
            padding: EdgeInsetsDirectional.only(
              bottom: MediaQuery.of(context).viewPadding.bottom + 10,
              top: 15,
            ),
            color: context.colorScheme.secondaryColor,
            child: CustomSizedBox(
              height: bottomItemSize,
              child: ValueListenableBuilder<int>(
                  valueListenable: _currentImageIndex,
                  builder: (context, value, _) {
                    return ListView.builder(
                      controller: _bottomItemsScrollController,
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsetsDirectional.only(
                          start: 100, end: 100),
                      physics: const ClampingScrollPhysics(),
                      itemCount: widget.listOfImages.length,
                      itemBuilder: (context, index) {
                        return GestureDetector(
                          onTap: () {
                            _pageController.jumpToPage(
                              index,
                            );
                            _adjustBottomItemScroll(currentItemIndex: index);
                          },
                          child: CustomContainer(
                            width: bottomItemSize,
                            borderRadius: 8,
                            border: value == index
                                ? Border.all(
                                    color: AppColors.whiteColors,
                                    width: 3,
                                    strokeAlign: BorderSide.strokeAlignInside,
                                  )
                                : null,
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                            child: CustomContainer(
                              borderRadius: UiUtils.borderRadiusOf5,
                              clipBehavior: Clip.antiAlias,
                              child: CommonImageWidget(
                                imagePath: widget.listOfImages[index],
                                isFile: widget.isFile,
                                boxFit: BoxFit.contain,
                              ),
                            ),
                          ),
                        );
                      },
                    );
                  }),
            ),
          );
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        body: Stack(
          children: [
            GestureDetector(
              onTap: () {
                setState(() {
                  isFullScreen = !isFullScreen;
                });
              },
              child: PageView(
                controller: _pageController,
                onPageChanged: (value) {
                  _currentImageIndex.value = value;
                  _adjustBottomItemScroll(currentItemIndex: value);
                },
                children: widget.listOfImages
                    .map((e) => InteractiveViewer(
                          minScale: 0.5,
                          maxScale: 4,
                          child: CommonImageWidget(
                            imagePath: e,
                            isFile: widget.isFile,
                            boxFit: BoxFit.contain,
                          ),
                        ))
                    .toList(),
              ),
            ),
            Align(
              alignment: Alignment.topCenter,
              child: AnimatedOpacity(
                opacity: !isFullScreen ? 1.0 : 0.0,
                duration: const Duration(milliseconds: 250),
                child: _topWidget(),
              ),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: AnimatedOpacity(
                opacity: !isFullScreen ? 1.0 : 0.0,
                duration: const Duration(milliseconds: 250),
                child: _bottomWidget(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
