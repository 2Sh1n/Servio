import 'package:e_demand/ui/widgets/customLoadingMore.dart';
import 'package:e_demand/ui/widgets/noDataFoundWidget.dart';
import 'package:e_demand/utils/scrollExtension.dart';
import 'package:flutter/material.dart';

class PaginatedListView<T> extends StatefulWidget {
  const PaginatedListView({
    required this.items,
    required this.itemBuilder,
    required this.isLoadingMoreError,
    required this.onLoadMore,
    required this.isLoadingMore,
    required this.separatorBuilder,
    this.noDataTitleKey,
    this.shrinkWrap = false,
    this.physics,
    this.padding,
    this.scrollDirection,
    super.key,
  });

  final List<T> items;
  final Future<void> Function()? onLoadMore;
  final Widget Function(BuildContext, T) itemBuilder;
  final Widget Function(BuildContext, T)? separatorBuilder;
  final bool? isLoadingMore;
  final bool isLoadingMoreError;
  final bool shrinkWrap;
  final ScrollPhysics? physics;
  final EdgeInsetsGeometry? padding;
  final String? noDataTitleKey;
  final Axis? scrollDirection;

  @override
  State<PaginatedListView<T>> createState() => _PaginatedListViewState<T>();
}

class _PaginatedListViewState<T> extends State<PaginatedListView<T>> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addLoadMoreListener(() {
      if (!(widget.isLoadingMore ?? true)) {
        widget.onLoadMore?.call();
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.items.isEmpty) {
      return NoDataFoundWidget(
          titleKey: widget.noDataTitleKey ?? 'noDataFound');
    }
    return ListView.separated(
      physics: widget.physics,
      shrinkWrap: widget.shrinkWrap,
      padding: widget.padding,
      scrollDirection: widget.scrollDirection ?? Axis.vertical,
      controller: _scrollController,
      itemCount: (widget.isLoadingMore ?? false)
          ? widget.items.length + 1
          : widget.items.length,
      itemBuilder: (context, index) {
        if (index < widget.items.length) {
          return widget.itemBuilder(context, widget.items[index]);
        } else {
          return CustomLoadingMore(
            isError: widget.isLoadingMoreError,
            onErrorButtonPressed: () {
              if (!(widget.isLoadingMore ?? true)) {
                widget.onLoadMore?.call();
              }
            },
          );
        }
      },
      separatorBuilder: (context, index) {
        if (widget.separatorBuilder != null) {
          return widget.separatorBuilder!(context, widget.items[index]);
        }
        return const SizedBox.shrink();
      },
    );
  }
}
