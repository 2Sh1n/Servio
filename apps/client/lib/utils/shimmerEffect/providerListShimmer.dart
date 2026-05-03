import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class ProviderListShimmerEffect extends StatelessWidget {
  final bool showTotalProviderContainer;

  const ProviderListShimmerEffect(
      {final Key? key, required this.showTotalProviderContainer})
      : super(key: key);

  Widget getProviderListShimmerEffect(final BuildContext context) =>
      SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            showTotalProviderContainer
                ? Padding(
                    padding: const EdgeInsetsDirectional.only(
                        top: 10, start: 10, end: 10),
                    child: CustomShimmerLoadingContainer(
                      width: context.screenWidth * 0.5.rw(context),
                      height: 30,
                    ),
                  )
                : const SizedBox.shrink(),
            Column(
              children: List.generate(
                UiUtils.numberOfShimmerContainer,
                (final int index) => Padding(
                  padding: const EdgeInsetsDirectional.only(
                      top: 10, start: 10, end: 10),
                  child: Row(
                    children: [
                      const CustomShimmerLoadingContainer(
                        width: 100,
                        height: 100,
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CustomShimmerLoadingContainer(
                            width: context.screenWidth * 0.5,
                            height: 10,
                            margin: const EdgeInsetsDirectional.only(
                                top: 10, start: 10, end: 10),
                          ),
                          CustomShimmerLoadingContainer(
                            width: context.screenWidth * 0.4,
                            height: 10,
                            margin: const EdgeInsetsDirectional.only(
                                top: 10, start: 10, end: 10),
                          ),
                          const CustomShimmerLoadingContainer(
                            width: 100,
                            height: 10,
                            margin: EdgeInsetsDirectional.only(
                                top: 10, start: 10, end: 10),
                          ),
                          const CustomShimmerLoadingContainer(
                            width: 100,
                            height: 10,
                            margin: EdgeInsetsDirectional.only(
                                top: 10, start: 10, end: 10),
                          ),
                        ],
                      )
                    ],
                  ),
                ),
              ),
            )
          ],
        ),
      );

  @override
  Widget build(final BuildContext context) =>
      getProviderListShimmerEffect(context);
}
