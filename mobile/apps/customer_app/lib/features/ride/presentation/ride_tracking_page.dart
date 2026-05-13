import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../demo/presentation/demo_stepper.dart';
import '../../demo/state/demo_mode_controller.dart';
import '../state/ride_flow_controller.dart';

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
      body: Stack(
        children: [
          Positioned.fill(
            child: mapProvider.mapWidget(initialCenter: ride.pickup, markers: markers),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: _RideSheet(
              ride: ride,
              onClose: () {
                // In demo, keep preview mode on and bounce back to home;
                // out of demo, clear the ride flow and route home.
                final demo = ref.read(demoModeProvider);
                if (demo.enabled) {
                  ref
                      .read(demoModeProvider.notifier)
                      .jumpTo(CustomerDemoStage.homeIdle);
                } else {
                  ref.read(rideFlowProvider.notifier).reset();
                }
                context.go('/home');
              },
            ),
          ),
          const Align(alignment: Alignment.topCenter, child: DemoStepper()),
        ],
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
    return BottomSheetCard(
      child: AnimatedSize(
        duration: Motion.med,
        curve: Curves.easeInOutCubic,
        alignment: Alignment.bottomCenter,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            RideStatusChip(phase: _phaseFor(ride.status)),
            const SizedBox(height: Insets.l),
            ..._content(context, ref, ride),
          ],
        ),
      ),
    );
  }

  static RidePhase _phaseFor(RideStatus s) => switch (s) {
        RideStatus.requested || RideStatus.searching || RideStatus.offered => RidePhase.searching,
        RideStatus.accepted => RidePhase.assigned,
        RideStatus.driverArriving => RidePhase.arriving,
        RideStatus.driverArrived || RideStatus.inProgress => RidePhase.onTrip,
        RideStatus.completed => RidePhase.completed,
        _ => RidePhase.cancelled,
      };

  List<Widget> _content(BuildContext context, WidgetRef ref, Ride ride) {
    return switch (ride.status) {
      RideStatus.requested || RideStatus.searching || RideStatus.offered =>
          _searching(context, ref),
      RideStatus.accepted || RideStatus.driverArriving =>
          _driverAssigned(context, ref, ride),
      RideStatus.driverArrived => _arrived(context, ride),
      RideStatus.inProgress => _inProgress(context, ride),
      RideStatus.completed => _completed(context, ride),
      RideStatus.cancelled => _cancelled(context, ride),
      RideStatus.noDrivers || RideStatus.failed => _failure(context, ride),
    };
  }

  // ---- per-phase content -------------------------------------------------

  List<Widget> _searching(BuildContext context, WidgetRef ref) => [
        Row(
          children: [
            const StatusPill(label: 'Searching', tone: StatusTone.info, pulse: true),
            const Spacer(),
            Text('avg 38 s', style: Theme.of(context).textTheme.labelMedium),
          ],
        ),
        const SizedBox(height: Insets.m),
        Text('Looking for a driver nearby', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: Insets.xs),
        Text(
          '5 scooters are within 1.5 km. Most rides match in under a minute.',
          style: Theme.of(context).textTheme.bodyLarge,
        ),
        const SizedBox(height: Insets.l),
        SecondaryButton(
          label: 'Cancel request',
          color: AppColors.danger,
          onPressed: () => ref.read(rideFlowProvider.notifier).cancelActive(),
        ),
      ];

  List<Widget> _driverAssigned(BuildContext context, WidgetRef ref, Ride ride) {
    final d = ride.driver;
    final label = ride.status == RideStatus.driverArriving
        ? 'Arriving in 2 min'
        : 'Driver on the way';
    return [
      Row(
        children: [
          StatusPill(label: label, tone: StatusTone.success, pulse: true),
          const Spacer(),
          Text(ride.status == RideStatus.driverArriving ? '2 min' : '4 min',
              style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
      const SizedBox(height: Insets.m),
      Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.surfaceVariant,
            ),
            alignment: Alignment.center,
            child: const Icon(Icons.person_rounded, color: AppColors.inkSoft),
          ),
          const SizedBox(width: Insets.m),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(d?.name ?? 'Your driver', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 2),
                Row(
                  children: [
                    const Icon(Icons.star_rounded, size: 14, color: AppColors.warning),
                    const SizedBox(width: 2),
                    Text('${d?.ratingAvg.toStringAsFixed(2) ?? '—'}',
                        style: Theme.of(context).textTheme.bodyMedium),
                    Text(' · 230 trips', style: Theme.of(context).textTheme.bodyMedium),
                  ],
                ),
              ],
            ),
          ),
          _RoundIcon(icon: Icons.chat_bubble_outline_rounded, color: AppColors.ink, bg: AppColors.surfaceVariant),
          const SizedBox(width: Insets.s),
          _RoundIcon(icon: Icons.phone_rounded, color: Colors.white, bg: AppColors.seed),
        ],
      ),
      if (d?.vehicle != null) ...[
        const SizedBox(height: Insets.m),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: Insets.l, vertical: Insets.m),
          decoration: BoxDecoration(
            color: AppColors.surfaceVariant,
            borderRadius: BorderRadius.circular(Radii.m),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Vehicle', style: Theme.of(context).textTheme.labelMedium),
                    Text(
                      '${d!.vehicle!.brand} ${d.vehicle!.model} · ${d.vehicle!.color ?? '—'}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(Radii.s),
                ),
                child: Text(
                  d.vehicle!.plate,
                  style: const TextStyle(
                    fontFamily: 'monospace',
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
      const SizedBox(height: Insets.m),
      Row(
        children: [
          Text('${ride.quotedAmount.toStringAsFixed(2)} ${ride.currency}',
              style: Theme.of(context).textTheme.titleMedium),
          Text(' · ${ride.paymentMethod}', style: Theme.of(context).textTheme.bodyMedium),
          const Spacer(),
          TextButton(
            onPressed: () => ref.read(rideFlowProvider.notifier).cancelActive(),
            child: const Text('Cancel ride'),
          ),
        ],
      ),
    ];
  }

  List<Widget> _arrived(BuildContext context, Ride ride) => [
        const StatusPill(label: 'Driver arrived', tone: StatusTone.success),
        const SizedBox(height: Insets.m),
        Text('Your driver is here', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: Insets.xs),
        if (ride.driver?.vehicle != null)
          Text(
            'Look for ${ride.driver!.vehicle!.brand} · plate ${ride.driver!.vehicle!.plate}',
            style: Theme.of(context).textTheme.bodyLarge,
          ),
      ];

  List<Widget> _inProgress(BuildContext context, Ride ride) => [
        const StatusPill(label: 'On trip', tone: StatusTone.accent, pulse: true),
        const SizedBox(height: Insets.m),
        Text('Heading to dropoff', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: Insets.xs),
        Text(ride.dropoffAddress, style: Theme.of(context).textTheme.bodyLarge),
      ];

  List<Widget> _completed(BuildContext context, Ride ride) => [
        SuccessState(
          headline: 'Trip complete',
          body: 'Thanks for riding with us.',
          amount: ride.finalAmount ?? ride.quotedAmount,
          currency: ride.currency,
        ),
        const SizedBox(height: Insets.m),
        PrimaryButton(label: 'Rate your driver', onPressed: onClose),
      ];

  List<Widget> _cancelled(BuildContext context, Ride ride) => [
        const StatusPill(label: 'Cancelled', tone: StatusTone.danger),
        const SizedBox(height: Insets.m),
        Text('Ride cancelled', style: Theme.of(context).textTheme.titleLarge),
        if (ride.cancellationReason != null)
          Text('Reason: ${ride.cancellationReason}',
              style: Theme.of(context).textTheme.bodyLarge),
        const SizedBox(height: Insets.l),
        PrimaryButton(label: 'Back to home', onPressed: onClose),
      ];

  List<Widget> _failure(BuildContext context, Ride ride) => [
        ErrorStateView(
          headline: ride.status == RideStatus.noDrivers
              ? 'No drivers available'
              : 'Something went wrong',
          message: 'Please try again in a few moments.',
          onRetry: onClose,
          retryLabel: 'Back to home',
        ),
      ];
}

class _RoundIcon extends StatelessWidget {
  const _RoundIcon({required this.icon, required this.color, required this.bg});

  final IconData icon;
  final Color color;
  final Color bg;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(color: bg, shape: BoxShape.circle),
      alignment: Alignment.center,
      child: Icon(icon, color: color, size: 20),
    );
  }
}
