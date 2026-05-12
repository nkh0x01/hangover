import 'dart:async';

import 'package:core/core.dart';

import 'realtime_event.dart';

/// Abstract realtime client. Phase 3 ships a concrete
/// PusherChannelsRealtimeClient backed by `pusher_channels_flutter` (which
/// works against Laravel Reverb's Pusher-protocol broker).
///
/// Defined as an abstraction so widget + repository tests can swap in a
/// fake stream without standing up a real broker.
abstract class RealtimeClient {
  /// Connect with the bearer token used for channel auth.
  Future<void> connect({required String authToken});

  Future<void> disconnect();

  /// Subscribe to a private or presence channel; returns a broadcast
  /// stream that emits every event for the channel.
  Stream<RealtimeEvent> subscribe(String channel);

  Future<void> unsubscribe(String channel);
}

/// Convenience factory the apps wire up during bootstrap.
class RealtimeConfig {
  RealtimeConfig({
    required this.url,
    required this.key,
    required this.logger,
    this.authEndpoint = '/broadcasting/auth',
  });

  final String url;
  final String key;
  final String authEndpoint;
  final AppLogger logger;
}
