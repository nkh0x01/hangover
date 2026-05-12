/// Generic envelope for events arriving on a private/presence channel.
class RealtimeEvent {
  RealtimeEvent({
    required this.channel,
    required this.event,
    required this.data,
  });

  final String channel;
  final String event;
  final Map<String, Object?> data;
}
