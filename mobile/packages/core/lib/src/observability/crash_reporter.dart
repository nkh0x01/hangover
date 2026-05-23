import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

import '../env/env_config.dart';

/// Crash reporting facade.
///
/// Remote crash reporting is temporarily disabled for Ride 360 release builds
/// while the iOS package resolution issue is handled. Keep this API stable so
/// app code does not need to change when reporting is re-enabled.
class CrashReporter {
  const CrashReporter._();

  static Future<void> bootstrap({
    required EnvConfig env,
    required FutureOr<void> Function() appRunner,
    String release = 'hangover@0.1.0',
  }) async {
    if (kDebugMode && env.sentryDsn.isNotEmpty) {
      debugPrint('Remote crash reporting disabled: $release');
    }

    await runZonedGuarded<Future<void>>(
      () async => await appRunner(),
      (error, stack) {
        debugPrint('UNCAUGHT: $error\n$stack');
      },
    );
  }

  static Future<void> captureException(
    Object error, {
    StackTrace? stackTrace,
    Map<String, Object?> tags = const {},
    String? hint,
  }) async {
    if (kDebugMode) {
      debugPrint('captureException: $error${hint != null ? ' - $hint' : ''}');
    }
  }

  static void breadcrumb(
    String message, {
    String? category,
    Map<String, Object?> data = const {},
  }) {}

  static Future<void> setUser({
    required String? userUlid,
    required String? type,
  }) async {}
}

NavigatorObserver sentryRouteObserver() => NavigatorObserver();
