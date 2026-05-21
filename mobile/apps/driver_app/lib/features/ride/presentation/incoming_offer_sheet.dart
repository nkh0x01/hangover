import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../shift/state/shift_controller.dart';

/// Driver-facing urgent offer modal — Phase 1.6 v2.
///
/// The sheet pulses an emerald glow around its edge (urgency cue), the
/// countdown is a real ring with a numerical center, the fare card is
/// a gradient hero, and the Accept button is a tall gradient CTA that
/// shouts "primary action" without needing a colour-blind safety net.
/// Reject is a quiet outline.
class IncomingOfferSheet extends ConsumerStatefulWidget {
  const IncomingOfferSheet({super.key, required this.offer});

  final RideOfferPayload offer;

  @override
  ConsumerState<IncomingOfferSheet> createState() => _IncomingOfferSheetState();
}

class _IncomingOfferSheetState extends ConsumerState<IncomingOfferSheet>
    with TickerProviderStateMixin {
  Timer? _ticker;
  Duration _remaining = Duration.zero;
  late final AnimationController _glow;

  @override
  void initState() {
    super.initState();
    _glow = AnimationController(vsync: this, duration: AppMotion.breathe)..repeat(reverse: true);
    _remaining = widget.offer.expiresAt.difference(DateTime.now());
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      final r = widget.offer.expiresAt.difference(DateTime.now());
      if (r.isNegative) {
        _ticker?.cancel();
        ref.read(shiftProvider.notifier).rejectOffer(widget.offer.rideId);
        return;
      }
      setState(() => _remaining = r);
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _glow.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final offer = widget.offer;
    final progress = (_remaining.inMilliseconds / 12000).clamp(0.0, 1.0);

    return Container(
      color: Colors.black.withValues(alpha: 0.7),
      alignment: Alignment.bottomCenter,
      child: AnimatedBuilder(
        animation: _glow,
        builder: (_, child) {
          final intensity = 0.6 + 0.4 * _glow.value;
          return Container(
            decoration: BoxDecoration(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(Radii.xl)),
              boxShadow: [
                BoxShadow(
                  color: AppColors.seed.withValues(alpha: 0.45 * intensity),
                  blurRadius: 40 * intensity,
                  spreadRadius: 4 * intensity,
                  offset: const Offset(0, -8),
                ),
              ],
            ),
            child: child!,
          );
        },
        child: BottomSheetCard(
          padding: const EdgeInsets.fromLTRB(Insets.xl, Insets.xl, Insets.xl, Insets.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const StatusPill(label: 'New ride', tone: StatusTone.accent, pulse: true),
                  const Spacer(),
                  _CountdownRing(progress: progress, remaining: _remaining),
                ],
              ),
              const SizedBox(height: Insets.l),

              Text('Accept this ride', style: AppType.headlineM),
              const SizedBox(height: Insets.l),

              FareHeroCard(
                amount: offer.fareAmount,
                currency: offer.currency,
                distanceKm: 2.7,
                durationMin: 7,
                subtitle: '★ 4.92 customer',
              ),

              const SizedBox(height: Insets.l),

              _OfferRow(
                icon: Icons.my_location_rounded,
                iconColor: AppColors.seed,
                title: offer.pickupAddress,
                subtitle: '~${offer.distanceToPickupM} m to pickup · 2 min',
              ),
              const SizedBox(height: Insets.s),
              _OfferRow(
                icon: Icons.flag_rounded,
                iconColor: AppColors.danger,
                title: offer.dropoffAddress,
                subtitle: '2.7 km trip · ~7 min',
              ),

              const SizedBox(height: Insets.xl),

              GradientButton(
                label: 'Accept ride',
                leading: const Icon(Icons.check_rounded, color: Colors.white, size: 20),
                onPressed: () => ref.read(shiftProvider.notifier).acceptOffer(offer.rideId),
              ),
              const SizedBox(height: Insets.s),
              SecondaryButton(
                label: 'Reject',
                color: AppColors.inkSoft,
                onPressed: () => ref.read(shiftProvider.notifier).rejectOffer(offer.rideId),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CountdownRing extends StatelessWidget {
  const _CountdownRing({required this.progress, required this.remaining});
  final double progress;
  final Duration remaining;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 56,
      height: 56,
      child: Stack(
        alignment: Alignment.center,
        children: [
          SizedBox.expand(
            child: CircularProgressIndicator(
              value: progress,
              backgroundColor: AppColors.outlineSubtle,
              valueColor: const AlwaysStoppedAnimation(AppColors.seed),
              strokeWidth: 5,
            ),
          ),
          Text('${remaining.inSeconds}s',
              style: AppType.bodyStrong.copyWith(color: AppColors.seed)),
        ],
      ),
    );
  }
}

class _OfferRow extends StatelessWidget {
  const _OfferRow({required this.icon, required this.iconColor, required this.title, required this.subtitle});
  final IconData icon;
  final Color iconColor;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: iconColor.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          alignment: Alignment.center,
          child: Icon(icon, size: 16, color: iconColor),
        ),
        const SizedBox(width: Insets.m),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: AppType.titleM),
              const SizedBox(height: 2),
              Text(subtitle, style: AppType.body),
            ],
          ),
        ),
      ],
    );
  }
}
