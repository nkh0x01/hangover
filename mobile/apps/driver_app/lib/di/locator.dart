import 'package:auth/auth.dart';
import 'package:core/core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:maps/maps.dart';
import 'package:network/network.dart';
import 'package:rides/rides.dart';
import 'package:uuid/uuid.dart';

final envProvider = Provider<EnvConfig>((ref) {
  throw UnimplementedError('Override via buildContainer()');
});

final tokenStoreProvider = Provider<TokenStore>((ref) {
  return TokenStore(namespace: 'driver');
});

final networkDiagnosticsProvider = Provider<NetworkDiagnosticsRecorder>((ref) {
  return NetworkDiagnosticsRecorder();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final env = ref.watch(envProvider);
  final tokens = ref.watch(tokenStoreProvider);
  final diagnostics = ref.watch(networkDiagnosticsProvider);

  return ApiClient(
    env: env,
    tokenStore: tokens,
    appPlatform: 'mobile',
    appVersion: '0.1.0',
    diagnostics: diagnostics,
    deviceUuidProvider: () async {
      final existing = await tokens.readDeviceUuid();
      if (existing != null) return existing;
      final generated = const Uuid().v4();
      await tokens.writeDeviceUuid(generated);
      return generated;
    },
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    client: ref.watch(apiClientProvider),
    tokenStore: ref.watch(tokenStoreProvider),
    diagnostics: ref.watch(networkDiagnosticsProvider),
  );
});

final driverRideRepositoryProvider = Provider<DriverRideRepository>((ref) {
  return DriverRideRepository(client: ref.watch(apiClientProvider));
});

final driverProfileRepositoryProvider =
    Provider<DriverProfileRepository>((ref) {
  return DriverProfileRepository(client: ref.watch(apiClientProvider));
});

final mapProviderProvider =
    Provider<MapProvider>((ref) => GoogleMapsProvider());

final appLoggerProvider = Provider<AppLogger>((ref) => AppLogger());

/// Push service for the driver app. Returns [NullPushService] when
/// Firebase isn't initialized; otherwise [FirebasePushService] for FCM.
/// Driver app subscribes to `rideOffered` here to drive the incoming-offer modal.
final pushServiceProvider = Provider<PushService>((ref) {
  try {
    return FirebasePushService(logger: ref.watch(appLoggerProvider));
  } catch (_) {
    return NullPushService();
  }
});

Future<ProviderContainer> buildContainer(EnvConfig env) async {
  return ProviderContainer(
    overrides: [envProvider.overrideWithValue(env)],
  );
}
