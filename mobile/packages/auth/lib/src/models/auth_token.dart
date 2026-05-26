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
      abilities: _stringList(json['abilities']),
      userType: _string(user?['type'] ?? json['user_type'] ?? json['type']),
    );
  }

  static String? _string(Object? value) {
    if (value == null) return null;
    final text = value.toString().trim();
    return text.isEmpty ? null : text;
  }

  static List<String> _stringList(Object? value) {
    if (value is! List) return const [];
    return value
        .map((item) => item.toString().trim())
        .where((item) => item.isNotEmpty)
        .toList(growable: false);
  }
}
