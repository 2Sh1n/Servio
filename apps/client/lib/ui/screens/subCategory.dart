import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/bottomsheets/layouts/selectableListBottomSheet.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

enum CategoryType { category, subcategory }

class SubCategoryScreen extends StatefulWidget {
  const SubCategoryScreen({
    required this.categoryId,
    required this.appBarTitle,
    final Key? key,
    this.subCategoryId,
  }) : super(key: key);

  final String categoryId;
  final String? subCategoryId;
  final String appBarTitle;

  @override
  State<SubCategoryScreen> createState() => _SubCategoryScreenState();

  static Route route(final RouteSettings routeSettings) {
    final arguments = routeSettings.arguments as Map;
    return CupertinoPageRoute(
      builder: (final _) => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (final context) => SubCategoryAndProviderCubit(
              providerRepository: ProviderRepository(),
              subCategoryRepository: SubCategoryRepository(),
            ),
          ),
          BlocProvider(
            create: (final context) => ProviderCubit(ProviderRepository()),
          ),
          BlocProvider(
            create: (final context) => InterstitialAdCubit(),
          ),
        ],
        child: SubCategoryScreen(
          categoryId: arguments["categoryId"],
          appBarTitle: arguments["appBarTitle"],
          subCategoryId: arguments["subCategoryId"],
        ),
      ),
    );
  }
}

class _SubCategoryScreenState extends State<SubCategoryScreen>
    with SingleTickerProviderStateMixin {
  String sortByDefaultOptionValue = 'popularity';
  late String sortByDefaultOptionName = "popularity";

  @override
  void initState() {
    super.initState();

    Future.delayed(Duration.zero).then((final value) {
      fetchSubCategoryAndProviderData();
    });
  }

  void fetchSubCategoryAndProviderData() {
    context.read<SubCategoryAndProviderCubit>().getSubCategoriesAndProviderList(
          subCategoryID:
              widget.subCategoryId == '' ? null : widget.subCategoryId,
          categoryID: widget.categoryId == '' ? null : widget.categoryId,
          providerSortBy: sortByDefaultOptionValue,
        );
  }

  Widget _getSubCategoryShimmerEffect() => Padding(
        padding: const EdgeInsetsDirectional.only(top: 10, start: 10, end: 10),
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: List.generate(
              UiUtils.numberOfShimmerContainer,
              (final int index) => Column(
                children: [
                  const CustomShimmerLoadingContainer(
                    width: 100,
                    height: 100,
                    margin: EdgeInsets.symmetric(horizontal: 10),
                    borderRadius: UiUtils.borderRadiusOf50,
                  ),
                  CustomShimmerLoadingContainer(
                    width: 120,
                    height: context.screenHeight * 0.01,
                    margin:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  ),
                ],
              ),
            ),
          ),
        ),
      );

  @override
  Widget build(final BuildContext context) => Scaffold(
        backgroundColor: context.colorScheme.primaryColor,
        appBar: UiUtils.getSimpleAppBar(
            context: context,
            backgroundColor: context.colorScheme.primaryColor,
            centerTitle: true,
            titleWidget: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                CustomText(
                  widget.appBarTitle,
                  color: context.colorScheme.blackColor,
                  fontWeight: FontWeight.w600,
                  fontSize: 18,
                ),
                context.watch<SubCategoryAndProviderCubit>().state
                        is SubCategoryAndProviderFetchSuccess
                    ? HeadingAmountAnimation(
                        key: ValueKey((context
                                .read<SubCategoryAndProviderCubit>()
                                .state as SubCategoryAndProviderFetchSuccess)
                            .totalProviders),
                        text:
                            '${(context.read<SubCategoryAndProviderCubit>().state as SubCategoryAndProviderFetchSuccess).totalProviders.toString()} ${'providers'.translate(context: context)}',
                        textStyle: TextStyle(
                          color: context.colorScheme.lightGreyColor,
                          fontWeight: FontWeight.w400,
                          fontSize: 14,
                        ),
                      )
                    : CustomSizedBox(
                        width: context.screenWidth * 0.7,
                        height: context.screenHeight * 0.025,
                      )
              ],
            ),
            actions: [
              BlocBuilder<SubCategoryAndProviderCubit,
                      SubCategoryAndProviderState>(
                  builder: (final BuildContext context,
                      final SubCategoryAndProviderState
                          subCategoryAndProviderState) {
                if (subCategoryAndProviderState
                    is SubCategoryAndProviderFetchSuccess)
                  return subCategoryAndProviderState.providerList.isNotEmpty
                      ? Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 8.0),
                          child: CustomInkWellContainer(
                            onTap: () {
                              UiUtils.showBottomSheet(
                                  context: context,
                                  enableDrag: true,
                                  child: SelectableListBottomSheet(
                                      bottomSheetTitle: "sortBy",
                                      itemList: [
                                        {
                                          "title": "popularity",
                                          "id": "0",
                                          "isSelected":
                                              sortByDefaultOptionName ==
                                                  "popularity"
                                        },
                                        {
                                          "title": "discountHighToLow",
                                          "id": "1",
                                          "isSelected":
                                              sortByDefaultOptionName ==
                                                  "discount"
                                        },
                                        {
                                          "title": "topRated",
                                          "id": "2",
                                          "isSelected":
                                              sortByDefaultOptionName ==
                                                  "ratings"
                                        },
                                      ])).then((value) {
                                if (value != null) {
                                  value as Map;

                                  if (value["selectedItemName"] ==
                                      'popularity') {
                                    sortByDefaultOptionName = 'popularity';
                                  } else if (value["selectedItemName"] ==
                                      'discountHighToLow') {
                                    sortByDefaultOptionName = 'discount';
                                  } else if (value["selectedItemName"] ==
                                      'topRated') {
                                    sortByDefaultOptionName = 'ratings';
                                  }
                                  context.read<ProviderCubit>().getProviders(
                                        categoryID: widget.categoryId,
                                        subCategoryID: widget.subCategoryId,
                                        filter: sortByDefaultOptionName,
                                      );
                                }
                              });
                            },
                            child: CustomSvgPicture(
                              svgImage: AppAssets.short,
                              color: context.colorScheme.accentColor,
                            ),
                          ),
                        )
                      : const SizedBox.shrink();
                else
                  return const SizedBox.shrink();
              })
            ]),
        bottomNavigationBar: Padding(
          padding:
              EdgeInsets.only(bottom: MediaQuery.of(context).padding.bottom),
          child: const BannerAdWidget(),
        ),
        body: Stack(
          children: [
            CustomSizedBox(
              height: context.screenHeight,
              width: context.screenWidth,
              child: BlocBuilder<SubCategoryAndProviderCubit,
                  SubCategoryAndProviderState>(
                builder: (final BuildContext context,
                    final SubCategoryAndProviderState
                        subCategoryAndProviderState) {
                  if (subCategoryAndProviderState
                      is SubCategoryAndProviderFetchFailure) {
                    return ErrorContainer(
                      errorMessage: subCategoryAndProviderState.errorMessage
                          .translate(context: context),
                      onTapRetry: () {
                        fetchSubCategoryAndProviderData();
                      },
                    );
                  } else if (subCategoryAndProviderState
                      is SubCategoryAndProviderFetchSuccess) {
                    // If subCategory and provider list is empty then show No Data found message
                    if (subCategoryAndProviderState.subCategoryList.isEmpty &&
                        subCategoryAndProviderState.providerList.isEmpty) {
                      return NoDataFoundWidget(
                          titleKey:
                              'noProvidersFound'.translate(context: context),
                          subtitleKey: 'noProvidersFoundSubTitle'
                              .translate(context: context));
                    }
                    // If only provider list is empty but subcategory list has items
                    if (subCategoryAndProviderState
                            .subCategoryList.isNotEmpty &&
                        subCategoryAndProviderState.providerList.isEmpty) {
                      return _getSubCategoryAndProviderList(
                        subCategoryAndProviderState.subCategoryList,
                        subCategoryAndProviderState.providerList,
                        '0', // Show 0 providers
                        context,
                      );
                    }
                    return _getSubCategoryAndProviderList(
                      subCategoryAndProviderState.subCategoryList,
                      subCategoryAndProviderState.providerList,
                      subCategoryAndProviderState.providerList.length
                          .toString(),
                      context,
                    );
                  }

                  return SingleChildScrollView(
                    child: Column(
                      children: [
                        _getSubCategoryShimmerEffect(),
                        const ProviderListShimmerEffect(
                            showTotalProviderContainer: true),
                      ],
                    ),
                  );
                },
              ),
            ),
            BlocConsumer<ProviderCubit, ProviderState>(
              listener:
                  (final BuildContext context, final ProviderState state) {
                if (state is ProviderFetchSuccess) {
                  context.read<SubCategoryAndProviderCubit>().emitSuccessState(
                        providerList: state.providerList,
                        totalProviders: state.totalProviders,
                      );
                }
              },
              builder: (final BuildContext context, final ProviderState state) {
                if (state is ProviderFetchInProgress) {
                  return Center(
                    child: CustomCircularProgressIndicator(
                      color: context.colorScheme.accentColor,
                    ),
                  );
                }
                return const CustomSizedBox();
              },
            ),
          ],
        ),
      );

  Widget _getSubCategoryList(final List<SubCategory> subCategoryList) =>
      ListView.builder(
        padding: const EdgeInsetsDirectional.only(start: 5, end: 15),
        scrollDirection: Axis.horizontal,
        itemCount: subCategoryList.length,
        itemBuilder: (final BuildContext context, final int index) =>
            _getSubCategoryItem(subCategoryList[index]),
      );

  Widget _getSubCategoryItem(final SubCategory subCategoryList) {
    final darkModeColor = subCategoryList.darkBackgroundColor == ''
        ? context.colorScheme.secondaryColor
        : subCategoryList.darkBackgroundColor!.toColor();
    final lightModeColor = subCategoryList.lightBackgroundColor == ''
        ? context.colorScheme.secondaryColor
        : subCategoryList.lightBackgroundColor!.toColor();

    return CustomContainer(
      height: 160,
      width: 130,
      margin: const EdgeInsetsDirectional.only(
        top: 10,
        start: 10,
      ),
      shape: BoxShape.rectangle,
      color: Theme.of(context).brightness == Brightness.light
          ? lightModeColor
          : darkModeColor,
      boxShadow: [],
      borderRadius: UiUtils.borderRadiusOf10,
      child: GestureDetector(
        onTap: () {
          Navigator.pushNamed(
            context,
            subCategoryRoute,
            arguments: {
              'subCategoryId': subCategoryList.id,
              'categoryId': '',
              'appBarTitle': subCategoryList.translatedName,
              'type': CategoryType.subcategory
            },
          );
        },
        child: Stack(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
              child: CustomCachedNetworkImage(
                networkImageUrl: subCategoryList.categoryImage!,
                fit: BoxFit.fill,
                height: 166,
                width: 130,
              ),
            ),
            Align(
              alignment: AlignmentDirectional.bottomCenter,
              child: CustomContainer(
                width: 166,
                height: 130 * 0.9,
                borderRadiusStyle: const BorderRadius.only(
                  bottomLeft: Radius.circular(UiUtils.borderRadiusOf8),
                  bottomRight: Radius.circular(UiUtils.borderRadiusOf10),
                ),
                gradient: LinearGradient(
                  colors: [
                    const Color(0xffFFFFFF).withValues(alpha: 0),
                    const Color(0xff000000).withValues(alpha: 0.2),
                    const Color(0xff000000).withValues(alpha: 0.7),
                  ],
                  begin: FractionalOffset.topCenter,
                  end: FractionalOffset.bottomCenter,
                ),
              ),
            ),
            Align(
              alignment: AlignmentDirectional.bottomCenter,
              child: Padding(
                padding: const EdgeInsetsDirectional.only(bottom: 10),
                child: CustomText(
                  subCategoryList.translatedName!,
                  textAlign: TextAlign.center,
                  color: AppColors.whiteColors,
                  fontWeight: FontWeight.w700,
                  fontStyle: FontStyle.normal,
                  fontSize: 16,
                  maxLines: 2,
                ),
              ),
            )
          ],
        ),
      ),
    );
  }

  Widget _getSubCategoryAndProviderList(
    final List<SubCategory> subCategoryList,
    final List<Providers> providerList,
    final String totalProviders,
    final BuildContext context,
  ) {
    return NestedScrollView(
      headerSliverBuilder:
          (final BuildContext context, final bool innerBoxIsScrolled) =>
              <Widget>[],
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: subCategoryList.isNotEmpty
                ? CustomSizedBox(
                    height: 175, child: _getSubCategoryList(subCategoryList))
                : const CustomSizedBox(),
          ),
          if (providerList.isNotEmpty)
            SliverToBoxAdapter(
              child: _getProviderList(providerList, totalProviders),
            )
          else if (subCategoryList.isNotEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 15, vertical: 30),
                child: NoDataFoundWidget(
                  titleKey: 'noProvidersFound'.translate(context: context),
                  subtitleKey:
                      'noProvidersFoundSubTitle'.translate(context: context),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _getProviderList(
          final List<Providers> providerList, final String totalProviders) =>
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 15),
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: List.generate(
              providerList.length,
              (index) => ProviderListItem(
                providerDetails: providerList[index],
                categoryId: widget.categoryId,
              ),
            )),
      );
}
