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
      body: Stack(
        children: [
          Positioned.fill(child: mapProvider.mapWidget(initialCenter: shift.position)),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(Insets.l, Insets.l, Insets.l, 0),
              child: Column(
                children: [
                  _OnlineToggleBar(shift: shift),
                  const SizedBox(height: Insets.s),
                  Align(
                    alignment: Alignment.centerRight,
                    child: _EarningsBadge(amount: 87.50, currency: 'GEL'),
                  ),
                ],
              ),
            ),
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
    );
  }
}

class _OnlineToggleBar extends ConsumerWidget {
  const _OnlineToggleBar({required this.shift});

  final ShiftState shift;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isOnline = shift.online;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Insets.l, vertical: Insets.m),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.l),
        boxShadow: const [BoxShadow(blurRadius: 16, color: Color(0x14000000))],
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
              isOnline ? Icons.flash_on_rounded : Icons.power_settings_new_rounded,
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
                  isOnline ? 'You are online' : 'Tap to start your shift',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 2),
                Text(
                  isOnline ? 'Tbilisi · accepting rides' : "You won't receive offers while offline",
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
            ),
          ),
          Switch.adaptive(
            value: isOnline,
            activeThumbColor: AppColors.seed,
            onChanged: shift.isWorking
                ? null
                : (v) => v
                    ? ref.read(shiftProvider.notifier).goOnline()
                    : ref.read(shiftProvider.notifier).goOffline(),
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
      padding: const EdgeInsets.symmetric(horizontal: Insets.m, vertical: Insets.s),
      decoration: BoxDecoration(
        color: AppColors.ink,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.account_balance_wallet_rounded, color: AppColors.accent, size: 14),
          const SizedBox(width: 6),
          Text(
            'Today · ${amount.toStringAsFixed(2)} $currency',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13),
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
              const StatusPill(label: 'Live', tone: StatusTone.success, pulse: true),
              const SizedBox(width: Insets.s),
              Text('Waiting for ride requests',
                  style: Theme.of(context).textTheme.titleLarge),
            ],
          ),
          const SizedBox(height: Insets.xs),
          Text('12 scooters online nearby · Saburtalo is busiest right now.',
              style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: Insets.l),
          Row(
            children: const [
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
            onPressed: () => ref.read(shiftProvider.notifier).dismissCompletedRide(),
          ),
        ],
      ),
    );
  }
}
