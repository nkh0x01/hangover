import 'package:core/core.dart';
import 'package:dio/dio.dart';

import 'interceptors/auth_interceptor.dart';
import 'interceptors/error_interceptor.dart';
import 'interceptors/headers_interceptor.dart';
import 'interceptors/idempotency_interceptor.dart';
import 'token_store.dart';

/// Centralised Dio configuration shared by both apps. Interceptors are
/// composed in a deterministic order; tests can replace any of them.
class ApiClient {
  ApiClient({
    required EnvConfig env,
    required TokenStore tokenStore,
    required String appPlatform,
    required String appVersion,
    required Future<String> Function() deviceUuidProvider,
  }) {
    dio = Dio(
      BaseOptions(
        baseUrl: '${env.apiBaseUrl}/api/v1',
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 20),
        sendTimeout: const Duration(seconds: 20),
        contentType: 'application/json',
        responseType: ResponseType.json,
        validateStatus: (status) => status != null && status < 500,
      ),
    );

    dio.interceptors.addAll([
      HeadersInterceptor(
        platform: appPlatform,
        appVersion: appVersion,
        deviceUuidProvider: deviceUuidProvider,
      ),
      AuthInterceptor(tokenStore: tokenStore),
      IdempotencyInterceptor(),
      ErrorInterceptor(),
    ]);
  }

  late final Dio dio;
}
