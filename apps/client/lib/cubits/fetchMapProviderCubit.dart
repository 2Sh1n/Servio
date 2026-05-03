import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/data/model/providerMapModel.dart';

abstract class FetchMapProviderState {}

class FetchMapProviderInitial extends FetchMapProviderState {}

class FetchMapProviderFetchInProgress extends FetchMapProviderState {}

class FetchMapProviderFetchSuccess extends FetchMapProviderState {
  FetchMapProviderFetchSuccess(
      {required this.providerList, required this.filteredProviderList});

  final List<ProviderMapModel> providerList;
  final List<ProviderMapModel> filteredProviderList;
}

class FetchMapProviderFetchFailure extends FetchMapProviderState {
  FetchMapProviderFetchFailure({required this.errorMessage});

  final String errorMessage;
}

class FetchMapProviderCubit extends Cubit<FetchMapProviderState> {
  FetchMapProviderCubit(this.providerRepository)
      : super(FetchMapProviderInitial());
  ProviderRepository providerRepository;

  ///This method is used to fetch subCategories and FetchMapProviders based on CategoryId/SubCategory Id
  Future<void> fetchMapProviders({
    required String latitude,
    required String longitude,
  }) async {
    try {
      emit(FetchMapProviderFetchInProgress());
      final providers = await providerRepository.getMapProviderList(
        latitude: latitude,
        longitude: longitude,
      );

      emit(
        FetchMapProviderFetchSuccess(
          providerList: providers,
          filteredProviderList: providers,
        ),
      );
    } catch (error) {
      emit(FetchMapProviderFetchFailure(errorMessage: error.toString()));
    }
  }

  void applySearchText(String searchText) {
    if (state is FetchMapProviderFetchSuccess) {
      final stateAs = state as FetchMapProviderFetchSuccess;
      final providerList = [...stateAs.providerList];
      if (providerList.isEmpty) return;

      if (searchText.trim().isNotEmpty) {
        providerList.removeWhere((provider) =>
            !provider.translatedProviderName
                .toLowerCase()
                .contains(searchText.toLowerCase()) &&
            !provider.translatedCompanyName
                .toLowerCase()
                .contains(searchText.toLowerCase()));
        emit(
          FetchMapProviderFetchSuccess(
            providerList: stateAs.providerList,
            filteredProviderList: providerList,
          ),
        );
      } else {
        emit(
          FetchMapProviderFetchSuccess(
            providerList: stateAs.providerList,
            filteredProviderList: stateAs.providerList,
          ),
        );
      }
    }
  }
}
