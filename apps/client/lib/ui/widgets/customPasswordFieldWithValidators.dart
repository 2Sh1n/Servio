import 'package:flutter/material.dart';

import '../../app/generalImports.dart';

class CustomPasswordFieldWithValidators extends StatefulWidget {
  const CustomPasswordFieldWithValidators({
    required this.controller,
    super.key,
    this.textInputAction,
    this.currentFocusNode,
    this.nextFocusNode,
    this.labelText,
    this.validator,
  });

  final TextEditingController controller;
  final FocusNode? currentFocusNode;
  final FocusNode? nextFocusNode;
  final TextInputAction? textInputAction;
  final String? labelText;
  final String? Function(String?)? validator;

  @override
  State<CustomPasswordFieldWithValidators> createState() =>
      _CustomPasswordFieldWithValidatorsState();
}

class _CustomPasswordFieldWithValidatorsState
    extends State<CustomPasswordFieldWithValidators> {
  bool hasMinLength = false;
  bool hasUppercase = false;
  bool hasLowercase = false;
  bool hasNumber = false;
  bool hasSpecialChar = false;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_validatePassword);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_validatePassword);
    super.dispose();
  }

  void _validatePassword() {
    final password = widget.controller.text;

    setState(() {
      hasMinLength = password.length >= 8;
      hasUppercase = password.contains(RegExp('[A-Z]'));
      hasLowercase = password.contains(RegExp('[a-z]'));
      hasNumber = password.contains(RegExp('[0-9]'));
      hasSpecialChar = password.contains(RegExp(r'[!@#$%^&*(),.?":{}|<>]'));
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomTextFormField(
          controller: widget.controller,
          labelText: widget.labelText,
          isPassword: true,
          textInputAction: widget.textInputAction,
          currentFocusNode: widget.currentFocusNode,
          nextFocusNode: widget.nextFocusNode,
          validator: widget.validator,
          bottomPadding: 0,
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildValidationItem(
                'passwordMustBeAtLeast8Chars',
                hasMinLength,
              ),
              const CustomSizedBox(height: 6),
              _buildValidationItem(
                'passwordMustHaveUppercase',
                hasUppercase,
              ),
              const CustomSizedBox(height: 6),
              _buildValidationItem(
                'passwordMustHaveLowercase',
                hasLowercase,
              ),
              const CustomSizedBox(height: 6),
              _buildValidationItem(
                'passwordMustHaveNumber',
                hasNumber,
              ),
              const CustomSizedBox(height: 6),
              _buildValidationItem(
                'passwordMustHaveSpecialChar',
                hasSpecialChar,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildValidationItem(String text, bool isValid) {
    final greyColor = Theme.of(context).colorScheme.lightGreyColor;
    final greenColor = AppColors.greenColor;

    return Row(
      children: [
        AnimatedSwitcher(
          duration: const Duration(milliseconds: 300),
          transitionBuilder: (child, animation) {
            return ScaleTransition(
              scale: animation,
              child: child,
            );
          },
          child: Icon(
            isValid ? Icons.check_circle : Icons.circle_outlined,
            key: ValueKey(isValid),
            size: 16,
            color: isValid ? greenColor : greyColor,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: TweenAnimationBuilder<Color?>(
            duration: const Duration(milliseconds: 300),
            tween: ColorTween(
              begin: greyColor,
              end: isValid ? greenColor : greyColor,
            ),
            builder: (context, color, child) {
              return Text(
                text.translate(context: context),
                style: TextStyle(
                  fontSize: 12,
                  color: color,
                  fontFamily: 'Lexend',
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
