import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../shift/state/shift_controller.dart';

/// Bottom sheet that adapts to the driver's current phase of the active
/// ride: heading to pickup → arriving → arrived → in progress.
///
/// Phase 1.6: phase chip at the top reflects the driver's progress;
/// primary CTA scales up to a full-bleed 60-tap target; secondary
/// chat / call icons stay accessible without crowding the action.
class ActiveRideSheet extends ConsumerWidget {
  const ActiveRideSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ride = ref.watch(shiftProvider).activeRide;
    if (ride == null) return const SizedBox.shrink();

    return BottomSheetCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: _content(context, ref, ride),
      ),
    );
  }

  List<Widget> _content(BuildContext context, WidgetRef ref, Ride ride) {
    final ctl = ref.read(shiftProvider.notifier);
    final phaseLabel = switch (ride.status) {
      RideStatus.accepted => 'Heading to pickup',
      RideStatus.driverArriving => 'Arriving at pickup',
      RideStatus.driverArrived => 'Waiting for customer',
      RideStatus.inProgress => 'On the trip',
      _ => 'Active',
    };

    final primary = switch (ride.status) {
      RideStatus.accepted => _Primary("I'm on the way", ctl.arriving),
      RideStatus.driverArriving => _Primary('Arrived at pickup', ctl.arrived),
      RideStatus.driverArrived => _Primary('Start the trip', ctl.start),
      RideStatus.inProgress => _Primary('Complete trip', ctl.complete),
      _ => null,
    };

    final address = ride.status == RideStatus.inProgress
        ? ride.dropoffAddress
        : ride.pickupAddress;
    final addressLabel = ride.status == RideStatus.inProgress ? 'Dropoff' : 'Pickup';

    return [
      Row(
        children: [
          RidePhaseLabel(label: phaseLabel),
          const Spacer(),
          Text('7.50 GEL · cash', style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
      const SizedBox(height: Insets.m),
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            ride.status == RideStatus.inProgress ? Icons.flag_rounded : Icons.circle,
            color: ride.status == RideStatus.inProgress ? AppColors.danger : AppColors.seed,
            size: 18,
          ),
          const SizedBox(width: Insets.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(addressLabel, style: Theme.of(context).textTheme.labelMedium),
                Text(address, style: Theme.of(context).textTheme.titleLarge),
              ],
            ),
          ),
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.surfaceVariant,
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: const Icon(Icons.chat_bubble_outline_rounded, size: 18, color: AppColors.ink),
          ),
          const SizedBox(width: Insets.s),
          Container(
            width: 44,
            height: 44,
            decoration: const BoxDecoration(color: AppColors.seed, shape: BoxShape.circle),
            alignment: Alignment.center,
            child: const Icon(Icons.phone_rounded, size: 18, color: Colors.white),
          ),
        ],
      ),
      const SizedBox(height: Insets.l),
      if (primary != null)
        PrimaryButton(label: primary.label, onPressed: primary.onTap),
    ];
  }
}

class _Primary {
  _Primary(this.label, this.onTap);
  final String label;
  final VoidCallback onTap;
}
