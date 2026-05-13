import 'dart:async';

import 'package:core/core.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'di/locator.dart';

/// Single bootstrap path shared by main_dev / main_staging / main_prod.
///
/// Order of operations:
///   1. Ensure widget binding (must be first for plugins).
///   2. Bring up [CrashReporter] — wraps the rest of bootstrap so any
///      DI failure is captured.
///   3. Build the Riverpod container.
///   4. Initialize the [PushService] (FCM) — non-blocking; failures
///      are logged but never crash the app.
///   5. `runApp`.
Future<void> bootstrap(EnvConfig env) async {
  WidgetsFlutterBinding.ensureInitialized();

  await CrashReporter.bootstrap(
    env: env,
    appRunner: () async {
      final container = await buildContainer(env);

      // Best-effort push init. The provider returns NullPushService when
      // Firebase isn't initialized, so this is safe even in dev.
      try {
        final push = container.read(pushServiceProvider);
        await push.initialize();
      } catch (e, st) {
        await CrashReporter.captureException(
          e,
          stackTrace: st,
          hint: 'push init failed (non-fatal)',
        );
      }

      runApp(
        UncontrolledProviderScope(
          container: container,
          child: const HangoverCustomerApp(),
        ),
      );
    },
  );
}
