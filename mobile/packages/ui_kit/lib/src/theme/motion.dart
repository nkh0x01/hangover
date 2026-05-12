import 'package:flutter/animation.dart';

/// Motion + haptics tokens for Phase 1.6 v2 premium polish.
///
/// Durations live alongside the [AppCurves] so every animated widget
/// in the kit looks like it came from the same studio.
class AppMotion {
  // Durations
  static const xFast = Duration(milliseconds: 100);
  static const fast = Duration(milliseconds: 180);
  static const med = Duration(milliseconds: 260);
  static const slow = Duration(milliseconds: 420);

  // Long-running loops
  static const pulse = Duration(milliseconds: 1400);
  static const shimmer = Duration(milliseconds: 1100);
  static const breathe = Duration(milliseconds: 2400);
}

class AppCurves {
  /// Default outgoing easing — used on entering UI.
  static const enter = Curves.easeOutCubic;

  /// Default incoming easing — used on dismissals.
  static const exit = Curves.easeInCubic;

  /// Status / phase transitions (state machine morphs).
  static const status = Curves.easeInOutCubic;

  /// Spring-y "popped" feel — sheets, success states.
  static const pop = Curves.easeOutBack;
}

/// Names for haptic patterns. The kit doesn't bind them to a specific
/// engine — apps wire the actual `HapticFeedback.*` calls so we can
/// swap to `gtk_haptics` / vendor SDKs later without ripping out
/// the call sites.
enum HapticPattern {
  /// Small tap — primary CTA press.
  tap,

  /// Selection — chip / payment selector / segmented control.
  selection,

  /// Light impact — driver offer arrival.
  light,

  /// Medium impact — phase transition (driver arrived, trip started).
  medium,

  /// Heavy impact — match confirmed, ride complete, SOS triggered.
  heavy,

  /// Warning pattern — destructive confirmation, expired offer.
  warning,
}
