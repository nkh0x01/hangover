import 'dart:async';

import 'package:core/core.dart';

import 'realtime_client.dart';
import 'realtime_event.dart';

/// Fallback client used while the websocket transport is detached or
/// when running on environments that don't support the broker. Uses
/// an injected [fetcher] to poll the API for the latest ride state.
///
/// The [ReconnectScheduler] is used to throttle retries when the
/// fetcher throws — mirrors the websocket back-off so the connection
/// state stream looks the same to UI subscribers.
class PollingRealtimeClient implements RealtimeClient {
  PollingRealtimeClient({
    required this.config,
    required this.fetcher,
    this.tick = const Duration(seconds: 2),
  }) : _scheduler = ReconnectScheduler(config);

  final RealtimeConfig config;
  final ReconnectScheduler _scheduler;

  /// Polls one channel; called repeatedly on [tick].
  /// Returns 0..n events the listener should be aware of.
  final Future<List<RealtimeEvent>> Function(String channel) fetcher;

  final Duration tick;

  final _connectionController = StreamController<ConnectionState>.broadcast();
  final Map<String, StreamController<RealtimeEvent>> _subs = {};
  final Map<String, Timer> _timers = {};
  String? _token;

  @override
  Stream<ConnectionState> get connectionState => _connectionController.stream;

  @override
  Future<void> connect({required String authToken}) async {
    _token = authToken;
    _connectionController.add(ConnectionState.connecting);
    _scheduler.reset();
    _connectionController.add(ConnectionState.connected);
  }

  @override
  Future<void> disconnect() async {
    for (final t in _timers.values) {
      t.cancel();
    }
    _timers.clear();
    for (final s in _subs.values) {
      await s.close();
    }
    _subs.clear();
    _connectionController.add(ConnectionState.disconnected);
  }

  @override
  Stream<RealtimeEvent> subscribe(String channel) {
    if (_token == null) {
      throw StateError('Cannot subscribe before connect().');
    }

    final existing = _subs[channel];
    if (existing != null) return existing.stream;

    final controller = StreamController<RealtimeEvent>.broadcast(
      onCancel: () => unsubscribe(channel),
    );
    _subs[channel] = controller;
    _startPolling(channel, controller);
    return controller.stream;
  }

  @override
  Future<void> unsubscribe(String channel) async {
    _timers.remove(channel)?.cancel();
    final c = _subs.remove(channel);
    await c?.close();
  }

  void _startPolling(String channel, StreamController<RealtimeEvent> sink) {
    _timers[channel]?.cancel();
    _timers[channel] = Timer.periodic(tick, (_) async {
      try {
        final events = await fetcher(channel);
        if (!sink.isClosed) {
          for (final e in events) {
            sink.add(e);
          }
        }
        if (_scheduler.attempt > 0) {
          // Connectivity recovered.
          _scheduler.reset();
          _connectionController.add(ConnectionState.connected);
        }
      } on Object catch (e) {
        config.logger.w('Polling fetch failed for $channel: $e');
        final delay = _scheduler.nextDelay();
        _connectionController.add(ConnectionState.reconnecting);
        _timers[channel]?.cancel();
        _timers[channel] = Timer(delay, () {
          if (!_scheduler.exhausted) {
            _startPolling(channel, sink);
          } else {
            _connectionController.add(ConnectionState.failed);
          }
        });
      }
    });
  }
}
