import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/model/blogs/blogCategoryModel.dart';
import 'package:e_demand/ui/screens/blogs/widgets/categoryItems.dart';
import 'package:flutter/material.dart';

class CategoryFilterBottomSheet extends StatelessWidget {
  final List<BlogCategoryModel> categories;
  final String? selectedCategory;
  final Function(String) onCategorySelected;

  const CategoryFilterBottomSheet({
    super.key,
    required this.categories,
    required this.selectedCategory,
    required this.onCategorySelected,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(
        maxHeight:
            MediaQuery.of(context).size.height * 0.7, // 70% of screen height
      ),
      child: Padding(
        padding: EdgeInsets.all(20.rw(context)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Categories Title
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                CustomText(
                  'categories'.translate(context: context),
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: context.colorScheme.blackColor,
                ),
              ],
            ),
            const CustomSizedBox(height: 16),

            // Categories List with ScrollView
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  children: categories.map((category) {
                    final isSelected =
                        (selectedCategory == null && category.id == 'all') ||
                            selectedCategory == category.id;

                    return CategoryItem(
                      category: category,
                      count: category.blogCount,
                      isSelected: isSelected,
                      onTap: () => onCategorySelected(category.id),
                    );
                  }).toList(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
