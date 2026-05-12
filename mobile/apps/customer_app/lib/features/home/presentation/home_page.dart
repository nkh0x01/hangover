import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../ride/state/ride_flow_controller.dart';

class HomePage extends ConsumerStatefulWidget {
  const HomePage({super.key});

  @override
  ConsumerState<HomePage> createState() => _HomePageState();
}

class _HomePageState extends ConsumerState<HomePage> {
  Timer? _nearbyTicker;
  List<LatLng> _nearby = const [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkActiveAndStart());
  }

  @override
  void dispose() {
    _nearbyTicker?.cancel();
    super.dispose();
  }

  Future<void> _checkActiveAndStart() async {
    try {
      final active = await ref.read(rideRepositoryProvider).active();
      if (active != null && !active.status.isTerminal && mounted) {
        context.go('/ride/${active.id}');
        return;
      }
    } on Object catch (_) {}
    _startNearbyTicker();
  }

  void _startNearbyTicker() {
    _nearbyTicker?.cancel();
    _nearbyTicker = Timer.periodic(const Duration(seconds: 10), (_) => _refreshNearby());
    _refreshNearby();
  }

  Future<void> _refreshNearby() async {
    final pickup = ref.read(rideFlowProvider).pickup;
    if (pickup == null) return;
    try {
      final list = await ref.read(rideRepositoryProvider).nearbyDrivers(center: pickup);
      if (mounted) setState(() => _nearby = list);
    } on Object catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final flow = ref.watch(rideFlowProvider);
    final mapProvider = ref.watch(mapProviderProvider);

    final markers = <MapMarker>[
      if (flow.pickup != null)
        MapMarker(id: 'pickup', position: flow.pickup!, title: 'Pickup'),
      for (var i = 0; i < _nearby.length; i++)
        MapMarker(id: 'driver-$i', position: _nearby[i]),
    ];

    return Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            Positioned.fill(
              child: mapProvider.mapWidget(
                initialCenter: flow.pickup ?? const LatLng(41.7151, 44.8271),
                markers: markers,
                onTap: (point) => ref.read(rideFlowProvider.notifier).setPickup(point),
              ),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: _DestinationCard(
                onTap: () async {
                  await context.push('/ride/destination');
                  // Destination page sets the dropoff on the controller
                  // itself and then routes the user forward.
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DestinationCard extends StatelessWidget {
  const _DestinationCard({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(Insets.l),
      child: Material(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(Radii.l),
        elevation: 4,
        child: InkWell(
          borderRadius: BorderRadius.circular(Radii.l),
          onTap: onTap,
          child: const Padding(
            padding: EdgeInsets.all(Insets.l),
            child: Row(
              children: [
                Icon(Icons.search),
                SizedBox(width: Insets.m),
                Expanded(child: Text('Where to?')),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
