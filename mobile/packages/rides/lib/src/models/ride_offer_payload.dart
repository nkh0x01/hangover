class RideOfferPayload {
  RideOfferPayload({
    required this.rideId,
    required this.expiresAt,
    required this.pickupAddress,
    required this.dropoffAddress,
    required this.distanceToPickupM,
    required this.fareAmount,
    required this.currency,
  });

  final String rideId;
  final DateTime expiresAt;
  final String pickupAddress;
  final String dropoffAddress;
  final int distanceToPickupM;
  final double fareAmount;
  final String currency;

  factory RideOfferPayload.fromJson(Map<String, Object?> json) {
    final pickup = (json['pickup'] as Map).cast<String, Object?>();
    final dropoff = (json['dropoff'] as Map).cast<String, Object?>();
    final fare = (json['fare'] as Map).cast<String, Object?>();

    return RideOfferPayload(
      rideId: json['ride_ulid']! as String,
      expiresAt: DateTime.parse(json['expires_at']! as String),
      pickupAddress: pickup['address'] as String? ?? '',
      dropoffAddress: dropoff['address'] as String? ?? '',
      distanceToPickupM: (json['distance_to_pickup_m'] as num).toInt(),
      fareAmount: (fare['amount'] as num).toDouble(),
      currency: fare['currency']! as String,
    );
  }
}
