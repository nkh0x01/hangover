import 'package:core/core.dart';

import 'bootstrap.dart';

void main() => bootstrap(
      const EnvConfig(
        flavor: AppFlavor.staging,
        apiBaseUrl: 'https://api.staging.hangover.app',
        wsUrl: 'wss://ws.staging.hangover.app',
        wsKey: 'staging-key',
        sentryDsn: '',
        googleMapsKey: '',
      ),
    );
