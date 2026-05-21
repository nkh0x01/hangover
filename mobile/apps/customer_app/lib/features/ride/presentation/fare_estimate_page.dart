import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../demo/presentation/demo_stepper.dart';
import '../../demo/state/demo_mode_controller.dart';
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
      // Demo: ensure dropoff + canned fare are populated even if the
      // reviewer landed here without going through the picker.
      if (ref.read(demoModeProvider).enabled) {
        ref.read(rideFlowProvider.notifier).demoEnterFareEstimate();
      } else {
        ref.read(rideFlowProvider.notifier).requestEstimate();
      }
    });
  }

  Future<void> _confirm() async {
    final notifier = ref.read(rideFlowProvider.notifier);
    // Demo: synthesize a "searching" ride and advance the stage in
    // lockstep so the floating stepper stays accurate.
    if (ref.read(demoModeProvider).enabled) {
      ref.read(demoModeProvider.notifier).jumpTo(CustomerDemoStage.searching);
      if (context.mounted) context.go('/ride/demo-ride-1');
      return;
    }
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
      appBar: AppBar(leading: const BackButton(), title: const Text('Confirm ride')),
      body: Stack(children: [
        SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _RouteCard(pickup: state.pickupAddress, dropoff: state.dropoffAddress),
              const SizedBox(height: Insets.l),
              if (fare == null && state.isWorking)
                _FareSkeleton()
              else if (fare != null)
                FareHeroCard(
                  amount: fare.totalAmount,
                  currency: fare.currency,
                  distanceKm: fare.distanceKm,
                  durationMin: fare.durationMin,
                  surgeMultiplier: fare.surgeMultiplier,
                  subtitle: 'Includes booking fee',
                ),
              const SizedBox(height: Insets.xl),
              Text('Payment', style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: Insets.s),
              _PaymentSelector(
                value: _paymentMethod,
                onChanged: (v) => setState(() => _paymentMethod = v),
              ),
              if (state.error != null) ...[
                const SizedBox(height: Insets.m),
                Text(state.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ],
              const Spacer(),
              GradientButton(
                label: fare == null
                    ? 'Calculating…'
                    : 'Request ride · ${fare.totalAmount.toStringAsFixed(2)} ${fare.currency}',
                busy: state.isWorking && fare != null,
                onPressed: fare == null ? null : _confirm,
                trailing: const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 18),
              ),
              const SizedBox(height: Insets.s),
              Center(
                child: Text(
                  'You will be charged after the trip completes.',
                  style: AppType.label,
                ),
              ),
            ],
          ),
        ),
      ),
        const Align(alignment: Alignment.topCenter, child: DemoStepper()),
      ]),
    );
  }
}

class _FareSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(Radii.l),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Skeleton(width: 120, height: 12),
          SizedBox(height: 12),
          Skeleton(width: 220, height: 40, radius: Radii.s),
          SizedBox(height: 12),
          Skeleton(width: 160, height: 12),
        ],
      ),
    );
  }
}

class _RouteCard extends StatelessWidget {
  const _RouteCard({required this.pickup, required this.dropoff});

  final String pickup;
  final String dropoff;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Insets.l),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Radii.l),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        children: [
          _RouteRow(color: AppColors.seed, label: 'Pickup', value: pickup),
          Padding(
            padding: const EdgeInsets.only(left: 6, top: Insets.xs, bottom: Insets.xs),
            child: Column(
              children: List.generate(
                3,
                (_) => Container(
                  width: 3,
                  height: 3,
                  margin: const EdgeInsets.symmetric(vertical: 1),
                  decoration: const BoxDecoration(color: AppColors.outline, shape: BoxShape.circle),
                ),
              ),
            ),
          ),
          _RouteRow(color: AppColors.danger, label: 'Dropoff', value: dropoff),
        ],
      ),
    );
  }
}

class _RouteRow extends StatelessWidget {
  const _RouteRow({required this.color, required this.label, required this.value});
  final Color color;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 14,
          height: 14,
          margin: const EdgeInsets.only(top: 2),
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 2),
            boxShadow: [BoxShadow(color: color.withValues(alpha: 0.3), blurRadius: 6)],
          ),
        ),
        const SizedBox(width: Insets.m),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: AppType.label),
              const SizedBox(height: 2),
              Text(value, style: AppType.titleM),
            ],
          ),
        ),
      ],
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
    return InkWell(
      borderRadius: BorderRadius.circular(Radii.m),
      onTap: onTap,
      child: AnimatedContainer(
        duration: AppMotion.fast,
        curve: AppCurves.status,
        padding: const EdgeInsets.symmetric(vertical: Insets.m),
        decoration: BoxDecoration(
          gradient: selected ? AppGradients.primary : null,
          color: selected ? null : Colors.white,
          borderRadius: BorderRadius.circular(Radii.m),
          border: Border.all(color: selected ? Colors.transparent : AppColors.outline, width: 1.5),
          boxShadow: selected ? AppShadows.heroGreen : AppShadows.card,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 18, color: selected ? Colors.white : AppColors.ink),
            const SizedBox(width: Insets.s),
            Text(
              label,
              style: AppType.titleM.copyWith(color: selected ? Colors.white : AppColors.ink),
            ),
          ],
        ),
      ),
    );
  }
}
