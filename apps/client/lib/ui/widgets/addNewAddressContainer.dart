import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class AddAddressContainer extends StatelessWidget {
  final Function()? onTap;

  const AddAddressContainer({super.key, this.onTap});

  @override
  Widget build(BuildContext context) {
    return CustomInkWellContainer(
      onTap: () {
        onTap?.call();
      },
      child: InkWell(
        child: CustomContainer(
          padding: const EdgeInsets.all(10),
          height: 45,
          color: context.colorScheme.accentColor,
          borderRadius: UiUtils.borderRadiusOf10,
          child: Center(
              child: CustomText(
            " ${"addNewAddress".translate(context: context)}",
            color: AppColors.whiteColors,
            fontWeight: FontWeight.w700,
            fontStyle: FontStyle.normal,
            fontSize: 14,
            textAlign: TextAlign.left,
          )),
        ),
      ),
    );
  }
}
