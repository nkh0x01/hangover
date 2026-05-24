import 'dart:async';

import 'package:core/core.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart' as geo;
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
    this.lastAction = 'app opened',
    this.lastApiEndpoint,
    this.lastApiStatus,
    this.lastApiError,
    this.authTokenPresent,
    this.driverProfilePresent,
    this.driverProfileState = 'unknown',
    this.locationStatus = 'permission not asked',
    this.locationAvailable = false,
    this.onlineSince,
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

  final String lastAction;
  final String? lastApiEndpoint;
  final int? lastApiStatus;
  final String? lastApiError;
  final bool? authTokenPresent;
  final bool? driverProfilePresent;
  final String driverProfileState;
  final String locationStatus;
  final bool locationAvailable;
  final String? onlineSince;

  ShiftState copyWith({
    bool? online,
    LatLng? position,
    Ride? activeRide,
    RideOfferPayload? pendingOffer,
    String? error,
    bool? isWorking,
    bool? demoActive,
    String? lastAction,
    String? lastApiEndpoint,
    bool clearApiEndpoint = false,
    int? lastApiStatus,
    bool clearApiStatus = false,
    String? lastApiError,
    bool clearApiError = false,
    bool? authTokenPresent,
    bool clearAuthTokenPresent = false,
    bool? driverProfilePresent,
    bool clearDriverProfilePresent = false,
    String? driverProfileState,
    String? locationStatus,
    bool? locationAvailable,
    String? onlineSince,
    bool clearOnlineSince = false,
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
        lastAction: lastAction ?? this.lastAction,
        lastApiEndpoint:
            clearApiEndpoint ? null : (lastApiEndpoint ?? this.lastApiEndpoint),
        lastApiStatus:
            clearApiStatus ? null : (lastApiStatus ?? this.lastApiStatus),
        lastApiError:
            clearApiError ? null : (lastApiError ?? this.lastApiError),
        authTokenPresent: clearAuthTokenPresent
            ? null
            : (authTokenPresent ?? this.authTokenPresent),
        driverProfilePresent: clearDriverProfilePresent
            ? null
            : (driverProfilePresent ?? this.driverProfilePresent),
        driverProfileState: driverProfileState ?? this.driverProfileState,
        locationStatus: locationStatus ?? this.locationStatus,
        locationAvailable: locationAvailable ?? this.locationAvailable,
        onlineSince:
            clearOnlineSince ? null : (onlineSince ?? this.onlineSince),
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
    _recordAction('go online tap');
    if (state.demoActive) {
      // Skip the network call; just flip the flag so the home page
      // re-renders the "online" affordances.
      state = state.copyWith(
        online: true,
        isWorking: false,
        lastAction: 'go online tap',
        clearError: true,
      );
      return;
    }

    _heartbeat?.cancel();
    _activeRidePoll?.cancel();
    final endpoint = _endpoint(DriverRideRepository.onlinePath);
    state = state.copyWith(
      online: false,
      isWorking: true,
      lastAction: 'go online tap',
      lastApiEndpoint: endpoint,
      clearApiStatus: true,
      clearApiError: true,
      clearError: true,
      clearOnlineSince: true,
    );

    final token = await ref.read(tokenStoreProvider).read();
    final hasToken = token != null && token.trim().isNotEmpty;
    debugPrint('[Ride360 Driver] auth token present: $hasToken');
    state = state.copyWith(authTokenPresent: hasToken);
    if (!hasToken) {
      _failOnline(
        message: 'ავტორიზაცია ვერ დადასტურდა',
        apiMessage: 'missing bearer token',
        driverProfileState: 'unknown',
      );
      return;
    }

    final current = await _resolveCurrentLocation();
    if (current == null) return;

    await _submitOnline(position: current, retriedAfterRefresh: false);
  }

  Future<void> goOffline() async {
    _recordAction('go offline tap');
    if (state.demoActive) {
      state = state.copyWith(online: false, isWorking: false);
      return;
    }

    final endpoint = _endpoint(DriverRideRepository.offlinePath);
    state = state.copyWith(
      isWorking: true,
      lastAction: 'go offline tap',
      lastApiEndpoint: endpoint,
      clearApiStatus: true,
      clearApiError: true,
      clearError: true,
    );
    try {
      final status = await ref.read(driverRideRepositoryProvider).goOffline();
      debugPrint('[Ride360 Driver] POST $endpoint -> $status');
      state = state.copyWith(
        online: false,
        isWorking: false,
        lastApiStatus: status,
        lastAction: 'go offline success',
        clearApiError: true,
        clearOnlineSince: true,
      );
      _heartbeat?.cancel();
      _activeRidePoll?.cancel();
    } catch (e, st) {
      _recordFailure('go offline failed', e, st);
      final apiError = _apiErrorFrom(e);
      state = state.copyWith(
        isWorking: false,
        error: _driverErrorMessage(apiError,
            fallback: 'სერვერთან კავშირი ვერ მოხერხდა'),
        lastApiStatus: apiError?.httpStatus,
        clearApiStatus: apiError?.httpStatus == null,
        lastApiError: _apiDebugMessage(e, apiError),
        lastAction: 'go offline failed',
      );
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
      final ride =
          await ref.read(driverRideRepositoryProvider).acceptOffer(rideId);
      state =
          state.copyWith(activeRide: ride, clearOffer: true, isWorking: false);
    } catch (e) {
      state = state.copyWith(
        isWorking: false,
        error: e.toString(),
        clearOffer: true,
      );
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

  Future<void> arriving() =>
      _transition((r) => ref.read(driverRideRepositoryProvider).arriving(r));
  Future<void> arrived() =>
      _transition((r) => ref.read(driverRideRepositoryProvider).arrived(r));
  Future<void> start() =>
      _transition((r) => ref.read(driverRideRepositoryProvider).start(r));
  Future<void> complete() =>
      _transition((r) => ref.read(driverRideRepositoryProvider).complete(r));

  Future<void> cancel({String reason = 'driver_cancelled'}) async {
    final ride = state.activeRide;
    if (ride == null) return;
    state = state.copyWith(isWorking: true);
    try {
      final cancelled =
          await ref.read(driverRideRepositoryProvider).cancel(ride.id, reason);
      state = state.copyWith(activeRide: cancelled, isWorking: false);
    } catch (e) {
      state = state.copyWith(isWorking: false, error: e.toString());
    }
  }

  void dismissCompletedRide() {
    state = state.copyWith(clearActive: true);
  }

  void updatePosition(LatLng point) {
    state = state.copyWith(
      position: point,
      locationAvailable: true,
      locationStatus: 'granted',
    );
  }

  void simulateIncomingOffer(RideOfferPayload payload) {
    state = state.copyWith(pendingOffer: payload);
  }

  void recordAction(String action) {
    _recordAction(action);
  }

  Future<void> openLocationSettings() async {
    _recordAction('open location settings tap');
    if (state.locationStatus == 'service disabled') {
      await geo.Geolocator.openLocationSettings();
    } else {
      await geo.Geolocator.openAppSettings();
    }
  }

  Future<void> _submitOnline({
    required LatLng position,
    required bool retriedAfterRefresh,
  }) async {
    final endpoint = _endpoint(DriverRideRepository.onlinePath);
    try {
      debugPrint(
        '[Ride360 Driver] POST $endpoint lat=${position.lat} lng=${position.lng}',
      );
      final result = await ref
          .read(driverRideRepositoryProvider)
          .goOnline(position: position);
      debugPrint(
        '[Ride360 Driver] POST $endpoint -> ${result.statusCode} ${result.body}',
      );

      if (!result.online) {
        _failOnline(
          message: 'ცვლის დაწყება ვერ მოხერხდა',
          status: result.statusCode,
          apiMessage: 'backend returned online=false',
          driverProfilePresent: true,
          driverProfileState: 'approved',
        );
        return;
      }

      state = state.copyWith(
        online: true,
        isWorking: false,
        position: position,
        lastApiEndpoint: endpoint,
        lastApiStatus: result.statusCode,
        clearApiError: true,
        clearError: true,
        lastAction: 'go online success',
        driverProfilePresent: true,
        driverProfileState: 'approved',
        onlineSince: result.onlineSince,
      );
      _startHeartbeat();
      _startActiveRidePoll();
    } catch (e, st) {
      _recordFailure('go online failed', e, st);
      final apiError = _apiErrorFrom(e);

      if (!retriedAfterRefresh && _canRefreshFor(apiError)) {
        await _refreshTokenAndRetry(position);
        return;
      }

      final message =
          _driverErrorMessage(apiError, fallback: 'ცვლის დაწყება ვერ მოხერხდა');
      _failOnline(
        message: message,
        status: apiError?.httpStatus,
        apiMessage: _apiDebugMessage(e, apiError),
        driverProfilePresent: _profilePresentFrom(apiError),
        driverProfileState: _profileStateFrom(apiError),
      );
    }
  }

  Future<void> _refreshTokenAndRetry(LatLng position) async {
    try {
      _recordAction('refresh token before go online retry');
      await ref.read(authRepositoryProvider).refresh();
      final token = await ref.read(tokenStoreProvider).read();
      state = state.copyWith(
        authTokenPresent: token != null && token.trim().isNotEmpty,
      );
      await _submitOnline(position: position, retriedAfterRefresh: true);
    } catch (e, st) {
      _recordFailure('token refresh failed', e, st);
      final apiError = _apiErrorFrom(e);
      _failOnline(
        message: 'ავტორიზაცია ვერ დადასტურდა',
        status: apiError?.httpStatus,
        apiMessage: _apiDebugMessage(e, apiError),
        driverProfileState: 'unknown',
      );
    }
  }

  Future<LatLng?> _resolveCurrentLocation() async {
    try {
      final serviceEnabled = await geo.Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        _setLocationState('service disabled', available: false);
        _failOnline(
          message: 'ლოკაციის ნებართვა საჭიროა',
          apiMessage: 'location services disabled',
        );
        return null;
      }

      var permission = await geo.Geolocator.checkPermission();
      debugPrint(
          '[Ride360 Driver] location permission before request: $permission');
      if (permission == geo.LocationPermission.denied) {
        permission = await geo.Geolocator.requestPermission();
        debugPrint(
            '[Ride360 Driver] location permission after request: $permission');
      }

      switch (permission) {
        case geo.LocationPermission.denied:
          _setLocationState('denied', available: false);
          _failOnline(
            message: 'ლოკაციის ნებართვა საჭიროა',
            apiMessage: 'location permission denied',
          );
          return null;
        case geo.LocationPermission.deniedForever:
          _setLocationState('denied', available: false);
          _failOnline(
            message: 'ლოკაციის ნებართვა საჭიროა',
            apiMessage: 'location permission denied forever',
          );
          return null;
        case geo.LocationPermission.unableToDetermine:
          _setLocationState('restricted', available: false);
          _failOnline(
            message: 'ლოკაციის ნებართვა საჭიროა',
            apiMessage: 'location permission restricted',
          );
          return null;
        case geo.LocationPermission.whileInUse:
        case geo.LocationPermission.always:
          _setLocationState('granted', available: true);
      }

      final position = await geo.Geolocator.getCurrentPosition(
        locationSettings: const geo.LocationSettings(
          accuracy: geo.LocationAccuracy.high,
          timeLimit: Duration(seconds: 8),
        ),
      );
      final point = LatLng(position.latitude, position.longitude);
      debugPrint(
        '[Ride360 Driver] current location available: true lat=${point.lat} lng=${point.lng}',
      );
      state = state.copyWith(
        position: point,
        locationStatus: 'granted',
        locationAvailable: true,
      );
      return point;
    } catch (e, st) {
      _recordFailure('location lookup failed', e, st);
      _setLocationState('restricted', available: false);
      _failOnline(
        message: 'ლოკაციის ნებართვა საჭიროა',
        apiMessage: 'location lookup failed: $e',
      );
      return null;
    }
  }

  void _setLocationState(String status, {required bool available}) {
    debugPrint(
      '[Ride360 Driver] location status=$status available=$available',
    );
    state = state.copyWith(
      locationStatus: status,
      locationAvailable: available,
    );
  }

  void _failOnline({
    required String message,
    int? status,
    String? apiMessage,
    bool? driverProfilePresent,
    String? driverProfileState,
  }) {
    debugPrint(
      '[Ride360 Driver] go online blocked: status=$status message=$apiMessage',
    );
    state = state.copyWith(
      online: false,
      isWorking: false,
      error: message,
      lastApiStatus: status,
      clearApiStatus: status == null,
      lastApiError: apiMessage,
      clearApiError: apiMessage == null,
      lastAction: 'go online failed',
      driverProfilePresent: driverProfilePresent,
      driverProfileState: driverProfileState,
      clearOnlineSince: true,
    );
  }

  Future<void> _transition(Future<Ride> Function(String) op) async {
    final ride = state.activeRide;
    if (ride == null) return;
    if (state.demoActive) {
      // Network call is skipped - demo stepper drives status changes
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
      lastAction: 'demo offline',
    );
  }

  /// Demo: flip to "online · waiting for offers" without a network call.
  void demoEnterOnline() {
    state = state.copyWith(
      demoActive: true,
      online: true,
      isWorking: false,
      lastAction: 'demo online',
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
      lastAction: 'demo incoming offer',
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
      lastAction: 'demo ride ${status.name}',
    );
  }

  /// Leave demo mode entirely and reset to a clean offline state.
  void demoExit() {
    _heartbeat?.cancel();
    _activeRidePoll?.cancel();
    state = ShiftState(lastAction: 'demo exit');
  }

  void _startHeartbeat() {
    _heartbeat?.cancel();
    _heartbeat = Timer.periodic(const Duration(seconds: 3), (_) async {
      try {
        await ref.read(driverRideRepositoryProvider).heartbeat(
              position: state.position,
            );
      } catch (e) {
        final apiError = _apiErrorFrom(e);
        debugPrint(
          '[Ride360 Driver] heartbeat failed: ${_apiDebugMessage(e, apiError)}',
        );
      }
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
      } catch (e) {
        final apiError = _apiErrorFrom(e);
        debugPrint(
          '[Ride360 Driver] active ride poll failed: ${_apiDebugMessage(e, apiError)}',
        );
      }
    });
  }

  void _recordAction(String action) {
    debugPrint('[Ride360 Driver] $action');
    state = state.copyWith(lastAction: action);
  }

  void _recordFailure(String action, Object error, StackTrace stackTrace) {
    final apiError = _apiErrorFrom(error);
    debugPrint(
      '[Ride360 Driver] $action: ${_apiDebugMessage(error, apiError)}',
    );
    debugPrintStack(stackTrace: stackTrace);
  }

  String _endpoint(String path) {
    final base =
        ref.read(envProvider).apiBaseUrl.replaceFirst(RegExp(r'/$'), '');
    return '$base/api/v1$path';
  }

  bool _canRefreshFor(ApiError? error) {
    if (error == null) return false;
    if (error.code == 'driver.not_approved' ||
        error.code == 'driver.no_active_vehicle' ||
        error.code == 'driver.not_found') {
      return false;
    }
    return error.code == 'auth.invalid_token' ||
        error.code == 'auth.forbidden' ||
        error.httpStatus == 401;
  }

  ApiError? _apiErrorFrom(Object error) {
    if (error is ApiError) return error;
    try {
      final candidate = (error as dynamic).error;
      if (candidate is ApiError) return candidate;
    } catch (_) {}
    return null;
  }

  String _apiDebugMessage(Object error, ApiError? apiError) {
    if (apiError != null) {
      return '${apiError.code} ${apiError.httpStatus ?? 'no-status'} ${apiError.message}';
    }
    return error.toString();
  }

  String _driverErrorMessage(ApiError? error, {required String fallback}) {
    if (error == null) return 'სერვერთან კავშირი ვერ მოხერხდა';
    return switch (error.code) {
      'auth.invalid_token' => 'ავტორიზაცია ვერ დადასტურდა',
      'auth.forbidden' => 'მძღოლის პროფილი არ არის აქტიური',
      'driver.not_found' => 'მძღოლის პროფილი არ არის აქტიური',
      'driver.not_approved' => 'მძღოლის პროფილი არ არის აქტიური',
      'driver.no_active_vehicle' => 'მძღოლის პროფილი არ არის აქტიური',
      'validation.failed' => 'ლოკაციის ნებართვა საჭიროა',
      'http.request_timeout' => 'სერვერთან კავშირი ვერ მოხერხდა',
      'server.unexpected' => 'სერვერთან კავშირი ვერ მოხერხდა',
      'server.unavailable' => 'სერვერთან კავშირი ვერ მოხერხდა',
      'server.bad_gateway' => 'სერვერთან კავშირი ვერ მოხერხდა',
      _ => fallback,
    };
  }

  bool? _profilePresentFrom(ApiError? error) {
    if (error == null) return state.driverProfilePresent;
    return switch (error.code) {
      'driver.not_found' => false,
      'driver.not_approved' => true,
      'driver.no_active_vehicle' => true,
      'auth.forbidden' => state.driverProfilePresent,
      _ => state.driverProfilePresent,
    };
  }

  String _profileStateFrom(ApiError? error) {
    if (error == null) return state.driverProfileState;
    return switch (error.code) {
      'driver.not_found' => 'not found',
      'driver.not_approved' => 'not approved',
      'driver.no_active_vehicle' => 'missing active vehicle',
      'auth.forbidden' => 'not approved/onboarding or stale token',
      _ => state.driverProfileState,
    };
  }
}

final shiftProvider =
    NotifierProvider<ShiftController, ShiftState>(ShiftController.new);
