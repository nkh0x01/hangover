import 'dart:ui';

import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';

/// Frosted-glass card. Used by the "Where to?" pill, the active-ride
/// ETA banner, and any element that should float over the map without
/// stealing focus.
///
/// Falls back to a solid card on platforms where backdrop filter is a
/// no-op (older Android, web). The fallback colour is tuned to read
/// like the blur result against the [AppGradients.surface] background.
class GlassCard extends StatelessWidget {
  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(Insets.l),
    this.radius = Radii.l,
    this.tint = const Color(0xCCFFFFFF),
    this.borderColor = const Color(0x66FFFFFF),
    this.blur = 18,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;
  final Color tint;
  final Color borderColor;
  final double blur;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            color: tint,
            borderRadius: BorderRadius.circular(radius),
            border: Border.all(color: borderColor, width: 1),
            boxShadow: AppShadows.card,
          ),
          child: child,
        ),
      ),
    );
  }
}
