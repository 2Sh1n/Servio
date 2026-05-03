import 'package:edemand_partner/app/generalImports.dart';

class AppBarPersistentHeaderDelegate extends SliverPersistentHeaderDelegate {
  final Widget child;

  AppBarPersistentHeaderDelegate({required this.child});

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Container(color: context.colorScheme.primaryColor, child: child);
  }

  @override
  double get maxExtent => 80.0;

  @override
  double get minExtent => 70.0;

  @override
  bool shouldRebuild(covariant SliverPersistentHeaderDelegate oldDelegate) {
    return true;
  }
}

class LongAppBarPersistentHeaderDelegate
    extends SliverPersistentHeaderDelegate {
  final Widget child;

  LongAppBarPersistentHeaderDelegate({required this.child});

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Container(color: context.colorScheme.primaryColor, child: child);
  }

  @override
  double get maxExtent => 150.0;

  @override
  double get minExtent => 130.0;

  @override
  bool shouldRebuild(covariant SliverPersistentHeaderDelegate oldDelegate) {
    return true;
  }
}
