import 'package:maps/maps.dart';

class DriverLocationPayload {
  DriverLocationPayload({
    required this.rideId,
    required this.position,
    required this.heading,
    required this.speedKmh,
    required this.at,
  });

  final String rideId;
  final LatLng position;
  final int heading;
  final double speedKmh;
  final DateTime at;

  factory DriverLocationPayload.fromJson(Map<String, Object?> json) => DriverLocationPayload(
        rideId: json['ride_ulid']! as String,
        position: LatLng((json['lat'] as num).toDouble(), (json['lng'] as num).toDouble()),
        heading: (json['heading'] as num?)?.toInt() ?? 0,
        speedKmh: (json['speed_kmh'] as num?)?.toDouble() ?? 0,
        at: DateTime.parse(json['at']! as String),
      );
}
