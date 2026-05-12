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
      appBar: AppBar(title: const Text('Fare estimate')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _RouteSummary(pickup: state.pickupAddress, dropoff: state.dropoffAddress),
              const SizedBox(height: Insets.xl),
              if (fare == null && state.isWorking) const LoadingState(message: 'Calculating fare…'),
              if (fare != null) _FareCard(fare: fare),
              const Spacer(),
              _PaymentSelector(
                value: _paymentMethod,
                onChanged: (v) => setState(() => _paymentMethod = v),
              ),
              const SizedBox(height: Insets.l),
              PrimaryButton(
                label: fare == null ? 'Waiting for fare…' : 'Request ride',
                busy: state.isWorking,
                onPressed: fare == null ? null : _confirm,
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ListTile(leading: const Icon(Icons.my_location), title: Text(pickup)),
        const Divider(height: 1),
        ListTile(leading: const Icon(Icons.flag), title: Text(dropoff)),
      ],
    );
  }
}

class _FareCard extends StatelessWidget {
  const _FareCard({required this.fare});

  final FareEstimate fare;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Insets.l),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('~${fare.distanceKm.toStringAsFixed(1)} km · ${fare.durationMin} min'),
                Text(
                  '${fare.totalAmount.toStringAsFixed(2)} ${fare.currency}',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
              ],
            ),
            if (fare.surgeMultiplier > 1.0)
              Chip(label: Text('x${fare.surgeMultiplier.toStringAsFixed(1)}'))
          ],
        ),
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
    return SegmentedButton<String>(
      segments: const [
        ButtonSegment(value: 'cash', label: Text('Cash'), icon: Icon(Icons.money)),
        ButtonSegment(value: 'card', label: Text('Card'), icon: Icon(Icons.credit_card)),
      ],
      selected: {value},
      onSelectionChanged: (s) => onChanged(s.first),
    );
  }
}
