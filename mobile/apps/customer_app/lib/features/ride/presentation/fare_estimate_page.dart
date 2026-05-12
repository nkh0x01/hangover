import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../state/ride_flow_controller.dart';

class FareEstimatePage extends ConsumerStatefulWidget {
  const FareEstimatePage({super.key});

  @override
  ConsumerState<FareEstimatePage> createState() => _FareEstimatePageState();
}

class _FareEstimatePageState extends ConsumerState<FareEstimatePage> {
  String _paymentMethod = 'cash';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(rideFlowProvider.notifier).requestEstimate();
    });
  }

  Future<void> _confirm() async {
    final notifier = ref.read(rideFlowProvider.notifier);
    await notifier.requestRide(paymentMethod: _paymentMethod);
    final ride = ref.read(rideFlowProvider).activeRide;
    if (ride != null && context.mounted) {
      context.go('/ride/${ride.id}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(rideFlowProvider);
    final fare = state.fareEstimate;

    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Confirm ride'),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _RouteSummary(pickup: state.pickupAddress, dropoff: state.dropoffAddress),
              const SizedBox(height: Insets.xl),
              if (fare == null && state.isWorking)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    Skeleton(width: 120, height: 14),
                    SizedBox(height: 12),
                    Skeleton(width: 200, height: 36, radius: Radii.s),
                    SizedBox(height: 8),
                    Skeleton(width: 140, height: 12),
                  ],
                )
              else if (fare != null)
                _FareHero(fare: fare),
              const SizedBox(height: Insets.xl),
              Text('Payment', style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: Insets.s),
              _PaymentSelector(
                value: _paymentMethod,
                onChanged: (v) => setState(() => _paymentMethod = v),
              ),
              if (state.error != null) ...[
                const SizedBox(height: Insets.m),
                Text(
                  state.error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const Spacer(),
              PrimaryButton(
                label: fare == null
                    ? 'Calculating…'
                    : 'Request ride · ${fare.totalAmount.toStringAsFixed(2)} ${fare.currency}',
                busy: state.isWorking && fare != null,
                onPressed: fare == null ? null : _confirm,
              ),
              const SizedBox(height: Insets.s),
              Center(
                child: Text(
                  'You will be charged after the trip completes.',
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RouteSummary extends StatelessWidget {
  const _RouteSummary({required this.pickup, required this.dropoff});

  final String pickup;
  final String dropoff;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.l),
        border: Border.all(color: AppColors.outlineSubtle),
      ),
      child: Column(
        children: [
          _RouteRow(
            icon: Icons.circle,
            iconColor: AppColors.seed,
            label: 'Pickup',
            value: pickup,
          ),
          Padding(
            padding: const EdgeInsets.only(left: 18, top: Insets.xs, bottom: Insets.xs),
            child: Row(
              children: List.generate(
                3,
                (_) => Container(
                  width: 3,
                  height: 3,
                  margin: const EdgeInsets.symmetric(vertical: 1),
                  decoration: const BoxDecoration(
                    color: AppColors.outline,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            ),
          ),
          _RouteRow(
            icon: Icons.flag_rounded,
            iconColor: AppColors.danger,
            label: 'Dropoff',
            value: dropoff,
          ),
        ],
      ),
    );
  }
}

class _RouteRow extends StatelessWidget {
  const _RouteRow({required this.icon, required this.iconColor, required this.label, required this.value});

  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: iconColor, size: 16),
        const SizedBox(width: Insets.m),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: 2),
              Text(value, style: Theme.of(context).textTheme.titleMedium),
            ],
          ),
        ),
      ],
    );
  }
}

class _FareHero extends StatelessWidget {
  const _FareHero({required this.fare});

  final FareEstimate fare;

  @override
  Widget build(BuildContext context) {
    final isSurging = fare.surgeMultiplier > 1.0;
    return Container(
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.l),
        border: Border.all(color: AppColors.outlineSubtle),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text('Estimated fare', style: Theme.of(context).textTheme.labelMedium),
              const Spacer(),
              if (isSurging)
                StatusPill(
                  label: '×${fare.surgeMultiplier.toStringAsFixed(1)} surge',
                  tone: StatusTone.accent,
                ),
            ],
          ),
          const SizedBox(height: Insets.s),
          RichText(
            text: TextSpan(
              style: Theme.of(context).textTheme.displayLarge,
              children: [
                TextSpan(text: fare.totalAmount.toStringAsFixed(2)),
                TextSpan(
                  text: ' ${fare.currency}',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(color: AppColors.inkMuted),
                ),
              ],
            ),
          ),
          const SizedBox(height: Insets.s),
          Row(
            children: [
              const Icon(Icons.timer_outlined, size: 14, color: AppColors.inkSoft),
              const SizedBox(width: 4),
              Text('${fare.durationMin} min', style: Theme.of(context).textTheme.bodyMedium),
              const SizedBox(width: Insets.m),
              const Icon(Icons.route_rounded, size: 14, color: AppColors.inkSoft),
              const SizedBox(width: 4),
              Text('${fare.distanceKm.toStringAsFixed(1)} km',
                  style: Theme.of(context).textTheme.bodyMedium),
              const Spacer(),
              Text(
                'Includes booking fee',
                style: Theme.of(context).textTheme.labelMedium,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PaymentSelector extends StatelessWidget {
  const _PaymentSelector({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: _PaymentOption(
          icon: Icons.payments_rounded,
          label: 'Cash',
          selected: value == 'cash',
          onTap: () => onChanged('cash'),
        )),
        const SizedBox(width: Insets.s),
        Expanded(child: _PaymentOption(
          icon: Icons.credit_card_rounded,
          label: 'Card',
          selected: value == 'card',
          onTap: () => onChanged('card'),
        )),
      ],
    );
  }
}

class _PaymentOption extends StatelessWidget {
  const _PaymentOption({required this.icon, required this.label, required this.selected, required this.onTap});

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.seed : Colors.white,
      borderRadius: BorderRadius.circular(Radii.m),
      child: InkWell(
        borderRadius: BorderRadius.circular(Radii.m),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: Insets.m),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(Radii.m),
            border: Border.all(color: selected ? AppColors.seed : AppColors.outline, width: 1.5),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 18, color: selected ? Colors.white : AppColors.ink),
              const SizedBox(width: Insets.s),
              Text(
                label,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: selected ? Colors.white : AppColors.ink,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
