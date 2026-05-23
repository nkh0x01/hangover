import 'dart:async';

/// Push-notification service contract.
///
/// Phase 1.6 → 2.0 ships this interface plus a stub `NullPushService`.
/// The concrete `FirebasePushService` lives in the apps' DI layer (so
/// the core package stays Firebase-agnostic and can be unit-tested
/// without the Firebase messaging package).
///
/// Lifecycle (called once at app boot, after auth):
///
///   final svc = ref.read(pushServiceProvider);
///   await svc.initialize();
///   svc.tokenStream.listen((token) =>
///     ref.read(deviceApiProvider).registerFcmToken(token));
///
/// The `incoming` stream emits in three contexts:
///   - app in foreground → emits `IncomingPush.foreground`
///   - app in background, user taps the notification → `.opened`
///   - app cold-started from a notification → `.coldStart`
///
/// UIs subscribe and route appropriately. Driver-app's incoming-offer
/// page listens for `IncomingPushKind.rideOffered` and `.coldStart`
/// reactions to launch the offer modal immediately.
abstract class PushService {
  /// Request platform permission + fetch initial token. Idempotent.
  Future<void> initialize();

  /// Latest FCM/APNs registration token. Fires on rotation.
  Stream<String> get tokenStream;

  /// Latest token cached locally (null until [initialize] resolves).
  Future<String?> getToken();

  /// Server-pushed notifications. See [IncomingPush] for shape.
  Stream<IncomingPush> get incoming;

  /// Mark the user as logged out — wipes any subscriptions and asks
  /// the platform to delete the registration token.
  Future<void> teardown();
}

/// Shape of a push the apps care about. Backend mirrors this in the
/// FCM `data` payload so foreground / background / cold-start paths
/// all read the same fields.
class IncomingPush {
  IncomingPush({
    required this.kind,
    required this.context,
    this.title,
    this.body,
    this.rideId,
    this.driverUlid,
    this.payload = const {},
  });

  final IncomingPushKind kind;
  final IncomingPushContext context;
  final String? title;
  final String? body;
  final String? rideId;
  final String? driverUlid;
  final Map<String, Object?> payload;

  factory IncomingPush.fromFcmData(
    Map<String, dynamic> data, {
    required IncomingPushContext context,
    String? title,
    String? body,
  }) {
    return IncomingPush(
      kind: IncomingPushKind.fromString((data['kind'] as String?) ?? 'unknown'),
      context: context,
      title: title,
      body: body,
      rideId: data['ride_ulid'] as String?,
      driverUlid: data['driver_ulid'] as String?,
      payload: data.cast<String, Object?>(),
    );
  }
}

enum IncomingPushContext {
  /// App is open and visible; the push should typically become a snackbar
  /// or take over the current screen if the kind is offer/critical.
  foreground,

  /// App is backgrounded, user tapped the notification.
  opened,

  /// App was killed; the notification launched the app.
  coldStart,
}

enum IncomingPushKind {
  rideOffered,
  rideAccepted,
  rideStatusChanged,
  rideCancelled,
  driverArrived,
  payoutProcessed,
  systemMessage,
  unknown;

  static IncomingPushKind fromString(String s) => switch (s) {
        'ride.offered' || 'ride_offered' => rideOffered,
        'ride.accepted' || 'ride_accepted' => rideAccepted,
        'ride.status_changed' || 'ride_status_changed' => rideStatusChanged,
        'ride.cancelled' || 'ride_cancelled' => rideCancelled,
        'driver.arrived' || 'driver_arrived' => driverArrived,
        'payout.processed' => payoutProcessed,
        'system.message' => systemMessage,
        _ => unknown,
      };
}

/// Drop-in stub used by tests and the local dev flavor. Logs every
/// call; never actually talks to FCM.
class NullPushService implements PushService {
  final _token = StreamController<String>.broadcast();
  final _incoming = StreamController<IncomingPush>.broadcast();

  @override
  Future<void> initialize() async {}

  @override
  Stream<String> get tokenStream => _token.stream;

  @override
  Future<String?> getToken() async => null;

  @override
  Stream<IncomingPush> get incoming => _incoming.stream;

  @override
  Future<void> teardown() async {
    await _token.close();
    await _incoming.close();
  }
}
