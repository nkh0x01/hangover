import 'package:core/core.dart';
import 'package:dio/dio.dart';

/// Translates server-side JSON envelopes into ApiError exceptions so
/// repositories can branch on `code` without parsing JSON themselves.
class ErrorInterceptor extends Interceptor {
  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    final status = response.statusCode ?? 0;
    if (status >= 400) {
      final body = response.data;
      if (body is Map<String, Object?>) {
        throw ApiError.fromJson(body, httpStatus: status);
      }
      throw ApiError(
        code: 'http.error',
        message: 'HTTP $status',
        httpStatus: status,
      );
    }
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final response = err.response;
    if (response?.data is Map<String, Object?>) {
      handler.reject(
        DioException(
          requestOptions: err.requestOptions,
          response: response,
          error: ApiError.fromJson(
            response!.data as Map<String, Object?>,
            httpStatus: response.statusCode,
          ),
        ),
      );
      return;
    }
    handler.next(err);
  }
}
