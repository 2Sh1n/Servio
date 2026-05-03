import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/cubits/cart/removeCartCubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_shader_snap/flutter_shader_snap.dart';
import 'package:intl/intl.dart';

class CartSubDetailsContainer extends StatefulWidget {
  const CartSubDetailsContainer({final Key? key, this.providerID})
      : super(key: key);
  final String? providerID;

  @override
  State<CartSubDetailsContainer> createState() =>
      _CartSubDetailsContainerState();
}

class _CartSubDetailsContainerState extends State<CartSubDetailsContainer>
    with TickerProviderStateMixin {
  late AnimationController _animController;
  late Animation<Offset> _animOffset;

  late final _controller = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 1),
  );

  @override
  void initState() {
    super.initState();

    _controller.addStatusListener((status) async {
      if (status == AnimationStatus.completed) {
        context.read<CartCubit>().clearCartCubit();
        _controller.reset();
      }
    });
  }

  @override
  void didChangeDependencies() {
    _animController = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 400));
    final curve =
        CurvedAnimation(curve: Curves.easeInOut, parent: _animController);
    _animOffset = Tween<Offset>(begin: Offset.zero, end: const Offset(-0.25, 0))
        .animate(curve);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (Directionality.of(context)
          .toString()
          .contains(TextDirection.RTL.value.toLowerCase())) {
        _animOffset =
            Tween<Offset>(begin: Offset.zero, end: const Offset(0.25, 0))
                .animate(curve);
        setState(() {});
      }
    });

    super.didChangeDependencies();
  }

  @override
  Widget build(final BuildContext context) => AnimationFromBottomSide(
        delay: 250,
        child: SnapShader(
          snapShaderType: SnapShaderType.smoke,
          controller: _controller,
          child: BlocBuilder<SystemSettingCubit, SystemSettingState>(
            builder: (context, systemState) {
              // Don't show cart data until system settings are successfully fetched
              if (systemState is! SystemSettingFetchSuccess) {
                return const CustomSizedBox();
              }

              return BlocBuilder<CartCubit, CartState>(
                builder: (BuildContext context, final CartState state) {
                  if (state is CartInitial) {
                    return const CustomSizedBox();
                  } else if (state is CartFetchSuccess) {
                    if (state.cartData.cartDetails == null) {
                      return const CustomSizedBox();
                    }

                    return state.cartData.cartDetails!.isEmpty
                        ? const CustomSizedBox()
                        : BlocProvider(
                            create: (context) => RemoveCartCubit(),
                            child: Builder(builder: (context) {
                              return Stack(
                                children: [
                                  CustomContainer(
                                    padding: EdgeInsets.fromLTRB(
                                        10.rw(context),
                                        5.rh(context),
                                        5.rw(context),
                                        5.rh(context)),
                                    color: AppColors.redColor,
                                    borderRadiusStyle: const BorderRadius.only(
                                      topLeft: Radius.circular(
                                          UiUtils.borderRadiusOf10),
                                      topRight: Radius.circular(
                                          UiUtils.borderRadiusOf10),
                                    ),
                                    height: kBottomNavigationBarHeight * 1.2,
                                    width: context.screenWidth,
                                    child: CustomInkWellContainer(
                                      borderRadius: BorderRadius.circular(
                                          UiUtils.borderRadiusOf10),
                                      onTap: () {
                                        _animController.reverse();
                                        context
                                            .read<RemoveCartCubit>()
                                            .removeCart(
                                                providerId:
                                                    state.cartData.providerId ??
                                                        "0");
                                      },
                                      child: Align(
                                        alignment:
                                            AlignmentDirectional.centerEnd,
                                        child: Padding(
                                          padding:
                                              const EdgeInsetsDirectional.only(
                                                  end: 10),
                                          child: CustomText(
                                            'remove'
                                                .translate(context: context),
                                            color: AppColors.whiteColors,
                                            fontSize: 14,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                  SlideTransition(
                                    position: _animOffset,
                                    child: CustomContainer(
                                      padding: const EdgeInsets.fromLTRB(
                                          10, 5, 5, 5),
                                      color: context.colorScheme.accentColor,
                                      // borderRadius: UiUtils.borderRadiusOf10,
                                      borderRadiusStyle:
                                          const BorderRadius.only(
                                        topLeft: Radius.circular(
                                            UiUtils.borderRadiusOf10),
                                        topRight: Radius.circular(
                                            UiUtils.borderRadiusOf10),
                                      ),
                                      height: kBottomNavigationBarHeight * 1.2,
                                      width: context.screenWidth,
                                      child: Row(
                                        children: <Widget>[
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              mainAxisAlignment:
                                                  MainAxisAlignment.center,
                                              children: [
                                                RichText(
                                                  text: TextSpan(
                                                    children: [
                                                      TextSpan(
                                                        text: (state.cartData
                                                                    .subTotal ??
                                                                "0")
                                                            .priceFormat(
                                                                context),
                                                        style: const TextStyle(
                                                          color: AppColors
                                                              .whiteColors,
                                                          fontWeight:
                                                              FontWeight.w600,
                                                        ),
                                                      ),
                                                      const TextSpan(
                                                        text:
                                                            "  |  ", // Acts as a divider
                                                        style: TextStyle(
                                                          color: AppColors
                                                              .whiteColors,
                                                          fontWeight:
                                                              FontWeight.w600,
                                                        ),
                                                      ),
                                                      WidgetSpan(
                                                        child: GestureDetector(
                                                          onTap: () {
                                                            if (state.cartData
                                                                    .providerId !=
                                                                widget
                                                                    .providerID) {
                                                              Navigator
                                                                  .pushNamed(
                                                                context,
                                                                providerRoute,
                                                                arguments: {
                                                                  "providerId": state
                                                                      .cartData
                                                                      .providerId
                                                                },
                                                              ).then((Object?
                                                                  value) {
                                                                Routes.previousRoute =
                                                                    Routes
                                                                        .currentRoute;
                                                                Routes.currentRoute =
                                                                    navigationRoute;
                                                              });
                                                            }
                                                          },
                                                          child: Row(
                                                            mainAxisSize:
                                                                MainAxisSize
                                                                    .min,
                                                            children: [
                                                              CustomText(
                                                                "${state.cartData.totalQuantity} ${(int.parse(state.cartData.totalQuantity ?? "0") > 1 ? "services" : "service").translate(context: context)} ",
                                                                color: AppColors
                                                                    .whiteColors,
                                                                fontWeight:
                                                                    FontWeight
                                                                        .w600,
                                                              ),
                                                              const Icon(
                                                                Icons
                                                                    .arrow_forward_ios,
                                                                color: AppColors
                                                                    .whiteColors,
                                                                size: 12,
                                                              ),
                                                            ],
                                                          ),
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                ),
                                                CustomText(
                                                  "${"by".translate(context: context)}. ${state.cartData.translatedCompanyName}",
                                                  fontSize: 12,
                                                  color: AppColors.whiteColors,
                                                  maxLines: 1,
                                                ),
                                              ],
                                            ),
                                          ),
                                          MaterialButton(
                                            onPressed: () {
                                              context
                                                  .read<GetPromocodeCubit>()
                                                  .getPromocodes(
                                                      providerIdOrSlug: state
                                                              .cartData
                                                              .providerId ??
                                                          "0");

                                              Navigator.pushNamed(
                                                context,
                                                scheduleScreenRoute,
                                                arguments: {
                                                  "isFrom": "cart",
                                                  "providerName": state.cartData
                                                          .translatedProviderName ??
                                                      '',
                                                  "providerId": state.cartData
                                                          .providerId ??
                                                      "0",
                                                  "companyName": state.cartData
                                                          .translatedCompanyName ??
                                                      '',
                                                  "providerAdvanceBookingDays":
                                                      state.cartData
                                                          .advanceBookingDays,
                                                },
                                              );
                                            },
                                            height: double.maxFinite,
                                            child: CustomContainer(
                                              padding:
                                                  const EdgeInsetsDirectional
                                                      .symmetric(
                                                      vertical: 12,
                                                      horizontal: 18),
                                              borderRadius:
                                                  UiUtils.borderRadiusOf6,
                                              color: AppColors.whiteColors,
                                              child: CustomText(
                                                'checkOut'.translate(
                                                    context: context),
                                                color: context
                                                    .colorScheme.accentColor,
                                                fontSize: 13,
                                                fontWeight: FontWeight.w500,
                                              ),
                                            ),
                                          ),
                                          BlocConsumer<RemoveCartCubit,
                                              RemoveCartState>(
                                            listener:
                                                (context, removeCartState) {
                                              if (removeCartState
                                                  is RemoveCartSuccess) {
                                                _controller.forward();
                                              }
                                            },
                                            builder:
                                                (context, removeCartState) {
                                              final bool isLoading =
                                                  removeCartState
                                                      is RemoveCartInProgress;

                                              return CustomInkWellContainer(
                                                onTap: () {
                                                  if (_animController
                                                      .isCompleted) {
                                                    _animController.reverse();
                                                  } else {
                                                    _animController.forward();
                                                  }
                                                },
                                                borderRadius:
                                                    BorderRadius.circular(
                                                        UiUtils
                                                            .borderRadiusOf50),
                                                child: CustomContainer(
                                                    margin:
                                                        const EdgeInsetsDirectional
                                                            .only(end: 5),
                                                    height: 24,
                                                    width: 24,
                                                    borderRadius: UiUtils
                                                        .borderRadiusOf50,
                                                    color: AppColors.whiteColors
                                                        .withValues(alpha: 0.7),
                                                    child: isLoading
                                                        ? const Center(
                                                            child:
                                                                CustomCircularProgressIndicator(
                                                              widthAndHeight:
                                                                  20,
                                                            ),
                                                          )
                                                        : CustomToolTip(
                                                            toolTipMessage:
                                                                "deleteCart"
                                                                    .translate(
                                                                        context:
                                                                            context),
                                                            child: Icon(
                                                              Icons.close,
                                                              color: context
                                                                  .colorScheme
                                                                  .accentColor,
                                                              size: 16,
                                                            ),
                                                          )),
                                              );
                                            },
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ],
                              );
                            }),
                          );
                  }
                  return const CustomSizedBox();
                },
              );
            },
          ),
        ),
      );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }
}
