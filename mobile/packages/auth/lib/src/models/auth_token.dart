class AuthToken {
  AuthToken({
    required this.token,
    required this.expiresAt,
    required this.abilities,
    this.userType,
  });

  final String token;
  final DateTime expiresAt;
  final List<String> abilities;
  final String? userType;

  factory AuthToken.fromJson(Map<String, Object?> json) {
    final user = (json['user'] as Map?)?.cast<String, Object?>();

    return AuthToken(
      token: json['token']! as String,
      expiresAt: DateTime.parse(json['expires_at']! as String),
      abilities: (json['abilities'] as List).cast<String>(),
      userType: user?['type'] as String?,
    );
  }
}
