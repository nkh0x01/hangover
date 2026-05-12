import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/typography.dart';

class AppTextField extends StatelessWidget {
  const AppTextField({
    super.key,
    required this.controller,
    this.label,
    this.hint,
    this.keyboardType,
    this.inputFormatters,
    this.autofocus = false,
    this.obscure = false,
    this.maxLength,
    this.prefixIcon,
    this.helper,
  });

  final TextEditingController controller;
  final String? label;
  final String? hint;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final bool autofocus;
  final bool obscure;
  final int? maxLength;
  final Widget? prefixIcon;
  final String? helper;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      autofocus: autofocus,
      obscureText: obscure,
      maxLength: maxLength,
      style: AppType.titleM.copyWith(fontWeight: FontWeight.w500),
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        helperText: helper,
        prefixIcon: prefixIcon,
        counterText: '',
      ),
    );
  }
}
