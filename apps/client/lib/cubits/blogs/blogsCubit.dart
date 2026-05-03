// features/giftCard/cubits/giftCardCubit.dart

import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/baseState.dart';
import 'package:e_demand/data/repository/blogsRepository.dart';
import 'package:e_demand/data/model/blogs/blogModel.dart';

class BlogsCubit extends Cubit<BaseState> {
  BlogsCubit(this._blogsRepository) : super(InitialState());

  final BlogsRepository _blogsRepository;
  String? _currentCategory;

  Future<void> getBlogs(
      {String? categoryId, String? id, String? tagSlug}) async {
    try {
      emit(LoadingState(loading: true));

      final response = await _blogsRepository.getBlogs(
        limit: UiUtils.limitOfAPIData,
        offset: "0",
        categoryId: categoryId,
        id: id,
        tagSlug: tagSlug,
      );

      if (response['error']) {
        emit(FailureState(response['message']));
      } else {
        emit(
          SuccessState<List<BlogModel>>(
            data: ((response['data'] ?? <dynamic>[]) as List)
                .cast<Map<String, dynamic>>()
                .map((e) => BlogModel.fromJson(e))
                .toList(),
            error: response['error'],
            message: response['message'],
            total: response['total'] ?? 0,
          ),
        );
      }
    } catch (e) {
      emit(FailureState(e.toString()));
    }
  }

  Future<void> getMoreBlogs(
      {String? categoryId, String? id, String? tagSlug}) async {
    final currentState = state;
    if (currentState is! SuccessState<List<BlogModel>>) return;

    try {
      if ((!currentState.hasMoreData) || currentState.data.isEmpty) {
        return;
      }

      emit(currentState.copyWithLoadingMoreData());

      final response = await _blogsRepository.getBlogs(
        limit: UiUtils.limitOfAPIData,
        offset: currentState.data.length.toString(),
        categoryId: categoryId,
        id: id,
        tagSlug: tagSlug,
      );

      if (response['error']) {
        emit(currentState.copyWithErrorOnLoadingMoreData());
      } else {
        emit(
          currentState.copyWith(
            data: [
              ...currentState.data,
              ...(((response['data'] ?? <dynamic>[]) as List)
                  .cast<Map<String, dynamic>>()
                  .map((e) => BlogModel.fromJson(e))
                  .toList()),
            ],
          ),
        );
      }
    } catch (e) {
      emit(currentState.copyWithErrorOnLoadingMoreData());
    }
  }

  String? get currentCategory => _currentCategory;
}

class BlogDetailsCubit extends BlogsCubit {
  BlogDetailsCubit(super.blogsRepository);

  BlogModel? _currentBlog;
  List<BlogModel> _relevantBlogs = [];

  BlogModel? get currentBlog => _currentBlog;
  List<BlogModel> get relevantBlogs => _relevantBlogs;

  Future<void> getBlogDetails(String blogId) async {
    try {
      emit(LoadingState(loading: true));

      final response = await _blogsRepository.getBlogDetails(blogId);

      if (response['error']) {
        emit(FailureState(response['message']));
      } else {
        _currentBlog = BlogModel.fromJson(response['blogDetails']);
        _relevantBlogs = (response['relevantBlogs'] as List<BlogModel>?) ?? [];

        emit(
          SuccessState<BlogDetailsData>(
            data: BlogDetailsData(
              blogDetails: _currentBlog!,
              relevantBlogs: _relevantBlogs,
            ),
            error: response['error'],
            message: response['message'],
          ),
        );
      }
    } catch (e) {
      emit(FailureState(e.toString()));
    }
  }

  @override
  Future<void> getBlogs(
      {String? categoryId, String? id, String? tagSlug}) async {
    // Override to prevent conflicts with blog details functionality
    // This cubit is specifically for blog details, not general blog listing
    return;
  }

  @override
  Future<void> getMoreBlogs(
      {String? categoryId, String? id, String? tagSlug}) async {
    // Override to prevent conflicts with blog details functionality
    return;
  }
}

class BlogDetailsData {
  final BlogModel blogDetails;
  final List<BlogModel> relevantBlogs;

  BlogDetailsData({
    required this.blogDetails,
    required this.relevantBlogs,
  });
}
