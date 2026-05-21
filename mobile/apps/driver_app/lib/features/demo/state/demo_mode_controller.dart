import 'package:core/core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';

import '../../../di/locator.dart';
import '../../shift/state/shift_controller.dart';
import '../data/demo_fixtures.dart';

enum DriverDemoStage {
  offlineIdle,
  online,
  incomingOffer,
  driverArriving,
  driverArrived,
  inProgress,
  completed;

  String get label => switch (this) {
        DriverDemoStage.offlineIdle => 'Offline',
        DriverDemoStage.online => 'Online · waiting',
        DriverDemoStage.incomingOffer => 'Incoming offer',
        DriverDemoStage.driverArriving => 'Heading to pickup',
        DriverDemoStage.driverArrived => 'Arrived at pickup',
        DriverDemoStage.inProgress => 'Trip in progress',
        DriverDemoStage.completed => 'Trip completed',
      };

  DriverDemoStage? get next {
    final i = DriverDemoStage.values.indexOf(this);
    if (i + 1 >= DriverDemoStage.values.length) return null;
    return DriverDemoStage.values[i + 1];
  }
}

class DriverDemoState {
  const DriverDemoState({this.enabled = false, this.stage = DriverDemoStage.offlineIdle});

  final bool enabled;
  final DriverDemoStage stage;

  DriverDemoState copyWith({bool? enabled, DriverDemoStage? stage}) =>
      DriverDemoState(enabled: enabled ?? this.enabled, stage: stage ?? this.stage);
}

class DriverDemoController extends Notifier<DriverDemoState> {
  @override
  DriverDemoState build() => const DriverDemoState();

  /// Allowed in any non-prod flavor (dev + staging) so QA can drive
  /// the canned flow from an installed staging APK without a backend
  /// driver profile.
  bool activate() {
    final env = ref.read(envProvider);
    if (env.isProd) return false;
    state = const DriverDemoState(enabled: true, stage: DriverDemoStage.offlineIdle);
    ref.read(shiftProvider.notifier).demoEnterOffline();
    return true;
  }

  void advance() {
    if (!state.enabled) return;
    final next = state.stage.next;
    if (next == null) return;
    _applyStage(next);
  }

  void jumpTo(DriverDemoStage stage) {
    if (!state.enabled) return;
    _applyStage(stage);
  }

  void _applyStage(DriverDemoStage stage) {
    state = state.copyWith(stage: stage);
    final ctl = ref.read(shiftProvider.notifier);
    switch (stage) {
      case DriverDemoStage.offlineIdle:
        ctl.demoEnterOffline();
      case DriverDemoStage.online:
        ctl.demoEnterOnline();
      case DriverDemoStage.incomingOffer:
        ctl.demoInjectOffer();
      case DriverDemoStage.driverArriving:
        ctl.demoSetRideStatus(RideStatus.driverArriving);
      case DriverDemoStage.driverArrived:
        ctl.demoSetRideStatus(RideStatus.driverArrived);
      case DriverDemoStage.inProgress:
        ctl.demoSetRideStatus(RideStatus.inProgress);
      case DriverDemoStage.completed:
        ctl.demoSetRideStatus(RideStatus.completed);
    }
  }

  void exit() {
    state = const DriverDemoState();
    ref.read(shiftProvider.notifier).demoExit();
  }
}

final driverDemoProvider =
    NotifierProvider<DriverDemoController, DriverDemoState>(DriverDemoController.new);
