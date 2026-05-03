import 'package:edemand_partner/app/generalImports.dart';

class CustomCachedNetworkImage extends StatelessWidget {
  const CustomCachedNetworkImage({
    required this.imageUrl,
    Key? key,
    this.width,
    this.height,
    this.fit,
    this.isFile,
  }) : super(key: key);

  final String imageUrl;
  final double? width, height;
  final BoxFit? fit;
  final bool? isFile;

  @override
  Widget build(BuildContext context) {
    final String lowerUrl = imageUrl.toLowerCase();

    if (lowerUrl.endsWith('.svg')) {
      return SvgPicture.network(
        imageUrl,
        fit: fit ?? BoxFit.fill,
        width: width,
        height: height,
        colorFilter: ColorFilter.mode(
          context.colorScheme.accentColor,
          BlendMode.srcIn,
        ),
        placeholderBuilder: (context) => Center(
          child: Image.asset(
            AppAssets.placeholder, // now PNG
            width: width,
            height: height,
            fit: BoxFit.contain,
          ),
        ),
      );
    }

    return CachedNetworkImage(
      imageUrl: imageUrl,
      imageBuilder: (context, imageProvider) {
        return CustomContainer(
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
      width: width,
      height: height,
      fit: fit ?? BoxFit.contain,
      errorWidget: (context, url, error) => Center(
        child: Image.asset(
          AppAssets.noImageFound, // now PNG
          width: width,
          height: height,
          fit: BoxFit.contain,
        ),
      ),
      placeholder: (context, url) => Center(
        child: Image.asset(
          AppAssets.placeholder, // now PNG
          width: width,
          height: height,
          fit: BoxFit.cover,
        ),
      ),
    );
  }
}