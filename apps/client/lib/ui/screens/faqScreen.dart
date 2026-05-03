import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import '../../app/generalImports.dart';

class FaqsScreen extends StatefulWidget {
  const FaqsScreen({final Key? key}) : super(key: key);

  static Route route(final RouteSettings routeSettings) => CupertinoPageRoute(
        builder: (final _) => BlocProvider<FandqsCubit>(
          create: (final BuildContext context) =>
              FandqsCubit(SystemRepository()),
          child: const FaqsScreen(),
        ),
      );

  @override
  State<FaqsScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends State<FaqsScreen> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    setScrollListener();
    Future.delayed(Duration.zero).then((final value) {
      fetchFAQs();
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void setScrollListener() {
    _scrollController.addListener(fetchMoreFaqs);
  }

  void fetchFAQs() {
    context.read<FandqsCubit>().fetchFAQs();
  }

  void fetchMoreFaqs() {
    if (!context.read<FandqsCubit>().hasMoreFAndQs()) {
      return;
    }

    if (!_scrollController.hasClients) {
      return;
    }

    final position = _scrollController.position;

    // Check if content is scrollable
    if (position.maxScrollExtent <= 0) {
      // Content doesn't fill screen, check if we need to load more
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && context.read<FandqsCubit>().hasMoreFAndQs()) {
          context.read<FandqsCubit>().fetchMoreFAndQs();
        }
      });
      return;
    }

    // Use threshold-based approach: load more when within 200 pixels of bottom
    // or when scrolled past 70% of content
    const threshold = 200.0;
    final nextPageTrigger = 0.7 * position.maxScrollExtent;
    final distanceFromBottom = position.maxScrollExtent - position.pixels;

    if (position.pixels > nextPageTrigger || distanceFromBottom < threshold) {
      if (mounted) {
        context.read<FandqsCubit>().fetchMoreFAndQs();
      }
    }
  }

  @override
  Widget build(final BuildContext context) => InterstitialAdWidget(
        child: Scaffold(
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: 'faqs'.translate(context: context),
          ),
          bottomNavigationBar: const BannerAdWidget(),
          body: CustomRefreshIndicator(
            displacment: 12,
            onRefreshCallback: () {
              fetchFAQs();
            },
            child: BlocBuilder<FandqsCubit, FandqsState>(
              builder:
                  (final BuildContext context, final FandqsState faqState) {
                if (faqState is FaqsFetchFailure) {
                  return ErrorContainer(
                    errorMessage:
                        faqState.errorMessage.translate(context: context),
                    onTapRetry: fetchFAQs,
                    showRetryButton: true,
                  );
                } else if (faqState is FaqsFetchSuccess) {
                  // Check if we need to load more FAQs after build
                  WidgetsBinding.instance.addPostFrameCallback((_) {
                    if (mounted &&
                        context.read<FandqsCubit>().hasMoreFAndQs() &&
                        _scrollController.hasClients) {
                      final position = _scrollController.position;
                      // If content doesn't fill screen, load more FAQs
                      if (position.maxScrollExtent <= 0 ||
                          position.maxScrollExtent <
                              position.viewportDimension) {
                        context.read<FandqsCubit>().fetchMoreFAndQs();
                      }
                    }
                  });

                  var isExpanded = false;
                  return faqState.faqsList.isEmpty
                      ? NoDataFoundWidget(
                          titleKey: 'noFaqsFound'.translate(context: context),
                          subtitleKey:
                              "noFaqsFoundSubTitle".translate(context: context),
                        )
                      : SingleChildScrollView(
                          controller: _scrollController,
                          child: Column(
                            children: List.generate(
                              faqState.faqsList.length +
                                  (faqState.isLoadingMoreFaqs ||
                                          faqState.isLoadingMoreError
                                      ? 1
                                      : 0),
                              (final int index) {
                                if (index >= faqState.faqsList.length) {
                                  return CustomLoadingMoreContainer(
                                    isError: faqState.isLoadingMoreError,
                                    onErrorButtonPressed: () {
                                      fetchMoreFaqs();
                                    },
                                  );
                                }
                                return Padding(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 15),
                                  child: Theme(
                                    data: Theme.of(context).copyWith(
                                        dividerColor: Colors.transparent),
                                    child: ExpansionTile(
                                      onExpansionChanged: (final bool value) {
                                        setState(() {
                                          isExpanded = value;
                                        });
                                      },
                                      leading: isExpanded
                                          ? const Icon(Icons.remove)
                                          : const Icon(Icons.add),
                                      tilePadding: EdgeInsets.zero,
                                      childrenPadding: EdgeInsets.zero,
                                      collapsedIconColor:
                                          context.colorScheme.blackColor,
                                      expandedAlignment: Alignment.topLeft,
                                      title: CustomText(
                                        faqState
                                            .faqsList[index].translatedQuestion,
                                        color: context.colorScheme.blackColor,
                                        fontWeight: FontWeight.w600,
                                        fontStyle: FontStyle.normal,
                                        fontSize: 12,
                                        textAlign: TextAlign.left,
                                      ),
                                      controlAffinity:
                                          ListTileControlAffinity.leading,
                                      children: <Widget>[
                                        CustomText(
                                          faqState
                                              .faqsList[index].translatedAnswer,
                                          color: context
                                              .colorScheme.lightGreyColor,
                                          fontWeight: FontWeight.w400,
                                          fontStyle: FontStyle.normal,
                                          fontSize: 12,
                                          maxLines: 100,
                                          textAlign: TextAlign.left,
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        );
                }

                return _getShimmerEffect();
              },
            ),
          ),
        ),
      );

  Widget _getShimmerEffect() => Padding(
        padding: const EdgeInsetsDirectional.only(start: 15, end: 15, top: 15),
        child: SingleChildScrollView(
          child: Column(
            children: List.generate(
                UiUtils.numberOfShimmerContainer,
                (final index) => CustomShimmerLoadingContainer(
                      margin: const EdgeInsets.all(15),
                      width: context.screenWidth,
                      height: 50,
                      borderRadius: UiUtils.borderRadiusOf10,
                    )),
          ),
        ),
      );
}
