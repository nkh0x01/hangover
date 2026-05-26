import 'package:flutter/material.dart';
import 'package:ui_kit/ui_kit.dart';

class DriverBuildIdentityLabel extends StatelessWidget {
  const DriverBuildIdentityLabel({super.key});

  static const _versionName =
      String.fromEnvironment('HANGOVER_BUILD_VERSION_NAME');
  static const _versionCode =
      String.fromEnvironment('HANGOVER_BUILD_VERSION_CODE');
  static const _commit = String.fromEnvironment('HANGOVER_BUILD_COMMIT');

  static String get label {
    final version = _versionName.ifEmpty('0.1.0');
    final build = _versionCode.ifEmpty('9');
    return 'Driver $version ($build) · commit ${_shortCommit(_commit)}';
  }

  static String _shortCommit(String value) {
    final normalized = value.trim();
    if (normalized.isEmpty) return 'local';
    if (normalized.length <= 7) return normalized;
    return normalized.substring(0, 7);
  }

  @override
  Widget build(BuildContext context) {
    return Text(
      label,
      style: AppType.caption.copyWith(color: AppColors.inkMuted),
    );
  }
}

extension on String {
  String ifEmpty(String fallback) => isEmpty ? fallback : this;
}
