import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:maps/maps.dart';
import 'package:rides/rides.dart';

import '../../../di/locator.dart';
import '../../demo/data/demo_fixtures.dart';

/// State for the customer's ride flow. A single controller owns every
/// stage from "picking a destination" to "ride complete" so transitions
/// happen in one place and screens stay tiny.
class RideFlowState {
  RideFlowState({
    this.pickup,
    this.pickupAddress = 'Current location',
    this.dropoff,
    this.dropoffAddress = '',
    this.fareEstimate,
    this.activeRide,
    this.driverLocation,
    this.isWorking = false,
    this.error,
    this.demoActive = false,
  });

  final LatLng? pickup;
  final String pickupAddress;
  final LatLng? dropoff;
  final String dropoffAddress;
  final FareEstimate? fareEstimate;
  final Ride? activeRide;
  final LatLng? driverLocation;
  final bool isWorking;
  final String? error;

  /// True while the dev-only preview is driving this state. Suppresses
  /// real repository/WS calls so the canned flow stays consistent.
  final bool demoActive;

  RideFlowState copyWith({
    LatLng? pickup,
    String? pickupAddress,
    LatLng? dropoff,
    String? dropoffAddress,
    FareEstimate? fareEstimate,
    Ride? activeRide,
    LatLng? driverLocation,
    bool? isWorking,
    String? error,
    bool? demoActive,
    bool clearError = false,
    bool clearFareEstimate = false,
    bool clearActiveRide = false,
    bool clearDropoff = false,
    bool clearDriverLocation = false,
  }) =>
      RideFlowState(
        pickup: pickup ?? this.pickup,
        pickupAddress: pickupAddress ?? this.pickupAddress,
        dropoff: clearDropoff ? null : (dropoff ?? this.dropoff),
        dropoffAddress: clearDropoff ? '' : (dropoffAddress ?? this.dropoffAddress),
        fareEstimate: clearFareEstimate ? null : (fareEstimate ?? this.fareEstimate),
        activeRide: clearActiveRide ? null : (activeRide ?? this.activeRide),
        driverLocation: clearDriverLocation ? null : (driverLocation ?? this.driverLocation),
        isWorking: isWorking ?? this.isWorking,
        error: clearError ? null : (error ?? this.error),
        demoActive: demoActive ?? this.demoActive,
      );
}

class RideFlowController extends Notifier<RideFlowState> {
  StreamSubscription<RideEvent>? _subscription;

  @override
  RideFlowState build() {
    ref.onDispose(() => _subscription?.cancel());
    return RideFlowState(
      // Tbilisi center default — the map widget will move to the user's
      // location as soon as platform permission is granted in Phase 2.
      pickup: const LatLng(41.7151, 44.8271),
    );
  }

  void setPickup(LatLng point, {String? address}) {
    state = state.copyWith(pickup: point, pickupAddress: address ?? 'Pickup');
  }

  void setDropoff(LatLng point, {String? address}) {
    state = state.copyWith(
      dropoff: point,
      dropoffAddress: address ?? 'Dropoff',
      clearFareEstimate: true,
    );
  }

  void clearDropoff() {
    state = state.copyWith(clearDropoff: true, clearFareEstimate: true);
  }

  Future<void> requestEstimate() async {
    if (state.demoActive) return; // demo: fare already populated, no API call.
    final pickup = state.pickup;
    final dropoff = state.dropoff;
    if (pickup == null || dropoff == null) return;

    state = state.copyWith(isWorking: true, clearError: true);
    try {
      final estimate = await ref.read(rideRepositoryProvider).estimate(
            pickup: pickup,
            dropoff: dropoff,
          );
      state = state.copyWith(fareEstimate: estimate, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  Future<void> requestRide({String paymentMethod = 'cash'}) async {
    if (state.demoActive) {
      // Demo: synthesize an immediately-"searching" ride; advancing the
      // demo stage drives the rest of the journey.
      state = state.copyWith(
        activeRide: DemoFixtures.ride(RideStatus.searching),
        isWorking: false,
        clearError: true,
      );
      return;
    }
    final fare = state.fareEstimate;
    final pickup = state.pickup;
    final dropoff = state.dropoff;
    if (fare == null || pickup == null || dropoff == null) return;

    state = state.copyWith(isWorking: true, clearError: true);
    try {
      final ride = await ref.read(rideRepositoryProvider).requestRide(
            fareEstimateId: fare.id,
            pickup: pickup,
            pickupAddress: state.pickupAddress,
            dropoff: dropoff,
            dropoffAddress: state.dropoffAddress,
            paymentMethod: paymentMethod,
          );
      state = state.copyWith(activeRide: ride, isWorking: false);
      _subscribe(ride.id);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  Future<void> cancelActive({String reason = 'customer_cancelled'}) async {
    final ride = state.activeRide;
    if (ride == null) return;
    state = state.copyWith(isWorking: true);
    try {
      final cancelled = await ref.read(rideRepositoryProvider).cancel(ride.id, reason);
      state = state.copyWith(activeRide: cancelled, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  /// Re-attach to a ride id (used when the customer reopens the app
  /// mid-ride or hits the tracking deep link).
  Future<void> attachExisting(String rideId) async {
    // Demo: state.activeRide is already canned; nothing to fetch and
    // nothing to subscribe to.
    if (state.demoActive) return;
    final current = state.activeRide;
    if (current?.id == rideId) {
      _subscribe(rideId);
      return;
    }
    try {
      final ride = await ref.read(rideRepositoryProvider).show(rideId);
      state = state.copyWith(activeRide: ride);
      _subscribe(rideId);
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  /// Called when the customer dismisses the post-trip summary.
  void reset() {
    _subscription?.cancel();
    _subscription = null;
    state = RideFlowState(pickup: state.pickup, pickupAddress: state.pickupAddress);
  }

  // ---- Dev-only preview helpers -----------------------------------------
  // These never call the network. Driven by DemoModeController; the
  // pages skip their real bootstrap paths when [state.demoActive] is on.

  /// Enter demo mode at the home screen with a canned pickup.
  void demoPrimeHome() {
    _subscription?.cancel();
    _subscription = null;
    state = RideFlowState(
      pickup: DemoFixtures.pickup,
      pickupAddress: DemoFixtures.pickupAddress,
      demoActive: true,
    );
  }

  /// Move the demo to "fare estimate" — pickup, dropoff, and a canned
  /// fare are all populated; no network call is made.
  void demoEnterFareEstimate() {
    state = state.copyWith(
      demoActive: true,
      pickup: DemoFixtures.pickup,
      pickupAddress: DemoFixtures.pickupAddress,
      dropoff: DemoFixtures.dropoff,
      dropoffAddress: DemoFixtures.dropoffAddress,
      fareEstimate: DemoFixtures.fareEstimate(),
      clearActiveRide: true,
      clearDriverLocation: true,
      clearError: true,
    );
  }

  /// Pin the demo to a specific ride status. Driver location is also
  /// faked so the tracking map has something to render.
  void demoSetRideStatus(RideStatus status) {
    state = state.copyWith(
      demoActive: true,
      pickup: DemoFixtures.pickup,
      pickupAddress: DemoFixtures.pickupAddress,
      dropoff: DemoFixtures.dropoff,
      dropoffAddress: DemoFixtures.dropoffAddress,
      fareEstimate: state.fareEstimate ?? DemoFixtures.fareEstimate(),
      activeRide: DemoFixtures.ride(status),
      driverLocation: DemoFixtures.driverLocationFor(status),
      isWorking: false,
      clearError: true,
    );
  }

  void _subscribe(String rideId) {
    _subscription?.cancel();
    _subscription = ref.read(rideEventStreamProvider).watch(rideId).listen((event) {
      state = state.copyWith(
        activeRide: event.ride,
        driverLocation: event.driverLocation?.position ?? state.driverLocation,
      );
    });
  }
}

final rideFlowProvider = NotifierProvider<RideFlowController, RideFlowState>(
  RideFlowController.new,
);
