import 'package:flutter/material.dart';

import '../../../app/generalImports.dart';

class BookingScreen extends StatefulWidget {
  const BookingScreen({super.key, required this.scrollController});

  final ScrollController scrollController;

  @override
  BookingScreenState createState() => BookingScreenState();
}

class BookingScreenState extends State<BookingScreen>
    with
        AutomaticKeepAliveClientMixin,
        SingleTickerProviderStateMixin,
        WidgetsBindingObserver {
  int currFilter = 0;
  String? selectedStatus;
  String? selectedBookingOrder;
  int? currentOrder = 0;
  List<Map> filters = []; //set  model  from  API  Response
  List<Map> orderFilters = [];
  late final AnimationController _animationController = AnimationController(
    vsync: this,
  );
  late Animation<double> fontAnimation = Tween<double>(begin: 22.0, end: 20.0)
      .animate(
        CurvedAnimation(
          parent: _animationController,
          curve: Curves.easeInOut,
          reverseCurve: Curves.easeInOut,
        ),
      );
  late Animation<double> fontAnimation2 = Tween<double>(begin: 16.0, end: 8.0)
      .animate(
        CurvedAnimation(
          parent: _animationController,
          curve: Curves.easeInOut,
          reverseCurve: Curves.easeInOut,
        ),
      );

  void scrollListener() {
    _animationController.value = UiUtils.inRange(
      currentValue: widget.scrollController.offset,
      minValue: widget.scrollController.position.minScrollExtent,
      maxValue: widget.scrollController.position.maxScrollExtent,
      newMaxValue: 1.0,
      newMinValue: 0.0,
    );
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _animationController.dispose();
    widget.scrollController.removeListener(scrollListener);
    widget.scrollController.dispose();
    super.dispose();
  }

  void _onLanguageChanged() {
    // Reset to first filter (All) when language changes
    currFilter = 0;
    selectedStatus = null;

    // Preserve current booking order selection
    // currentOrder and selectedBookingOrder remain unchanged

    // Update filter lists with new translations
    _updateFilterLists();

    // Fetch bookings with current booking order but reset status filter
    context.read<FetchBookingsCubit>().fetchBookings(
      status: selectedStatus,
      customRequestOrder: selectedBookingOrder,
    );

    setState(() {});
  }

  void _updateFilterLists() {
    filters = [
      {'id': '0', 'fName': 'all'.translate(context: context)},
      {'id': '1', 'fName': 'awaiting'.translate(context: context)},
      {'id': '2', 'fName': 'confirmed'.translate(context: context)},
      {'id': '3', 'fName': 'started'.translate(context: context)},
      {'id': '4', 'fName': 'rescheduled'.translate(context: context)},
      {'id': '5', 'fName': 'booking_ended'.translate(context: context)},
      {'id': '6', 'fName': 'completed'.translate(context: context)},
      {'id': '7', 'fName': 'cancelled'.translate(context: context)},
    ];
    orderFilters = [
      {'id': '0', 'fName': 'defaultBookings'.translate(context: context)},
      {'id': '1', 'fName': 'customBookings'.translate(context: context)},
    ];
  }

  bool _isInitialized = false;

  @override
  void didChangeDependencies() {
    widget.scrollController.addListener(scrollListener);

    // Update filter lists with current translations
    _updateFilterLists();

    // Only call fetchBookings once when the screen is first initialized
    if (!_isInitialized) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.read<FetchBookingsCubit>().fetchBookings(
          status: selectedStatus,
          customRequestOrder: selectedBookingOrder,
        );
      });
      _isInitialized = true;
    }

    super.didChangeDependencies();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return BlocListener<LanguageDataCubit, LanguageDataState>(
      listener: (context, state) {
        if (state is GetLanguageDataSuccess) {
          _onLanguageChanged();
        }
      },
      child: DefaultTabController(
        length: filters.length,
        child: AnnotatedRegion<SystemUiOverlayStyle>(
          value: UiUtils.getSystemUiOverlayStyle(context: context),
          child: SafeArea(
            child: Scaffold(
              backgroundColor: Theme.of(context).colorScheme.primaryColor,
              body: Stack(
                children: [
                  BlocBuilder<FetchBookingsCubit, FetchBookingsState>(
                    builder: (context, state) {
                      // Check if we have data to determine scroll behavior
                      final bool hasData =
                          state is FetchBookingsSuccess &&
                          state.bookings.isNotEmpty;

                      if (!hasData) {
                        // When no data, use a simple layout without unnecessary scrolling
                        return Column(
                          children: [
                            // Header section (always visible)
                            AnimatedBuilder(
                              animation: _animationController,
                              builder: (context, _) {
                                return Container(
                                  padding: const EdgeInsets.all(16),
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.primaryColor,
                                  child: Column(
                                    children: [
                                      CustomText(
                                        'bookingTab'.translate(
                                          context: context,
                                        ),
                                        fontSize: 22,
                                        fontWeight: FontWeight.bold,
                                        color: Theme.of(
                                          context,
                                        ).colorScheme.blackColor,
                                      ),
                                      BlocBuilder<
                                        FetchBookingsCubit,
                                        FetchBookingsState
                                      >(
                                        builder: (context, state) {
                                          if (state is FetchBookingsSuccess) {
                                            return HeadingAmountAnimation(
                                              key: ValueKey(state.total),
                                              text:
                                                  '${state.total.toString()} ${'bookingTab'.translate(context: context)}',
                                              textStyle: TextStyle(
                                                color: context
                                                    .colorScheme
                                                    .lightGreyColor,
                                                fontWeight: FontWeight.w400,
                                                fontSize: 16,
                                              ),
                                            );
                                          } else {
                                            return SizedBox(
                                              width:
                                                  MediaQuery.of(
                                                    context,
                                                  ).size.width *
                                                  0.7,
                                              height: 24,
                                            );
                                          }
                                        },
                                      ),
                                      const SizedBox(height: 10),
                                      const CustomShadowLineForDevider(
                                        direction:
                                            ShadowDirection.centerToSides,
                                      ),
                                      const SizedBox(height: 10),
                                      SizedBox(
                                        height: 55,
                                        child: _buildTabBar(context),
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                            // Content section
                            Expanded(
                              child: BookingsTabContent(
                                status: selectedStatus,
                                bookingOrder: selectedBookingOrder,
                              ),
                            ),
                          ],
                        );
                      }

                      // When data exists, use the scrollable layout
                      return CustomScrollView(
                        controller: widget.scrollController,
                        slivers: [
                          SliverPersistentHeader(
                            delegate: LongAppBarPersistentHeaderDelegate(
                              child: Center(
                                child: AnimatedBuilder(
                                  animation: _animationController,
                                  builder: (context, _) {
                                    return Column(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceAround,
                                      children: [
                                        Column(
                                          children: [
                                            CustomText(
                                              'bookingTab'.translate(
                                                context: context,
                                              ),
                                              fontSize: fontAnimation.value,
                                              fontWeight: FontWeight.bold,
                                              color: Theme.of(
                                                context,
                                              ).colorScheme.blackColor,
                                            ),
                                            BlocBuilder<
                                              FetchBookingsCubit,
                                              FetchBookingsState
                                            >(
                                              builder: (context, state) {
                                                if (state
                                                    is FetchBookingsSuccess) {
                                                  return HeadingAmountAnimation(
                                                    key: ValueKey(state.total),
                                                    text:
                                                        '${state.total.toString()} ${'bookingTab'.translate(context: context)}',
                                                    textStyle: TextStyle(
                                                      color: context
                                                          .colorScheme
                                                          .lightGreyColor,
                                                      fontWeight:
                                                          FontWeight.w400,
                                                      fontSize:
                                                          fontAnimation2.value,
                                                    ),
                                                  );
                                                } else {
                                                  return SizedBox(
                                                    width:
                                                        MediaQuery.of(
                                                          context,
                                                        ).size.width *
                                                        0.7,
                                                    height:
                                                        fontAnimation2.value +
                                                        8,
                                                  );
                                                }
                                              },
                                            ),
                                            const SizedBox(height: 10),
                                            const CustomShadowLineForDevider(
                                              direction:
                                                  ShadowDirection.centerToSides,
                                            ),
                                          ],
                                        ),
                                        SizedBox(
                                          height: 55,
                                          child: _buildTabBar(context),
                                        ),
                                      ],
                                    );
                                  },
                                ),
                              ),
                            ),
                            pinned: true,
                          ),
                          SliverToBoxAdapter(
                            child: BookingsTabContent(
                              status: selectedStatus,
                              bookingOrder: selectedBookingOrder,
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  // Bottom tab bar - always visible outside of BlocBuilder
                  Align(
                    alignment: Alignment.bottomCenter,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 15,
                        vertical: 70,
                      ),
                      child: CustomContainer(
                        height: 55,
                        borderRadius: UiUtils.borderRadiusOf50,
                        border: Border.all(
                          color: context.colorScheme.lightGreyColor,
                          width: 0.2,
                        ),
                        color: context.colorScheme.secondaryColor,
                        child: _buildBookingOrderTabBar(context),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildTabBar(BuildContext context) {
    return ListView.builder(
      scrollDirection: Axis.horizontal,
      itemCount: filters.length,
      itemBuilder: (BuildContext context, int index) {
        return CustomInkWellContainer(
          onTap: () {
            if (currFilter == index) {
              return;
            }

            final String? oldStatus = selectedStatus;
            currFilter = index;
            setState(() {});

            switch (currFilter) {
              case 0:
                selectedStatus = null;
                break;
              case 1:
                selectedStatus = 'awaiting';
                break;
              case 2:
                selectedStatus = 'confirmed';
                break;
              case 3:
                selectedStatus = 'started';
                break;
              case 4:
                selectedStatus = 'rescheduled';
                break;
              case 5:
                selectedStatus = 'booking_ended';
                break;
              case 6:
                selectedStatus = 'completed';
                break;
              case 7:
                selectedStatus = 'cancelled';
                break;
            }

            // Only fetch if status actually changed
            if (oldStatus != selectedStatus) {
              context.read<FetchBookingsCubit>().fetchBookings(
                status: selectedStatus,
                customRequestOrder: selectedBookingOrder,
              );
            }
          },
          child: CustomContainer(
            color: currFilter == index
                ? Theme.of(context).colorScheme.accentColor
                : Colors.transparent,
            borderRadius: UiUtils.borderRadiusOf10,
            margin: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            constraints: const BoxConstraints(minWidth: 90),
            child: Center(
              child: Text(
                filters[index]['fName'],
                style: TextStyle(
                  color: currFilter == index
                      ? AppColors.whiteColors
                      : Theme.of(context).colorScheme.blackColor,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildBookingOrderTabBar(BuildContext context) {
    return Row(
      mainAxisAlignment:
          MainAxisAlignment.center, // Centers the tabs horizontally

      children: List.generate(orderFilters.length, (int index) {
        return Expanded(
          child: CustomInkWellContainer(
            showSplashEffect: false,
            onTap: () {
              if (currentOrder == index) {
                return;
              }

              final String? oldBookingOrder = selectedBookingOrder;
              currentOrder = index;
              setState(() {});

              switch (currentOrder) {
                case 0:
                  selectedBookingOrder = '';
                  break;
                case 1:
                  selectedBookingOrder = '1';
                  break;
              }

              // Only fetch if booking order actually changed
              if (oldBookingOrder != selectedBookingOrder) {
                context.read<FetchBookingsCubit>().fetchBookings(
                  status: selectedStatus,
                  customRequestOrder: selectedBookingOrder,
                );
              }
            },
            child: CustomContainer(
              color: currentOrder == index
                  ? Theme.of(context).colorScheme.accentColor
                  : Theme.of(context).colorScheme.secondaryColor,
              borderRadius: UiUtils.borderRadiusOf50,
              margin: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
              padding: const EdgeInsets.symmetric(horizontal: 10),
              height: 50,
              child: Center(
                child: CustomText(
                  orderFilters[index]['fName'],
                  color: currentOrder == index
                      ? AppColors.whiteColors
                      : Theme.of(context).colorScheme.blackColor,
                  fontWeight: FontWeight.w500,
                  maxLines: 1,
                ),
              ),
            ),
          ),
        );
      }),
    );
  }

  @override
  bool get wantKeepAlive => true;
}
