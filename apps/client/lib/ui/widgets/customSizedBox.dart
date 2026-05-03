import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/widgets.dart';

class CustomSizedBox extends StatelessWidget {
  const CustomSizedBox(
      {super.key,
      this.height,
      this.width,
      this.avoideResponsive = true,
      this.child});
  final double? height;
  final double? width;
  final bool avoideResponsive;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: avoideResponsive ? height : height?.rh(context),
      width: avoideResponsive ? width : width?.rw(context),
      child: child,
    );
  }
}
