import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomImageContainer extends StatelessWidget {
  const CustomImageContainer({
    this.borderRadius,
    required this.imageURL,
    required this.height,
    required this.width,
    this.borderRadiusStyle,
    this.boxShadow,
    final Key? key,
    this.boxFit,
  }) : super(key: key);
  final double height;
  final double width;
  final double? borderRadius;
  final String imageURL;
  final BoxFit? boxFit;
  final BorderRadiusGeometry? borderRadiusStyle;
  final List<BoxShadow>? boxShadow;

  @override
  Widget build(final BuildContext context) => CustomContainer(
        height: height,
        width: width,
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: borderRadius,
        borderRadiusStyle: borderRadiusStyle,
        boxShadow: boxShadow ??
            const [
              BoxShadow(
                color: Color(0x33000000),
                offset: Offset(0, 3),
                blurRadius: 6,
              )
            ],
        child: ClipRRect(
          borderRadius: borderRadius != null
              ? BorderRadius.circular(borderRadius ?? 0)
              : borderRadiusStyle!,
          child: CustomCachedNetworkImage(
            height: height,
            width: width,
            networkImageUrl: imageURL,
            fit: boxFit,
          ),
        ),
      );
}
