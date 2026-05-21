class FareEstimate {
  FareEstimate({
    required this.id,
    required this.currency,
    required this.totalAmount,
    required this.distanceKm,
    required this.durationMin,
    required this.surgeMultiplier,
    required this.expiresAt,
  });

  final String id;
  final String currency;
  final double totalAmount;
  final double distanceKm;
  final int durationMin;
  final double surgeMultiplier;
  final DateTime expiresAt;

  factory FareEstimate.fromJson(Map<String, Object?> json) => FareEstimate(
        id: json['id']! as String,
        currency: json['currency']! as String,
        totalAmount: (json['total_amount'] as num).toDouble(),
        distanceKm: (json['distance_km'] as num).toDouble(),
        durationMin: (json['duration_min'] as num).toInt(),
        surgeMultiplier: (json['surge_multiplier'] as num).toDouble(),
        expiresAt: DateTime.parse(json['expires_at']! as String),
      );
}
