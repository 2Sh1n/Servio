import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class NotesWidget extends StatelessWidget {
  final String notes;
  final String title;

  const NotesWidget({
    super.key,
    required this.notes,
    this.title = "notesLbl",
  });

  @override
  Widget build(BuildContext context) {
    return CustomContainer(
      color: context.colorScheme.secondaryColor,
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSectionHeader(context),
          const CustomSizedBox(height: 10),
          _buildNotesContent(context),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(BuildContext context) {
    return Row(
      children: [
        CustomSvgPicture(
          svgImage: AppAssets.icNotes,
          height: 20,
          width: 20,
          color: context.colorScheme.accentColor,
        ),
        const CustomSizedBox(width: 8),
        CustomText(
          title.translate(context: context),
          fontSize: 14,
          fontWeight: FontWeight.w400,
          color: context.colorScheme.lightGreyColor,
        ),
      ],
    );
  }

  Widget _buildNotesContent(BuildContext context) {
    return CustomText(
      notes,
      fontSize: 14,
      fontWeight: FontWeight.w400,
      color: context.colorScheme.lightGreyColor,
    );
  }
}
