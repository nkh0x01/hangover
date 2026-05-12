import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../shift/state/shift_controller.dart';

/// Full-screen takeover when an offer comes in. Phase 1.6 makes Accept
/// dominant, Reject visually quieter, and surfaces the customer rating
/// + fare more prominently. The countdown is a real visual ring, not
/// just a number — makes the urgency obvious without copy.
class IncomingOfferSheet extends ConsumerStatefulWidget {
  const IncomingOfferSheet({super.key, required this.offer});

  final RideOfferPayload offer;

  @override
  ConsumerState<IncomingOfferSheet> createState() => _IncomingOfferSheetState();
}

class _IncomingOfferSheetState extends ConsumerState<IncomingOfferSheet>
    with SingleTickerProviderStateMixin {
  Timer? _ticker;
  Duration _remaining = Duration.zero;
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))
      ..repeat();
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
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final offer = widget.offer;
    final progress = (_remaining.inMilliseconds / 12000).clamp(0.0, 1.0);

    return Container(
      color: Colors.black.withValues(alpha: 0.6),
      alignment: Alignment.bottomCenter,
      child: BottomSheetCard(
        padding: const EdgeInsets.fromLTRB(Insets.xl, Insets.xl, Insets.xl, Insets.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Text('New ride', style: Theme.of(context).textTheme.headlineMedium),
                const Spacer(),
                _CountdownRing(progress: progress, remaining: _remaining),
              ],
            ),
            const SizedBox(height: Insets.l),

            // Pickup / dropoff list
            _OfferRow(
              icon: Icons.circle,
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

            const SizedBox(height: Insets.l),

            // Fare hero
            Container(
              padding: const EdgeInsets.all(Insets.l),
              decoration: BoxDecoration(
                color: AppColors.surfaceVariant,
                borderRadius: BorderRadius.circular(Radii.l),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Fare', style: Theme.of(context).textTheme.labelMedium),
                        const SizedBox(height: 2),
                        Text(
                          '${offer.fareAmount.toStringAsFixed(2)} ${offer.currency}',
                          style: Theme.of(context).textTheme.headlineMedium,
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text('Customer', style: Theme.of(context).textTheme.labelMedium),
                      const SizedBox(height: 2),
                      Row(
                        children: const [
                          Icon(Icons.star_rounded, size: 16, color: AppColors.warning),
                          SizedBox(width: 4),
                          Text('4.92', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: Insets.l),

            // Actions — Accept dominant, Reject secondary
            PrimaryButton(
              label: 'Accept ride',
              leading: const Icon(Icons.check_rounded, color: Colors.white),
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
          Text(
            '${remaining.inSeconds}s',
            style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.seed),
          ),
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
              Text(title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 2),
              Text(subtitle, style: Theme.of(context).textTheme.bodyMedium),
            ],
          ),
        ),
      ],
    );
  }
}
