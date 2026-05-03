import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/cubits/authentication/logoutCubit.dart';
import 'package:flutter/material.dart';

class ErrorContainer extends StatelessWidget {
  const ErrorContainer({
    required this.errorMessage,
    final Key? key,
    this.errorMessageColor,
    this.buttonName,
    this.errorMessageFontSize,
    this.onTapRetry,
    this.showErrorImage,
    this.retryButtonBackgroundColor,
    this.retryButtonTextColor,
    this.subErrorMessage,
    this.showRetryButton = true,
    this.loginSource,
  }) : super(key: key);
  final String errorMessage;
  final String? buttonName;
  final bool? showErrorImage;
  final Color? errorMessageColor;
  final double? errorMessageFontSize;
  final Function? onTapRetry;
  final bool showRetryButton;
  final Color? retryButtonBackgroundColor;
  final Color? retryButtonTextColor;
  final String? subErrorMessage;
  final String? loginSource;

  @override
  Widget build(final BuildContext context) => Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (errorMessage == "noInternetFound".translate(context: context))
                CustomSizedBox(
                  height: context.screenHeight * 0.35,
                  child:
                      const CustomSvgPicture(svgImage: AppAssets.noConnection),
                )
              else
                CustomSizedBox(
                  height: context.screenHeight * 0.35,
                  child: const CustomSvgPicture(
                    svgImage: AppAssets.somethingWentWrong,
                  ),
                ),
              CustomSizedBox(
                height: context.screenHeight * 0.025,
              ),
              CustomText(
                (errorMessage == "noInternetFound"
                        ? "noInternetFoundTitle"
                        : errorMessage == "somethingWentWrong"
                            ? "somethingWentWrongTitle"
                            : errorMessage)
                    .translate(context: context),
                textAlign: TextAlign.center,
                color: context.colorScheme.blackColor,
                fontSize: 24,
                fontWeight: FontWeight.w400,
              ),
              Padding(
                padding: const EdgeInsets.all(8),
                child: CustomText(
                  subErrorMessage?.translate(context: context) ?? '',
                  textAlign: TextAlign.center,
                  color: context.colorScheme.blackColor,
                  fontSize: errorMessageFontSize ?? 14,
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
                    if (UiUtils.authenticationError) {
                      //logout and redirect to login screen
                      context.read<LogoutCubit>().logout(context);
                      Navigator.pushNamed(
                        context,
                        loginRoute,
                        arguments: {'source': 'profileScreen'},
                      );
                      UiUtils.authenticationError = false;
                    } else {
                      onTapRetry?.call();
                    }
                  },
                  titleColor: retryButtonTextColor ?? AppColors.whiteColors,
                  buttonTitle: UiUtils.authenticationError
                      ? 'goToLogin'.translate(context: context)
                      : (buttonName ?? 'retry').translate(context: context),
                  showBorder: false,
                )
            ],
          ),
        ),
      );
}
