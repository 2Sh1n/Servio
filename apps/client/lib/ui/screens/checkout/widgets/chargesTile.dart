import 'package:flutter/material.dart';

import '../../../../app/generalImports.dart';

class ChargesTile extends StatelessWidget {
  final String title1;
  final String title2;
  final String title3;
  final double fontSize;
  final FontWeight? fontweight;

  const ChargesTile(
      {super.key,
      required this.title1,
      required this.title2,
      required this.title3,
      required this.fontSize,
      this.fontweight});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: CustomText(
            title1,
            fontSize: fontSize,
            fontWeight: fontweight,
            maxLines: 1,
          ),
        ),
        if (title2 != '')
          Flexible(
            flex: 2,
            child: CustomText(
              title2,
              fontSize: fontSize,
            ),
          ),
        CustomText(title3,
            textAlign: TextAlign.end,
            maxLines: 2,
            fontWeight: fontweight,
            fontSize: fontSize)
      ],
    );
  }
}
