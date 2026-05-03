import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class RatingBottomSheet extends StatefulWidget {
  final String serviceID;
  final String serviceName;
  final List<String> serviceReviewImages;
  final int? ratingStar;
  final String reviewComment;
  final String? customJobRequestId;

  const RatingBottomSheet({
    Key? key,
    required this.serviceID,
    required this.serviceName,
    required this.reviewComment,
    required this.serviceReviewImages,
    this.ratingStar,
    this.customJobRequestId,
  }) : super(key: key);

  @override
  State<RatingBottomSheet> createState() => _RatingBottomSheetState();
}

class _RatingBottomSheetState extends State<RatingBottomSheet> {
  // comment controller
  final TextEditingController reviewController = TextEditingController();

  int? selectedRating;

//image picker for review images
  final ImagePicker imagePicker = ImagePicker();
  ValueNotifier<List<XFile>> reviewImages = ValueNotifier([]);
  ValueNotifier<List<String>> pastReviewImages = ValueNotifier([]);

  Set<String> deletedPastReviewImages = {};

  Future<void> selectReviewImage() async {
    final List<XFile> listOfSelectedImage = await imagePicker.pickMultiImage();
    if (listOfSelectedImage.isNotEmpty) {
      reviewImages.value = listOfSelectedImage;
    }
  }

  @override
  void dispose() {
    reviewImages.dispose();
    reviewController.dispose();
    pastReviewImages.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    if (widget.reviewComment != '') {
      reviewController.text = widget.reviewComment;
    }
    if (widget.ratingStar != null) {
      selectedRating = widget.ratingStar! - 1;
    }
    pastReviewImages.value = widget.serviceReviewImages;
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsetsDirectional.only(
          bottom: MediaQuery.of(context).viewInsets.bottom),
      child: BottomSheetLayout(
        title: "reviewAndRating",
        child: Padding(
          padding:
              const EdgeInsetsDirectional.only(bottom: 10, start: 10, end: 15),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CustomContainer(
                margin: const EdgeInsetsDirectional.only(
                    bottom: 10, start: 5, top: 5),
                child: CustomText(
                  widget.serviceName,
                  color: context.colorScheme.blackColor,
                  fontSize: 16,
                  fontWeight: FontWeight.w500,
                ),
              ),
              Align(
                alignment: Alignment.center,
                child: Row(
                  children: List.generate(5, (index) {
                    return CustomInkWellContainer(
                      onTap: () {
                        selectedRating = index;
                        setState(() {});
                      },
                      child: CustomContainer(
                        margin: const EdgeInsets.symmetric(
                            horizontal: 5, vertical: 5),
                        width: 40,
                        height: 25,
                        color: selectedRating == index
                            ? context.colorScheme.accentColor
                            : null,
                        borderRadius: UiUtils.borderRadiusOf5,
                        border:
                            Border.all(color: context.colorScheme.accentColor),
                        child: Center(
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              CustomText(
                                '${index + 1}',
                                color: selectedRating == index
                                    ? AppColors.whiteColors
                                    : context.colorScheme.lightGreyColor,
                                fontSize: 12,
                              ),
                              Icon(
                                Icons.star_outlined,
                                size: 15,
                                color: selectedRating == index
                                    ? AppColors.whiteColors
                                    : context.colorScheme.lightGreyColor,
                              )
                            ],
                          ),
                        ),
                      ),
                    );
                  }),
                ),
              ),
              CustomContainer(
                margin: const EdgeInsets.symmetric(vertical: 10, horizontal: 5),
                height: 100,
                child: TextField(
                  style: TextStyle(
                      fontSize: 12, color: context.colorScheme.blackColor),
                  maxLines: 5,
                  textAlign: TextAlign.start,
                  controller: reviewController,
                  cursorColor: context.colorScheme.blackColor,
                  decoration: InputDecoration(
                    contentPadding: const EdgeInsets.all(15),
                    filled: false,
                    fillColor: context.colorScheme.secondaryColor,
                    hintText:
                        "${"writeReview".translate(context: context)} for ${widget.serviceName}",
                    hintStyle: TextStyle(
                      fontSize: 12,
                      color: context.colorScheme.lightGreyColor,
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderSide:
                          BorderSide(color: context.colorScheme.accentColor),
                      borderRadius: const BorderRadius.all(
                          Radius.circular(UiUtils.borderRadiusOf5)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderSide:
                          BorderSide(color: context.colorScheme.accentColor),
                      borderRadius: const BorderRadius.all(
                          Radius.circular(UiUtils.borderRadiusOf5)),
                    ),
                    border: OutlineInputBorder(
                      borderSide:
                          BorderSide(color: context.colorScheme.accentColor),
                      borderRadius: const BorderRadius.all(
                          Radius.circular(UiUtils.borderRadiusOf5)),
                    ),
                  ),
                ),
              ),
              ValueListenableBuilder(
                  valueListenable: pastReviewImages,
                  builder: (context, pastValue, child) {
                    return ValueListenableBuilder(
                      valueListenable: reviewImages,
                      builder: (context, List<XFile?> value, child) {
                        final bool areThereNoImages =
                            value.isEmpty && pastValue.isEmpty;
                        return SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          //if images is there then we will enable scroll
                          physics: areThereNoImages
                              ? const NeverScrollableScrollPhysics()
                              : const AlwaysScrollableScrollPhysics(),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              CustomInkWellContainer(
                                onTap: selectReviewImage,
                                child: Padding(
                                  padding:
                                      const EdgeInsets.symmetric(horizontal: 5),
                                  child: DottedBorderWithHint(
                                    width: areThereNoImages
                                        ? context.screenWidth - 40
                                        : 100,
                                    height: 100,
                                    radius: 5,
                                    borderColor:
                                        context.colorScheme.accentColor,
                                    hint: "chooseImage"
                                        .translate(context: context),
                                    svgImage: AppAssets.imageIcon,
                                    needToShowHintText: areThereNoImages,
                                  ),
                                ),
                              ),
                              if (value.isNotEmpty)
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: List.generate(
                                    value.length,
                                    (index) => CustomContainer(
                                      margin: const EdgeInsets.symmetric(
                                          horizontal: 5),
                                      height: 100,
                                      width: 100,
                                      border: Border.all(
                                        color: context.colorScheme.blackColor
                                            .withValues(alpha: 0.5),
                                      ),
                                      child: Stack(
                                        children: [
                                          Center(
                                              child: Image.file(
                                                  File(value[index]!.path))),
                                          Align(
                                            alignment:
                                                AlignmentDirectional.topEnd,
                                            child: CustomInkWellContainer(
                                              onTap: () async {
                                                //assigning new list, because listener will not notify if we remove the values only to the list
                                                reviewImages.value = List.from(
                                                    reviewImages.value)
                                                  ..removeAt(index);
                                              },
                                              child: CustomContainer(
                                                height: 20,
                                                width: 20,
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .blackColor
                                                    .withValues(alpha: 0.4),
                                                child: const Center(
                                                  child: Icon(
                                                    Icons.clear_rounded,
                                                    size: 15,
                                                  ),
                                                ),
                                              ),
                                            ),
                                          )
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              if (pastValue.isNotEmpty)
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: List.generate(
                                    pastValue.length,
                                    (index) => CustomContainer(
                                      margin: const EdgeInsets.symmetric(
                                          horizontal: 5),
                                      height: 100,
                                      width: 100,
                                      border: Border.all(
                                        color: context.colorScheme.blackColor
                                            .withValues(alpha: 0.5),
                                      ),
                                      child: Stack(
                                        children: [
                                          Center(
                                            child: CustomCachedNetworkImage(
                                                networkImageUrl:
                                                    pastValue[index]),
                                          ),
                                          Align(
                                            alignment:
                                                AlignmentDirectional.topEnd,
                                            child: CustomInkWellContainer(
                                              onTap: () async {
                                                pastReviewImages.value =
                                                    List.from(
                                                  pastReviewImages.value,
                                                )..removeAt(index);
                                                deletedPastReviewImages
                                                    .add(pastValue[index]);
                                              },
                                              child: CustomContainer(
                                                height: 20,
                                                width: 20,
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .blackColor
                                                    .withValues(alpha: 0.4),
                                                child: const Center(
                                                  child: Icon(
                                                    Icons.clear_rounded,
                                                    size: 15,
                                                  ),
                                                ),
                                              ),
                                            ),
                                          )
                                        ],
                                      ),
                                    ),
                                  ),
                                )
                            ],
                          ),
                        );
                      },
                    );
                  }),
              const CustomSizedBox(
                height: 8,
              ),
              BlocConsumer<SubmitReviewCubit, SubmitReviewState>(
                listener: (context, state) async {
                  if (state is SubmitReviewSuccess) {
                    UiUtils.showMessage(
                      context,
                      'reviewSubmittedSuccessfully'.translate(context: context),
                      ToastificationType.success,
                    );
                    Navigator.pop(context, {
                      "comment": state.data["comment"],
                      "rating": state.data["rating"],
                      "images": state.data["images"]
                    });
                  } else if (state is SubmitReviewFailure) {
                    Navigator.pop(context);
                    UiUtils.showMessage(
                      context,
                      state.errorMessage,
                      ToastificationType.error,
                    );
                  }
                },
                builder: (context, state) {
                  Widget? child;
                  if (state is SubmitReviewInProgress) {
                    child = const CustomCircularProgressIndicator(
                        color: AppColors.whiteColors);
                  }
                  return Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 5),
                    child: CustomRoundedButton(
                      onTap: () {
                        if (state is SubmitReviewInProgress) return;

                        if (selectedRating == null) {
                          UiUtils.showMessage(
                            context,
                            'pleaseGiveRating'.translate(context: context),
                            ToastificationType.warning,
                          );
                          return;
                        }

                        context.read<SubmitReviewCubit>().submitReview(
                              serviceId: widget.serviceID,
                              customJobRequestId: widget.customJobRequestId,
                              ratingStar: (selectedRating! + 1).toString(),
                              reviewComment:
                                  reviewController.text.trim().toString(),
                              reviewImages: reviewImages.value,
                              deletedPastReviewImages:
                                  deletedPastReviewImages.toList(),
                            );
                      },
                      widthPercentage: 1,
                      backgroundColor: context.colorScheme.accentColor,
                      buttonTitle: "submitReview".translate(context: context),
                      showBorder: false,
                      child: child,
                    ),
                  );
                },
              )
            ],
          ),
        ),
      ),
    );
  }
}
