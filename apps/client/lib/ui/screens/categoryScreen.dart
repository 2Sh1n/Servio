import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/ui/widgets/categoryContainer.dart';
import 'package:flutter/material.dart';

class CategoryScreen extends StatefulWidget {
  const CategoryScreen({required this.scrollController, final Key? key})
      : super(key: key);
  final ScrollController scrollController;

  @override
  State<CategoryScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends State<CategoryScreen> {
  @override
  void initState() {
    Future.delayed(Duration.zero).then((final value) {
      fetchCategory();
    });
    super.initState();
  }

  @override
  void dispose() {
    super.dispose();
  }

  void fetchCategory() {
    context.read<CategoryCubit>().getCategory();
  }

  @override
  Widget build(final BuildContext context) =>
      AnnotatedRegion<SystemUiOverlayStyle>(
        value: UiUtils.getSystemUiOverlayStyle(context: context),
        child: Scaffold(
          appBar: UiUtils.getSimpleAppBar(
            context: context,
            title: 'services'.translate(context: context),
            centerTitle: true,
            isLeadingIconEnable: false,
            fontWeight: FontWeight.w600,
            fontSize: 18,
            elevation: 0.5,
          ),
          body: CustomContainer(
            color: context.colorScheme.primaryColor,
            child: CustomRefreshIndicator(
              displacment: 12,
              onRefreshCallback: () {
                fetchCategory();
              },
              child: BlocBuilder<CategoryCubit, CategoryState>(
                builder: (final BuildContext context,
                    final CategoryState categoryState) {
                  if (categoryState is CategoryFetchFailure) {
                    return ErrorContainer(
                      errorMessage: categoryState.errorMessage
                          .translate(context: context),
                      onTapRetry: fetchCategory,
                      showRetryButton: true,
                    );
                  } else if (categoryState is CategoryFetchSuccess) {
                    return categoryState.categoryList.isEmpty
                        ? NoDataFoundWidget(
                            titleKey:
                                'noServicesFound'.translate(context: context),
                            subtitleKey: 'noServicesFoundSubTitle'
                                .translate(context: context),
                            showRetryButton: true,
                            onTapRetry: () {
                              fetchCategory();
                            },
                          )
                        : _getCategoryList(
                            categoryList: categoryState.categoryList);
                  }

                  return _getCategoryShimmerEffect();
                },
              ),
            ),
          ),
        ),
      );

  Widget _getCategoryShimmerEffect() => GridView.builder(
        padding: const EdgeInsetsDirectional.only(start: 15, end: 15, top: 15),
        itemCount: UiUtils.numberOfShimmerContainer * 2,
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCountAndFixedHeight(
          crossAxisCount: ResponsiveHelper.isTabletDevice(context) ? 3 : 2,
          height: 80,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
        ),
        itemBuilder: (final context, final index) =>
            const CustomShimmerLoadingContainer(
          borderRadius: UiUtils.borderRadiusOf10,
        ),
      );

  Widget _getCategoryList({required final List<CategoryModel> categoryList}) =>
      GridView.builder(
        controller: widget.scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(15.rw(context), 15.rh(context),
            15.rw(context), kBottomNavigationBarHeight.rh(context)),
        itemCount: categoryList.length,
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCountAndFixedHeight(
          crossAxisCount: ResponsiveHelper.isTabletDevice(context) ? 3 : 2,
          height: 80,
          crossAxisSpacing: ResponsiveHelper.isTabletDevice(context) ? 8 : 4,
          mainAxisSpacing: ResponsiveHelper.isTabletDevice(context) ? 8 : 4,
        ),
        itemBuilder: (final BuildContext context, int index) {
          return CategoryContainer(
            backgroundColor: context.colorScheme.secondaryColor,
            imageURL: categoryList[index].categoryImage!,
            title: categoryList[index].translatedName!,
            providers: categoryList[index].totalProviders!,
            cardWidth: ResponsiveHelper.isLargePhone(context) ||
                    ResponsiveHelper.isLargeTablet(context)
                ? 99.rw(context)
                : null,
            maxLines: 2,
            imageRadius: UiUtils.borderRadiusOf10,
            fontWeight: FontWeight.w500,
            darkModeBackgroundColor: categoryList[index].backgroundDarkColor,
            lightModeBackgroundColor: categoryList[index].backgroundLightColor,
            onTap: () {
              Navigator.pushNamed(
                context,
                subCategoryRoute,
                arguments: {
                  'categoryId': categoryList[index].id,
                  'appBarTitle': categoryList[index].translatedName,
                  'type': CategoryType.category,
                },
              );
            },
          );
        },
      );
}
