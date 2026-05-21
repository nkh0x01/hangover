import 'package:flutter/material.dart';

import 'colors.dart';

/// Branded type ramp. Uses platform-default sans for Latin and falls
/// back to Noto Sans Georgian for Georgian glyphs, matching the
/// architectural decision in `docs/architecture/04-flutter-app-structure.md`.
///
/// Phase 1.6 adds a `display` style for fare hero numbers and tightens
/// the body line-height so cards feel less airy/clinical.
class AppType {
  static const _family = 'Inter';
  static const _familyFallback = <String>['Noto Sans Georgian', 'Roboto'];

  static const display = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 40,
    fontWeight: FontWeight.w700,
    letterSpacing: -0.5,
    height: 1.0,
    color: AppColors.ink,
  );

  static const headlineL = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 28,
    fontWeight: FontWeight.w700,
    letterSpacing: -0.3,
    height: 1.15,
    color: AppColors.ink,
  );

  static const headlineM = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 22,
    fontWeight: FontWeight.w600,
    height: 1.2,
    color: AppColors.ink,
  );

  static const titleL = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 17,
    fontWeight: FontWeight.w600,
    height: 1.25,
    color: AppColors.ink,
  );

  static const titleM = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 15,
    fontWeight: FontWeight.w600,
    height: 1.3,
    color: AppColors.ink,
  );

  static const body = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 14,
    fontWeight: FontWeight.w400,
    height: 1.4,
    color: AppColors.inkSoft,
  );

  static const bodyStrong = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 14,
    fontWeight: FontWeight.w600,
    height: 1.4,
    color: AppColors.ink,
  );

  static const caption = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 12,
    fontWeight: FontWeight.w500,
    height: 1.3,
    color: AppColors.inkMuted,
    letterSpacing: 0.1,
  );

  static const label = TextStyle(
    fontFamily: _family,
    fontFamilyFallback: _familyFallback,
    fontSize: 11,
    fontWeight: FontWeight.w600,
    height: 1.3,
    color: AppColors.inkMuted,
    letterSpacing: 0.6,
  );
}
