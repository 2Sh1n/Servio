import 'package:flutter/widgets.dart';

extension ScrollPagination on ScrollController {
  void addLoadMoreListener(VoidCallback onLoadMore, {double threshold = 0.7}) {
    addListener(() {
      if (position.pixels >= position.maxScrollExtent * threshold) {
        onLoadMore();
      }
    });
  }
}
