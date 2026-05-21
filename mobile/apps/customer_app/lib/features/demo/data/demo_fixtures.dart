import 'package:maps/maps.dart';
import 'package:rides/rides.dart';

/// Hard-coded preview data. Kept inline so the demo flow never touches
/// the network. Only consumed when [DemoModeController.enabled] is true,
/// which itself is gated by the dev flavor.
class DemoFixtures {
  static const pickup = LatLng(41.7151, 44.8271); // Tbilisi center
  static const dropoff = LatLng(41.7044, 44.7794); // Vake
  static const driverAtPickup = LatLng(41.7140, 44.8240);
  static const driverEnRoute = LatLng(41.7100, 44.8100);

  static const pickupAddress = 'Liberty Square, Tbilisi';
  static const dropoffAddress = 'Chavchavadze Ave 37, Vake';

  static FareEstimate fareEstimate() => FareEstimate(
        id: 'demo-fare-1',
        currency: 'GEL',
        totalAmount: 8.50,
        distanceKm: 4.2,
        durationMin: 14,
        surgeMultiplier: 1.0,
        expiresAt: DateTime.now().add(const Duration(minutes: 5)),
      );

  static RideDriverSnapshot driver() => RideDriverSnapshot(
        id: 'demo-driver-1',
        name: 'გიორგი მ. · Giorgi M.',
        phone: '+995 555 000 000',
        ratingAvg: 4.9,
        vehicle: RideVehicleSnapshot(
          brand: 'Honda',
          model: 'Wave 110',
          plate: 'AA-001-AA',
          color: 'emerald',
        ),
      );

  /// Build a [Ride] for the given [status], reusing the same id across
  /// stages so the tracking page never thinks the ride changed.
  static Ride ride(RideStatus status) {
    final fare = fareEstimate();
    final hasDriver = status.hasDriver || status == RideStatus.completed;
    return Ride(
      id: 'demo-ride-1',
      status: status,
      pickup: pickup,
      dropoff: dropoff,
      pickupAddress: pickupAddress,
      dropoffAddress: dropoffAddress,
      quotedAmount: fare.totalAmount,
      currency: fare.currency,
      paymentMethod: 'cash',
      surgeMultiplier: fare.surgeMultiplier,
      finalAmount: status == RideStatus.completed ? fare.totalAmount : null,
      driver: hasDriver ? driver() : null,
      timestamps: const {},
    );
  }

  /// Where the driver is shown on the customer's tracking map at each
  /// stage.
  static LatLng? driverLocationFor(RideStatus status) => switch (status) {
        RideStatus.accepted || RideStatus.driverArriving => driverEnRoute,
        RideStatus.driverArrived => driverAtPickup,
        RideStatus.inProgress => dropoff,
        _ => null,
      };
}
