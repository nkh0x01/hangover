import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:maps/maps.dart';
import 'package:rides/rides.dart';

import '../../../di/locator.dart';

/// Holds the driver's current online/offline state, the heartbeat timer,
/// and the most recent active ride. One screen subscribes to this and
/// renders whichever phase is current.
class ShiftState {
  ShiftState({
    this.online = false,
    this.position = const LatLng(41.7151, 44.8271),
    this.activeRide,
    this.pendingOffer,
    this.error,
    this.isWorking = false,
  });

  final bool online;
  final LatLng position;
  final Ride? activeRide;
  final RideOfferPayload? pendingOffer;
  final String? error;
  final bool isWorking;

  ShiftState copyWith({
    bool? online,
    LatLng? position,
    Ride? activeRide,
    RideOfferPayload? pendingOffer,
    String? error,
    bool? isWorking,
    bool clearOffer = false,
    bool clearActive = false,
    bool clearError = false,
  }) =>
      ShiftState(
        online: online ?? this.online,
        position: position ?? this.position,
        activeRide: clearActive ? null : (activeRide ?? this.activeRide),
        pendingOffer: clearOffer ? null : (pendingOffer ?? this.pendingOffer),
        error: clearError ? null : (error ?? this.error),
        isWorking: isWorking ?? this.isWorking,
      );
}

class ShiftController extends Notifier<ShiftState> {
  Timer? _heartbeat;
  Timer? _activeRidePoll;

  @override
  ShiftState build() {
    ref.onDispose(() {
      _heartbeat?.cancel();
      _activeRidePoll?.cancel();
    });
    return ShiftState();
  }

  Future<void> goOnline() async {
    state = state.copyWith(isWorking: true, clearError: true);
    try {
      await ref.read(driverRideRepositoryProvider).goOnline(position: state.position);
      state = state.copyWith(online: true, isWorking: false);
      _startHeartbeat();
      _startActiveRidePoll();
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  Future<void> goOffline() async {
    state = state.copyWith(isWorking: true);
    try {
      await ref.read(driverRideRepositoryProvider).goOffline();
      state = state.copyWith(online: false, isWorking: false);
      _heartbeat?.cancel();
      _activeRidePoll?.cancel();
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  Future<void> acceptOffer(String rideId) async {
    state = state.copyWith(isWorking: true);
    try {
      final ride = await ref.read(driverRideRepositoryProvider).acceptOffer(rideId);
      state = state.copyWith(activeRide: ride, clearOffer: true, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString(), clearOffer: true);
    }
  }

  Future<void> rejectOffer(String rideId) async {
    try {
      await ref.read(driverRideRepositoryProvider).rejectOffer(rideId);
    } catch (_) {}
    state = state.copyWith(clearOffer: true);
  }

  Future<void> arriving() => _transition((r) => ref.read(driverRideRepositoryProvider).arriving(r));
  Future<void> arrived() => _transition((r) => ref.read(driverRideRepositoryProvider).arrived(r));
  Future<void> start() => _transition((r) => ref.read(driverRideRepositoryProvider).start(r));
  Future<void> complete() => _transition((r) => ref.read(driverRideRepositoryProvider).complete(r));

  Future<void> cancel({String reason = 'driver_cancelled'}) async {
    final ride = state.activeRide;
    if (ride == null) return;
    state = state.copyWith(isWorking: true);
    try {
      final cancelled = await ref.read(driverRideRepositoryProvider).cancel(ride.id, reason);
      state = state.copyWith(activeRide: cancelled, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  void dismissCompletedRide() {
    state = state.copyWith(clearActive: true);
  }

  void updatePosition(LatLng point) {
    state = state.copyWith(position: point);
  }

  void simulateIncomingOffer(RideOfferPayload payload) {
    state = state.copyWith(pendingOffer: payload);
  }

  Future<void> _transition(Future<Ride> Function(String) op) async {
    final ride = state.activeRide;
    if (ride == null) return;
    state = state.copyWith(isWorking: true);
    try {
      final updated = await op(ride.id);
      state = state.copyWith(activeRide: updated, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  void _startHeartbeat() {
    _heartbeat?.cancel();
    _heartbeat = Timer.periodic(const Duration(seconds: 3), (_) async {
      try {
        await ref.read(driverRideRepositoryProvider).heartbeat(position: state.position);
      } catch (_) {}
    });
  }

  void _startActiveRidePoll() {
    _activeRidePoll?.cancel();
    _activeRidePoll = Timer.periodic(const Duration(seconds: 3), (_) async {
      try {
        final ride = await ref.read(driverRideRepositoryProvider).active();
        if (ride != null) {
          state = state.copyWith(activeRide: ride);
        }
      } catch (_) {}
    });
  }
}

final shiftProvider = NotifierProvider<ShiftController, ShiftState>(ShiftController.new);
