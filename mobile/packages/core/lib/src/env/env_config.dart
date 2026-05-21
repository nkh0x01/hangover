import 'app_flavor.dart';

/// Per-flavor runtime configuration. Flavor entrypoints construct the
/// concrete value and inject it via the DI container.
class EnvConfig {
  const EnvConfig({
    required this.flavor,
    required this.apiBaseUrl,
    required this.wsUrl,
    required this.wsKey,
    required this.sentryDsn,
    required this.googleMapsKey,
  });

  final AppFlavor flavor;
  final String apiBaseUrl;
  final String wsUrl;
  final String wsKey;
  final String sentryDsn;
  final String googleMapsKey;

  bool get isProd => flavor == AppFlavor.prod;
}
