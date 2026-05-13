import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../demo/presentation/demo_stepper.dart';
import '../../demo/state/demo_mode_controller.dart';
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
    // Preview mode: no backend, so no active-ride lookup and no
    // nearby-drivers polling. Show a few static markers so the map
    // still reads as "drivers around me".
    if (ref.read(rideFlowProvider).demoActive) {
      if (mounted) {
        setState(() => _nearby = const [
              LatLng(41.7180, 44.8290),
              LatLng(41.7120, 44.8260),
              LatLng(41.7160, 44.8330),
            ]);
      }
      return;
    }
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

          // Top floating chrome
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: Insets.l),
              child: Row(
                children: [
                  _CircleControl(icon: Icons.menu_rounded),
                  const Spacer(),
                  GlassCard(
                    padding: const EdgeInsets.symmetric(
                      horizontal: Insets.m,
                      vertical: Insets.s - 2,
                    ),
                    radius: Radii.pill,
                    blur: 22,
                    tint: Colors.white.withValues(alpha: 0.78),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: AppColors.success,
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.success.withValues(alpha: 0.35),
                                blurRadius: 8,
                                spreadRadius: 2,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          '${_nearby.length} online nearby',
                          style: AppType.bodyStrong,
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  _CircleControl(icon: Icons.notifications_none_rounded),
                ],
              ),
            ),
          ),

          // Locate FAB
          Positioned(
            right: Insets.l,
            bottom: 200,
            child: _LocateFab(onTap: _refreshNearby),
          ),

          // Where-to card
          Align(
            alignment: Alignment.bottomCenter,
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.all(Insets.l),
                child: _WhereToCard(
                  driversNearby: _nearby.length,
                  onTap: () {
                    // Demo: skip the picker, jump straight to fare
                    // estimate with canned pickup/dropoff so the
                    // reviewer sees the full flow without typing.
                    if (ref.read(demoModeProvider).enabled) {
                      ref
                          .read(demoModeProvider.notifier)
                          .jumpTo(CustomerDemoStage.fareEstimate);
                      context.push('/ride/estimate');
                    } else {
                      context.push('/ride/search');
                    }
                  },
                ),
              ),
            ),
          ),

          // Dev preview overlay (renders nothing in non-demo state).
          const Align(alignment: Alignment.topCenter, child: DemoStepper()),
        ],
      ),
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
      decoration: const BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: AppShadows.card,
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
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(TouchTargets.fab / 2),
      child: Container(
        width: TouchTargets.fab,
        height: TouchTargets.fab,
        decoration: const BoxDecoration(
          gradient: AppGradients.primary,
          shape: BoxShape.circle,
          boxShadow: AppShadows.fab,
        ),
        child: const Icon(Icons.my_location_rounded, color: Colors.white, size: 24),
      ),
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
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(Radii.xl),
            boxShadow: AppShadows.card,
          ),
          padding: const EdgeInsets.all(Insets.l),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: const BoxDecoration(
                      color: AppColors.surfaceVariant,
                      shape: BoxShape.circle,
                    ),
                    alignment: Alignment.center,
                    child: const Icon(Icons.search_rounded, color: AppColors.ink, size: 20),
                  ),
                  const SizedBox(width: Insets.m),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('სად მიდიხართ?', style: AppType.titleL),
                        Text('Where to?',
                            style: AppType.body.copyWith(color: AppColors.inkMuted)),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right_rounded, color: AppColors.inkSoft),
                ],
              ),
              const SizedBox(height: Insets.m),
              const Divider(height: 1),
              const SizedBox(height: Insets.m),
              Row(
                children: const [
                  _QuickChip(icon: Icons.home_rounded, label: 'Home'),
                  SizedBox(width: Insets.s),
                  _QuickChip(icon: Icons.work_rounded, label: 'Work'),
                  SizedBox(width: Insets.s),
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
          Icon(icon, size: 14, color: AppColors.ink),
          const SizedBox(width: 6),
          Text(label, style: AppType.bodyStrong),
        ],
      ),
    );
  }
}
