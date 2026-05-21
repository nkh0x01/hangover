import 'package:flutter/widgets.dart';

import 'lat_lng.dart';

/// Abstraction so feature code is independent of the concrete map SDK.
/// Phase 2 ships GoogleMapsProvider; Mapbox is plugged in via the same
/// interface without changing call sites.
abstract class MapProvider {
  Widget mapWidget({
    required LatLng initialCenter,
    double initialZoom = 14,
    List<MapMarker> markers = const [],
    void Function(LatLng)? onTap,
  });

  Future<List<LatLng>> route(LatLng from, LatLng to);
  Future<Duration> eta(LatLng from, LatLng to);
  Future<String?> reverseGeocode(LatLng point);
  Future<List<PlaceSuggestion>> placeAutocomplete(String query, {LatLng? near});
}

class MapMarker {
  const MapMarker({required this.id, required this.position, this.title});
  final String id;
  final LatLng position;
  final String? title;
}

class PlaceSuggestion {
  const PlaceSuggestion({required this.placeId, required this.title, this.subtitle});
  final String placeId;
  final String title;
  final String? subtitle;
}
