import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import 'token_store.dart';

class NetworkDiagnosticsState {
  const NetworkDiagnosticsState({
    this.lastRequestMethod,
    this.lastRequestUrl,
    this.lastResponseStatus,
    this.lastResponseBodyExcerpt,
    this.lastNetworkException,
    this.tokenPresent,
    this.lastAuthAbilities = const [],
    this.lastAuthUserType,
  });

  final String? lastRequestMethod;
  final String? lastRequestUrl;
  final int? lastResponseStatus;
  final String? lastResponseBodyExcerpt;
  final String? lastNetworkException;
  final bool? tokenPresent;
  final List<String> lastAuthAbilities;
  final String? lastAuthUserType;

  NetworkDiagnosticsState copyWith({
    String? lastRequestMethod,
    String? lastRequestUrl,
    int? lastResponseStatus,
    bool clearResponseStatus = false,
    String? lastResponseBodyExcerpt,
    bool clearResponseBodyExcerpt = false,
    String? lastNetworkException,
    bool clearNetworkException = false,
    bool? tokenPresent,
    List<String>? lastAuthAbilities,
    String? lastAuthUserType,
    bool clearLastAuthUserType = false,
  }) {
    return NetworkDiagnosticsState(
      lastRequestMethod: lastRequestMethod ?? this.lastRequestMethod,
      lastRequestUrl: lastRequestUrl ?? this.lastRequestUrl,
      lastResponseStatus: clearResponseStatus
          ? null
          : (lastResponseStatus ?? this.lastResponseStatus),
      lastResponseBodyExcerpt: clearResponseBodyExcerpt
          ? null
          : (lastResponseBodyExcerpt ?? this.lastResponseBodyExcerpt),
      lastNetworkException: clearNetworkException
          ? null
          : (lastNetworkException ?? this.lastNetworkException),
      tokenPresent: tokenPresent ?? this.tokenPresent,
      lastAuthAbilities: lastAuthAbilities ?? this.lastAuthAbilities,
      lastAuthUserType: clearLastAuthUserType
          ? null
          : (lastAuthUserType ?? this.lastAuthUserType),
    );
  }
}

class NetworkDiagnosticsRecorder
    extends ValueNotifier<NetworkDiagnosticsState> {
  NetworkDiagnosticsRecorder() : super(const NetworkDiagnosticsState());

  void recordRequest(RequestOptions options, {bool? tokenPresent}) {
    value = value.copyWith(
      lastRequestMethod: options.method.toUpperCase(),
      lastRequestUrl: options.uri.toString(),
      clearResponseStatus: true,
      clearResponseBodyExcerpt: true,
      clearNetworkException: true,
      tokenPresent: tokenPresent,
    );
  }

  void recordResponse(Response<dynamic> response) {
    value = value.copyWith(
      lastRequestMethod: response.requestOptions.method.toUpperCase(),
      lastRequestUrl: response.requestOptions.uri.toString(),
      lastResponseStatus: response.statusCode,
      lastResponseBodyExcerpt: _excerpt(response.data),
      clearNetworkException: true,
    );
  }

  void recordError(DioException error) {
    value = value.copyWith(
      lastRequestMethod: error.requestOptions.method.toUpperCase(),
      lastRequestUrl: error.requestOptions.uri.toString(),
      lastResponseStatus: error.response?.statusCode,
      clearResponseStatus: error.response?.statusCode == null,
      lastResponseBodyExcerpt:
          error.response == null ? null : _excerpt(error.response?.data),
      clearResponseBodyExcerpt: error.response == null,
      lastNetworkException: _exceptionLabel(error),
    );
  }

  void recordAuthResult({
    required List<String> abilities,
    required String? userType,
  }) {
    value = value.copyWith(
      lastAuthAbilities: List<String>.unmodifiable(abilities),
      lastAuthUserType: userType,
      clearLastAuthUserType: userType == null,
    );
  }

  static String _exceptionLabel(DioException error) {
    final status = error.response?.statusCode;
    final type = error.type.name;
    final message = error.message ?? error.error?.toString() ?? 'unknown';
    return status == null
        ? '$type: $message'
        : 'HTTP $status / $type: $message';
  }

  static String _excerpt(Object? value) {
    final redacted = _redact(value);
    final encoded = _safeEncode(redacted);
    if (encoded.length <= 700) return encoded;
    return '${encoded.substring(0, 700)}...';
  }

  static String _safeEncode(Object? value) {
    try {
      return jsonEncode(value);
    } catch (_) {
      return value.toString();
    }
  }

  static Object? _redact(Object? value) {
    if (value is Map) {
      return value.map((key, child) {
        final keyText = key.toString().toLowerCase();
        if (keyText.contains('token') ||
            keyText.contains('authorization') ||
            keyText.contains('api_key') ||
            keyText == 'code') {
          return MapEntry(key, '[redacted]');
        }
        return MapEntry(key, _redact(child));
      });
    }
    if (value is List) {
      return value.map(_redact).toList(growable: false);
    }
    return value;
  }
}

class DiagnosticsInterceptor extends Interceptor {
  DiagnosticsInterceptor({
    required this.tokenStore,
    required this.recorder,
  });

  final TokenStore tokenStore;
  final NetworkDiagnosticsRecorder recorder;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await tokenStore.read();
    recorder.recordRequest(
      options,
      tokenPresent: token != null && token.trim().isNotEmpty,
    );
    handler.next(options);
  }

  @override
  void onResponse(
      Response<dynamic> response, ResponseInterceptorHandler handler) {
    recorder.recordResponse(response);
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    recorder.recordError(err);
    handler.next(err);
  }
}
