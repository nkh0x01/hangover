import 'package:flutter/material.dart';

/// Brand colour tokens. Phase 1.6 extends Phase 0's emerald with a warm
/// terracotta accent (a nod to Tbilisi rooftops) and replaces the cold
/// `#FAFAFA` surface with a slightly warm cream so the UI stops reading
/// "default Material" and starts reading "local product".
///
/// Hex values are referenced from screen previews under
/// `docs/screenshots/source/_shared.css`; the two stay in sync.
class AppColors {
  // ---- Brand ------------------------------------------------------------

  /// Primary seed colour — unchanged since Phase 0.
  static const seed = Color(0xFF1F8F60);

  /// Phase 1.6 accent. Used for secondary actions, "live" pulses, the
  /// today-progress arc on the driver app, and surge-pricing chips.
  static const accent = Color(0xFFE07A3C);

  /// Deep ink for headlines on light surfaces. Higher contrast than the
  /// default `onSurface` Material derives for our seed.
  static const ink = Color(0xFF1A2421);

  /// Softer ink for body copy.
  static const inkSoft = Color(0xFF515955);

  /// Captions, helper text, disabled labels.
  static const inkMuted = Color(0xFF8B928D);

  // ---- Surfaces ---------------------------------------------------------

  /// Warm cream — replaces the Phase 0 `#FAFAFA`.
  static const surface = Color(0xFFFBF8F2);

  /// Tinted version for cards-on-cards.
  static const surfaceVariant = Color(0xFFEFEAE0);

  static const surfaceDark = Color(0xFF13171A);
  static const surfaceVariantDark = Color(0xFF1B2024);

  static const outline = Color(0xFFD6D1C5);
  static const outlineSubtle = Color(0xFFE9E4D6);

  // ---- Semantic ---------------------------------------------------------

  static const danger = Color(0xFFD7263D);
  static const warning = Color(0xFFE6B23A);
  static const success = Color(0xFF2EA265);
  static const info = Color(0xFF2C7BE5);
}
