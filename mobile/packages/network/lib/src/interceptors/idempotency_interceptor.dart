import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

/// Generates an Idempotency-Key header for every mutating request so
/// safe retries don't double-bill or double-book.
class IdempotencyInterceptor extends Interceptor {
  IdempotencyInterceptor() : _uuid = const Uuid();

  final Uuid _uuid;
  static const _methods = {'POST', 'PATCH', 'PUT'};

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (_methods.contains(options.method.toUpperCase()) &&
        !options.headers.containsKey('Idempotency-Key')) {
      options.headers['Idempotency-Key'] = _uuid.v4();
    }
    handler.next(options);
  }
}
