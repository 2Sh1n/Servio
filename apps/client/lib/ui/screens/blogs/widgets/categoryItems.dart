import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/model/blogs/blogCategoryModel.dart';
import 'package:flutter/material.dart';

class CategoryItem extends StatelessWidget {
  final BlogCategoryModel category;
  final int count;
  final bool isSelected;
  final VoidCallback onTap;

  const CategoryItem({
    required this.category,
    required this.count,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf8),
      child: CustomContainer(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(
          horizontal: 5,
          vertical: 10,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              flex: 10,
              child: CustomText(
                category.translatedName == 'All'
                    ? 'all'.translate(context: context)
                    : category.translatedName,
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: isSelected
                    ? context.colorScheme.accentColor
                    : context.colorScheme.blackColor,
              ),
            ),
            Expanded(
              flex: 1,
              child: CustomText(
                '($count)',
                fontSize: 12,
                fontWeight: FontWeight.w500,
                color: isSelected
                    ? context.colorScheme.accentColor
                    : context.colorScheme.blackColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
