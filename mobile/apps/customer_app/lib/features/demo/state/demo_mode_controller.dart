import 'package:core/core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';

import '../../../di/locator.dart';
import '../../ride/state/ride_flow_controller.dart';
import '../data/demo_fixtures.dart';

/// Discrete points in the customer journey we can preview without a
/// backend. Order matters — [DemoModeController.advance] walks from the
/// top of the list to the bottom.
enum CustomerDemoStage {
  homeIdle,
  fareEstimate,
  searching,
  driverAssigned,
  driverArriving,
  driverArrived,
  inProgress,
  completed;

  /// Human label shown in the floating stepper.
  String get label => switch (this) {
        CustomerDemoStage.homeIdle => 'Home',
        CustomerDemoStage.fareEstimate => 'Fare estimate',
        CustomerDemoStage.searching => 'Searching for driver',
        CustomerDemoStage.driverAssigned => 'Driver assigned',
        CustomerDemoStage.driverArriving => 'Driver en route',
        CustomerDemoStage.driverArrived => 'Driver arrived',
        CustomerDemoStage.inProgress => 'Trip in progress',
        CustomerDemoStage.completed => 'Trip completed',
      };

  CustomerDemoStage? get next {
    final i = CustomerDemoStage.values.indexOf(this);
    if (i + 1 >= CustomerDemoStage.values.length) return null;
    return CustomerDemoStage.values[i + 1];
  }

  /// The ride-tracking phases map onto a [RideStatus]. The pre-tracking
  /// stages don't have a status (the ride doesn't exist yet).
  RideStatus? get rideStatus => switch (this) {
        CustomerDemoStage.homeIdle || CustomerDemoStage.fareEstimate => null,
        CustomerDemoStage.searching => RideStatus.searching,
        CustomerDemoStage.driverAssigned => RideStatus.accepted,
        CustomerDemoStage.driverArriving => RideStatus.driverArriving,
        CustomerDemoStage.driverArrived => RideStatus.driverArrived,
        CustomerDemoStage.inProgress => RideStatus.inProgress,
        CustomerDemoStage.completed => RideStatus.completed,
      };
}

class DemoModeState {
  const DemoModeState({this.enabled = false, this.stage = CustomerDemoStage.homeIdle});

  final bool enabled;
  final CustomerDemoStage stage;

  DemoModeState copyWith({bool? enabled, CustomerDemoStage? stage}) =>
      DemoModeState(enabled: enabled ?? this.enabled, stage: stage ?? this.stage);
}

class DemoModeController extends Notifier<DemoModeState> {
  @override
  DemoModeState build() => const DemoModeState();

  /// Allowed only in the dev flavor. Returns true if demo mode was
  /// entered; false if the build is non-dev (silently refused so the
  /// caller never has to know about flavors).
  bool activate() {
    final env = ref.read(envProvider);
    if (!env.flavor.isDev) return false;
    state = const DemoModeState(enabled: true, stage: CustomerDemoStage.homeIdle);
    // Prime the ride flow with the canned pickup so the home map has
    // something to centre on without GPS permission.
    ref.read(rideFlowProvider.notifier).demoPrimeHome();
    return true;
  }

  /// Walk to the next stage and mutate the ride flow accordingly. Stops
  /// at [CustomerDemoStage.completed].
  void advance() {
    if (!state.enabled) return;
    final next = state.stage.next;
    if (next == null) return;
    _applyStage(next);
  }

  /// Jump to a specific stage. Useful for the "Reset" button.
  void jumpTo(CustomerDemoStage stage) {
    if (!state.enabled) return;
    _applyStage(stage);
  }

  void _applyStage(CustomerDemoStage stage) {
    state = state.copyWith(stage: stage);
    final flow = ref.read(rideFlowProvider.notifier);
    switch (stage) {
      case CustomerDemoStage.homeIdle:
        flow.demoPrimeHome();
      case CustomerDemoStage.fareEstimate:
        flow.demoEnterFareEstimate();
      case CustomerDemoStage.searching:
      case CustomerDemoStage.driverAssigned:
      case CustomerDemoStage.driverArriving:
      case CustomerDemoStage.driverArrived:
      case CustomerDemoStage.inProgress:
      case CustomerDemoStage.completed:
        flow.demoSetRideStatus(stage.rideStatus!);
    }
  }

  /// Leave demo mode and reset everything to a clean home.
  void exit() {
    state = const DemoModeState();
    ref.read(rideFlowProvider.notifier).reset();
  }
}

final demoModeProvider = NotifierProvider<DemoModeController, DemoModeState>(
  DemoModeController.new,
);
