import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/baseState.dart';
import 'package:e_demand/data/repository/blogsRepository.dart';
import 'package:e_demand/data/model/blogs/blogCategoryModel.dart';

class BlogCategoryCubit extends Cubit<BaseState> {
  BlogCategoryCubit(this._blogsRepository) : super(InitialState());
  final BlogsRepository _blogsRepository;

  List<BlogCategoryModel> _categories = [];

  Future<void> getBlogCategories() async {
    try {
      emit(LoadingState(loading: true));

      final response = await _blogsRepository.getBlogCategories();
      if (response['error']) {
        emit(FailureState(response['message']));
      } else {
        final List<BlogCategoryModel> categories = response['blogCategories'];

        // Calculate total blog count for "All" category
        final totalBlogCount = categories.fold<int>(
          0,
          (sum, category) => sum + category.blogCount,
        );

        // Add "All" category at the beginning
        final allCategory = BlogCategoryModel(
          id: 'all',
          name: 'All',
          translatedName: 'All',
          slug: 'all',
          status: '1',
          createdAt: DateTime.now().toString(),
          updatedAt: DateTime.now().toString(),
          blogCount: totalBlogCount,
        );

        _categories = [allCategory, ...categories];

        emit(SuccessState<List<BlogCategoryModel>>(
          data: _categories,
          error: response['error'],
          message: response['message'],
          total: _categories.length,
        ));
      }
    } catch (e) {
      emit(FailureState(e.toString()));
    }
  }

  int getCategoryCount(String categoryId) {
    final category = _categories.firstWhere(
      (category) => category.id == categoryId,
      orElse: () => BlogCategoryModel(
        id: '',
        name: '',
        translatedName: '',
        slug: '',
        status: '1',
        createdAt: '',
        updatedAt: '',
        blogCount: 0,
      ),
    );
    return category.blogCount;
  }

  List<BlogCategoryModel> get categories => _categories;
}
