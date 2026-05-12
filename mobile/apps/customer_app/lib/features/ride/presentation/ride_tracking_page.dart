import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../state/ride_flow_controller.dart';

/// One screen, four phases — searching, driver assigned, in progress,
/// completed. Keeping it one screen means transitions feel native (the
/// bottom sheet morphs instead of cross-fading routes).
class RideTrackingPage extends ConsumerStatefulWidget {
  const RideTrackingPage({super.key, required this.rideId});

  final String rideId;

  @override
  ConsumerState<RideTrackingPage> createState() => _RideTrackingPageState();
}

class _RideTrackingPageState extends ConsumerState<RideTrackingPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(rideFlowProvider.notifier).attachExisting(widget.rideId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final flow = ref.watch(rideFlowProvider);
    final ride = flow.activeRide;
    final mapProvider = ref.watch(mapProviderProvider);

    if (ride == null) {
      return const Scaffold(body: LoadingState(message: 'Loading ride…'));
    }

    final markers = <MapMarker>[
      MapMarker(id: 'pickup', position: ride.pickup, title: 'Pickup'),
      MapMarker(id: 'dropoff', position: ride.dropoff, title: 'Dropoff'),
      if (flow.driverLocation != null)
        MapMarker(id: 'driver', position: flow.driverLocation!, title: 'Driver'),
    ];

    return Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            Positioned.fill(
              child: mapProvider.mapWidget(initialCenter: ride.pickup, markers: markers),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: _RideSheet(ride: ride, onClose: () {
                ref.read(rideFlowProvider.notifier).reset();
                context.go('/home');
              }),
            ),
          ],
        ),
      ),
    );
  }
}

class _RideSheet extends ConsumerWidget {
  const _RideSheet({required this.ride, required this.onClose});

  final Ride ride;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Container(
      margin: const EdgeInsets.all(Insets.l),
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(Radii.l),
        boxShadow: const [BoxShadow(blurRadius: 12, color: Colors.black26)],
      ),
      child: _buildContent(context, ref),
    );
  }

  Widget _buildContent(BuildContext context, WidgetRef ref) {
    return switch (ride.status) {
      RideStatus.requested || RideStatus.searching || RideStatus.offered => _SearchingContent(
          onCancel: () => ref.read(rideFlowProvider.notifier).cancelActive(),
        ),
      RideStatus.accepted || RideStatus.driverArriving => _DriverAssignedContent(
          ride: ride,
          onCancel: () => ref.read(rideFlowProvider.notifier).cancelActive(),
        ),
      RideStatus.driverArrived => _PickupReadyContent(ride: ride),
      RideStatus.inProgress => _InProgressContent(ride: ride),
      RideStatus.completed => _CompletedContent(ride: ride, onClose: onClose),
      RideStatus.cancelled => _CancelledContent(ride: ride, onClose: onClose),
      RideStatus.noDrivers || RideStatus.failed => _FailureContent(ride: ride, onClose: onClose),
    };
  }
}

class _SearchingContent extends StatelessWidget {
  const _SearchingContent({required this.onCancel});

  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2)),
            const SizedBox(width: Insets.m),
            Text('Looking for a driver…', style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
        const SizedBox(height: Insets.s),
        const Text('We are checking nearby scooters.'),
        const SizedBox(height: Insets.l),
        TextButton(onPressed: onCancel, child: const Text('Cancel request')),
      ],
    );
  }
}

class _DriverAssignedContent extends StatelessWidget {
  const _DriverAssignedContent({required this.ride, required this.onCancel});

  final Ride ride;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final driver = ride.driver;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          ride.status == RideStatus.accepted ? 'Driver accepted' : 'Driver on the way',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: Insets.s),
        if (driver != null) ...[
          Text(driver.name ?? 'Driver'),
          if (driver.vehicle != null)
            Text('${driver.vehicle!.brand} ${driver.vehicle!.model} · ${driver.vehicle!.plate}'),
          Text('★ ${driver.ratingAvg.toStringAsFixed(2)}'),
        ],
        const SizedBox(height: Insets.l),
        TextButton(onPressed: onCancel, child: const Text('Cancel ride')),
      ],
    );
  }
}

class _PickupReadyContent extends StatelessWidget {
  const _PickupReadyContent({required this.ride});

  final Ride ride;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.electric_scooter, size: 40),
        Text('Driver has arrived', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: Insets.s),
        if (ride.driver?.vehicle != null) Text(ride.driver!.vehicle!.plate),
      ],
    );
  }
}

class _InProgressContent extends StatelessWidget {
  const _InProgressContent({required this.ride});

  final Ride ride;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('Trip in progress', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: Insets.s),
        Text('Heading to ${ride.dropoffAddress}'),
      ],
    );
  }
}

class _CompletedContent extends StatelessWidget {
  const _CompletedContent({required this.ride, required this.onClose});

  final Ride ride;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final amount = ride.finalAmount ?? ride.quotedAmount;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('Trip completed', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: Insets.s),
        Text('${amount.toStringAsFixed(2)} ${ride.currency}'),
        const SizedBox(height: Insets.l),
        PrimaryButton(label: 'Done', onPressed: onClose),
      ],
    );
  }
}

class _CancelledContent extends StatelessWidget {
  const _CancelledContent({required this.ride, required this.onClose});

  final Ride ride;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('Ride cancelled', style: Theme.of(context).textTheme.titleMedium),
        if (ride.cancellationReason != null) Text('Reason: ${ride.cancellationReason}'),
        const SizedBox(height: Insets.l),
        PrimaryButton(label: 'Back to home', onPressed: onClose),
      ],
    );
  }
}

class _FailureContent extends StatelessWidget {
  const _FailureContent({required this.ride, required this.onClose});

  final Ride ride;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          ride.status == RideStatus.noDrivers ? 'No drivers available' : 'Ride failed',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: Insets.s),
        const Text('Please try again in a few moments.'),
        const SizedBox(height: Insets.l),
        PrimaryButton(label: 'Back to home', onPressed: onClose),
      ],
    );
  }
}
