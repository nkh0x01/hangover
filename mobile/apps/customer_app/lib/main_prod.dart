import 'package:core/core.dart';

import 'bootstrap.dart';

void main() => bootstrap(
      const EnvConfig(
        flavor: AppFlavor.prod,
        apiBaseUrl: 'https://api.hangover.app',
        wsUrl: 'wss://ws.hangover.app',
        wsKey: String.fromEnvironment('WS_KEY'),
        sentryDsn: String.fromEnvironment('SENTRY_DSN'),
        googleMapsKey: String.fromEnvironment('GOOGLE_MAPS_KEY'),
      ),
    );
