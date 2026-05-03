import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class LanguageListShimmerEffect extends StatelessWidget {
  const LanguageListShimmerEffect({final Key? key}) : super(key: key);

  Widget getLanguageItemShimmer(final BuildContext context) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            child: Row(
              children: [
                const CustomShimmerLoadingContainer(
                  height: 25,
                  width: 25,
                  borderRadius: 12.5,
                ),
                const CustomSizedBox(width: 10),
                Expanded(
                  child: CustomShimmerLoadingContainer(
                    width: context.screenWidth * 0.6,
                    height: 18,
                  ),
                ),
              ],
            ),
          ),
          CustomShimmerLoadingContainer(
            width: context.screenWidth,
            height: 1,
            margin: const EdgeInsets.symmetric(horizontal: 10),
            borderRadius: 0,
          ),
        ],
      );

  @override
  Widget build(final BuildContext context) => Column(
        children: List.generate(
          UiUtils.numberOfShimmerContainer,
          (final int index) => getLanguageItemShimmer(context),
        ),
      );
}
