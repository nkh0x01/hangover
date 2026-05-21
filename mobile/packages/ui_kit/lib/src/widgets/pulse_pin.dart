import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/motion.dart';

/// Animated map pin. Three modes:
///  - pickup (emerald, two pulse rings)
///  - dropoff (terracotta teardrop)
///  - driver (dark disc with scooter emoji + halo)
///
/// The pulse rings use [AppMotion.pulse] and fade out as they expand,
/// matching the pattern in [StatusPill.live].
class PulsePin extends StatefulWidget {
  const PulsePin({
    super.key,
    this.kind = PinKind.pickup,
    this.size = 22,
    this.pulse = true,
    this.label,
  });

  final PinKind kind;
  final double size;
  final bool pulse;
  final String? label;

  @override
  State<PulsePin> createState() => _PulsePinState();
}

enum PinKind { pickup, dropoff, driver }

class _PulsePinState extends State<PulsePin> with SingleTickerProviderStateMixin {
  late final AnimationController _ctl;

  @override
  void initState() {
    super.initState();
    _ctl = AnimationController(vsync: this, duration: AppMotion.pulse);
    if (widget.pulse) _ctl.repeat();
  }

  @override
  void dispose() {
    _ctl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = switch (widget.kind) {
      PinKind.pickup => AppColors.seed,
      PinKind.dropoff => AppColors.danger,
      PinKind.driver => AppColors.ink,
    };

    return AnimatedBuilder(
      animation: _ctl,
      builder: (_, __) {
        final t = _ctl.value;
        return SizedBox(
          width: widget.size * 3,
          height: widget.size * 3,
          child: CustomPaint(
            painter: _PulsePainter(
              t: widget.pulse ? t : 0,
              core: color,
              size: widget.size,
              kind: widget.kind,
            ),
          ),
        );
      },
    );
  }
}

class _PulsePainter extends CustomPainter {
  _PulsePainter({required this.t, required this.core, required this.size, required this.kind});
  final double t;
  final Color core;
  final double size;
  final PinKind kind;

  @override
  void paint(Canvas canvas, Size avail) {
    final c = Offset(avail.width / 2, avail.height / 2);

    if (t > 0) {
      for (final phase in [0.0, 0.5]) {
        final p = (t + phase) % 1.0;
        final r = size / 2 + (size * 1.5) * p;
        final a = (1 - p).clamp(0.0, 1.0) * 0.45;
        canvas.drawCircle(c, r, Paint()..color = core.withValues(alpha: a));
      }
    }

    final paint = Paint()..color = core;
    switch (kind) {
      case PinKind.pickup:
        canvas.drawCircle(c, size / 2 + 3, Paint()..color = Colors.white);
        canvas.drawCircle(c, size / 2, paint);
        break;
      case PinKind.dropoff:
        // Tear drop using rotated round-square.
        canvas.save();
        canvas.translate(c.dx, c.dy);
        canvas.rotate(-0.785398); // -45°
        final rect = RRect.fromRectAndCorners(
          Rect.fromCenter(center: Offset.zero, width: size, height: size),
          topLeft: const Radius.circular(2),
          topRight: Radius.circular(size / 2),
          bottomLeft: Radius.circular(size / 2),
          bottomRight: Radius.circular(size / 2),
        );
        canvas.drawRRect(rect, paint);
        canvas.restore();
        canvas.drawCircle(c, size * 0.18, Paint()..color = Colors.white);
        break;
      case PinKind.driver:
        canvas.drawCircle(c, size / 2 + 3, Paint()..color = Colors.white);
        canvas.drawCircle(c, size / 2, paint);
        // Scooter icon-ish: terracotta dot.
        canvas.drawCircle(c, size * 0.22, Paint()..color = AppColors.accent);
        break;
    }
  }

  @override
  bool shouldRepaint(_PulsePainter old) =>
      old.t != t || old.core != core || old.size != size || old.kind != kind;
}
