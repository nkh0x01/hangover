import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:maps/maps.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../state/ride_flow_controller.dart';

/// MVP: tap on the map to pick a destination. The full text-search
/// autocomplete lands in Phase 2 once the MapProvider stops returning
/// an empty suggestion list.
class DestinationPage extends ConsumerStatefulWidget {
  const DestinationPage({super.key});

  @override
  ConsumerState<DestinationPage> createState() => _DestinationPageState();
}

class _DestinationPageState extends ConsumerState<DestinationPage> {
  LatLng? _picked;

  @override
  Widget build(BuildContext context) {
    final flow = ref.watch(rideFlowProvider);
    final mapProvider = ref.watch(mapProviderProvider);

    final markers = <MapMarker>[
      if (flow.pickup != null) MapMarker(id: 'pickup', position: flow.pickup!, title: 'Pickup'),
      if (_picked != null) MapMarker(id: 'dropoff', position: _picked!, title: 'Dropoff'),
    ];

    return Scaffold(
      appBar: AppBar(title: const Text('Choose destination')),
      body: Column(
        children: [
          Expanded(
            child: mapProvider.mapWidget(
              initialCenter: flow.pickup ?? const LatLng(41.7151, 44.8271),
              markers: markers,
              onTap: (p) => setState(() => _picked = p),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(Insets.l),
              child: PrimaryButton(
                label: _picked == null ? 'Tap a point on the map' : 'Confirm destination',
                onPressed: _picked == null
                    ? null
                    : () async {
                        final address = await mapProvider.reverseGeocode(_picked!);
                        ref.read(rideFlowProvider.notifier).setDropoff(
                              _picked!,
                              address: address ?? 'Dropoff',
                            );
                        if (!context.mounted) return;
                        context.replace('/ride/estimate');
                      },
              ),
            ),
          ),
        ],
      ),
    );
  }
}
