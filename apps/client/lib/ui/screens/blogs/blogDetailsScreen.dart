import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/cubits/blogs/blogsCubit.dart';
import 'package:e_demand/data/baseState.dart';
import 'package:e_demand/data/model/blogs/blogModel.dart';
import 'package:e_demand/data/repository/blogsRepository.dart';
import 'package:e_demand/ui/screens/blogs/widgets/blogCard.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';

class BlogDetailsScreen extends StatefulWidget {
  final String blogId;

  const BlogDetailsScreen({super.key, required this.blogId});

  static Route route(final RouteSettings routeSettings) {
    final arguments = routeSettings.arguments as Map<String, dynamic>;
    return CupertinoPageRoute(
      builder: (final BuildContext context) => BlocProvider(
        create: (context) => BlogDetailsCubit(BlogsRepository()),
        child: BlogDetailsScreen(
          blogId: arguments['blogId'] as String,
        ),
      ),
    );
  }

  @override
  State<BlogDetailsScreen> createState() => _BlogDetailsScreenState();
}

class _BlogDetailsScreenState extends State<BlogDetailsScreen> {
  late BlogDetailsCubit _blogDetailsCubit;

  @override
  void initState() {
    super.initState();
    _blogDetailsCubit = context.read<BlogDetailsCubit>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _blogDetailsCubit.getBlogDetails(widget.blogId);
    });
  }

  Widget _getBlogDetailsShimmerLoading() {
    return SingleChildScrollView(
      padding: EdgeInsets.all(16.rw(context)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomShimmerLoadingContainer(
            margin: EdgeInsets.all(16.rw(context)),
            height: 260.rh(context),
            borderRadius: 10,
          ),
          CustomShimmerLoadingContainer(
            margin: EdgeInsets.all(16.rw(context)),
            height: 20.rh(context),
            width: 260.rw(context),
            borderRadius: 10,
          ),
          CustomShimmerLoadingContainer(
            margin: EdgeInsets.all(16.rw(context)),
            height: 100.rh(context),
            width: double.infinity,
            borderRadius: 10,
          ),
          ListView.builder(
            shrinkWrap: true,
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: 2,
            itemBuilder: (context, index) {
              return CustomShimmerLoadingContainer(
                height: 60.rh(context),
                margin: EdgeInsets.symmetric(
                    horizontal: 16.rw(context), vertical: 5.rh(context)),
              );
            },
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedSafeArea(
      isAnnotated: true,
      child: Scaffold(
        appBar: UiUtils.getSimpleAppBar(
          context: context,
          // title: 'blogDetails'.translate(context: context),
          centerTitle: true,
          isLeadingIconEnable: true,
          fontWeight: FontWeight.w600,
          fontSize: 18,
          elevation: 0.5,
        ),
        body: BlocConsumer<BlogDetailsCubit, BaseState>(
          listener: (context, state) {
            if (state is FailureState) {
              UiUtils.showMessage(
                context,
                state.message,
                ToastificationType.error,
              );
            }
          },
          builder: (context, state) {
            if (state is LoadingState) {
              return _getBlogDetailsShimmerLoading();
            }

            if (state is FailureState) {
              return ErrorContainer(
                errorMessage: state.message,
                showRetryButton: true,
                onTapRetry: () {
                  _blogDetailsCubit.getBlogDetails(widget.blogId);
                },
              );
            }

            if (state is SuccessState<BlogDetailsData>) {
              final blogData = state.data;
              return _buildBlogContent(blogData);
            }

            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  Widget _buildBlogContent(BlogDetailsData blogData) {
    return CustomContainer(
      margin: EdgeInsetsDirectional.only(top: 8.rh(context)),
      color: context.colorScheme.secondaryColor,
      child: SingleChildScrollView(
        padding: EdgeInsets.all(16.rw(context)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Blog Header
            _buildBlogHeader(blogData.blogDetails),

            const CustomSizedBox(height: 16),

            // Blog Content (HTML)
            _buildBlogHtmlContent(blogData.blogDetails),

            const CustomSizedBox(height: 16),

            // Relevant Blogs Section
            if (blogData.relevantBlogs.isNotEmpty) ...[
              _buildRelevantBlogsSection(blogData.relevantBlogs),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildBlogHeader(BlogModel blog) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
            borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf12),
            child: CustomCachedNetworkImage(
              networkImageUrl: blog.image!,
              height: 170,
              width: double.infinity,
              fit: BoxFit.cover,
            )),
        const CustomSizedBox(height: 16),
        CustomText(
          blog.createdAt!.formatDateForBlogs(),
          fontSize: 12,
          fontWeight: FontWeight.w400,
          color: context.colorScheme.lightGreyColor,
        ),
        const CustomSizedBox(height: 8),
        CustomText(
          blog.translatedTitle ?? '',
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: context.colorScheme.blackColor,
          textAlign: TextAlign.start,
        ),
        if (blog.tags.isNotEmpty) ...[
          const CustomSizedBox(height: 12),
          Wrap(
            spacing: 8.rw(context),
            runSpacing: 8.rh(context),
            children: blog.tags.map((tag) {
              return InkWell(
                onTap: () {
                  Navigator.pushNamed(
                    context,
                    blogsRoute,
                    arguments: {'tagSlug': tag.slug},
                  );
                },
                borderRadius: BorderRadius.circular(16.rw(context)),
                child: Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: 12.rw(context),
                    vertical: 6.rh(context),
                  ),
                  decoration: BoxDecoration(
                    color:
                        context.colorScheme.accentColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(16.rw(context)),
                    border: Border.all(
                      color: context.colorScheme.accentColor
                          .withValues(alpha: 0.3),
                      width: 1,
                    ),
                  ),
                  child: CustomText(
                    tag.translatedName,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: context.colorScheme.accentColor,
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      ],
    );
  }

  Widget _buildBlogHtmlContent(BlogModel blog) {
    return HtmlWidget(
      blog.translatedDescription ?? '',
      textStyle: TextStyle(
        fontSize: 16.0,
        height: 1.6,
        color: context.colorScheme.blackColor,
        fontFamily: 'Lexend',
      ),
      onTapUrl: (url) async {
        if (await canLaunchUrl(Uri.parse(url))) {
          await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
        }
        return true;
      },
    );
  }

  Widget _buildRelevantBlogsSection(List<BlogModel> relevantBlogs) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Section Title
        CustomText(
          'relatedBlogs'.translate(context: context),
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: context.colorScheme.blackColor,
        ),

        const CustomSizedBox(height: 16),

        // Horizontal Scrolling List
        CustomSizedBox(
          height: 280.rh(context),
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: EdgeInsets.symmetric(horizontal: 4.rw(context)),
            itemCount: relevantBlogs.length,
            separatorBuilder: (context, index) =>
                const CustomSizedBox(width: 10),
            itemBuilder: (context, index) {
              final blog = relevantBlogs[index];
              return CustomSizedBox(
                width: 310,
                child: InkWell(
                  onTap: () {
                    Navigator.pushReplacementNamed(
                      context,
                      blogDetailsScreen,
                      arguments: {'blogId': blog.id},
                    );
                  },
                  borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf12),
                  child: BlogCard(blog: blog, isBorder: true),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
