import 'dart:async';

import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart' as geo;
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../diagnostics/state/diagnostics_controller.dart';
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
  String _locationStatus = 'permission not asked';
  String? _mapError;
  bool _locationGranted = false;
  bool _askedLocation = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      _recordAction('home opened');
      await _refreshLocationStatus();
      await _checkActiveAndStart();
    });
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
      ref
          .read(diagnosticsProvider.notifier)
          .apiOk(200, action: 'active ride check');
      if (active != null && !active.status.isTerminal && mounted) {
        context.go('/ride/${active.id}');
        return;
      }
    } on ApiError catch (e) {
      _recordApiError(e, action: 'active ride check');
    } on Object catch (e) {
      _recordError('აქტიური მგზავრობის შემოწმება ვერ მოხერხდა.', e);
    }
    _startNearbyTicker();
  }

  void _startNearbyTicker() {
    _nearbyTicker?.cancel();
    _nearbyTicker = Timer.periodic(
      const Duration(seconds: 10),
      (_) => _refreshNearby(showMissingPickup: false),
    );
    _refreshNearby(showMissingPickup: false);
  }

  Future<void> _refreshNearby({bool showMissingPickup = true}) async {
    _recordAction('nearby drivers refresh');
    final pickup = ref.read(rideFlowProvider).pickup;
    if (pickup == null) {
      if (showMissingPickup) {
        _showMessage('ჯერ აირჩიეთ ან ჩართეთ პიკაპის მდებარეობა.');
      }
      return;
    }
    try {
      final list =
          await ref.read(rideRepositoryProvider).nearbyDrivers(center: pickup);
      if (mounted) setState(() => _nearby = list);
      ref
          .read(diagnosticsProvider.notifier)
          .apiOk(200, action: 'nearby drivers refresh');
    } on ApiError catch (e) {
      _recordApiError(e, action: 'nearby drivers refresh');
    } on Object catch (e) {
      _recordError('მძღოლების მოძიება ვერ მოხერხდა.', e);
    }
  }

  Future<void> _refreshLocationStatus({bool request = false}) async {
    _recordAction(request ? 'retry/location tap' : 'location status check');

    if (ref.read(envProvider).googleMapsKey.trim().isEmpty) {
      setState(() {
        _mapError = 'Google Maps-ის გასაღები აკლია ამ build-ში.';
        _locationStatus = 'maps key missing';
        _locationGranted = false;
      });
      ref.read(diagnosticsProvider.notifier).location(_locationStatus);
      return;
    }

    try {
      final serviceEnabled = await geo.Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        _setLocationState('service disabled', granted: false);
        return;
      }

      var permission = await geo.Geolocator.checkPermission();
      if (request && permission == geo.LocationPermission.denied) {
        _askedLocation = true;
        permission = await geo.Geolocator.requestPermission();
      }

      switch (permission) {
        case geo.LocationPermission.denied:
          _setLocationState(_askedLocation ? 'denied' : 'permission not asked',
              granted: false);
          return;
        case geo.LocationPermission.deniedForever:
          _setLocationState('denied', granted: false);
          return;
        case geo.LocationPermission.unableToDetermine:
          _setLocationState('restricted', granted: false);
          return;
        case geo.LocationPermission.whileInUse:
        case geo.LocationPermission.always:
          _setLocationState('granted', granted: true);
      }

      if (request) {
        final position = await geo.Geolocator.getCurrentPosition(
          locationSettings: const geo.LocationSettings(
            accuracy: geo.LocationAccuracy.high,
            timeLimit: Duration(seconds: 8),
          ),
        );
        ref
            .read(rideFlowProvider.notifier)
            .setPickup(LatLng(position.latitude, position.longitude));
        await _refreshNearby();
      }
    } on Object catch (e) {
      _setLocationState('restricted', granted: false);
      _recordError('ლოკაციის მიღება ვერ მოხერხდა.', e);
    }
  }

  void _setLocationState(String value, {required bool granted}) {
    if (!mounted) return;
    setState(() {
      _locationStatus = value;
      _locationGranted = granted;
      _mapError = null;
    });
    ref.read(diagnosticsProvider.notifier).location(value);
  }

  void _recordAction(String action) {
    debugPrint('[Ride360] $action');
    ref.read(diagnosticsProvider.notifier).action(action);
  }

  void _recordApiError(ApiError e, {required String action}) {
    debugPrint(
        '[Ride360] $action failed: ${e.code} ${e.httpStatus} ${e.message}');
    final message = _kaError(e);
    ref.read(diagnosticsProvider.notifier).apiError(
          message: message,
          status: e.httpStatus,
          action: action,
        );
    _showMessage(message);
  }

  void _recordError(String message, Object error) {
    debugPrint('[Ride360] $message $error');
    ref.read(diagnosticsProvider.notifier).apiError(
          message: message,
          action: message,
        );
    _showMessage(message);
  }

  String _kaError(ApiError e) {
    return switch (e.code) {
      'auth.otp_delivery_failed' =>
        'SMS კოდის გაგზავნა ვერ მოხერხდა. გთხოვთ სცადოთ თავიდან.',
      'http.request_timeout' => 'კავშირი დაგვიანდა. სცადეთ თავიდან.',
      _ =>
        e.message.isEmpty ? 'დაფიქსირდა შეცდომა. სცადეთ თავიდან.' : e.message,
    };
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  void _openMenu() {
    _recordAction('menu tap');
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(Insets.l, 0, Insets.l, Insets.l),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.info_outline_rounded),
                title: const Text('Diagnostics'),
                subtitle:
                    const Text('Build, auth, location, and last API state'),
                onTap: () {
                  _recordAction('diagnostics tap');
                  Navigator.of(context).pop();
                  context.push('/diagnostics');
                },
              ),
              ListTile(
                leading: const Icon(Icons.support_agent_rounded),
                title: const Text('დახმარება · Help'),
                onTap: () {
                  _recordAction('help tap');
                  Navigator.of(context).pop();
                  _showMessage('დახმარების გვერდი მალე დაემატება.');
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final flow = ref.watch(rideFlowProvider);
    final mapProvider = ref.watch(mapProviderProvider);
    final mapsKeyPresent =
        ref.watch(envProvider).googleMapsKey.trim().isNotEmpty;

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
              myLocationEnabled: _locationGranted,
              onTap: (point) {
                _recordAction('pickup tap');
                ref.read(rideFlowProvider.notifier).setPickup(point);
              },
            ),
          ),
          if (!mapsKeyPresent || _mapError != null || !_locationGranted)
            Positioned(
              left: Insets.l,
              right: Insets.l,
              top: 96,
              child: _MapStateCard(
                mapError: _mapError,
                locationStatus: _locationStatus,
                mapsKeyPresent: mapsKeyPresent,
                onRetry: () => _refreshLocationStatus(request: true),
                onSettings: () {
                  _recordAction('open location settings tap');
                  if (_locationStatus == 'service disabled') {
                    geo.Geolocator.openLocationSettings();
                  } else {
                    geo.Geolocator.openAppSettings();
                  }
                },
              ),
            ),

          // Top floating chrome
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: Insets.l),
              child: Row(
                children: [
                  _CircleControl(icon: Icons.menu_rounded, onTap: _openMenu),
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
                                color:
                                    AppColors.success.withValues(alpha: 0.35),
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
                  _CircleControl(
                    icon: Icons.notifications_none_rounded,
                    onTap: () {
                      _recordAction('notifications tap');
                      _showMessage('შეტყობინებები მალე დაემატება.');
                    },
                  ),
                ],
              ),
            ),
          ),

          // Locate FAB
          Positioned(
            right: Insets.l,
            bottom: 200,
            child:
                _LocateFab(onTap: () => _refreshLocationStatus(request: true)),
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
                  onQuickAction: (label) {
                    _recordAction('$label quick tap');
                    _showMessage('$label მალე დაემატება.');
                  },
                  onTap: () {
                    _recordAction('destination tap');
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
  const _CircleControl({required this.icon, required this.onTap});
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      elevation: 8,
      shadowColor: Colors.black12,
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(icon, size: 20, color: AppColors.ink),
        ),
      ),
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
        child: const Icon(Icons.my_location_rounded,
            color: Colors.white, size: 24),
      ),
    );
  }
}

class _WhereToCard extends StatelessWidget {
  const _WhereToCard({
    required this.onTap,
    required this.driversNearby,
    required this.onQuickAction,
  });

  final VoidCallback onTap;
  final int driversNearby;
  final ValueChanged<String> onQuickAction;

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
                    child: const Icon(Icons.search_rounded,
                        color: AppColors.ink, size: 20),
                  ),
                  const SizedBox(width: Insets.m),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('სად მიდიხართ?', style: AppType.titleL),
                        Text('Where to?',
                            style: AppType.body
                                .copyWith(color: AppColors.inkMuted)),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right_rounded,
                      color: AppColors.inkSoft),
                ],
              ),
              const SizedBox(height: Insets.m),
              const Divider(height: 1),
              const SizedBox(height: Insets.m),
              Row(
                children: [
                  _QuickChip(
                    icon: Icons.home_rounded,
                    label: 'Home',
                    onTap: () => onQuickAction('Home'),
                  ),
                  const SizedBox(width: Insets.s),
                  _QuickChip(
                    icon: Icons.work_rounded,
                    label: 'Work',
                    onTap: () => onQuickAction('Work'),
                  ),
                  const SizedBox(width: Insets.s),
                  _QuickChip(
                    icon: Icons.history_rounded,
                    label: 'Recent',
                    onTap: () => onQuickAction('Recent'),
                  ),
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
  const _QuickChip(
      {required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Radii.pill),
      child: Container(
        padding: const EdgeInsets.symmetric(
            horizontal: Insets.m, vertical: Insets.s - 2),
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
      ),
    );
  }
}

class _MapStateCard extends StatelessWidget {
  const _MapStateCard({
    required this.mapError,
    required this.locationStatus,
    required this.mapsKeyPresent,
    required this.onRetry,
    required this.onSettings,
  });

  final String? mapError;
  final String locationStatus;
  final bool mapsKeyPresent;
  final VoidCallback onRetry;
  final VoidCallback onSettings;

  @override
  Widget build(BuildContext context) {
    final title = !mapsKeyPresent
        ? 'რუკის გასაღები აკლია'
        : locationStatus == 'granted'
            ? 'რუკა მზადაა'
            : 'ლოკაციის ნებართვა საჭიროა';
    final body = mapError ??
        switch (locationStatus) {
          'permission not asked' =>
            'ლოკაციის მოთხოვნა ჯერ არ გაგზავნილა. დააჭირეთ ღილაკს და ჩართეთ ნებართვა.',
          'denied' =>
            'ლოკაციის ნებართვა უარყოფილია. გახსენით Settings და ჩართეთ Location.',
          'restricted' => 'ლოკაცია შეზღუდულია ამ მოწყობილობაზე.',
          'service disabled' => 'Location Services გამორთულია მოწყობილობაზე.',
          _ => 'რუკის ან ლოკაციის მდგომარეობა მოწმდება.',
        };

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(Radii.l),
      elevation: 10,
      shadowColor: Colors.black12,
      child: Padding(
        padding: const EdgeInsets.all(Insets.m),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(title, style: AppType.titleM),
            const SizedBox(height: 4),
            Text(body, style: AppType.caption),
            const SizedBox(height: Insets.s),
            Row(
              children: [
                TextButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.my_location_rounded, size: 18),
                  label: const Text('ხელახლა ცდა'),
                ),
                const Spacer(),
                if (locationStatus == 'denied' ||
                    locationStatus == 'service disabled')
                  TextButton.icon(
                    onPressed: onSettings,
                    icon: const Icon(Icons.settings_rounded, size: 18),
                    label: const Text('Settings'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
