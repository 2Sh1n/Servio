import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:e_demand/utils/shimmerEffect/languageListShimmer.dart';

class ChooseLanguageBottomSheet extends StatefulWidget {
  const ChooseLanguageBottomSheet({final Key? key}) : super(key: key);

  @override
  State<ChooseLanguageBottomSheet> createState() =>
      _ChooseLanguageBottomSheetState();
}

class _ChooseLanguageBottomSheetState extends State<ChooseLanguageBottomSheet> {
  late final ScrollController _scrollController;
  bool _isProcessingLanguageData = false;

  @override
  void initState() {
    super.initState();
    _scrollController = ScrollController();
    // Fetch language list when bottom sheet opens
    getLanguageList();
  }

  void getLanguageList() {
    context.read<LanguageListCubit>().getLanguageList();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  Column getLanguageTile({required final AppLanguage appLanguage}) => Column(
        children: [
          CustomInkWellContainer(
            onTap: () {
              setState(() {
                _isProcessingLanguageData = true;
              });
              context
                  .read<LanguageDataCubit>()
                  .getLanguageData(languageData: appLanguage);
            },
            child: Padding(
              padding: const EdgeInsetsDirectional.symmetric(
                  horizontal: 10, vertical: 5),
              child: Row(
                textDirection: TextDirection.ltr,
                children: [
                  CustomSizedBox(
                      height: 25,
                      width: 25,
                      child: CustomCachedNetworkImage(
                        networkImageUrl: appLanguage.imageURL,
                        height: 25,
                        width: 25,
                        fit: BoxFit.fill,
                      )),
                  const CustomSizedBox(width: 10),
                  Expanded(
                    child: CustomText(
                      appLanguage.languageName,
                      color: context.colorScheme.blackColor,
                      fontWeight: FontWeight.w400,
                      fontStyle: FontStyle.normal,
                      fontSize: 18,
                    ),
                  )
                ],
              ),
            ),
          ),
          const CustomDivider()
        ],
      );

  @override
  Widget build(final BuildContext context) =>
      BlocListener<LanguageDataCubit, LanguageDataState>(
          listener: (context, state) async {
            debugPrint("language data state***$state");
            if (state is GetLanguageDataSuccess) {
              // Wait for language to be stored before making API calls
              await HiveRepository.storeLanguage(
                  data: state.jsonData, lang: state.currentLanguage);
              await context.read<SystemSettingCubit>().getSystemSettings();
              // Step 2: After settings are done, fetch other data in parallel
              final futures = <Future>[
                context.read<HomeScreenCubit>().fetchHomeScreenData(),
                if (HiveRepository.getUserToken != '') ...[
                  context.read<BookingCubit>().fetchBookingDetails(status: ''),
                  context.read<CategoryCubit>().getCategory(),
                  context
                      .read<CartCubit>()
                      .getCartDetails(isReorderCart: false),
                  context.read<BookmarkCubit>().fetchBookmark(type: 'list'),
                ]
              ];

              // Wait for all calls to complete
              await Future.wait(futures);

              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (mounted) {
                  setState(() {
                    _isProcessingLanguageData = false;
                  });
                  Navigator.pop(context);
                }
              });
            }
          },
          child: Directionality(
            textDirection: Directionality.of(context),
            child: Stack(
              children: [
                BottomSheetLayout(
                  title: 'chooseLanguage'.translate(context: context),
                  child: BlocBuilder<LanguageListCubit, LanguageListState>(
                    builder: (context, state) {
                      if (state is GetLanguageListInProgress) {
                        return const LanguageListShimmerEffect();
                      }

                      if (state is GetLanguageListError) {
                        return ErrorContainer(
                          errorMessage: state.error
                              .toString()
                              .translate(context: context),
                          onTapRetry: getLanguageList,
                          showRetryButton: true,
                        );
                      }

                      if (state is GetLanguageListSuccess) {
                        if (state.languages.isEmpty) {
                          return Center(
                            child: CustomText(
                              'noLanguagesAvailable'
                                  .translate(context: context),
                              color: context.colorScheme.blackColor,
                            ),
                          );
                        }

                        return ListView.builder(
                          shrinkWrap: true,
                          controller: _scrollController,
                          itemCount: state.languages.length,
                          itemBuilder: (context, index) {
                            return getLanguageTile(
                                appLanguage: state.languages[index]);
                          },
                        );
                      }

                      // Initial state - show loading and fetch languages
                      return const LanguageListShimmerEffect();
                    },
                  ),
                ),
                if (_isProcessingLanguageData)
                  Positioned.fill(
                    child: Center(
                      child: CircularProgressIndicator(
                        color: context.colorScheme.accentColor,
                      ),
                    ),
                  ),
              ],
            ),
          ));
}
