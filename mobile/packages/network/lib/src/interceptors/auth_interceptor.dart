import 'package:dio/dio.dart';

import '../token_store.dart';

/// Attaches the Sanctum bearer token. On a 401 it does NOT auto-refresh
/// — that policy lives in the auth package so the network layer stays
/// dumb. The 401 is surfaced via the Dio response and the auth layer's
/// interceptor decides what to do.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({required this.tokenStore});

  final TokenStore tokenStore;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await tokenStore.read();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }
}
