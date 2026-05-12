import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';

/// Coloured pill with an optional pulsing leading dot. Used for ride
/// status, driver-online indicator, "live" indicators in the admin
/// panel, etc.
class StatusPill extends StatelessWidget {
  const StatusPill({
    super.key,
    required this.label,
    this.tone = StatusTone.neutral,
    this.pulse = false,
  });

  final String label;
  final StatusTone tone;
  final bool pulse;

  @override
  Widget build(BuildContext context) {
    final palette = _palette(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: palette.bg,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (pulse)
            _PulseDot(color: palette.fg)
          else
            Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(color: palette.fg, shape: BoxShape.circle),
            ),
          const SizedBox(width: Insets.xs + 2),
          Text(
            label.toUpperCase(),
            style: AppType.label.copyWith(color: palette.fg, fontSize: 10),
          ),
        ],
      ),
    );
  }

  ({Color bg, Color fg}) _palette(StatusTone t) => switch (t) {
        StatusTone.success => (bg: const Color(0xFFDCEFE3), fg: const Color(0xFF1F6A48)),
        StatusTone.warning => (bg: const Color(0xFFFBEFD0), fg: const Color(0xFF7B5B14)),
        StatusTone.danger  => (bg: const Color(0xFFF7D8DD), fg: const Color(0xFF8E1827)),
        StatusTone.info    => (bg: const Color(0xFFD6E4F1), fg: const Color(0xFF1C3D5C)),
        StatusTone.accent  => (bg: const Color(0xFFFCE4D5), fg: AppColors.accent),
        StatusTone.neutral => (bg: AppColors.surfaceVariant, fg: AppColors.inkSoft),
      };
}

enum StatusTone { success, warning, danger, info, accent, neutral }

class _PulseDot extends StatefulWidget {
  const _PulseDot({required this.color});
  final Color color;

  @override
  State<_PulseDot> createState() => _PulseDotState();
}

class _PulseDotState extends State<_PulseDot> with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 10,
      height: 10,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (_, __) {
          final t = _controller.value;
          return CustomPaint(painter: _PulsePainter(t, widget.color));
        },
      ),
    );
  }
}

class _PulsePainter extends CustomPainter {
  _PulsePainter(this.t, this.color);
  final double t;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final c = Offset(size.width / 2, size.height / 2);
    final ringR = size.shortestSide / 2 * (1 + t * 2);
    final ringPaint = Paint()
      ..color = color.withValues(alpha: (1 - t).clamp(0, 1) * 0.6)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(c, ringR, ringPaint);

    canvas.drawCircle(c, size.shortestSide / 2 * 0.5, Paint()..color = color);
  }

  @override
  bool shouldRepaint(_PulsePainter old) => old.t != t || old.color != color;
}
