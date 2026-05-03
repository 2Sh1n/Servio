import 'package:flutter/material.dart';
import '../../../../app/generalImports.dart';
import '../../../../cubits/fetchUserCurrentLocationCubit.dart';

class MapControls extends StatelessWidget {
  final VoidCallback onCurrentLocationPressed;
  final bool isLocationMode;

  const MapControls(
      {Key? key,
      required this.onCurrentLocationPressed,
      this.isLocationMode = true})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (!isLocationMode) return const SizedBox.shrink();

    return Align(
      alignment: AlignmentDirectional.bottomEnd,
      child: CustomContainer(
        margin: const EdgeInsets.all(20),
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            blurRadius: 5,
            color: context.colorScheme.blackColor.withValues(alpha: 0.2),
          )
        ],
        child: CustomInkWellContainer(
          onTap: onCurrentLocationPressed,
          child: BlocConsumer<FetchUserCurrentLocationCubit,
              FetchUserCurrentLocationState>(
            listener: (context, state) {
              if (state is FetchUserCurrentLocationFailure) {
                UiUtils.showMessage(
                    context, state.errorMessage, ToastificationType.error);
              }
            },
            builder: (context, state) {
              Widget? child;
              if (state is FetchUserCurrentLocationInProgress) {
                child = CustomCircularProgressIndicator(
                    color: context.colorScheme.blackColor);
              }
              return CustomContainer(
                color: context.colorScheme.secondaryColor,
                borderRadius: UiUtils.borderRadiusOf50,
                width: 60,
                height: 60,
                child: child ??
                    Icon(Icons.my_location_outlined,
                        size: 35, color: context.colorScheme.blackColor),
              );
            },
          ),
        ),
      ),
    );
  }
}
