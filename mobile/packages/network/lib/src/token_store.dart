import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Bearer token storage with Keychain (iOS) / Keystore (Android) backing.
/// Keys are namespaced per app so the customer and driver tokens don't
/// collide on shared installs.
class TokenStore {
  TokenStore({required String namespace})
      : _namespace = namespace,
        _storage = const FlutterSecureStorage(
          iOptions:
              IOSOptions(accessibility: KeychainAccessibility.first_unlock),
          aOptions: AndroidOptions(encryptedSharedPreferences: true),
        );

  final String _namespace;
  final FlutterSecureStorage _storage;

  String get _tokenKey => 'hng.$_namespace.token';
  String get _expiresKey => 'hng.$_namespace.token_expires';
  String get _deviceKey => 'hng.$_namespace.device_uuid';
  String get _abilitiesKey => 'hng.$_namespace.token_abilities';
  String get _userTypeKey => 'hng.$_namespace.auth_user_type';

  Future<String?> read() => _storage.read(key: _tokenKey);
  Future<DateTime?> readExpiry() async {
    final raw = await _storage.read(key: _expiresKey);
    if (raw == null) return null;
    return DateTime.tryParse(raw);
  }

  Future<void> write(
      {required String token, required DateTime expiresAt}) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _expiresKey, value: expiresAt.toIso8601String());
  }

  Future<List<String>> readAuthAbilities() async {
    final raw = await _storage.read(key: _abilitiesKey);
    if (raw == null || raw.trim().isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .map((item) => item.toString().trim())
          .where((item) => item.isNotEmpty)
          .toList(growable: false);
    } catch (_) {
      return const [];
    }
  }

  Future<String?> readAuthUserType() => _storage.read(key: _userTypeKey);

  Future<void> writeAuthContext({
    required List<String> abilities,
    required String? userType,
  }) async {
    await _storage.write(key: _abilitiesKey, value: jsonEncode(abilities));
    if (userType == null || userType.trim().isEmpty) {
      await _storage.delete(key: _userTypeKey);
    } else {
      await _storage.write(key: _userTypeKey, value: userType.trim());
    }
  }

  Future<String?> readDeviceUuid() => _storage.read(key: _deviceKey);
  Future<void> writeDeviceUuid(String uuid) =>
      _storage.write(key: _deviceKey, value: uuid);

  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _expiresKey);
    await _storage.delete(key: _abilitiesKey);
    await _storage.delete(key: _userTypeKey);
  }
}
