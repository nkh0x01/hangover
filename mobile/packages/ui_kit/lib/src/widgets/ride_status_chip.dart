import 'package:flutter/material.dart';

import '../theme/colors.dart';
import '../theme/insets.dart';
import '../theme/typography.dart';

/// Visual progress bar for the customer's ride lifecycle:
///
///   searching → driver assigned → arriving → on trip → completed
///
/// Renders as a row of 5 connected segments; the current segment is
/// filled emerald + pulses, prior segments are filled, future are
/// outlined. Used in the ride-tracking sheet to give the customer a
/// sense of where in the flow they are without reading any copy.
class RideStatusChip extends StatelessWidget {
  const RideStatusChip({
    super.key,
    required this.phase,
    this.compact = false,
  });

  final RidePhase phase;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final segments = RidePhase.values.where((p) => p != RidePhase.cancelled && p != RidePhase.completed).toList();
    return Row(
      children: [
        for (final p in segments) ...[
          Expanded(child: _Segment(active: p.index <= phase.index)),
          if (p != segments.last) const SizedBox(width: 3),
        ],
      ],
    );
  }
}

enum RidePhase { searching, assigned, arriving, onTrip, completed, cancelled }

class _Segment extends StatelessWidget {
  const _Segment({required this.active});
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 4,
      decoration: BoxDecoration(
        color: active ? AppColors.seed : AppColors.outlineSubtle,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
    );
  }
}

/// Bigger label-style chip used on the driver side ("HEADING TO PICKUP",
/// "ARRIVING", etc).
class RidePhaseLabel extends StatelessWidget {
  const RidePhaseLabel({super.key, required this.label, this.tone = RidePhaseLabelTone.primary});

  final String label;
  final RidePhaseLabelTone tone;

  @override
  Widget build(BuildContext context) {
    final colors = switch (tone) {
      RidePhaseLabelTone.primary => (bg: AppColors.seed, fg: Colors.white),
      RidePhaseLabelTone.accent  => (bg: AppColors.accent, fg: Colors.white),
      RidePhaseLabelTone.dark    => (bg: AppColors.ink, fg: Colors.white),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Insets.m, vertical: Insets.s),
      decoration: BoxDecoration(
        color: colors.bg,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Text(label, style: AppType.label.copyWith(color: colors.fg, fontSize: 11)),
    );
  }
}

enum RidePhaseLabelTone { primary, accent, dark }
