import 'package:flutter/material.dart';

/// Brand colour tokens.
///
/// Phase 1.6 v1 added the terracotta accent + warm cream surface +
/// ink ramp on top of the original emerald.
///
/// Phase 1.6 v2 (premium polish) adds:
///  - hero gradient stops for primary CTAs and fare cards
///  - glass overlay tones for floating cards on top of the map
///  - shadow tokens (premium tactile elevation)
///  - urgency-red for driver offer pulse borders
class AppColors {
  // ---- Brand ----------------------------------------------------------
  static const seed = Color(0xFF1F8F60);

  /// Slightly deeper shade used as the lower stop in primary gradients.
  static const seedDeep = Color(0xFF155139);

  /// Higher shade for a glow / sheen on hero cards.
  static const seedGlow = Color(0xFF2EC07F);

  static const accent = Color(0xFFE07A3C);
  static const accentDeep = Color(0xFFB85B1F);

  /// Used by the driver "urgent offer" pulse border and SOS surfaces.
  static const urgent = Color(0xFFFF4D5E);

  // ---- Text ramp ------------------------------------------------------
  static const ink = Color(0xFF1A2421);
  static const inkSoft = Color(0xFF515955);
  static const inkMuted = Color(0xFF8B928D);
  static const inkOnDark = Color(0xFFF4F2EB);

  // ---- Surfaces -------------------------------------------------------
  static const surface = Color(0xFFFBF8F2);
  static const surfaceVariant = Color(0xFFEFEAE0);

  /// 5 % black laid over the surface — used by glass-card backings so
  /// floating elements still read as elevated even on bright maps.
  static const surfaceTint = Color(0x0D141E1B);

  static const surfaceDark = Color(0xFF0E1518);
  static const surfaceVariantDark = Color(0xFF181F22);

  static const outline = Color(0xFFD6D1C5);
  static const outlineSubtle = Color(0xFFE9E4D6);

  // ---- Semantic -------------------------------------------------------
  static const danger = Color(0xFFD7263D);
  static const warning = Color(0xFFE6B23A);
  static const success = Color(0xFF2EA265);
  static const info = Color(0xFF2C7BE5);
}

/// Hero gradients. Phase 1.6 v2 — premium polish.
class AppGradients {
  /// Primary CTA / fare hero / splash backdrop.
  static const primary = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [AppColors.seedGlow, AppColors.seed, AppColors.seedDeep],
    stops: [0.0, 0.55, 1.0],
  );

  /// Accent gradient — for "today's earnings" tile, surge badges,
  /// limited-time promo CTAs.
  static const accent = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFFFB079), AppColors.accent, AppColors.accentDeep],
  );

  /// Dark hero — driver app earnings card, admin sidebar accents.
  static const ink = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF252E2B), AppColors.ink, Color(0xFF0B100E)],
  );

  /// Subtle warm surface gradient — used as the page background under
  /// the splash and auth screens.
  static const surface = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [Color(0xFFFFFDF7), AppColors.surface, Color(0xFFF1EBDE)],
  );
}

/// Reusable shadow tokens. Material's elevation system produces flat
/// outlines that feel cheap; ours adds a tinted second shadow so cards
/// feel embedded in the surface, not stuck on top.
class AppShadows {
  static const card = <BoxShadow>[
    BoxShadow(blurRadius: 24, offset: Offset(0, 4), color: Color(0x16143018)),
    BoxShadow(blurRadius: 6, offset: Offset(0, 2), color: Color(0x0F143018)),
  ];

  static const sheet = <BoxShadow>[
    BoxShadow(blurRadius: 36, offset: Offset(0, -8), color: Color(0x22141E1B)),
    BoxShadow(blurRadius: 12, offset: Offset(0, -2), color: Color(0x0F141E1B)),
  ];

  static const fab = <BoxShadow>[
    BoxShadow(blurRadius: 18, offset: Offset(0, 6), color: Color(0x2A143018)),
    BoxShadow(blurRadius: 6, offset: Offset(0, 2), color: Color(0x14143018)),
  ];

  static const heroGreen = <BoxShadow>[
    BoxShadow(blurRadius: 28, offset: Offset(0, 12), color: Color(0x4D1F8F60)),
  ];

  static const heroAccent = <BoxShadow>[
    BoxShadow(blurRadius: 28, offset: Offset(0, 12), color: Color(0x4DE07A3C)),
  ];
}
