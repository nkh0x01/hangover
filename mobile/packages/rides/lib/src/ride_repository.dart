import 'package:maps/maps.dart';
import 'package:network/network.dart';

import 'models/fare_estimate.dart';
import 'models/ride.dart';

/// Customer-facing rides API surface. Errors surface as ApiError via
/// the network package's ErrorInterceptor.
class RideRepository {
  RideRepository({required this.client});

  final ApiClient client;

  Future<FareEstimate> estimate({
    required LatLng pickup,
    required LatLng dropoff,
    String vehicleType = 'scooter_electric',
  }) async {
    final response = await client.dio.post('/customer/rides/estimates', data: {
      'pickup': {'lat': pickup.lat, 'lng': pickup.lng},
      'dropoff': {'lat': dropoff.lat, 'lng': dropoff.lng},
      'vehicle_type': vehicleType,
    });
    final data = (response.data as Map<String, Object?>)['data'] as Map<String, Object?>;
    return FareEstimate.fromJson(data);
  }

  Future<Ride> requestRide({
    required String fareEstimateId,
    required LatLng pickup,
    required String pickupAddress,
    required LatLng dropoff,
    required String dropoffAddress,
    required String paymentMethod,
    String? note,
  }) async {
    final response = await client.dio.post('/customer/rides', data: {
      'fare_estimate_id': fareEstimateId,
      'pickup': {'lat': pickup.lat, 'lng': pickup.lng, 'address': pickupAddress},
      'dropoff': {'lat': dropoff.lat, 'lng': dropoff.lng, 'address': dropoffAddress},
      'payment_method': paymentMethod,
      if (note != null) 'note': note,
    });
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<Ride?> active() async {
    final response = await client.dio.get('/customer/rides/active');
    final data = (response.data as Map<String, Object?>)['data'];
    return data == null ? null : Ride.fromJson(data as Map<String, Object?>);
  }

  Future<Ride> show(String ulid) async {
    final response = await client.dio.get('/customer/rides/$ulid');
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<Ride> cancel(String ulid, String reason) async {
    final response =
        await client.dio.patch('/customer/rides/$ulid/cancel', data: {'reason': reason});
    return _parseRide(response.data as Map<String, Object?>);
  }

  Future<List<LatLng>> nearbyDrivers({required LatLng center, double radiusKm = 3.0}) async {
    final response = await client.dio.get('/customer/drivers/nearby', queryParameters: {
      'lat': center.lat,
      'lng': center.lng,
      'radius_km': radiusKm,
    });
    final list = ((response.data as Map<String, Object?>)['data'] as List).cast<Map<String, Object?>>();
    return list
        .map((row) => LatLng((row['lat'] as num).toDouble(), (row['lng'] as num).toDouble()))
        .toList(growable: false);
  }

  Ride _parseRide(Map<String, Object?> envelope) {
    final data = (envelope['data'] as Map).cast<String, Object?>();
    return Ride.fromJson(data);
  }
}
