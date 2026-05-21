import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:maps/maps.dart';
import 'package:rides/rides.dart';

import '../../../di/locator.dart';
import '../../demo/data/demo_fixtures.dart';

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
    this.demoActive = false,
  });

  final bool online;
  final LatLng position;
  final Ride? activeRide;
  final RideOfferPayload? pendingOffer;
  final String? error;
  final bool isWorking;

  /// True while the dev-only preview is driving this state. Suppresses
  /// heartbeat / active-ride polling and repository calls.
  final bool demoActive;

  ShiftState copyWith({
    bool? online,
    LatLng? position,
    Ride? activeRide,
    RideOfferPayload? pendingOffer,
    String? error,
    bool? isWorking,
    bool? demoActive,
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
        demoActive: demoActive ?? this.demoActive,
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
    if (state.demoActive) {
      // Skip the network call; just flip the flag so the home page
      // re-renders the "online" affordances.
      state = state.copyWith(online: true, isWorking: false, clearError: true);
      return;
    }
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
    if (state.demoActive) {
      state = state.copyWith(online: false, isWorking: false);
      return;
    }
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
    if (state.demoActive) {
      // Demo: pretend the dispatch accepted; activeRide moves to `accepted`.
      state = state.copyWith(
        activeRide: DriverDemoFixtures.ride(RideStatus.accepted),
        clearOffer: true,
        isWorking: false,
      );
      return;
    }
    state = state.copyWith(isWorking: true);
    try {
      final ride = await ref.read(driverRideRepositoryProvider).acceptOffer(rideId);
      state = state.copyWith(activeRide: ride, clearOffer: true, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString(), clearOffer: true);
    }
  }

  Future<void> rejectOffer(String rideId) async {
    if (state.demoActive) {
      state = state.copyWith(clearOffer: true);
      return;
    }
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
    if (state.demoActive) {
      // Network call is skipped — demo stepper drives status changes
      // directly via [demoSetRideStatus].
      return;
    }
    state = state.copyWith(isWorking: true);
    try {
      final updated = await op(ride.id);
      state = state.copyWith(activeRide: updated, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  // ---- Dev-only preview helpers -----------------------------------------

  /// Enter demo mode at the offline home screen.
  void demoEnterOffline() {
    _heartbeat?.cancel();
    _activeRidePoll?.cancel();
    state = ShiftState(
      demoActive: true,
      position: DriverDemoFixtures.driverPosition,
    );
  }

  /// Demo: flip to "online · waiting for offers" without a network call.
  void demoEnterOnline() {
    state = state.copyWith(
      demoActive: true,
      online: true,
      isWorking: false,
      clearOffer: true,
      clearActive: true,
      clearError: true,
    );
  }

  /// Demo: surface a canned incoming offer modal.
  void demoInjectOffer() {
    state = state.copyWith(
      demoActive: true,
      online: true,
      pendingOffer: DriverDemoFixtures.offer(),
      clearActive: true,
    );
  }

  /// Demo: pin the active ride to a specific status.
  void demoSetRideStatus(RideStatus status) {
    state = state.copyWith(
      demoActive: true,
      online: true,
      activeRide: DriverDemoFixtures.ride(status),
      clearOffer: true,
      isWorking: false,
    );
  }

  /// Leave demo mode entirely and reset to a clean offline state.
  void demoExit() {
    _heartbeat?.cancel();
    _activeRidePoll?.cancel();
    state = ShiftState();
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
