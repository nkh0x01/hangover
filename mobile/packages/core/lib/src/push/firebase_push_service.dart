import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import '../logging/app_logger.dart';
import 'push_service.dart';

/// Concrete FCM-backed implementation of [PushService].
///
/// Wiring (per-app, done in DI/bootstrap):
///
///   await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
///   final svc = FirebasePushService(logger: logger);
///   await svc.initialize();
///
/// On Android the foreground notification banner is delivered via the
/// `flutter_local_notifications` channel created in the app shell
/// (configure once with channel id `hangover_rides`). On iOS the
/// default banner ships automatically when APNs is properly entitled
/// and the user has granted permission.
class FirebasePushService implements PushService {
  FirebasePushService({required this.logger});

  final AppLogger logger;
  final _tokenController = StreamController<String>.broadcast();
  final _incomingController = StreamController<IncomingPush>.broadcast();

  String? _cachedToken;
  bool _initialized = false;

  @override
  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    if (Firebase.apps.isEmpty) {
      logger.w('Firebase not initialized — FCM disabled');
      return;
    }

    final messaging = FirebaseMessaging.instance;

    // Permissions (no-op on Android < 13; surfaces APNs prompt on iOS).
    await messaging.requestPermission(alert: true, badge: true, sound: true);

    // Initial token.
    _cachedToken = await messaging.getToken();
    if (_cachedToken != null) {
      _tokenController.add(_cachedToken!);
    }

    // Token rotations.
    messaging.onTokenRefresh.listen((token) {
      _cachedToken = token;
      _tokenController.add(token);
    });

    // Foreground messages.
    FirebaseMessaging.onMessage.listen((RemoteMessage msg) {
      _incomingController.add(IncomingPush.fromFcmData(
        msg.data,
        context: IncomingPushContext.foreground,
        title: msg.notification?.title,
        body: msg.notification?.body,
      ));
    });

    // Background-tap.
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage msg) {
      _incomingController.add(IncomingPush.fromFcmData(
        msg.data,
        context: IncomingPushContext.opened,
        title: msg.notification?.title,
        body: msg.notification?.body,
      ));
    });

    // Cold-start: app was killed and launched by a tap.
    final initial = await messaging.getInitialMessage();
    if (initial != null) {
      _incomingController.add(IncomingPush.fromFcmData(
        initial.data,
        context: IncomingPushContext.coldStart,
        title: initial.notification?.title,
        body: initial.notification?.body,
      ));
    }
  }

  @override
  Stream<String> get tokenStream => _tokenController.stream;

  @override
  Future<String?> getToken() async {
    _cachedToken ??= await FirebaseMessaging.instance.getToken();
    return _cachedToken;
  }

  @override
  Stream<IncomingPush> get incoming => _incomingController.stream;

  @override
  Future<void> teardown() async {
    try {
      await FirebaseMessaging.instance.deleteToken();
    } catch (_) {}
    await _tokenController.close();
    await _incomingController.close();
  }
}
