class AuthToken {
  AuthToken({
    required this.token,
    required this.expiresAt,
    required this.abilities,
  });

  final String token;
  final DateTime expiresAt;
  final List<String> abilities;

  factory AuthToken.fromJson(Map<String, Object?> json) => AuthToken(
        token: json['token']! as String,
        expiresAt: DateTime.parse(json['expires_at']! as String),
        abilities: (json['abilities'] as List).cast<String>(),
      );
}
