import 'package:flutter_riverpod/flutter_riverpod.dart';

class DiagnosticsState {
  const DiagnosticsState({
    this.authState = 'unknown',
    this.locationStatus = 'permission not asked',
    this.lastApiStatus,
    this.lastError,
    this.lastAction = 'app opened',
  });

  final String authState;
  final String locationStatus;
  final int? lastApiStatus;
  final String? lastError;
  final String lastAction;

  DiagnosticsState copyWith({
    String? authState,
    String? locationStatus,
    int? lastApiStatus,
    bool clearApiStatus = false,
    String? lastError,
    bool clearError = false,
    String? lastAction,
  }) {
    return DiagnosticsState(
      authState: authState ?? this.authState,
      locationStatus: locationStatus ?? this.locationStatus,
      lastApiStatus:
          clearApiStatus ? null : (lastApiStatus ?? this.lastApiStatus),
      lastError: clearError ? null : (lastError ?? this.lastError),
      lastAction: lastAction ?? this.lastAction,
    );
  }
}

class DiagnosticsController extends StateNotifier<DiagnosticsState> {
  DiagnosticsController() : super(const DiagnosticsState());

  void auth(String value) => state = state.copyWith(authState: value);

  void location(String value) => state = state.copyWith(locationStatus: value);

  void action(String value) {
    state = state.copyWith(lastAction: value);
  }

  void apiOk(int status, {String? action}) {
    state = state.copyWith(
      lastApiStatus: status,
      lastAction: action,
      clearError: true,
    );
  }

  void apiError({
    required String message,
    int? status,
    String? action,
  }) {
    state = state.copyWith(
      lastApiStatus: status,
      clearApiStatus: status == null,
      lastError: message,
      lastAction: action,
    );
  }
}

final diagnosticsProvider =
    StateNotifierProvider<DiagnosticsController, DiagnosticsState>(
  (ref) => DiagnosticsController(),
);
