import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';

/// Premium fare display used on the confirm-ride screen and on the
/// driver's incoming-offer surface. Renders the fare on a gradient
/// hero background with a subtle "shine" decoration.
class FareHeroCard extends StatelessWidget {
  const FareHeroCard({
    super.key,
    required this.amount,
    required this.currency,
    this.distanceKm,
    this.durationMin,
    this.surgeMultiplier = 1.0,
    this.subtitle,
  });

  final double amount;
  final String currency;
  final double? distanceKm;
  final int? durationMin;
  final double surgeMultiplier;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final isSurging = surgeMultiplier > 1.0;
    return Container(
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        gradient: AppGradients.primary,
        borderRadius: BorderRadius.circular(Radii.l),
        boxShadow: AppShadows.heroGreen,
      ),
      child: Stack(
        children: [
          // Decorative scooter wheel.
          Positioned(
            right: -20,
            top: -20,
            child: _DecorRing(size: 120, opacity: 0.10),
          ),
          Positioned(
            right: 30,
            bottom: -40,
            child: _DecorRing(size: 80, opacity: 0.06),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    'Estimated fare',
                    style: AppType.label.copyWith(color: Colors.white.withValues(alpha: 0.75)),
                  ),
                  const Spacer(),
                  if (isSurging) _SurgeBadge(multiplier: surgeMultiplier),
                ],
              ),
              const SizedBox(height: Insets.s),
              RichText(
                text: TextSpan(
                  style: AppType.display.copyWith(
                    color: Colors.white,
                    fontSize: 44,
                    fontWeight: FontWeight.w800,
                  ),
                  children: [
                    TextSpan(text: amount.toStringAsFixed(2)),
                    TextSpan(
                      text: '  $currency',
                      style: AppType.titleL.copyWith(
                        color: Colors.white.withValues(alpha: 0.7),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: Insets.s),
              Row(
                children: [
                  if (durationMin != null)
                    _HeroMeta(icon: Icons.schedule_rounded, label: '$durationMin min'),
                  if (durationMin != null && distanceKm != null) const SizedBox(width: Insets.m),
                  if (distanceKm != null)
                    _HeroMeta(icon: Icons.route_rounded, label: '${distanceKm!.toStringAsFixed(1)} km'),
                  const Spacer(),
                  if (subtitle != null)
                    Text(
                      subtitle!,
                      style: AppType.caption.copyWith(color: Colors.white.withValues(alpha: 0.8)),
                    ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SurgeBadge extends StatelessWidget {
  const _SurgeBadge({required this.multiplier});
  final double multiplier;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Insets.s, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(Radii.pill),
        border: Border.all(color: Colors.white.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.bolt_rounded, color: Colors.white, size: 12),
          const SizedBox(width: 3),
          Text(
            '×${multiplier.toStringAsFixed(1)} surge',
            style: AppType.label.copyWith(color: Colors.white, fontSize: 10),
          ),
        ],
      ),
    );
  }
}

class _HeroMeta extends StatelessWidget {
  const _HeroMeta({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 14, color: Colors.white.withValues(alpha: 0.85)),
        const SizedBox(width: 4),
        Text(label, style: AppType.bodyStrong.copyWith(color: Colors.white)),
      ],
    );
  }
}

class _DecorRing extends StatelessWidget {
  const _DecorRing({required this.size, required this.opacity});
  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white.withValues(alpha: opacity * 3), width: 4),
        gradient: RadialGradient(
          colors: [
            Colors.white.withValues(alpha: opacity),
            Colors.white.withValues(alpha: 0),
          ],
        ),
      ),
    );
  }
}
