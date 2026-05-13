<?php

declare(strict_types=1);

namespace App\Support\Observability;

/**
 * Sentry `before_send` callback. Strips phone numbers and Sanctum
 * tokens from event breadcrumb messages so PII never leaves the box.
 *
 * Mirrors the Flutter-side `_scrubPii` regex in
 * `mobile/packages/core/lib/src/observability/crash_reporter.dart`.
 */
final class SentryScrub
{
    /**
     * Signature is `function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event`.
     * We accept untyped args so this class stays loadable when the
     * sentry SDK isn't installed (CI without `composer install`).
     */
    public static function beforeSend(mixed $event, mixed $hint = null): mixed
    {
        if (! is_object($event) || ! method_exists($event, 'getBreadcrumbs')) {
            return $event;
        }

        $breadcrumbs = $event->getBreadcrumbs();
        if ($breadcrumbs === []) {
            return $event;
        }

        $scrubbed = array_map(static function (object $b): object {
            $msg = method_exists($b, 'getMessage') ? $b->getMessage() : null;
            if (! is_string($msg) || $msg === '') {
                return $b;
            }
            $clean = self::mask($msg);
            if ($clean === $msg) {
                return $b;
            }
            if (method_exists($b, 'withMessage')) {
                /** @var object $next */
                $next = $b->withMessage($clean);

                return $next;
            }

            return $b;
        }, $breadcrumbs);

        if (method_exists($event, 'setBreadcrumb')) {
            $event->setBreadcrumb($scrubbed);
        }

        return $event;
    }

    public static function mask(string $s): string
    {
        // Phone numbers (E.164-ish).
        $s = (string) preg_replace_callback(
            '/\+?\d{8,15}/',
            static fn (array $m): string => '+***'.substr($m[0], -3),
            $s,
        );
        // Sanctum personal-access tokens: 40-char hex after a pipe.
        $s = (string) preg_replace('/\|[a-f0-9]{40,}/', '|***', $s);

        return $s;
    }
}
