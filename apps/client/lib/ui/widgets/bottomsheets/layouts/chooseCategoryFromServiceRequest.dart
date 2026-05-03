import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class BottomSheetCategoryScreen extends StatefulWidget {
  const BottomSheetCategoryScreen({
    required this.categoryId,
    required this.scrollController,
    final Key? key,
  }) : super(key: key);
  final ScrollController scrollController;
  final String categoryId;

  @override
  State<BottomSheetCategoryScreen> createState() =>
      _BottomSheetCategoryScreenState();
}

class _BottomSheetCategoryScreenState extends State<BottomSheetCategoryScreen> {
  String? categoryID;
  String? categoryName;
  Set<String> expandedCategoryIds = {};
  bool _hasInitializedExpansion = false;

  @override
  void initState() {
    categoryID = widget.categoryId;
    super.initState();
  }

  void fetchCategory() {
    context.read<AllCategoryCubit>().getAllCategory();
  }

  void _initializeExpansionForSelectedCategory(
      List<AllCategoryModel> categories) {
    if (_hasInitializedExpansion || widget.categoryId.isEmpty) return;
    _hasInitializedExpansion = true;

    final path = <String>[];
    if (_findCategoryPath(categories, widget.categoryId, path)) {
      // Add all parent IDs to expanded set (exclude the selected category itself)
      if (path.isNotEmpty) {
        expandedCategoryIds.addAll(path.take(path.length - 1));
      }
      // Set the category name from the found category
      final selectedCategory = _findCategoryById(categories, widget.categoryId);
      if (selectedCategory != null) {
        categoryName = selectedCategory.translated_name;
      }
    }
  }

  bool _findCategoryPath(
      List<AllCategoryModel> categories, String targetId, List<String> path) {
    for (final category in categories) {
      path.add(category.id!);
      if (category.id == targetId) {
        return true;
      }
      if (category.subCategories != null &&
          category.subCategories!.isNotEmpty) {
        if (_findCategoryPath(category.subCategories!, targetId, path)) {
          return true;
        }
      }
      path.removeLast();
    }
    return false;
  }

  AllCategoryModel? _findCategoryById(
      List<AllCategoryModel> categories, String targetId) {
    for (final category in categories) {
      if (category.id == targetId) {
        return category;
      }
      if (category.subCategories != null &&
          category.subCategories!.isNotEmpty) {
        final found = _findCategoryById(category.subCategories!, targetId);
        if (found != null) return found;
      }
    }
    return null;
  }

  bool _hasSelectedDescendant(AllCategoryModel category) {
    if (category.subCategories == null || category.subCategories!.isEmpty) {
      return false;
    }
    for (final child in category.subCategories!) {
      if (child.id == categoryID) {
        return true;
      }
      if (_hasSelectedDescendant(child)) {
        return true;
      }
    }
    return false;
  }

  @override
  Widget build(final BuildContext context) =>
      BlocBuilder<AllCategoryCubit, AllCategoryState>(
        builder: (final BuildContext context,
            final AllCategoryState allcategoryState) {
          if (allcategoryState is AllCategoryFetchFailure) {
            return ErrorContainer(
              errorMessage:
                  allcategoryState.errorMessage.translate(context: context),
              onTapRetry: fetchCategory,
              showRetryButton: true,
            );
          } else if (allcategoryState is AllCategoryFetchSuccess) {
            _initializeExpansionForSelectedCategory(
                allcategoryState.allCategoryList);
            return allcategoryState.allCategoryList.isEmpty
                ? NoDataFoundWidget(
                    titleKey: 'noServicesFound'.translate(context: context),
                    subtitleKey:
                        'noServicesFoundSubTitle'.translate(context: context),
                    showRetryButton: true,
                    onTapRetry: () {
                      fetchCategory();
                    },
                  )
                : _getCategoryList(
                    allCategoryList: allcategoryState.allCategoryList);
          }
          return _getCategoryShimmerEffect();
        },
      );

  Widget _getCategoryShimmerEffect() => SafeArea(
        child: BottomSheetLayout(
          title: 'services'.translate(context: context),
          child: CustomSizedBox(
            height: context.screenHeight * 0.8,
            child: ListView.builder(
              padding: const EdgeInsetsDirectional.only(top: 15),
              itemCount: UiUtils.numberOfShimmerContainer,
              itemBuilder: (final context, final index) => const Padding(
                padding: EdgeInsets.only(bottom: 10),
                child: CustomShimmerLoadingContainer(
                  height: 56,
                  borderRadius: UiUtils.borderRadiusOf6,
                ),
              ),
            ),
          ),
        ),
      );

  Widget _getCategoryList(
          {required final List<AllCategoryModel> allCategoryList}) =>
      PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (didPop) {
            return;
          } else {
            Navigator.of(context).pop();
          }
        },
        child: SafeArea(
          child: StatefulBuilder(builder:
              (final BuildContext context, final StateSetter setState) {
            return BottomSheetLayout(
              title: 'services'.translate(context: context),
              child: CustomSizedBox(
                height: context.screenHeight * 0.8,
                child: Column(
                  children: [
                    Expanded(
                      child: ListView.builder(
                        controller: widget.scrollController,
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: EdgeInsets.only(
                          top: 15.rh(context),
                          bottom: kBottomNavigationBarHeight.rh(context),
                        ),
                        itemCount: allCategoryList.length,
                        itemBuilder: (final BuildContext context, int index) {
                          return _buildCategoryTile(
                            category: allCategoryList[index],
                            depth: 0,
                            setState: setState,
                          );
                        },
                      ),
                    ),
                    CloseAndConfirmButton(closeButtonPressed: () {
                      Navigator.of(context).pop();
                    }, confirmButtonPressed: () {
                      Navigator.of(context).pop({
                        'id': categoryID,
                        'name': categoryName,
                      });
                    }),
                  ],
                ),
              ),
            );
          }),
        ),
      );

  Widget _buildCategoryTile({
    required AllCategoryModel category,
    required int depth,
    required StateSetter setState,
  }) {
    final bool isSelected = categoryID == category.id;
    final bool hasChildren =
        category.subCategories != null && category.subCategories!.isNotEmpty;
    final bool isExpanded = expandedCategoryIds.contains(category.id);
    final bool hasSelectedChild = _hasSelectedDescendant(category);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: EdgeInsetsDirectional.only(
            start: (depth * 12).clamp(0, 48).toDouble(),
          ),
          child: Material(
            color: isSelected
                ? context.colorScheme.accentColor.withValues(alpha: 0.1)
                : hasSelectedChild
                    ? context.colorScheme.accentColor.withValues(alpha: 0.05)
                    : Colors.transparent,
            child: InkWell(
              onTap: () {
                setState(() {
                  categoryID = category.id;
                  categoryName = category.translated_name;
                  if (hasChildren) {
                    if (isExpanded) {
                      expandedCategoryIds.remove(category.id);
                    } else {
                      expandedCategoryIds.add(category.id!);
                    }
                  }
                });
              },
              child: Container(
                padding:
                    const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
                decoration: BoxDecoration(
                  border: Border(
                    bottom: BorderSide(
                      color: context.colorScheme.lightGreyColor
                          .withValues(alpha: 0.3),
                      width: 0.5,
                    ),
                  ),
                ),
                child: Row(
                  children: [
                    InkWell(
                      onTap: () {
                        setState(() {
                          categoryID = category.id;
                          categoryName = category.translated_name;
                        });
                      },
                      customBorder: const CircleBorder(),
                      child: Padding(
                        padding: const EdgeInsets.all(4),
                        child: Container(
                          width: 20,
                          height: 20,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: isSelected
                                  ? context.colorScheme.accentColor
                                  : context.colorScheme.lightGreyColor,
                              width: 2,
                            ),
                          ),
                          child: isSelected
                              ? Center(
                                  child: Container(
                                    width: 10,
                                    height: 10,
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: context.colorScheme.accentColor,
                                    ),
                                  ),
                                )
                              : null,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    CustomImageContainer(
                      borderRadius: UiUtils.borderRadiusOf5,
                      imageURL: category.image ?? '',
                      height: 40,
                      width: 40,
                      boxFit: BoxFit.cover,
                      boxShadow: const [],
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: CustomText(
                        category.translated_name ?? '',
                        fontSize: 13,
                        color: context.colorScheme.blackColor,
                        fontWeight:
                            isSelected ? FontWeight.w600 : FontWeight.w500,
                        maxLines: isExpanded ? 2 : 1,
                      ),
                    ),
                    if (hasChildren)
                      InkWell(
                        onTap: () {
                          setState(() {
                            if (isExpanded) {
                              expandedCategoryIds.remove(category.id);
                            } else {
                              expandedCategoryIds.add(category.id!);
                            }
                          });
                        },
                        customBorder: const CircleBorder(),
                        child: Padding(
                          padding: const EdgeInsets.all(8),
                          child: AnimatedRotation(
                            turns: isExpanded ? 0.5 : 0,
                            duration: const Duration(milliseconds: 200),
                            child: Icon(
                              Icons.keyboard_arrow_down_rounded,
                              color: context.colorScheme.lightGreyColor,
                              size: 24,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
        ClipRect(
          child: AnimatedAlign(
            alignment: Alignment.topCenter,
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutCubic,
            heightFactor: hasChildren && isExpanded ? 1.0 : 0.0,
            child: Column(
              children: hasChildren
                  ? category.subCategories!
                      .map(
                        (subCategory) => _buildCategoryTile(
                          category: subCategory,
                          depth: depth + 1,
                          setState: setState,
                        ),
                      )
                      .toList()
                  : [],
            ),
          ),
        ),
      ],
    );
  }
}
