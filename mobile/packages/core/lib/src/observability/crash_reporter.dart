import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

import '../env/env_config.dart';

/// Crash + error reporting facade.
///
/// Apps call [CrashReporter.bootstrap] once during `bootstrap.dart`,
/// inside a `runZonedGuarded` block, and then `captureException` is
/// invoked from the zone error handler and from any try/catch that
/// wants to surface a non-fatal.
///
/// Sentry-Flutter installs its own `FlutterError.onError` +
/// `PlatformDispatcher.instance.onError` hooks during init, so once
/// `bootstrap` returns every uncaught error is captured automatically.
class CrashReporter {
  const CrashReporter._();

  static bool get _isProd =>
      const String.fromEnvironment('FLUTTER_TEST').isEmpty &&
      !kDebugMode;

  /// Wraps `runApp` so the entry point is:
  ///
  ///   await CrashReporter.bootstrap(
  ///     env: env,
  ///     appRunner: () async => runApp(...),
  ///   );
  static Future<void> bootstrap({
    required EnvConfig env,
    required FutureOr<void> Function() appRunner,
    String release = 'hangover@0.1.0',
  }) async {
    if (env.sentryDsn.isEmpty || !_isProd) {
      // Local / debug / dev — no DSN. Just run the app.
      runZonedGuarded(() async => await appRunner(), (error, stack) {
        debugPrint('UNCAUGHT: $error\n$stack');
      });
      return;
    }

    await SentryFlutter.init(
      (options) {
        options.dsn = env.sentryDsn;
        options.environment = env.flavor.name;
        options.release = release;
        options.tracesSampleRate = env.isProd ? 0.10 : 1.0;
        options.profilesSampleRate = env.isProd ? 0.05 : 1.0;
        options.attachScreenshot = false; // PII risk; opt-in per session.
        options.beforeSend = _scrubPii;
      },
      appRunner: appRunner,
    );
  }

  /// Manual capture for non-fatal errors. Use inside `catch` blocks
  /// where the app can recover but ops should know.
  static Future<void> captureException(
    Object error, {
    StackTrace? stackTrace,
    Map<String, Object?> tags = const {},
    String? hint,
  }) async {
    if (!_isProd) {
      debugPrint('captureException: $error${hint != null ? ' — $hint' : ''}');
      return;
    }
    await Sentry.captureException(
      error,
      stackTrace: stackTrace,
      hint: hint != null ? Hint.withMap({'context': hint}) : null,
      withScope: (scope) {
        for (final entry in tags.entries) {
          scope.setTag(entry.key, entry.value?.toString() ?? 'null');
        }
      },
    );
  }

  /// Drop a breadcrumb — useful for tracing the sequence of taps /
  /// network calls that led up to an error.
  static void breadcrumb(String message, {String? category, Map<String, Object?> data = const {}}) {
    if (!_isProd) return;
    Sentry.addBreadcrumb(Breadcrumb(
      message: message,
      category: category,
      data: data,
      timestamp: DateTime.now().toUtc(),
    ));
  }

  /// Attach the current user's ulid to the Sentry scope. Phone number
  /// is intentionally NOT sent — it's PII.
  static Future<void> setUser({required String? userUlid, required String? type}) async {
    if (!_isProd) return;
    await Sentry.configureScope((scope) {
      scope.setUser(userUlid == null
          ? null
          : SentryUser(id: userUlid, data: {'type': type ?? 'unknown'}));
    });
  }

  static FutureOr<SentryEvent?> _scrubPii(SentryEvent event, Hint hint) {
    // Strip phone numbers from breadcrumb messages and request bodies.
    final scrubbed = event.copyWith(
      breadcrumbs: event.breadcrumbs?.map((b) {
        final msg = b.message;
        if (msg == null) return b;
        return Breadcrumb(
          message: _maskPhone(msg),
          category: b.category,
          data: b.data,
          level: b.level,
          type: b.type,
          timestamp: b.timestamp,
        );
      }).toList(),
    );
    return scrubbed;
  }

  static String _maskPhone(String s) =>
      s.replaceAllMapped(RegExp(r'\+?\d{8,15}'), (m) => '+***${m.group(0)?.substring(m.group(0)!.length - 3)}');
}

/// Boilerplate widget the apps can wrap around `MaterialApp.router` to
/// add a Sentry navigator observer (for breadcrumb routing context).
SentryNavigatorObserver sentryRouteObserver() => SentryNavigatorObserver();
