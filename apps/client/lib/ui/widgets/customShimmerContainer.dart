import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomShimmerLoadingContainer extends StatelessWidget {
  const CustomShimmerLoadingContainer(
      {final Key? key, this.height, this.width, this.borderRadius, this.margin})
      : super(key: key);
  final double? height;
  final double? width;
  final double? borderRadius;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(final BuildContext context) => Shimmer.fromColors(
        baseColor: context.colorScheme.shimmerBaseColor,
        highlightColor: context.colorScheme.shimmerHighlightColor,
        child: CustomContainer(
          width: width,
          margin: margin,
          height: height ?? 10,
          color: context.colorScheme.shimmerContentColor,
          borderRadius: borderRadius ?? UiUtils.borderRadiusOf10,
        ),
      );
}
