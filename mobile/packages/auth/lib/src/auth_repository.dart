import 'package:network/network.dart';

import 'models/auth_token.dart';

/// Thin wrapper around the /auth/* endpoints. Feature layer (UI) calls
/// these directly through Riverpod providers; no extra service layer.
class AuthRepository {
  AuthRepository({
    required this.client,
    required this.tokenStore,
    this.diagnostics,
  });

  final ApiClient client;
  final TokenStore tokenStore;
  final NetworkDiagnosticsRecorder? diagnostics;

  Future<void> requestOtp(
      {required String phone, required String purpose}) async {
    await client.dio.post<Map<String, Object?>>('/auth/otp/request', data: {
      'phone': phone,
      'purpose': purpose,
    });
  }

  Future<AuthToken> verifyOtp({
    required String phone,
    required String code,
    required String purpose,
    required String deviceUuid,
    required String platform,
    required String appVersion,
    String? fcmToken,
  }) async {
    final response =
        await client.dio.post<Map<String, Object?>>('/auth/otp/verify', data: {
      'phone': phone,
      'code': code,
      'purpose': purpose,
      'device_uuid': deviceUuid,
      'platform': platform,
      'app_version': appVersion,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });
    final data = response.data!['data'] as Map<String, Object?>;
    final token = AuthToken.fromJson(data);

    await tokenStore.write(token: token.token, expiresAt: token.expiresAt);
    await tokenStore.writeAuthContext(
      abilities: token.abilities,
      userType: token.userType,
    );
    await tokenStore.writeDeviceUuid(deviceUuid);
    diagnostics?.recordAuthResult(
      abilities: token.abilities,
      userType: token.userType,
    );

    return token;
  }

  Future<AuthToken> refresh() async {
    final response =
        await client.dio.post<Map<String, Object?>>('/auth/refresh');
    final data = response.data!['data'] as Map<String, Object?>;
    final token = AuthToken.fromJson(data);
    await tokenStore.write(token: token.token, expiresAt: token.expiresAt);
    await tokenStore.writeAuthContext(
      abilities: token.abilities,
      userType: token.userType,
    );
    return token;
  }

  Future<void> logout() async {
    try {
      await client.dio.post<void>('/auth/logout');
    } catch (_) {
      // ignore — we still want to wipe local state.
    } finally {
      await tokenStore.clear();
    }
  }
}
