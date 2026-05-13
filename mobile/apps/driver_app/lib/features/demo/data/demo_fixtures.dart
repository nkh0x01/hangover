import 'package:maps/maps.dart';
import 'package:rides/rides.dart';

/// Hard-coded preview data for the driver app. Mirrors the shape of
/// customer-app fixtures so screens render against a realistic ride.
class DriverDemoFixtures {
  static const driverPosition = LatLng(41.7151, 44.8271); // Tbilisi center
  static const pickup = LatLng(41.7140, 44.8240);
  static const dropoff = LatLng(41.7044, 44.7794); // Vake
  static const pickupAddress = 'Liberty Square, Tbilisi';
  static const dropoffAddress = 'Chavchavadze Ave 37, Vake';
  static const currency = 'GEL';
  static const fareAmount = 8.50;

  static RideOfferPayload offer() => RideOfferPayload(
        rideId: 'demo-ride-1',
        expiresAt: DateTime.now().add(const Duration(seconds: 20)),
        pickupAddress: pickupAddress,
        dropoffAddress: dropoffAddress,
        distanceToPickupM: 320,
        fareAmount: fareAmount,
        currency: currency,
      );

  static Ride ride(RideStatus status) => Ride(
        id: 'demo-ride-1',
        status: status,
        pickup: pickup,
        dropoff: dropoff,
        pickupAddress: pickupAddress,
        dropoffAddress: dropoffAddress,
        quotedAmount: fareAmount,
        currency: currency,
        paymentMethod: 'cash',
        finalAmount: status == RideStatus.completed ? fareAmount : null,
        timestamps: const {},
      );
}
