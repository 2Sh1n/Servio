import 'package:flutter/material.dart';

class BannerPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.blue.withValues(alpha: 0.1) // Light blue background
      ..style = PaintingStyle.fill;

    final path = Path();
    path.moveTo(0, 0); // Top-left
    path.lineTo(size.width * 0.2, 0); // Top edge slant
    path.lineTo(size.width * 0.8, 0); // Top edge slant
    path.lineTo(size.width, 0); // Top-right
    path.lineTo(size.width * 0.9, size.height); // Bottom-right slant
    path.lineTo(size.width * 0.1, size.height); // Bottom-left slant
    path.close();

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) {
    return false;
  }
}
