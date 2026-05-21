import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/typography.dart';

/// Brand mark + wordmark. Drawn with CustomPaint so we don't ship a
/// raster asset; pinned at 32 / 48 / 64 logical pixels.
class BrandLogo extends StatelessWidget {
  const BrandLogo({
    super.key,
    this.size = BrandLogoSize.m,
    this.showWordmark = true,
    this.compact = false,
  });

  final BrandLogoSize size;
  final bool showWordmark;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final markSize = switch (size) {
      BrandLogoSize.s => 24.0,
      BrandLogoSize.m => 32.0,
      BrandLogoSize.l => 48.0,
      BrandLogoSize.hero => 72.0,
    };

    final wordmarkStyle = switch (size) {
      BrandLogoSize.s => AppType.titleM,
      BrandLogoSize.m => AppType.titleL.copyWith(fontWeight: FontWeight.w700),
      BrandLogoSize.l => AppType.headlineM.copyWith(fontWeight: FontWeight.w700),
      BrandLogoSize.hero => AppType.headlineL.copyWith(fontWeight: FontWeight.w800),
    };

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          width: markSize,
          height: markSize,
          child: CustomPaint(painter: _BrandMarkPainter()),
        ),
        if (showWordmark) ...[
          SizedBox(width: markSize * 0.25),
          Text(compact ? 'Hangover' : 'Hangover Mobility', style: wordmarkStyle),
        ],
      ],
    );
  }
}

enum BrandLogoSize { s, m, l, hero }

class _BrandMarkPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final r = size.shortestSide / 2;
    final center = Offset(r, r);

    // Outer emerald disc.
    canvas.drawCircle(
      center,
      r,
      Paint()..color = AppColors.seed,
    );

    // Inner cream disc — leaves a 3px emerald ring.
    final innerR = r * 0.78;
    canvas.drawCircle(
      center,
      innerR,
      Paint()..color = AppColors.surface,
    );

    // Stylised "H" made of a scooter handlebar (left), a wheel-spoke axle
    // (centre), and a wheel arc (right).
    final stroke = Paint()
      ..color = AppColors.seed
      ..strokeWidth = r * 0.18
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;

    // Left vertical bar.
    canvas.drawLine(
      Offset(center.dx - innerR * 0.45, center.dy - innerR * 0.55),
      Offset(center.dx - innerR * 0.45, center.dy + innerR * 0.55),
      stroke,
    );

    // Right vertical bar.
    canvas.drawLine(
      Offset(center.dx + innerR * 0.45, center.dy - innerR * 0.55),
      Offset(center.dx + innerR * 0.45, center.dy + innerR * 0.55),
      stroke,
    );

    // Cross bar.
    canvas.drawLine(
      Offset(center.dx - innerR * 0.45, center.dy),
      Offset(center.dx + innerR * 0.45, center.dy),
      stroke,
    );

    // Tiny terracotta accent dot — the Tbilisi rooftop nod.
    canvas.drawCircle(
      Offset(center.dx + innerR * 0.45, center.dy + innerR * 0.55),
      r * 0.13,
      Paint()..color = AppColors.accent,
    );
  }

  @override
  bool shouldRepaint(_) => false;
}
