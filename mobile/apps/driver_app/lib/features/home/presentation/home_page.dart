import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../ride/presentation/active_ride_sheet.dart';
import '../../ride/presentation/incoming_offer_sheet.dart';
import '../../shift/state/shift_controller.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final shift = ref.watch(shiftProvider);
    final mapProvider = ref.watch(mapProviderProvider);

    return Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            Positioned.fill(
              child: mapProvider.mapWidget(initialCenter: shift.position),
            ),
            Positioned(
              top: Insets.l,
              left: Insets.l,
              right: Insets.l,
              child: _OnlineToggleBar(shift: shift),
            ),
            if (shift.activeRide != null && !shift.activeRide!.status.isTerminal)
              const Align(alignment: Alignment.bottomCenter, child: ActiveRideSheet())
            else if (shift.activeRide != null && shift.activeRide!.status == RideStatus.completed)
              Align(alignment: Alignment.bottomCenter, child: _CompletedSheet(ride: shift.activeRide!))
            else if (shift.online)
              const Align(alignment: Alignment.bottomCenter, child: _WaitingForRide()),
            if (shift.pendingOffer != null)
              Positioned.fill(child: IncomingOfferSheet(offer: shift.pendingOffer!)),
          ],
        ),
      ),
    );
  }
}

class _OnlineToggleBar extends ConsumerWidget {
  const _OnlineToggleBar({required this.shift});

  final ShiftState shift;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Material(
      color: Theme.of(context).colorScheme.surface,
      borderRadius: BorderRadius.circular(Radii.l),
      elevation: 4,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: Insets.l, vertical: Insets.s),
        child: Row(
          children: [
            Icon(
              shift.online ? Icons.power_settings_new : Icons.power_off,
              color: shift.online ? Colors.green : Colors.grey,
            ),
            const SizedBox(width: Insets.m),
            Expanded(child: Text(shift.online ? 'You are online' : 'You are offline')),
            Switch(
              value: shift.online,
              onChanged: shift.isWorking
                  ? null
                  : (v) => v
                      ? ref.read(shiftProvider.notifier).goOnline()
                      : ref.read(shiftProvider.notifier).goOffline(),
            ),
          ],
        ),
      ),
    );
  }
}

class _WaitingForRide extends StatelessWidget {
  const _WaitingForRide();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(Insets.l),
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(Radii.l),
        boxShadow: const [BoxShadow(blurRadius: 8, color: Colors.black26)],
      ),
      child: const Row(
        children: [
          SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
          SizedBox(width: Insets.m),
          Text('Waiting for ride requests…'),
        ],
      ),
    );
  }
}

class _CompletedSheet extends ConsumerWidget {
  const _CompletedSheet({required this.ride});

  final Ride ride;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final amount = ride.finalAmount ?? ride.quotedAmount;
    return Container(
      margin: const EdgeInsets.all(Insets.l),
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(Radii.l),
        boxShadow: const [BoxShadow(blurRadius: 8, color: Colors.black26)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Ride completed', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: Insets.s),
          Text('Earned ${amount.toStringAsFixed(2)} ${ride.currency}'),
          const SizedBox(height: Insets.l),
          PrimaryButton(
            label: 'Continue',
            onPressed: () => ref.read(shiftProvider.notifier).dismissCompletedRide(),
          ),
        ],
      ),
    );
  }
}
