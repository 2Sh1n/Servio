import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CustomDialogLayout extends StatelessWidget {
  final String title;
  final String? description;
  final String confirmButtonName;
  final String cancelButtonName;
  final Color confirmButtonBackgroundColor;
  final Color cancelButtonBackgroundColor;
  final Function cancelButtonPressed;
  final Function confirmButtonPressed;
  final Widget? icon;
  final Widget? confirmButtonChild;
  final bool? showProgressIndicator;

  const CustomDialogLayout(
      {final Key? key,
      required this.title,
      this.description,
      required this.confirmButtonName,
      required this.cancelButtonName,
      this.icon,
      required this.cancelButtonPressed,
      required this.confirmButtonPressed,
      required this.confirmButtonBackgroundColor,
      required this.cancelButtonBackgroundColor,
      this.confirmButtonChild,
      this.showProgressIndicator = false})
      : super(key: key);

  @override
  Widget build(final BuildContext context) => CustomContainer(
        avoideResponsive: true,
        height: context.screenHeight * 0.294,
        color: context.colorScheme.secondaryColor,
        borderRadius: UiUtils.borderRadiusOf10,
        alignment: Alignment.center,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: CustomSizedBox(
                height: context.screenHeight * 0.3 - 50,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      if (icon != null) ...[
                        const CustomSizedBox(height: 20),
                        icon!,
                        const CustomSizedBox(height: 20),
                      ] else ...[
                        const CustomSizedBox(height: 10),
                      ],
                      CustomText(
                        title.translate(context: context),
                        textAlign: TextAlign.center,
                        color: context.colorScheme.blackColor,
                        fontSize: 16,
                      ),
                      const CustomSizedBox(height: 5),
                      if (description != null) ...[
                        const CustomSizedBox(height: 10),
                        CustomText(
                          description!.translate(context: context),
                          textAlign: TextAlign.center,
                          color: context.colorScheme.lightGreyColor,
                          fontSize: 12,
                          maxLines: 10,
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: CloseAndConfirmButton(
                avoideResponsive: true,
                confirmButtonBackgroundColor: confirmButtonBackgroundColor,
                confirmButtonName: confirmButtonName,
                confirmButtonChild: confirmButtonChild,
                showProgressIndicator: showProgressIndicator,
                closeButtonBackgroundColor: cancelButtonBackgroundColor,
                closeButtonName: cancelButtonName,
                closeButtonPressed: () {
                  cancelButtonPressed.call();
                },
                confirmButtonPressed: () {
                  confirmButtonPressed.call();
                },
              ),
            )
          ],
        ),
      );
}
