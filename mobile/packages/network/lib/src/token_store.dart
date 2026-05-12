import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Bearer token storage with Keychain (iOS) / Keystore (Android) backing.
/// Keys are namespaced per app so the customer and driver tokens don't
/// collide on shared installs.
class TokenStore {
  TokenStore({required String namespace})
      : _namespace = namespace,
        _storage = const FlutterSecureStorage(
          iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
          aOptions: AndroidOptions(encryptedSharedPreferences: true),
        );

  final String _namespace;
  final FlutterSecureStorage _storage;

  String get _tokenKey => 'hng.$_namespace.token';
  String get _expiresKey => 'hng.$_namespace.token_expires';
  String get _deviceKey => 'hng.$_namespace.device_uuid';

  Future<String?> read() => _storage.read(key: _tokenKey);
  Future<DateTime?> readExpiry() async {
    final raw = await _storage.read(key: _expiresKey);
    if (raw == null) return null;
    return DateTime.tryParse(raw);
  }

  Future<void> write({required String token, required DateTime expiresAt}) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _expiresKey, value: expiresAt.toIso8601String());
  }

  Future<String?> readDeviceUuid() => _storage.read(key: _deviceKey);
  Future<void> writeDeviceUuid(String uuid) =>
      _storage.write(key: _deviceKey, value: uuid);

  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _expiresKey);
  }
}
