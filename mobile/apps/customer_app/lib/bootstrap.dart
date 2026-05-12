import 'dart:async';

import 'package:core/core.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'di/locator.dart';

/// Single bootstrap path shared by main_dev / main_staging / main_prod.
Future<void> bootstrap(EnvConfig env) async {
  WidgetsFlutterBinding.ensureInitialized();

  await runZonedGuarded<Future<void>>(() async {
    final container = await buildContainer(env);

    runApp(
      UncontrolledProviderScope(
        container: container,
        child: const HangoverCustomerApp(),
      ),
    );
  }, (error, stack) {
    // Phase 4 hook: forward to Sentry.captureException(error, stackTrace: stack)
  });
}
