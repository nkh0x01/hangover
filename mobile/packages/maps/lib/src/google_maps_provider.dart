import 'package:flutter/widgets.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart' as gm;

import 'lat_lng.dart';
import 'map_provider.dart';

/// Concrete Google Maps implementation of MapProvider. Phase 1.5 ships
/// the map widget + marker rendering; routing/autocomplete/geocoding
/// are left as not-implemented stubs to be filled by Phase 2.
class GoogleMapsProvider implements MapProvider {
  GoogleMapsProvider();

  @override
  Widget mapWidget({
    required LatLng initialCenter,
    double initialZoom = 14,
    List<MapMarker> markers = const [],
    void Function(LatLng)? onTap,
  }) {
    return gm.GoogleMap(
      initialCameraPosition: gm.CameraPosition(
        target: gm.LatLng(initialCenter.lat, initialCenter.lng),
        zoom: initialZoom,
      ),
      myLocationEnabled: true,
      myLocationButtonEnabled: false,
      mapToolbarEnabled: false,
      zoomControlsEnabled: false,
      compassEnabled: false,
      markers: {
        for (final m in markers)
          gm.Marker(
            markerId: gm.MarkerId(m.id),
            position: gm.LatLng(m.position.lat, m.position.lng),
            infoWindow: m.title != null ? gm.InfoWindow(title: m.title) : gm.InfoWindow.noText,
          ),
      },
      onTap: onTap == null ? null : (p) => onTap(LatLng(p.latitude, p.longitude)),
    );
  }

  @override
  Future<List<LatLng>> route(LatLng from, LatLng to) async {
    return [from, to];
  }

  @override
  Future<Duration> eta(LatLng from, LatLng to) async => const Duration(minutes: 5);

  @override
  Future<String?> reverseGeocode(LatLng point) async => null;

  @override
  Future<List<PlaceSuggestion>> placeAutocomplete(String query, {LatLng? near}) async => const [];
}
