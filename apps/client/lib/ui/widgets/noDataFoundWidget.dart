import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/enumForWarningMessage.dart';
import 'package:flutter/material.dart';

class NoDataFoundWidget extends StatelessWidget {
  final Color? textColor;
  final String titleKey;
  final Function? onTapRetry;
  final bool showRetryButton;
  final Color? retryButtonBackgroundColor;
  final Color? retryButtonTextColor;
  final String? buttonName;
  final String? subtitleKey;

  const NoDataFoundWidget(
      {required this.titleKey,
      final Key? key,
      this.textColor,
      this.onTapRetry,
      this.showRetryButton = false,
      this.retryButtonBackgroundColor,
      this.retryButtonTextColor,
      this.buttonName,
      this.subtitleKey})
      : super(key: key);

  @override
  Widget build(final BuildContext context) {
    final String imagePath = MessageType.getImageFromMessage(titleKey);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CustomSizedBox(
              height: context.screenHeight * 0.35,
              child: CustomSvgPicture(svgImage: imagePath),
            ),
            const CustomSizedBox(
              height: 11,
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              child: CustomText(
                titleKey,
                textAlign: TextAlign.center,
                color: context.colorScheme.blackColor,
                fontSize: 24,
                fontWeight: FontWeight.w400,
              ),
            ),
            if (subtitleKey != null)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 15),
                child: CustomText(
                  subtitleKey!,
                  textAlign: TextAlign.center,
                  color: context.colorScheme.lightGreyColor,
                  fontSize: 12,
                  fontWeight: FontWeight.w400,
                ),
              ),
            const CustomSizedBox(
              height: 15,
            ),
            if (showRetryButton)
              CustomRoundedButton(
                height: 40,
                widthPercentage: 0.6,
                backgroundColor: retryButtonBackgroundColor ??
                    context.colorScheme.accentColor,
                onTap: () {
                  onTapRetry?.call();
                },
                titleColor: retryButtonTextColor ?? AppColors.whiteColors,
                buttonTitle:
                    (buttonName ?? 'retry').translate(context: context),
                showBorder: false,
              )
          ],
        ),
      ),
    );
  }
}
