/// Mirror of `App\Modules\Riding\StateMachine\RideStatus`. Keep in sync
/// with the backend enum.
enum RideStatus {
  requested,
  searching,
  offered,
  accepted,
  driverArriving,
  driverArrived,
  inProgress,
  completed,
  cancelled,
  noDrivers,
  failed;

  static RideStatus fromWire(String value) => switch (value) {
        'requested' => RideStatus.requested,
        'searching' => RideStatus.searching,
        'offered' => RideStatus.offered,
        'accepted' => RideStatus.accepted,
        'driver_arriving' => RideStatus.driverArriving,
        'driver_arrived' => RideStatus.driverArrived,
        'in_progress' => RideStatus.inProgress,
        'completed' => RideStatus.completed,
        'cancelled' => RideStatus.cancelled,
        'no_drivers' => RideStatus.noDrivers,
        'failed' => RideStatus.failed,
        _ => RideStatus.failed,
      };

  bool get isTerminal => switch (this) {
        RideStatus.completed ||
        RideStatus.cancelled ||
        RideStatus.noDrivers ||
        RideStatus.failed =>
          true,
        _ => false,
      };

  bool get isPreAssignment => switch (this) {
        RideStatus.requested || RideStatus.searching || RideStatus.offered => true,
        _ => false,
      };

  bool get hasDriver => switch (this) {
        RideStatus.accepted ||
        RideStatus.driverArriving ||
        RideStatus.driverArrived ||
        RideStatus.inProgress =>
          true,
        _ => false,
      };
}
