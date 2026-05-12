import 'package:auth/auth.dart';
import 'package:core/core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:network/network.dart';
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
    appPlatform: _platformName(),
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

Future<ProviderContainer> buildContainer(EnvConfig env) async {
  return ProviderContainer(
    overrides: [
      envProvider.overrideWithValue(env),
    ],
  );
}

String _platformName() {
  // Avoid importing dart:io here — feature/di-level platform decisions
  // happen elsewhere. For now we mark dev / unknown.
  return 'mobile';
}
