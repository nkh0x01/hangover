import 'package:flutter/material.dart';

import 'colors.dart';
import 'insets.dart';
import 'typography.dart';

/// Application-wide theme. Phase 1.6 changes:
///
/// - Warm cream surface (`#FBF8F2`) instead of cold `#FAFAFA`.
/// - Branded type ramp (see [AppType]).
/// - Tighter card / button radii + subtle elevation that reads as
///   "tactile" rather than "Material default".
/// - `ColorScheme.tertiary` mapped to the Phase 1.6 terracotta accent
///   so we can use it as a semantic colour throughout (live pulses,
///   today-progress arc, surge chips).
class AppTheme {
  static ThemeData light() => _build(Brightness.light);
  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.seed,
      brightness: brightness,
    ).copyWith(
      surface: brightness == Brightness.light ? AppColors.surface : AppColors.surfaceDark,
      surfaceContainerHighest: brightness == Brightness.light
          ? AppColors.surfaceVariant
          : AppColors.surfaceVariantDark,
      tertiary: AppColors.accent,
      onTertiary: Colors.white,
      error: AppColors.danger,
      outline: AppColors.outline,
      outlineVariant: AppColors.outlineSubtle,
    );

    final isLight = brightness == Brightness.light;

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: isLight ? AppColors.surface : AppColors.surfaceDark,
      textTheme: const TextTheme(
        displayLarge: AppType.display,
        headlineLarge: AppType.headlineL,
        headlineMedium: AppType.headlineM,
        titleLarge: AppType.titleL,
        titleMedium: AppType.titleM,
        bodyLarge: AppType.body,
        bodyMedium: AppType.body,
        labelLarge: AppType.titleM,
        labelMedium: AppType.label,
        labelSmall: AppType.label,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: AppType.titleL,
      ),
      cardTheme: CardThemeData(
        color: isLight ? Colors.white : AppColors.surfaceVariantDark,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Radii.l),
          side: const BorderSide(color: AppColors.outlineSubtle),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          padding: const EdgeInsets.symmetric(vertical: Insets.l, horizontal: Insets.xl),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Radii.m)),
          minimumSize: const Size.fromHeight(TouchTargets.primaryAction),
          textStyle: AppType.titleM.copyWith(color: scheme.onPrimary),
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: scheme.primary,
          side: BorderSide(color: scheme.primary, width: 1.5),
          minimumSize: const Size.fromHeight(TouchTargets.primaryAction),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Radii.m)),
          textStyle: AppType.titleM.copyWith(color: scheme.primary),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: scheme.primary,
          textStyle: AppType.titleM.copyWith(color: scheme.primary),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Radii.m),
          borderSide: const BorderSide(color: AppColors.outline),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Radii.m),
          borderSide: const BorderSide(color: AppColors.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Radii.m),
          borderSide: BorderSide(color: scheme.primary, width: 2),
        ),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: Insets.l, vertical: Insets.m),
        labelStyle: AppType.body.copyWith(color: AppColors.inkSoft),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.outlineSubtle,
        thickness: 1,
        space: 1,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surfaceVariant,
        labelStyle: AppType.caption.copyWith(color: AppColors.ink),
        side: BorderSide.none,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Radii.pill)),
      ),
    );
  }
}
