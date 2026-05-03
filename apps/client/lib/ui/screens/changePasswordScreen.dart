import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({final Key? key}) : super(key: key);

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();

  static Route route(final RouteSettings routeSettings) {
    return CupertinoPageRoute(
      builder: (final _) => MultiBlocProvider(
        providers: [
          BlocProvider(create: (context) => ChangePasswordCubit()),
        ],
        child: const ChangePasswordScreen(),
      ),
    );
  }
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _currentPasswordController =
      TextEditingController();
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();

  @override
  void dispose() {
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  void _onChangePasswordButtonPressed() {
    UiUtils.removeFocus();

    if (!_formKey.currentState!.validate()) {
      return;
    }

    context.read<ChangePasswordCubit>().changePassword(
          currentPassword: _currentPasswordController.text,
          newPassword: _newPasswordController.text,
          loginType:
              context.read<UserDetailsCubit>().getUserDetails().loginType!,
        );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.primaryColor,
      appBar: UiUtils.getSimpleAppBar(
        context: context,
        title: "changePassword",
      ),
      body: BlocListener<ChangePasswordCubit, ChangePasswordState>(
        listener: (context, state) {
          if (state is ChangePasswordSuccess) {
            UiUtils.showMessage(
              context,
              state.message.translate(context: context),
              ToastificationType.success,
            );
            Navigator.pop(context);
          } else if (state is ChangePasswordFail) {
            UiUtils.showMessage(
              context,
              state.error.translate(context: context),
              ToastificationType.error,
            );
          }
        },
        child: SafeArea(
          child: Form(
            key: _formKey,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const CustomSizedBox(height: 20),
                  CustomTextFormField(
                    controller: _currentPasswordController,
                    labelText: "currentPassword",
                    isPassword: true,
                    textInputAction: TextInputAction.next,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return "fieldMustNotBeEmpty"
                            .translate(context: context);
                      }
                      return null;
                    },
                  ),
                  const CustomSizedBox(height: 20),
                  CustomPasswordFieldWithValidators(
                    controller: _newPasswordController,
                    labelText: "newPassword",
                    textInputAction: TextInputAction.next,
                    validator: (value) => isValidPassword(
                      password: value ?? '',
                      context: context,
                    ),
                  ),
                  const CustomSizedBox(height: 20),
                  CustomTextFormField(
                    controller: _confirmPasswordController,
                    labelText: "confirmPassword",
                    isPassword: true,
                    textInputAction: TextInputAction.done,
                    validator: (value) => confirmPasswordValidator(
                      password: _newPasswordController.text,
                      confirmPassword: value ?? '',
                      context: context,
                    ),
                  ),
                  const CustomSizedBox(height: 20),
                  BlocBuilder<ChangePasswordCubit, ChangePasswordState>(
                    builder: (context, state) {
                      final isLoading = state is ChangePasswordInProgress;
                      return CustomRoundedButton(
                        widthPercentage: 1,
                        backgroundColor:
                            Theme.of(context).colorScheme.accentColor,
                        buttonTitle: isLoading ? "" : "changePassword",
                        showBorder: false,
                        onTap:
                            isLoading ? null : _onChangePasswordButtonPressed,
                        titleColor: AppColors.whiteColors,
                        child: isLoading
                            ? const CustomCircularProgressIndicator()
                            : null,
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
