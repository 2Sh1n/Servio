import 'package:cached_network_image/cached_network_image.dart';
import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomCachedNetworkImage extends StatelessWidget {
  const CustomCachedNetworkImage({
    required this.networkImageUrl,
    final Key? key,
    this.width,
    this.height,
    this.fit,
    this.isFile,
    this.defaultColourFilter = true,
    this.avoideResponsive = false,
  }) : super(key: key);
  final String networkImageUrl;
  final double? width, height;
  final BoxFit? fit;
  final bool? isFile;
  final bool defaultColourFilter;
  final bool avoideResponsive;
  @override
  Widget build(final BuildContext context) {
    return networkImageUrl.endsWith('.svg')
        ? SvgPicture.network(
            networkImageUrl,
            fit: fit ?? BoxFit.fill,
            width: avoideResponsive ? width : width?.rs(context),
            height: avoideResponsive ? height : height?.rs(context),
            colorFilter: defaultColourFilter
                ? ColorFilter.mode(
                    context.colorScheme.accentColor, BlendMode.srcIn)
                : null,
            placeholderBuilder: (final BuildContext context) => Center(
              child: CustomSvgPicture(
                avoideResponsive: avoideResponsive,
                svgImage: AppAssets.placeHolder,
                width: width,
                height: height,
                boxFit: BoxFit.contain,
              ),
            ),
          )
        : CachedNetworkImage(
            imageUrl: networkImageUrl,
            imageBuilder: (context, imageProvider) {
              return CustomContainer(
                avoideResponsive: avoideResponsive,
                height: height,
                width: width,
                image: DecorationImage(
                  image: imageProvider,
                  fit: fit ?? BoxFit.contain,
                ),
              );
            },
            maxWidthDiskCache: 500,
            maxHeightDiskCache: 500,
            memCacheWidth: 150,
            memCacheHeight: 150,
            fit: fit ?? BoxFit.contain,
            errorWidget: (BuildContext context, String url, final error) =>
                Center(
              child: Center(
                child: CustomSvgPicture(
                  avoideResponsive: avoideResponsive,
                  svgImage: AppAssets.noImageFound,
                  width: width,
                  height: height,
                  boxFit: BoxFit.contain,
                ),
              ),
            ),
            placeholder: (final BuildContext context, final String url) =>
                Center(
              child: CustomSvgPicture(
                avoideResponsive: avoideResponsive,
                svgImage: AppAssets.placeHolder,
                width: width,
                height: height,
                boxFit: BoxFit.cover,
              ),
            ),
          );
  }
}
