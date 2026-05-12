import 'package:core/core.dart';

import 'bootstrap.dart';

void main() => bootstrap(
      const EnvConfig(
        flavor: AppFlavor.dev,
        apiBaseUrl: 'http://10.0.2.2:8000',
        wsUrl: 'ws://10.0.2.2:8080',
        wsKey: 'hangover-key',
        sentryDsn: '',
        googleMapsKey: '',
      ),
    );
