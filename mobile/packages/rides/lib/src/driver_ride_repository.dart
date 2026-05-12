import 'package:maps/maps.dart';
import 'package:network/network.dart';

import 'models/ride.dart';

/// Driver-facing rides API surface.
class DriverRideRepository {
  DriverRideRepository({required this.client});

  final ApiClient client;

  Future<void> goOnline({required LatLng position, int? vehicleId}) async {
    await client.dio.post('/driver/status/online', data: {
      'lat': position.lat,
      'lng': position.lng,
      if (vehicleId != null) 'vehicle_id': vehicleId,
    });
  }

  Future<void> goOffline() => client.dio.post('/driver/status/offline');

  Future<void> heartbeat({
    required LatLng position,
    int heading = 0,
    double speedKmh = 0,
    double? accuracyM,
    int? batteryPct,
  }) async {
    await client.dio.post('/driver/location', data: {
      'lat': position.lat,
      'lng': position.lng,
      'heading': heading,
      'speed_kmh': speedKmh,
      if (accuracyM != null) 'accuracy_m': accuracyM,
      if (batteryPct != null) 'battery_pct': batteryPct,
    });
  }

  Future<Ride?> active() async {
    final response = await client.dio.get('/driver/rides/active');
    final data = (response.data as Map<String, Object?>)['data'];
    return data == null ? null : Ride.fromJson(data as Map<String, Object?>);
  }

  Future<Ride> acceptOffer(String ulid) async {
    final response = await client.dio.post('/driver/offers/$ulid/accept');
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<void> rejectOffer(String ulid) =>
      client.dio.post('/driver/offers/$ulid/reject');

  Future<Ride> arriving(String ulid) async {
    final response = await client.dio.post('/driver/rides/$ulid/arriving');
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<Ride> arrived(String ulid) async {
    final response = await client.dio.post('/driver/rides/$ulid/arrived');
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<Ride> start(String ulid) async {
    final response = await client.dio.post('/driver/rides/$ulid/start');
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<Ride> complete(String ulid, {double? finalAmount, int? waitingSeconds}) async {
    final response = await client.dio.post('/driver/rides/$ulid/complete', data: {
      if (finalAmount != null) 'final_amount': finalAmount,
      if (waitingSeconds != null) 'waiting_seconds': waitingSeconds,
    });
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<Ride> cancel(String ulid, String reason) async {
    final response = await client.dio.patch('/driver/rides/$ulid/cancel', data: {'reason': reason});
    return _parseRide(response.data as Map<String, Object?>);
  }

  Ride _parseRide(Map<String, Object?> envelope) {
    final data = (envelope['data'] as Map).cast<String, Object?>();
    return Ride.fromJson(data);
  }
}
