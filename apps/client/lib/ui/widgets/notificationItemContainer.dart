import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

enum NotificationItemLocation {
  first,
  last,
  onlyOne,
  middle;
}

class NotificationItemContainer extends StatelessWidget {
  const NotificationItemContainer({
    required this.notification,
    required this.location,
    final Key? key,
    this.onSlideTap,
    this.slidableChild,
    this.showArrow = false,
    this.isLoading = false,
  }) : super(key: key);

  final NotificationItemLocation location;
  final NotificationDataModel notification;
  final VoidCallback? onSlideTap;
  final Widget? slidableChild;
  final bool showArrow;
  final bool isLoading;

  CustomContainer _buildNotificationContainer(
          {required final BuildContext context}) =>
      CustomContainer(
        width: double.infinity,
        padding: const EdgeInsets.all(8),
        child: Row(
          children: [
            notification.image != ''
                ? CustomContainer(
                    height: 50,
                    width: 50,
                    shape: BoxShape.circle,
                    clipBehavior: Clip.antiAlias,
                    child: CustomCachedNetworkImage(
                      networkImageUrl: notification.image ?? '',
                      height: 50,
                      width: 50,
                      fit: BoxFit.cover,
                    ),
                  )
                : CustomContainer(
                    height: 50,
                    width: 50,
                    color:
                        context.colorScheme.accentColor.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                    child: Icon(
                      Icons.notifications,
                      color: context.colorScheme.accentColor,
                      size: 25,
                    ),
                  ),
            const CustomSizedBox(
              width: 10,
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    spacing: 8,
                    children: [
                      Flexible(
                        child: CustomText(
                          notification.title?.trim() ?? '',
                          color: context.colorScheme.blackColor,
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      CustomText(
                        UiUtils.getTimeAgo(context,
                            date: notification.dateSent),
                        maxLines: 1,
                        color: context.colorScheme.lightGreyColor
                            .withValues(alpha: 0.75),
                        fontWeight: FontWeight.w400,
                        fontStyle: FontStyle.normal,
                        fontSize: 10,
                        textAlign: TextAlign.start,
                      ),
                    ],
                  ),
                  const CustomSizedBox(height: 4),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    spacing: 8,
                    children: [
                      Expanded(
                        child: CustomReadMoreTextContainer(
                          text: notification.message ?? '',
                          textStyle: TextStyle(
                            color: context.colorScheme.lightGreyColor,
                            fontWeight: FontWeight.w400,
                            fontStyle: FontStyle.normal,
                            fontSize: 12,
                          ),
                          trimLines: 2,
                        ),
                      ),
                      if (isLoading) ...[
                        SizedBox(
                          height: 16,
                          width: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: context.colorScheme.lightGreyColor,
                          ),
                        ),
                      ] else if (showArrow) ...[
                        Icon(
                          Icons.chevron_right,
                          color: context.colorScheme.lightGreyColor,
                          size: 20,
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      );

  BorderRadiusGeometry get _borderRadius {
    switch (location) {
      case NotificationItemLocation.onlyOne:
        return const BorderRadius.only(
          topLeft: Radius.circular(UiUtils.borderRadiusOf10),
          topRight: Radius.circular(UiUtils.borderRadiusOf10),
          bottomLeft: Radius.circular(UiUtils.borderRadiusOf10),
          bottomRight: Radius.circular(UiUtils.borderRadiusOf10),
        );
      case NotificationItemLocation.first:
        return const BorderRadius.only(
          topLeft: Radius.circular(UiUtils.borderRadiusOf10),
          topRight: Radius.circular(UiUtils.borderRadiusOf10),
        );
      case NotificationItemLocation.last:
        return const BorderRadius.only(
          bottomLeft: Radius.circular(UiUtils.borderRadiusOf10),
          bottomRight: Radius.circular(UiUtils.borderRadiusOf10),
        );
      case NotificationItemLocation.middle:
        return BorderRadius.zero;
    }
  }

  @override
  Widget build(final BuildContext context) => CustomInkWellContainer(
        onTap: onSlideTap,
        borderRadius: BorderRadius.circular(UiUtils.borderRadiusOf10),
        child: CustomContainer(
          clipBehavior: Clip.antiAlias,
          borderRadiusStyle: _borderRadius,
          border: location == NotificationItemLocation.middle ||
                  location == NotificationItemLocation.first
              ? Border(
                  bottom: BorderSide(
                    color: context.colorScheme.lightGreyColor
                        .withValues(alpha: 0.5),
                    width: 0.5,
                  ),
                )
              : null,
          color: notification.isRead == "1"
              ? context.colorScheme.secondaryColor.withValues(alpha: 0.5)
              : context.colorScheme.secondaryColor,
          child: Column(
            children: [
              Stack(
                clipBehavior: Clip.antiAlias,
                children: [
                  if (slidableChild != null) ...[
                    Positioned.fill(
                      child: Builder(
                        builder: (final BuildContext context) => Padding(
                          padding: EdgeInsets.zero,
                          child: CustomContainer(
                            color: AppColors.redColor,
                            borderRadiusStyle: _borderRadius,
                          ),
                        ),
                      ),
                    ),
                  ],
                  if (slidableChild != null) ...[
                    Slidable(
                      key: UniqueKey(),
                      endActionPane: ActionPane(
                        motion: const BehindMotion(),
                        extentRatio: 0.24,
                        children: slidableChild != null ? [slidableChild!] : [],
                      ),
                      child: _buildNotificationContainer(context: context),
                    ),
                  ] else ...[
                    _buildNotificationContainer(context: context)
                  ]
                ],
              ),
            ],
          ),
        ),
      );
}
