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
    this.lastDriverHasProfile,
    this.lastDriverApplicationStatus,
    this.lastDriverNeedsApplication,
    this.lastDriverCanSubmitApplication,
    this.lastDriverCanGoOnline,
    this.lastDriverCannotGoOnlineReason,
  });

  final String? lastRequestMethod;
  final String? lastRequestUrl;
  final int? lastResponseStatus;
  final String? lastResponseBodyExcerpt;
  final String? lastNetworkException;
  final bool? tokenPresent;
  final List<String> lastAuthAbilities;
  final String? lastAuthUserType;
  final bool? lastDriverHasProfile;
  final String? lastDriverApplicationStatus;
  final bool? lastDriverNeedsApplication;
  final bool? lastDriverCanSubmitApplication;
  final bool? lastDriverCanGoOnline;
  final String? lastDriverCannotGoOnlineReason;

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
    bool? lastDriverHasProfile,
    String? lastDriverApplicationStatus,
    bool? lastDriverNeedsApplication,
    bool? lastDriverCanSubmitApplication,
    bool? lastDriverCanGoOnline,
    String? lastDriverCannotGoOnlineReason,
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
      lastDriverHasProfile: lastDriverHasProfile ?? this.lastDriverHasProfile,
      lastDriverApplicationStatus:
          lastDriverApplicationStatus ?? this.lastDriverApplicationStatus,
      lastDriverNeedsApplication:
          lastDriverNeedsApplication ?? this.lastDriverNeedsApplication,
      lastDriverCanSubmitApplication:
          lastDriverCanSubmitApplication ?? this.lastDriverCanSubmitApplication,
      lastDriverCanGoOnline:
          lastDriverCanGoOnline ?? this.lastDriverCanGoOnline,
      lastDriverCannotGoOnlineReason:
          lastDriverCannotGoOnlineReason ?? this.lastDriverCannotGoOnlineReason,
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
    var next = value.copyWith(
      lastRequestMethod: response.requestOptions.method.toUpperCase(),
      lastRequestUrl: response.requestOptions.uri.toString(),
      lastResponseStatus: response.statusCode,
      lastResponseBodyExcerpt: _excerpt(response.data),
      clearNetworkException: true,
    );
    final auth = _authSnapshot(response.data);
    if (auth != null) {
      next = next.copyWith(
        lastAuthAbilities:
            auth.abilities.isEmpty ? null : List.unmodifiable(auth.abilities),
        lastAuthUserType: auth.userType,
        lastDriverHasProfile: auth.hasDriverProfile,
        lastDriverApplicationStatus: auth.applicationStatus,
        lastDriverNeedsApplication: auth.needsApplication,
        lastDriverCanSubmitApplication: auth.canSubmitApplication,
        lastDriverCanGoOnline: auth.canGoOnline,
        lastDriverCannotGoOnlineReason: auth.reasonIfCannotGoOnline,
      );
    }
    value = next;
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

  void recordAuthPayload(Object? payload) {
    final auth = _authSnapshot(payload);
    if (auth == null) return;
    value = value.copyWith(
      lastAuthAbilities:
          auth.abilities.isEmpty ? null : List.unmodifiable(auth.abilities),
      lastAuthUserType: auth.userType,
      lastDriverHasProfile: auth.hasDriverProfile,
      lastDriverApplicationStatus: auth.applicationStatus,
      lastDriverNeedsApplication: auth.needsApplication,
      lastDriverCanSubmitApplication: auth.canSubmitApplication,
      lastDriverCanGoOnline: auth.canGoOnline,
      lastDriverCannotGoOnlineReason: auth.reasonIfCannotGoOnline,
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

  static _AuthDiagnosticsSnapshot? _authSnapshot(Object? payload) {
    final root = _map(payload);
    if (root == null) return null;
    final data = _map(root['data']) ?? root;
    final token = _map(data['token']);
    final user = _map(data['user']) ?? data;
    final driverContext =
        _map(data['driver_context']) ?? _map(user['driver_context']);

    final abilities = _stringList(data['abilities'] ?? token?['abilities']);
    final userType = _string(
      user['type'] ?? data['user_type'] ?? data['type'],
    );
    if (abilities.isEmpty && userType == null && driverContext == null) {
      return null;
    }

    return _AuthDiagnosticsSnapshot(
      abilities: abilities,
      userType: userType,
      hasDriverProfile: _bool(driverContext?['has_driver_profile']),
      applicationStatus: _string(driverContext?['application_status']),
      needsApplication: _bool(driverContext?['needs_application']),
      canSubmitApplication: _bool(driverContext?['can_submit_application']),
      canGoOnline: _bool(driverContext?['can_go_online']),
      reasonIfCannotGoOnline:
          _string(driverContext?['reason_if_cannot_go_online']),
    );
  }

  static Map<String, Object?>? _map(Object? value) {
    if (value is Map<String, Object?>) return value;
    if (value is Map) return value.cast<String, Object?>();
    return null;
  }

  static List<String> _stringList(Object? value) {
    if (value is! List) return const [];
    return value
        .map((item) => item.toString().trim())
        .where((item) => item.isNotEmpty)
        .toList(growable: false);
  }

  static String? _string(Object? value) {
    if (value == null) return null;
    final text = value.toString().trim();
    return text.isEmpty ? null : text;
  }

  static bool? _bool(Object? value) {
    if (value is bool) return value;
    return null;
  }
}

class _AuthDiagnosticsSnapshot {
  const _AuthDiagnosticsSnapshot({
    required this.abilities,
    this.userType,
    this.hasDriverProfile,
    this.applicationStatus,
    this.needsApplication,
    this.canSubmitApplication,
    this.canGoOnline,
    this.reasonIfCannotGoOnline,
  });

  final List<String> abilities;
  final String? userType;
  final bool? hasDriverProfile;
  final String? applicationStatus;
  final bool? needsApplication;
  final bool? canSubmitApplication;
  final bool? canGoOnline;
  final String? reasonIfCannotGoOnline;
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
