/// Spacing scale. Phase 1.6 adds `xxxl` for hero gutters used by the
/// driver app's primary action sheets (where one-handed taps need more
/// vertical breathing room).
class Insets {
  static const xxs = 2.0;
  static const xs = 4.0;
  static const s = 8.0;
  static const m = 12.0;
  static const l = 16.0;
  static const xl = 24.0;
  static const xxl = 32.0;
  static const xxxl = 48.0;
}

class Radii {
  static const xs = 6.0;
  static const s = 8.0;
  static const m = 12.0;
  static const l = 16.0;

  /// Used by hero cards (fare summary, ride-completed) and the bottom
  /// sheets that lift over the map.
  static const xl = 24.0;

  /// Pill / capsule radius.
  static const pill = 999.0;
}

/// Recommended target sizes — these match Material guidelines plus a
/// slightly larger primary action size for one-handed driver use.
class TouchTargets {
  static const minTap = 44.0;
  static const primaryAction = 60.0;
  static const fab = 56.0;
}

/// Motion durations used by ride-status transitions and hero card morphs.
class Motion {
  static const fast = Duration(milliseconds: 150);
  static const med = Duration(milliseconds: 250);
  static const slow = Duration(milliseconds: 400);
}
