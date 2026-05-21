/// Decoded form of the server-side JSON error envelope.
/// Matches App\Support\Http\JsonErrorRenderer on the backend.
class ApiError implements Exception {
  ApiError({
    required this.code,
    required this.message,
    this.details = const {},
    this.requestId,
    this.httpStatus,
  });

  /// Machine-readable, e.g. "ride.no_drivers".
  final String code;

  /// Localised human message.
  final String message;

  /// Free-form structured details, e.g. {"fields": {...}}.
  final Map<String, Object?> details;

  final String? requestId;
  final int? httpStatus;

  @override
  String toString() =>
      'ApiError($code, "$message"${requestId != null ? ', req=$requestId' : ''})';

  factory ApiError.fromJson(Map<String, Object?> json, {int? httpStatus}) {
    final err = (json['error'] as Map?)?.cast<String, Object?>() ?? const {};
    return ApiError(
      code: (err['code'] as String?) ?? 'server.unexpected',
      message: (err['message'] as String?) ?? 'Something went wrong',
      details:
          (err['details'] as Map?)?.cast<String, Object?>() ?? const {},
      requestId: err['request_id'] as String?,
      httpStatus: httpStatus,
    );
  }
}
