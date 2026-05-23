import 'package:core/core.dart';

import 'bootstrap.dart';

void main() => bootstrap(
      const EnvConfig(
        flavor: AppFlavor.prod,
        apiBaseUrl: String.fromEnvironment(
          'API_BASE_URL',
          defaultValue: 'https://ride.365sakartvelo.com',
        ),
        wsUrl: String.fromEnvironment(
          'WS_URL',
          defaultValue: 'wss://ride.365sakartvelo.com',
        ),
        wsKey: String.fromEnvironment('WS_KEY'),
        sentryDsn: String.fromEnvironment('SENTRY_DSN'),
        googleMapsKey: String.fromEnvironment('GOOGLE_MAPS_KEY'),
      ),
    );
