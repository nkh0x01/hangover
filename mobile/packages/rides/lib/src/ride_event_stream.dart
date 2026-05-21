import 'dart:async';

import 'models/driver_location_payload.dart';
import 'models/ride.dart';
import 'models/ride_status.dart';
import 'ride_repository.dart';

/// One unified stream of ride lifecycle events. In Phase 1.5 we run a
/// polling fallback (2 s) against the API alongside the WS subscription
/// once the realtime package's concrete client is wired — this guarantees
/// the UI never freezes if the broker drops a frame.
///
/// Each emit carries the latest Ride snapshot and (optionally) the
/// freshest driver coordinate. UIs subscribe and treat any emit as the
/// canonical state.
class RideEventStream {
  RideEventStream({required this.repository});

  final RideRepository repository;

  Stream<RideEvent> watch(String rideId, {Duration pollInterval = const Duration(seconds: 2)}) async* {
    Ride? last;
    while (true) {
      try {
        final ride = await repository.show(rideId);
        if (last == null || last.status != ride.status || last.driver?.id != ride.driver?.id) {
          yield RideEvent(ride: ride);
        }
        last = ride;
        if (ride.status.isTerminal) {
          break;
        }
      } on Object catch (_) {
        // Swallow; the next tick will retry. Real WS subscription will
        // produce richer telemetry once wired.
      }
      await Future.delayed(pollInterval);
    }
  }
}

class RideEvent {
  RideEvent({required this.ride, this.driverLocation});

  final Ride ride;
  final DriverLocationPayload? driverLocation;

  RideStatus get status => ride.status;
}
