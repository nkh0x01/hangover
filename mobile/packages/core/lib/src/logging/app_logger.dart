import 'package:logger/logger.dart';

/// Thin wrapper around `logger` so the rest of the codebase imports a
/// single facade. In prod we attach a Sentry breadcrumb writer here.
class AppLogger {
  AppLogger({Level level = Level.info})
      : _logger = Logger(
          level: level,
          printer: PrettyPrinter(methodCount: 0, noBoxingByDefault: true),
        );

  final Logger _logger;

  void d(Object message, [Object? error, StackTrace? trace]) =>
      _logger.d(message, error: error, stackTrace: trace);
  void i(Object message, [Object? error, StackTrace? trace]) =>
      _logger.i(message, error: error, stackTrace: trace);
  void w(Object message, [Object? error, StackTrace? trace]) =>
      _logger.w(message, error: error, stackTrace: trace);
  void e(Object message, [Object? error, StackTrace? trace]) =>
      _logger.e(message, error: error, stackTrace: trace);
}
