import 'dart:io';

import 'package:dio/dio.dart';

/// Stamps every outbound request with the standard X-* headers the API
/// requires.
class HeadersInterceptor extends Interceptor {
  HeadersInterceptor({
    required this.platform,
    required this.appVersion,
    required this.deviceUuidProvider,
  });

  final String platform;
  final String appVersion;
  final Future<String> Function() deviceUuidProvider;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    options.headers['X-Platform'] = platform;
    options.headers['X-App-Version'] = appVersion;
    options.headers['X-Device-Id'] = await deviceUuidProvider();
    options.headers['Accept-Language'] = Platform.localeName.split('_').first;
    handler.next(options);
  }
}
