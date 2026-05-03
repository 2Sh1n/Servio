//Cubit
import 'package:e_demand/app/generalImports.dart';

class AllCategoryCubit extends Cubit<AllCategoryState> {
  AllCategoryCubit(this.categoryRepository) : super(AllCategoryInitial());
  CategoryRepository categoryRepository;

  void getAllCategory() {
    emit(AllCategoryFetchInProgress());

    categoryRepository.fetchAllCategory().then((final value) {
      emit(AllCategoryFetchSuccess(allCategoryList: value));
    }).catchError((final e) {
      emit(AllCategoryFetchFailure(errorMessage: e.toString()));
    });
  }
}

//State
@immutable
abstract class AllCategoryState {}

class AllCategoryInitial extends AllCategoryState {}

class AllCategoryFetchInProgress extends AllCategoryState {}

class AllCategoryFetchSuccess extends AllCategoryState {
  AllCategoryFetchSuccess({required this.allCategoryList});
  final List<AllCategoryModel> allCategoryList;
}

class AllCategoryFetchFailure extends AllCategoryState {
  AllCategoryFetchFailure({required this.errorMessage});
  final String errorMessage;
}
