import 'dart:async';

import 'package:core/core.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'di/locator.dart';

Future<void> bootstrap(EnvConfig env) async {
  WidgetsFlutterBinding.ensureInitialized();

  await CrashReporter.bootstrap(
    env: env,
    appRunner: () async {
      final container = await buildContainer(env);

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
          child: const HangoverDriverApp(),
        ),
      );
    },
  );
}
