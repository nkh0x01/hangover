import 'dart:async';

import 'package:core/core.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'di/locator.dart';

Future<void> bootstrap(EnvConfig env) async {
  WidgetsFlutterBinding.ensureInitialized();

  await runZonedGuarded<Future<void>>(() async {
    final container = await buildContainer(env);

    runApp(
      UncontrolledProviderScope(
        container: container,
        child: const HangoverDriverApp(),
      ),
    );
  }, (error, stack) {
    // Forward to Sentry in Phase 4.
  });
}
