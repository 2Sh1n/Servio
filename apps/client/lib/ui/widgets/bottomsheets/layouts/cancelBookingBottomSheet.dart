import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CancelBookingBottomSheet extends StatefulWidget {
  const CancelBookingBottomSheet({
    required this.customJobRequestId,
    final Key? key,
  }) : super(key: key);
  final String customJobRequestId;

  @override
  State<CancelBookingBottomSheet> createState() =>
      _CancelBookingBottomSheetState();
}

class _CancelBookingBottomSheetState extends State<CancelBookingBottomSheet> {
  @override
  Widget build(final BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        Navigator.of(context).pop();
      },
      child: SafeArea(
          child: BottomSheetLayout(
        topPadding: false,
        title: ''.translate(context: context),
        child: CustomContainer(
          padding: EdgeInsets.zero,
          color: context.colorScheme.secondaryColor,
          height: context.screenHeight * 0.5,
          child: Column(
            children: [
              Expanded(
                child: CustomContainer(
                  child: Column(
                    children: [
                      SvgPicture.asset(
                        AppAssets.confirmDelete,
                      ),
                      const CustomSizedBox(
                        height: 10,
                      ),
                      Text(
                        'areYouSure'.translate(context: context),
                        style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: context.colorScheme.blackColor),
                      ),
                      const CustomSizedBox(
                        height: 10,
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 15),
                        child: Text(
                          'cancelServiceWarning'.translate(context: context),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 12,
                            color: context.colorScheme.lightGreyColor,
                            fontWeight: FontWeight.w300,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              CloseAndConfirmButton(
                  closeButtonName: 'notYet'.translate(context: context),
                  confirmButtonName: 'confirm'.translate(context: context),
                  confirmButtonChild: CustomContainer(
                    padding: const EdgeInsets.all(5),
                    child: context.read<CancelCustomJobRequestCubit>().state
                            is CancelCustomJobRequestInProgress
                        ? CustomCircularProgressIndicator(
                            color: context.colorScheme.accentColor,
                          )
                        : CustomText(
                            'confirm'.translate(context: context),
                            color: context.colorScheme.secondaryColor,
                          ),
                  ),
                  closeButtonPressed: () {
                    Navigator.of(context).pop();
                  },
                  confirmButtonPressed: () async {
                    await context
                        .read<CancelCustomJobRequestCubit>()
                        .cancelBooking(
                          customJobRequestId: widget.customJobRequestId,
                        );
                    await context.read<MyRequestListCubit>().fetchRequests();
                    Navigator.of(context).pop();
                    Navigator.of(context).pop();
                  }),
            ],
          ),
        ),
      )),
    );
  }
}
