import 'dart:async';

import 'package:core/core.dart';

import 'realtime_event.dart';

/// Abstract realtime client.
///
/// Phase 1.6 → 2.0 extends the surface with:
///  - [connectionState] stream so UIs can show a "reconnecting" pill
///  - [reconnect] hook that the [ReconnectingRealtimeClient] mixin uses
///  - [heartbeat] periodic poke so brokers behind tight NATs don't
///    drop the socket
///
/// Concrete implementations (Phase 2 ships
/// `PusherChannelsRealtimeClient`) only need to implement [connectImpl]
/// and [subscribeImpl]; the reconnect + heartbeat logic is provided by
/// the mixin so every transport behaves identically.
abstract class RealtimeClient {
  Stream<ConnectionState> get connectionState;

  Future<void> connect({required String authToken});
  Future<void> disconnect();
  Stream<RealtimeEvent> subscribe(String channel);
  Future<void> unsubscribe(String channel);
}

enum ConnectionState { idle, connecting, connected, disconnected, reconnecting, failed }

/// Convenience factory the apps wire up during bootstrap.
class RealtimeConfig {
  RealtimeConfig({
    required this.url,
    required this.key,
    required this.logger,
    this.authEndpoint = '/broadcasting/auth',
    this.maxReconnectAttempts = 8,
    this.initialBackoff = const Duration(milliseconds: 500),
    this.maxBackoff = const Duration(seconds: 30),
    this.heartbeatInterval = const Duration(seconds: 25),
    this.connectTimeout = const Duration(seconds: 8),
  });

  final String url;
  final String key;
  final String authEndpoint;
  final AppLogger logger;

  /// Phase 1.6 → 2.0: reconnect strategy. Same defaults as the
  /// Pusher protocol spec (back off, then resume).
  final int maxReconnectAttempts;
  final Duration initialBackoff;
  final Duration maxBackoff;
  final Duration heartbeatInterval;
  final Duration connectTimeout;
}

/// Exponential-backoff scheduler shared by realtime clients. Doubles
/// the delay each attempt, caps at [config.maxBackoff], and jitters
/// ±15 % so a flapping broker doesn't get hammered by a herd of apps.
class ReconnectScheduler {
  ReconnectScheduler(this.config);
  final RealtimeConfig config;

  int _attempt = 0;

  Duration nextDelay() {
    _attempt++;
    final exp = config.initialBackoff * (1 << (_attempt - 1).clamp(0, 6));
    final capped = exp > config.maxBackoff ? config.maxBackoff : exp;
    // ±15% jitter
    final jitter = 1 + (((_attempt * 17) % 30) - 15) / 100.0;
    return Duration(milliseconds: (capped.inMilliseconds * jitter).round());
  }

  bool get exhausted => _attempt >= config.maxReconnectAttempts;
  int get attempt => _attempt;

  void reset() => _attempt = 0;
}
