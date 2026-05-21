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
  return TokenStore(namespace: 'customer');
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final env = ref.watch(envProvider);
  final tokens = ref.watch(tokenStoreProvider);

  return ApiClient(
    env: env,
    tokenStore: tokens,
    appPlatform: 'mobile',
    appVersion: '0.1.0',
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
  );
});

final rideRepositoryProvider = Provider<RideRepository>((ref) {
  return RideRepository(client: ref.watch(apiClientProvider));
});

final mapProviderProvider = Provider<MapProvider>((ref) => GoogleMapsProvider());

final rideEventStreamProvider = Provider<RideEventStream>((ref) {
  return RideEventStream(repository: ref.watch(rideRepositoryProvider));
});

final appLoggerProvider = Provider<AppLogger>((ref) => AppLogger());

/// Push service for the customer app. Returns [NullPushService] when
/// Firebase isn't initialized (e.g. local dev without `google-services.json`),
/// otherwise wires up a real [FirebasePushService] backed by FCM.
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
