<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Single source of truth for the JSON error envelope.
 *
 *   { "error": { "code", "message", "details", "request_id" } }
 *
 * The handler in bootstrap/app.php dispatches each exception type here.
 */
final class JsonErrorRenderer
{
    public static function validation(ValidationException $e): JsonResponse
    {
        return self::envelope(
            status: 422,
            code: 'validation.failed',
            message: __('Validation failed.'),
            details: ['fields' => $e->errors()],
        );
    }

    public static function unauthenticated(AuthenticationException $e): JsonResponse
    {
        return self::envelope(
            status: 401,
            code: 'auth.invalid_token',
            message: __('Authentication required.'),
        );
    }

    public static function forbidden(AuthorizationException $e): JsonResponse
    {
        return self::envelope(
            status: 403,
            code: 'auth.forbidden',
            message: $e->getMessage() ?: __('You are not allowed to perform this action.'),
        );
    }

    public static function domain(DomainException $e): JsonResponse
    {
        return self::envelope(
            status: $e->status(),
            code: $e->code(),
            message: $e->getMessage(),
            details: $e->details,
        );
    }

    public static function http(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        return self::envelope(
            status: $status,
            code: self::httpStatusCode($status),
            message: $e->getMessage() ?: self::httpStatusMessage($status),
        );
    }

    public static function unexpected(Throwable $e): JsonResponse
    {
        report($e);

        $debug = (bool) config('app.debug');

        return self::envelope(
            status: 500,
            code: 'server.unexpected',
            message: $debug ? $e->getMessage() : __('Something went wrong.'),
            details: $debug ? ['trace' => $e->getTraceAsString()] : [],
        );
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function envelope(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
                'request_id' => request()->header('X-Request-Id'),
            ],
        ];

        return new JsonResponse($payload, $status, [
            'Content-Type' => 'application/json',
        ]);
    }

    private static function httpStatusCode(int $status): string
    {
        return match ($status) {
            400 => 'http.bad_request',
            401 => 'auth.invalid_token',
            403 => 'auth.forbidden',
            404 => 'http.not_found',
            405 => 'http.method_not_allowed',
            408 => 'http.request_timeout',
            409 => 'http.conflict',
            410 => 'http.gone',
            413 => 'http.payload_too_large',
            422 => 'validation.failed',
            426 => 'app.outdated',
            429 => 'http.too_many_requests',
            500 => 'server.unexpected',
            502 => 'server.bad_gateway',
            503 => 'server.unavailable',
            504 => 'server.gateway_timeout',
            default => 'http.error',
        };
    }

    private static function httpStatusMessage(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'HTTP error';
    }
}
