// ignore_for_file: file_names
import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class SearchScreen extends StatefulWidget {
  const SearchScreen({final Key? key}) : super(key: key);

  @override
  State<SearchScreen> createState() => _SearchScreenState();

  static Route route(final RouteSettings routeSettings) => CupertinoPageRoute(
        builder: (final BuildContext context) => MultiBlocProvider(
          providers: [
            BlocProvider<SearchProviderCubit>(
              create: (final context) =>
                  SearchProviderCubit(ProviderRepository()),
            ),
            BlocProvider<SearchServicesCubit>(
              create: (final context) => SearchServicesCubit(),
            ),
          ],
          child: const SearchScreen(),
        ),
      );
}

class _SearchScreenState extends State<SearchScreen>
    with TickerProviderStateMixin {
  // search provider controller
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _providerScrollController = ScrollController();
  final ScrollController _serviceScrollController = ScrollController();
  late TabController _tabController = TabController(length: 2, vsync: this);
  List<String> tabs = ["services", "providers"]; //give delay to live search
  Timer? delayTimer;
  ValueNotifier<bool> hasValuesInTextField = ValueNotifier(false);

  //to check length of search text
  int previousLength = 0;
  FocusNode _focusNode = FocusNode();

  ///
  @override
  void initState() {
    super.initState();
    //listen to search text change to  fetch data
    _searchController.addListener(searchListener);
    _providerScrollController.addListener(searchMoreProviders);
    _serviceScrollController.addListener(searchMoreServices);
    _focusNode.addListener(() {
      setState(() {});
    });
    _tabController.addListener(() {
      setState(() {});
    });
  }

  void searchListener() {
    if (_searchController.text.isEmpty) {
      delayTimer?.cancel();
      hasValuesInTextField.value = false;
    }

    if (delayTimer?.isActive ?? false) delayTimer?.cancel();

    delayTimer = Timer(const Duration(milliseconds: 500), () {
      if (_searchController.text.isNotEmpty) {
        hasValuesInTextField.value = true;

        if (_searchController.text.length != previousLength) {
          ClarityService.logAction(
            ClarityActions.serviceSearchSubmitted,
            {
              'query': _searchController.text.trim(),
              'active_tab':
                  _tabController.index == 0 ? 'services' : 'providers',
            },
          );
          searchProviders();
          searchServices();
          previousLength = _searchController.text.length;
        }
      }
    });
  }

  void searchProviders() {
    context.read<SearchProviderCubit>().searchProvider(
        searchKeyword: _searchController.text.trim().toString());
  }

  void searchServices() {
    context
        .read<SearchServicesCubit>()
        .searchServices(searchString: _searchController.text.trim().toString());
  }

  @override
  void dispose() {
    _searchController.dispose();
    _providerScrollController.dispose();
    _serviceScrollController.dispose();
    delayTimer?.cancel();
    _tabController.dispose();
    hasValuesInTextField.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(final BuildContext context) => AnnotatedSafeArea(
        isAnnotated: true,
        child: Scaffold(
          appBar: AppBar(
            leading: CustomInkWellContainer(
              onTap: () {
                Navigator.pop(context);
              },
              child: Padding(
                padding: const EdgeInsets.all(15),
                child: CustomSvgPicture(
                  svgImage: Directionality.of(context)
                          .toString()
                          .contains(TextDirection.RTL.value.toLowerCase())
                      ? AppAssets.backArrowLtr
                      : AppAssets.backArrow,
                  color: context.colorScheme.accentColor,
                ),
              ),
            ),
            title: Align(
              alignment: Alignment.centerLeft,
              child: CustomSizedBox(
                height: 45,
                child: TextField(
                  autofocus: true,
                  style: TextStyle(
                      fontSize: 12, color: context.colorScheme.blackColor),
                  controller: _searchController,
                  focusNode: _focusNode,
                  cursorColor: context.colorScheme.blackColor,
                  decoration: InputDecoration(
                    contentPadding:
                        const EdgeInsetsDirectional.only(bottom: 2, start: 15),
                    filled: true,
                    fillColor: context.colorScheme.primaryColor,
                    hintText: 'startTyping'.translate(context: context),
                    hintStyle: TextStyle(
                        fontSize: 12, color: context.colorScheme.blackColor),
                    focusedBorder: OutlineInputBorder(
                      borderSide:
                          BorderSide(color: context.colorScheme.accentColor),
                      borderRadius: const BorderRadius.all(
                          Radius.circular(UiUtils.borderRadiusOf10)),
                    ),
                    enabledBorder: const OutlineInputBorder(
                      borderSide: BorderSide(color: Colors.transparent),
                      borderRadius: BorderRadius.all(
                          Radius.circular(UiUtils.borderRadiusOf10)),
                    ),
                    border: const OutlineInputBorder(
                      borderSide: BorderSide(color: Colors.transparent),
                      borderRadius: BorderRadius.all(
                          Radius.circular(UiUtils.borderRadiusOf10)),
                    ),
                    suffixIcon: ValueListenableBuilder(
                      valueListenable: hasValuesInTextField,
                      builder: (context, bool value, child) {
                        if (!value) {
                          return const SizedBox.shrink();
                        }
                        return CustomInkWellContainer(
                          onTap: () {
                            _searchController.clear();
                          },
                          child: Icon(
                            Icons.close,
                            color: context.colorScheme.blackColor,
                          ),
                        );
                      },
                    ),
                    prefixIcon: ValueListenableBuilder(
                        valueListenable: hasValuesInTextField,
                        builder: (context, bool value, child) {
                          return CustomContainer(
                            padding: const EdgeInsets.all(10),
                            child: CustomSvgPicture(
                              svgImage: AppAssets.search,
                              height: 12,
                              width: 12,
                              color: !value
                                  ? context.colorScheme.blackColor
                                  : context.colorScheme.lightGreyColor,
                            ),
                          );
                        }),
                  ),
                ),
              ),
            ),
            elevation: 0,
            backgroundColor: context.colorScheme.secondaryColor,
            surfaceTintColor: context.colorScheme.secondaryColor,
          ),
          body: Column(
            children: [
              CustomContainer(
                color: context.colorScheme.secondaryColor,
                height: 70,
                width: context.screenWidth,
                child: Padding(
                    padding: const EdgeInsets.all(8),
                    child: TabBar(
                      onTap: (value) {
                        // TabController automatically handles the index change
                      },
                      controller: _tabController,
                      isScrollable: false,
                      dividerColor: Colors.transparent,
                      padding: const EdgeInsetsDirectional.only(end: 5),
                      labelPadding: const EdgeInsets.all(2),
                      indicatorColor: Colors.transparent,
                      labelStyle: const TextStyle(fontWeight: FontWeight.bold),
                      unselectedLabelStyle:
                          const TextStyle(fontWeight: FontWeight.normal),
                      tabs: tabs.map((e) {
                        final int index =
                            tabs.indexOf(e); // Get the index of the current tab
                        return Tab(
                          child: CustomContainer(
                            height: 40,
                            borderRadius: UiUtils.borderRadiusOf8,
                            color: _tabController.index == index
                                ? context.colorScheme.accentColor
                                : context.colorScheme.primaryColor,
                            alignment: Alignment.center,
                            padding: const EdgeInsets.symmetric(
                                horizontal: 20, vertical: 2),
                            child: CustomText(
                              e.translate(context: context),
                              color: _tabController.index == index
                                  ? AppColors.whiteColors
                                  : context.colorScheme.blackColor,
                            ),
                          ),
                        );
                      }).toList(),
                    )),
              ),
              Expanded(
                child: TabBarView(controller: _tabController, children: [
                  ServicesListWidget(
                    onTapRetry: () => searchServices(),
                    onErrorButtonPressed: () => searchMoreServices(),
                    servicesScrollController: _serviceScrollController,
                  ),
                  ProviderListWidget(
                    onTapRetry: () => searchProviders(),
                    onErrorButtonPressed: () => searchMoreProviders(),
                    providerScrollController: _providerScrollController,
                  ),
                ]),
              ),
            ],
          ),
        ),
      );

  void searchMoreProviders() {
    if (!context.read<SearchProviderCubit>().hasMoreProviders()) {
      return;
    }

// nextPageTrigger will have a value equivalent to 70% of the list size.
    final nextPageTrigger =
        0.7 * _providerScrollController.position.maxScrollExtent;

// _providerScrollController fetches the next paginated data when the current position of the user on the screen has surpassed
    if (_providerScrollController.position.pixels > nextPageTrigger) {
      if (mounted) {
        context
            .read<SearchProviderCubit>()
            .searchMoreProvider(searchKeyword: _searchController.text.trim());
      }
    }
  }

  void searchMoreServices() {
    if (!context.read<SearchServicesCubit>().hasMore()) {
      return;
    }

// nextPageTrigger will have a value equivalent to 70% of the list size.
    final nextPageTrigger =
        0.7 * _serviceScrollController.position.maxScrollExtent;

// _serviceScrollController fetches the next paginated data when the current position of the user on the screen has surpassed
    if (_serviceScrollController.position.pixels > nextPageTrigger) {
      if (mounted) {
        context
            .read<SearchServicesCubit>()
            .searchMoreServices(searchString: _searchController.text.trim());
      }
    }
  }
}
