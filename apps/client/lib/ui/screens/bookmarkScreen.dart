import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart'; // ignore_for_file: file_names

class BookmarkScreen extends StatefulWidget {
  const BookmarkScreen({final Key? key}) : super(key: key);

  @override
  State<BookmarkScreen> createState() => _BookmarkScreenState();

  static Route route(final RouteSettings routeSettings) => CupertinoPageRoute(
        builder: (final BuildContext context) => const BookmarkScreen(),
      );
}

class _BookmarkScreenState extends State<BookmarkScreen> {
  final ScrollController _scrollController = ScrollController();

  void fetchBookmark() {
    context.read<BookmarkCubit>().fetchBookmark(type: 'list');
  }

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(fetchMoreBookMarks);
  }

  void fetchMoreBookMarks() {
    if (!context.read<BookmarkCubit>().hasMoreBookMark()) {
      return;
    }

// nextPageTrigger will have a value equivalent to 70% of the list size.
    final nextPageTrigger = 0.7 * _scrollController.position.maxScrollExtent;

// _scrollController fetches the next paginated data when the current position of the user on the screen has surpassed
    if (_scrollController.position.pixels > nextPageTrigger) {
      if (mounted) {
        context.read<BookmarkCubit>().fetchMoreBookmark(type: "list");
      }
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(final BuildContext context) => InterstitialAdWidget(
        child: Scaffold(
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: 'bookmark'.translate(context: context),
          ),
          bottomNavigationBar: Padding(
              padding: EdgeInsets.only(
                  bottom: MediaQuery.of(context).padding.bottom),
              child: const BannerAdWidget()),
          body: CustomSizedBox(
            height: context.screenHeight,
            width: context.screenWidth,
            child: BlocConsumer<BookmarkCubit, BookmarkState>(
              listener: (final BuildContext context,
                  final BookmarkState bookmarkState) {
                if (bookmarkState is BookmarkFetchFailure) {
                  UiUtils.showMessage(
                    context,
                    bookmarkState.errorMessage,
                    ToastificationType.error,
                  );
                } else if (bookmarkState is BookmarkFetchSuccess) {}
              },
              builder: (final BuildContext context,
                  final BookmarkState bookmarkState) {
                if (bookmarkState is BookmarkFetchFailure) {
                  return Center(
                    child: ErrorContainer(
                      errorMessage: bookmarkState.errorMessage
                          .translate(context: context),
                      onTapRetry: () {
                        fetchBookmark();
                      },
                      showRetryButton: true,
                    ),
                  );
                } else if (bookmarkState is BookmarkFetchSuccess) {
                  if (bookmarkState.bookmarkList.isEmpty) {
                    return NoDataFoundWidget(
                      titleKey: 'noBookmarkFound'.translate(context: context),
                      subtitleKey:
                          "noBookmarkFoundSubTitle".translate(context: context),
                    );
                  }
                  return _getProviderList(
                    providerList: bookmarkState.bookmarkList,
                    isLoadingMoreData: bookmarkState.isLoadingMoreData,
                    isLoadingMoreError: bookmarkState.isLoadingMoreError,
                  );
                }

                return const SingleChildScrollView(
                    child: ProviderListShimmerEffect(
                  showTotalProviderContainer: false,
                ));
              },
            ),
          ),
        ),
      );

  Widget _getProviderList({
    required final List<Providers> providerList,
    required final bool isLoadingMoreData,
    required final bool isLoadingMoreError,
  }) {
    return ListView.builder(
      padding: const EdgeInsets.all(10),
      controller: _scrollController,
      physics: const AlwaysScrollableScrollPhysics(),
      shrinkWrap: true,
      itemCount: providerList.length,
      itemBuilder: (final BuildContext context, final int index) {
        if (index >=
            providerList.length +
                (isLoadingMoreData || isLoadingMoreError ? 1 : 0)) {
          return CustomLoadingMoreContainer(
            isError: isLoadingMoreError,
            onErrorButtonPressed: () {
              fetchMoreBookMarks();
            },
          );
        }
        return ProviderListItem(
          providerDetails: providerList[index],
        );
      },
    );
  }
}
