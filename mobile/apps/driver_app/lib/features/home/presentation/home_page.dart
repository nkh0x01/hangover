import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../demo/presentation/demo_stepper.dart';
import '../../ride/presentation/active_ride_sheet.dart';
import '../../ride/presentation/incoming_offer_sheet.dart';
import '../../shift/state/shift_controller.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final shift = ref.watch(shiftProvider);
    final mapProvider = ref.watch(mapProviderProvider);
    ref.listen<String?>(
      shiftProvider.select((value) => value.error),
      (previous, next) {
        if (next == null || next == previous) return;
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(next)));
      },
    );

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: mapProvider.mapWidget(
              initialCenter: shift.position,
              myLocationEnabled: shift.locationStatus == 'granted',
            ),
          ),
          SafeArea(
            child: Padding(
              padding:
                  const EdgeInsets.fromLTRB(Insets.l, Insets.l, Insets.l, 0),
              child: Column(
                children: [
                  _OnlineToggleBar(shift: shift),
                  const SizedBox(height: Insets.s),
                  if (shift.error != null)
                    _DriverStateCard(shift: shift)
                  else if (shift.locationStatus != 'permission not asked' &&
                      shift.locationStatus != 'granted')
                    _DriverStateCard(shift: shift),
                  if (shift.error != null ||
                      (shift.locationStatus != 'permission not asked' &&
                          shift.locationStatus != 'granted'))
                    const SizedBox(height: Insets.s),
                  const Align(
                    alignment: Alignment.centerRight,
                    child: _EarningsBadge(amount: 87.50, currency: 'GEL'),
                  ),
                ],
              ),
            ),
          ),
          if (shift.activeRide != null && !shift.activeRide!.status.isTerminal)
            const Align(
                alignment: Alignment.bottomCenter, child: ActiveRideSheet())
          else if (shift.activeRide != null &&
              shift.activeRide!.status == RideStatus.completed)
            Align(
                alignment: Alignment.bottomCenter,
                child: _CompletedSheet(ride: shift.activeRide!))
          else if (shift.online)
            const Align(
                alignment: Alignment.bottomCenter, child: _WaitingForRide()),
          if (shift.pendingOffer != null)
            Positioned.fill(
                child: IncomingOfferSheet(offer: shift.pendingOffer!)),
          const Align(
              alignment: Alignment.topCenter, child: DriverDemoStepper()),
        ],
      ),
    );
  }
}

class _OnlineToggleBar extends ConsumerWidget {
  const _OnlineToggleBar({required this.shift});

  final ShiftState shift;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isOnline = shift.online;
    final canTap = !shift.isWorking;
    Future<void> toggle() => isOnline
        ? ref.read(shiftProvider.notifier).goOffline()
        : ref.read(shiftProvider.notifier).goOnline();

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(Radii.l),
      elevation: 6,
      shadowColor: const Color(0x14000000),
      child: InkWell(
        borderRadius: BorderRadius.circular(Radii.l),
        onTap: canTap ? toggle : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: Insets.l,
            vertical: Insets.m,
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: isOnline ? AppColors.seed : AppColors.surfaceVariant,
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: Icon(
                  isOnline
                      ? Icons.flash_on_rounded
                      : Icons.power_settings_new_rounded,
                  color: isOnline ? Colors.white : AppColors.inkSoft,
                  size: 22,
                ),
              ),
              const SizedBox(width: Insets.m),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      shift.isWorking
                          ? (isOnline
                              ? 'Going offline...'
                              : 'Starting shift...')
                          : (isOnline
                              ? 'You are online'
                              : 'Tap to start your shift'),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      isOnline
                          ? 'Tbilisi · accepting rides'
                          : "You won't receive offers while offline",
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Diagnostics',
                onPressed: () {
                  ref
                      .read(shiftProvider.notifier)
                      .recordAction('diagnostics tap');
                  context.push('/diagnostics');
                },
                icon: const Icon(Icons.info_outline_rounded),
              ),
              Switch.adaptive(
                value: isOnline,
                activeThumbColor: AppColors.seed,
                onChanged: canTap ? (_) => toggle() : null,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DriverStateCard extends ConsumerWidget {
  const _DriverStateCard({required this.shift});

  final ShiftState shift;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locationBlocked = shift.locationStatus == 'denied' ||
        shift.locationStatus == 'restricted' ||
        shift.locationStatus == 'service disabled';
    final message = shift.error ??
        (locationBlocked
            ? 'ლოკაციის ნებართვა საჭიროა'
            : 'ცვლის დაწყება ვერ მოხერხდა');

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Insets.m),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.m),
        border: Border.all(color: AppColors.outlineSubtle),
        boxShadow: const [BoxShadow(blurRadius: 16, color: Color(0x12000000))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.warning_amber_rounded,
                  color: AppColors.warning, size: 20),
              const SizedBox(width: Insets.s),
              Expanded(
                child: Text(
                  message,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.xs),
          Text(
            'Status: ${shift.locationStatus} · API: ${shift.lastApiStatus?.toString() ?? 'none'}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: Insets.s),
          Row(
            children: [
              if (locationBlocked)
                TextButton.icon(
                  onPressed: () =>
                      ref.read(shiftProvider.notifier).openLocationSettings(),
                  icon: const Icon(Icons.settings_rounded),
                  label: const Text('Settings'),
                ),
              TextButton.icon(
                onPressed: () {
                  ref
                      .read(shiftProvider.notifier)
                      .recordAction('diagnostics tap');
                  context.push('/diagnostics');
                },
                icon: const Icon(Icons.info_outline_rounded),
                label: const Text('Diagnostics'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _EarningsBadge extends StatelessWidget {
  const _EarningsBadge({required this.amount, required this.currency});

  final double amount;
  final String currency;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding:
          const EdgeInsets.symmetric(horizontal: Insets.m, vertical: Insets.s),
      decoration: BoxDecoration(
        color: AppColors.ink,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.account_balance_wallet_rounded,
              color: AppColors.accent, size: 14),
          const SizedBox(width: 6),
          Text(
            'Today · ${amount.toStringAsFixed(2)} $currency',
            style: const TextStyle(
                color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13),
          ),
        ],
      ),
    );
  }
}

class _WaitingForRide extends StatelessWidget {
  const _WaitingForRide();

  @override
  Widget build(BuildContext context) {
    return BottomSheetCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              const StatusPill(
                  label: 'Live', tone: StatusTone.success, pulse: true),
              const SizedBox(width: Insets.s),
              Text('Waiting for ride requests',
                  style: Theme.of(context).textTheme.titleLarge),
            ],
          ),
          const SizedBox(height: Insets.xs),
          Text('12 scooters online nearby · Saburtalo is busiest right now.',
              style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: Insets.l),
          const Row(
            children: [
              _ShiftStat(label: 'Trips', value: '6'),
              SizedBox(width: Insets.s),
              _ShiftStat(label: 'Hours', value: '3h 12m'),
              SizedBox(width: Insets.s),
              _ShiftStat(label: 'Rating', value: '★ 4.91'),
            ],
          ),
        ],
      ),
    );
  }
}

class _ShiftStat extends StatelessWidget {
  const _ShiftStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: Insets.m),
        decoration: BoxDecoration(
          color: AppColors.surfaceVariant,
          borderRadius: BorderRadius.circular(Radii.m),
        ),
        child: Column(
          children: [
            Text(label, style: Theme.of(context).textTheme.labelMedium),
            const SizedBox(height: 2),
            Text(value, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
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
    return BottomSheetCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          SuccessState(
            headline: 'Ride complete',
            body: 'Earnings paid to wallet',
            amount: amount,
            currency: ride.currency,
          ),
          const SizedBox(height: Insets.m),
          PrimaryButton(
            label: 'Continue',
            onPressed: () =>
                ref.read(shiftProvider.notifier).dismissCompletedRide(),
          ),
        ],
      ),
    );
  }
}
