import 'package:device_info_plus/device_info_plus.dart';
import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

class DownloadInvoiceButton extends StatelessWidget {
  final String bookingId;
  final String buttonScreenName;

  const DownloadInvoiceButton(
      {super.key, required this.bookingId, required this.buttonScreenName});

  static Future<bool> checkStoragePermission() async {
    if (Platform.isIOS) {
      var permissionGiven = await Permission.storage.isGranted;
      if (!permissionGiven) {
        return permissionGiven = (await Permission.storage.request()).isGranted;
      }
      return permissionGiven;
    }
    //if it is for android
    final deviceInfoPlugin = DeviceInfoPlugin();
    final androidDeviceInfo = await deviceInfoPlugin.androidInfo;
    if (androidDeviceInfo.version.sdkInt < 33) {
      var permissionGiven = await Permission.storage.isGranted;
      if (!permissionGiven) {
        return permissionGiven = (await Permission.storage.request()).isGranted;
      }
      return permissionGiven;
    } else {
      var permissionGiven = await Permission.photos.isGranted;
      if (!permissionGiven) {
        return permissionGiven = (await Permission.photos.request()).isGranted;
      }
      return permissionGiven;
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<DownloadInvoiceCubit, DownloadInvoiceState>(
      listener: (final BuildContext context, DownloadInvoiceState state) async {
        if (state is DownloadInvoiceSuccess) {
          if (state.bookingId == bookingId &&
              buttonScreenName == state.buttonScreenName) {
            try {
              final bool checkPermission = await checkStoragePermission();
              final appDocDirPath = Platform.isAndroid && checkPermission
                  ? (await ExternalPath.getExternalStoragePublicDirectory(
                      ExternalPath.DIRECTORY_DOWNLOAD,
                    ))
                  : (await getApplicationDocumentsDirectory()).path;

              final targetFileName =
                  "$appName-${"invoice".translate(context: context)}-$bookingId.pdf";

              final File file = File("$appDocDirPath/$targetFileName");

              // Write down the file as bytes from the bytes got from the HTTP request.
              await file.writeAsBytes(state.invoiceData).then((final value) {
                UiUtils.showMessage(
                  context,
                  'invoiceDownloadedSuccessfully'.translate(context: context),
                  ToastificationType.success,
                );

                OpenFile.open(file.path);
              });
            } catch (e) {
              UiUtils.showMessage(
                context,
                'somethingWentWrong'.translate(context: context),
                ToastificationType.error,
              );
            }
          }
        } else if (state is DownloadInvoiceFailure) {
          if (state.bookingId == bookingId)
            UiUtils.showMessage(
                context, state.errorMessage, ToastificationType.error);
        }
      },
      builder: (final BuildContext context, final DownloadInvoiceState state) {
        Widget? child;
        if (state is DownloadInvoiceInProgress) {
          if (state.bookingId == bookingId &&
              buttonScreenName == state.buttonScreenName)
            child = const CustomCircularProgressIndicator(
                color: AppColors.whiteColors);
        }
        return Align(
          alignment: Alignment.bottomCenter,
          child: CustomRoundedButton(
            onTap: () {
              context.read<DownloadInvoiceCubit>().downloadInvoice(
                  bookingId: bookingId, buttonScreenName: buttonScreenName);
            },
            backgroundColor: context.colorScheme.accentColor.withAlpha(30),
            radius: UiUtils.borderRadiusOf5,
            buttonTitle: '',
            showBorder: false,
            widthPercentage: 0.9,
            titleColor: context.colorScheme.accentColor,
            shadowColor: context.colorScheme.accentColor.withAlpha(30),
            child: child ??
                CustomText("downloadInvoice".translate(context: context),
                    maxLines: 1,
                    color: context.colorScheme.accentColor,
                    fontSize: 16,
                    fontWeight: FontWeight.w500),
          ),
        );
      },
    );
  }
}
