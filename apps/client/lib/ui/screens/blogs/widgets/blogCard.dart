import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/model/blogs/blogModel.dart';
import 'package:flutter/material.dart';

class BlogCard extends StatelessWidget {
  final BlogModel blog;
  final bool isBorder;

  const BlogCard({super.key, required this.blog, this.isBorder = false});

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      padding: EdgeInsets.all(12.rw(context)),
      borderRadius: UiUtils.borderRadiusOf12,
      border: isBorder
          ? Border.all(
              color: context.colorScheme.blackColor,
              width: 0.5,
            )
          : null,
      color: context.colorScheme.secondaryColor,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Blog Image
          ClipRRect(
            borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf8),
            child: CustomCachedNetworkImage(
              networkImageUrl: blog.image!,
              height: 170,
              width: double.infinity,
              fit: BoxFit.cover,
            ),
          ),
          const CustomSizedBox(height: 12),

          // Blog Date
          CustomText(
            blog.createdAt!.formatDateForBlogs(),
            fontSize: 12,
            fontWeight: FontWeight.w400,
            color: context.colorScheme.lightGreyColor,
          ),
          const CustomSizedBox(height: 8),

          // Blog Title
          CustomText(
            blog.translatedTitle ?? '',
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: context.colorScheme.blackColor,
            maxLines: 2,
            textAlign: TextAlign.start,
          ),
        ],
      ),
    );
  }
}
