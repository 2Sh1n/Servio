import 'package:e_demand/app/generalImports.dart';

import 'package:flutter/material.dart';

class AddToCartComtainer extends StatefulWidget {
  const AddToCartComtainer({super.key});

  @override
  State<AddToCartComtainer> createState() => _AddToCartComtainerState();
}

class _AddToCartComtainerState extends State<AddToCartComtainer> {
  @override
  Widget build(BuildContext context) {
    return BlocBuilder<CartCubit, CartState>(
      builder: (BuildContext context, final CartState state) {
        if (state is CartFetchSuccess) {
          return CustomContainer(
            boxShadow: [
              BoxShadow(
                  blurRadius: 2.0,
                  offset: const Offset(1.0, 0),
                  color: context.colorScheme.lightGreyColor),
            ],
            padding: const EdgeInsets.all(10),
            color: context.colorScheme.secondaryColor,
            height: kBottomNavigationBarHeight * 1.3,
            width: context.screenWidth,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CustomText(
                      state.cartData.subTotal!.priceFormat(context),
                      fontWeight: FontWeight.w600,
                      fontSize: 18,
                      color: context.colorScheme.blackColor,
                    ),
                    RichText(
                      text: TextSpan(
                        children: [
                          TextSpan(
                            text: "${state.cartData.totalQuantity} ",
                            style: TextStyle(
                              color: context.colorScheme.accentColor,
                              fontWeight: FontWeight.w400,
                              fontSize: 14,
                            ),
                          ),
                          TextSpan(
                            text: (int.parse(state.cartData.totalQuantity ??
                                            "0") >
                                        1
                                    ? "services"
                                    : "service")
                                .translate(context: context),
                            style: TextStyle(
                              color: context.colorScheme.blackColor,
                              fontWeight: FontWeight.w400,
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const CustomSizedBox(
                  width: 20,
                ),
                Expanded(
                  child: GestureDetector(
                    onTap: () {
                      Navigator.pushNamed(
                        context,
                        scheduleScreenRoute,
                        arguments: {
                          "isFrom": "cart",
                          "providerName":
                              state.cartData.translatedProviderName ?? '',
                          "providerId": state.cartData.providerId ?? "0",
                          "companyName":
                              state.cartData.translatedCompanyName ?? '',
                          "providerAdvanceBookingDays":
                              state.cartData.advanceBookingDays,
                          "cartFromProvider": true
                        },
                      );
                    },
                    child: CustomContainer(
                      color: context.colorScheme.accentColor,
                      borderRadius: UiUtils.borderRadiusOf10,
                      child: Center(
                        child: CustomText(
                          'continueText'.translate(context: context),
                          color: context.colorScheme.secondaryColor,
                          fontSize: 16,
                          fontWeight: FontWeight.w400,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          );
        }
        return const CustomSizedBox();
      },
    );
  }
}
