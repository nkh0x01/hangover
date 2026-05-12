import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../shift/state/shift_controller.dart';

/// Bottom sheet that adapts to the driver's current phase of the active
/// ride: heading to pickup → arriving → arrived → in progress.
class ActiveRideSheet extends ConsumerWidget {
  const ActiveRideSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ride = ref.watch(shiftProvider).activeRide;
    if (ride == null) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.all(Insets.l),
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(Radii.l),
        boxShadow: const [BoxShadow(blurRadius: 12, color: Colors.black26)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: _content(context, ref, ride),
      ),
    );
  }

  List<Widget> _content(BuildContext context, WidgetRef ref, Ride ride) {
    final ctl = ref.read(shiftProvider.notifier);

    return switch (ride.status) {
      RideStatus.accepted => [
          _Header(title: 'Heading to pickup', subtitle: ride.pickupAddress),
          const SizedBox(height: Insets.l),
          PrimaryButton(label: "I'm on the way", onPressed: ctl.arriving),
        ],
      RideStatus.driverArriving => [
          _Header(title: 'Arriving at pickup', subtitle: ride.pickupAddress),
          const SizedBox(height: Insets.l),
          PrimaryButton(label: 'Mark arrived', onPressed: ctl.arrived),
        ],
      RideStatus.driverArrived => [
          _Header(title: 'Customer pickup', subtitle: ride.pickupAddress),
          const SizedBox(height: Insets.l),
          PrimaryButton(label: 'Start trip', onPressed: ctl.start),
        ],
      RideStatus.inProgress => [
          _Header(title: 'Trip in progress', subtitle: 'Dropoff: ${ride.dropoffAddress}'),
          const SizedBox(height: Insets.l),
          PrimaryButton(label: 'Complete trip', onPressed: ctl.complete),
        ],
      _ => [const SizedBox.shrink()],
    };
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: Insets.xs),
        Text(subtitle),
      ],
    );
  }
}
