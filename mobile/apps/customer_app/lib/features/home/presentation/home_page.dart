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
      body: Stack(
        children: [
          Positioned.fill(
            child: mapProvider.mapWidget(
              initialCenter: flow.pickup ?? const LatLng(41.7151, 44.8271),
              markers: markers,
              onTap: (point) => ref.read(rideFlowProvider.notifier).setPickup(point),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: Insets.l),
              child: _TopBar(driversNearby: _nearby.length),
            ),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(Insets.l),
                child: _WhereToCard(
                  driversNearby: _nearby.length,
                  onTap: () => context.push('/ride/destination'),
                ),
              ),
            ),
          ),
          Positioned(
            right: Insets.l,
            bottom: 160,
            child: _LocateFab(onTap: _refreshNearby),
          ),
        ],
      ),
    );
  }
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.driversNearby});

  final int driversNearby;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _CircleControl(icon: Icons.menu_rounded),
        const Spacer(),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: Insets.m, vertical: Insets.s - 2),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(Radii.pill),
            boxShadow: const [BoxShadow(blurRadius: 8, color: Color(0x14000000))],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const _Pulse(),
              const SizedBox(width: 6),
              Text(
                '$driversNearby online nearby',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(color: AppColors.ink),
              ),
            ],
          ),
        ),
        const Spacer(),
        _CircleControl(icon: Icons.notifications_none_rounded),
      ],
    );
  }
}

class _CircleControl extends StatelessWidget {
  const _CircleControl({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: const [BoxShadow(blurRadius: 8, color: Color(0x14000000))],
      ),
      child: Icon(icon, size: 20, color: AppColors.ink),
    );
  }
}

class _LocateFab extends StatelessWidget {
  const _LocateFab({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: TouchTargets.fab,
      height: TouchTargets.fab,
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: const [BoxShadow(blurRadius: 10, color: Color(0x1F000000))],
      ),
      child: const Icon(Icons.my_location_rounded, color: AppColors.seed, size: 24),
    );
  }
}

class _Pulse extends StatelessWidget {
  const _Pulse();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 8,
      height: 8,
      decoration: const BoxDecoration(color: AppColors.success, shape: BoxShape.circle),
    );
  }
}

class _WhereToCard extends StatelessWidget {
  const _WhereToCard({required this.onTap, required this.driversNearby});

  final VoidCallback onTap;
  final int driversNearby;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(Radii.xl),
      elevation: 0,
      child: InkWell(
        borderRadius: BorderRadius.circular(Radii.xl),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(Insets.l),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  const Icon(Icons.search_rounded, color: AppColors.inkSoft),
                  const SizedBox(width: Insets.m),
                  Expanded(
                    child: Text(
                      'სად მიდიხართ? / Where to?',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  const Icon(Icons.chevron_right_rounded, color: AppColors.inkSoft),
                ],
              ),
              const SizedBox(height: Insets.s),
              const Divider(),
              const SizedBox(height: Insets.s),
              Row(
                children: [
                  _QuickChip(icon: Icons.home_rounded, label: 'Home'),
                  const SizedBox(width: Insets.s),
                  _QuickChip(icon: Icons.work_rounded, label: 'Work'),
                  const SizedBox(width: Insets.s),
                  _QuickChip(icon: Icons.history_rounded, label: 'Recent'),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _QuickChip extends StatelessWidget {
  const _QuickChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Insets.m, vertical: Insets.s - 2),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppColors.inkSoft),
          const SizedBox(width: 6),
          Text(label, style: Theme.of(context).textTheme.labelMedium?.copyWith(color: AppColors.ink)),
        ],
      ),
    );
  }
}
