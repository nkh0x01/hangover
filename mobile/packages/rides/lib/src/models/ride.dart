import 'package:maps/maps.dart';

import 'ride_status.dart';

class Ride {
  Ride({
    required this.id,
    required this.status,
    required this.pickup,
    required this.dropoff,
    required this.pickupAddress,
    required this.dropoffAddress,
    required this.quotedAmount,
    required this.currency,
    required this.paymentMethod,
    this.finalAmount,
    this.surgeMultiplier = 1.0,
    this.driver,
    this.timestamps = const {},
    this.cancellationReason,
  });

  final String id;
  final RideStatus status;
  final LatLng pickup;
  final LatLng dropoff;
  final String pickupAddress;
  final String dropoffAddress;
  final double quotedAmount;
  final double? finalAmount;
  final String currency;
  final String paymentMethod;
  final double surgeMultiplier;
  final RideDriverSnapshot? driver;
  final Map<String, DateTime> timestamps;
  final String? cancellationReason;

  Ride copyWith({RideStatus? status, RideDriverSnapshot? driver, double? finalAmount}) =>
      Ride(
        id: id,
        status: status ?? this.status,
        pickup: pickup,
        dropoff: dropoff,
        pickupAddress: pickupAddress,
        dropoffAddress: dropoffAddress,
        quotedAmount: quotedAmount,
        currency: currency,
        paymentMethod: paymentMethod,
        finalAmount: finalAmount ?? this.finalAmount,
        surgeMultiplier: surgeMultiplier,
        driver: driver ?? this.driver,
        timestamps: timestamps,
        cancellationReason: cancellationReason,
      );

  factory Ride.fromJson(Map<String, Object?> json) {
    final pickup = (json['pickup'] as Map).cast<String, Object?>();
    final dropoff = (json['dropoff'] as Map).cast<String, Object?>();
    final fare = (json['fare'] as Map).cast<String, Object?>();
    final timestamps = ((json['timestamps'] as Map?) ?? const {}).cast<String, Object?>();
    final driverJson = json['driver'] as Map<String, Object?>?;

    return Ride(
      id: json['id']! as String,
      status: RideStatus.fromWire(json['status']! as String),
      pickup: LatLng((pickup['lat'] as num).toDouble(), (pickup['lng'] as num).toDouble()),
      dropoff: LatLng((dropoff['lat'] as num).toDouble(), (dropoff['lng'] as num).toDouble()),
      pickupAddress: pickup['address'] as String? ?? '',
      dropoffAddress: dropoff['address'] as String? ?? '',
      quotedAmount: (fare['quoted'] as num).toDouble(),
      finalAmount: (fare['final'] as num?)?.toDouble(),
      currency: fare['currency'] as String? ?? 'GEL',
      surgeMultiplier: (fare['surge_multiplier'] as num?)?.toDouble() ?? 1.0,
      paymentMethod: json['payment_method'] as String? ?? 'cash',
      driver: driverJson != null ? RideDriverSnapshot.fromJson(driverJson) : null,
      timestamps: timestamps.map(
        (k, v) => MapEntry(k, v == null ? DateTime(0) : DateTime.parse(v as String)),
      )..removeWhere((_, v) => v.year == 0),
      cancellationReason: json['cancellation_reason'] as String?,
    );
  }
}

class RideDriverSnapshot {
  RideDriverSnapshot({
    required this.id,
    required this.ratingAvg,
    this.name,
    this.phone,
    this.vehicle,
  });

  final String id;
  final double ratingAvg;
  final String? name;
  final String? phone;
  final RideVehicleSnapshot? vehicle;

  factory RideDriverSnapshot.fromJson(Map<String, Object?> json) {
    final v = json['vehicle'] as Map<String, Object?>?;
    return RideDriverSnapshot(
      id: json['id']! as String,
      name: json['name'] as String?,
      phone: json['phone'] as String?,
      ratingAvg: (json['rating_avg'] as num?)?.toDouble() ?? 0.0,
      vehicle: v != null ? RideVehicleSnapshot.fromJson(v) : null,
    );
  }
}

class RideVehicleSnapshot {
  RideVehicleSnapshot({required this.brand, required this.model, required this.plate, this.color});

  final String brand;
  final String model;
  final String plate;
  final String? color;

  factory RideVehicleSnapshot.fromJson(Map<String, Object?> json) => RideVehicleSnapshot(
        brand: json['brand'] as String? ?? '',
        model: json['model'] as String? ?? '',
        plate: json['plate'] as String? ?? '',
        color: json['color'] as String?,
      );
}
