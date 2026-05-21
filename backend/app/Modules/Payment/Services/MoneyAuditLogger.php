<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\CauserResolver;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit-log facade for every money-touching action.
 *
 * Dual-write strategy:
 *   1. `storage/logs/payment.log` (daily-rotated, retained 60 days)
 *      — for SRE incident response.
 *   2. `spatie/activitylog` — `activity_log` table, queryable from
 *      the Filament admin panel and joinable to the actor user.
 *
 * Keys logged are intentionally typed (`event`, `subject_id`,
 * `subject_type`, `amount_minor`, `currency`) so the JSON in
 * `properties` stays grep-friendly.
 *
 * Call signatures:
 *
 *   $this->logger->record('payment.captured',
 *       subject: $payment,
 *       amountMinor: 750,
 *       currency: 'GEL',
 *       meta: ['gateway' => 'cash', 'ride_ulid' => '01HX...'],
 *   );
 *
 * The `event` slug uses dot-namespacing to match Sentry tags +
 * Filament filters.
 */
final class MoneyAuditLogger
{
    /** @param array<string, mixed> $meta */
    public function record(
        string $event,
        ?Model $subject = null,
        ?int $amountMinor = null,
        ?string $currency = null,
        array $meta = [],
    ): void {
        $causerId = CauserResolver::resolve()?->getKey();

        $properties = array_filter([
            'event' => $event,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'meta' => $meta !== [] ? $meta : null,
        ], static fn ($v) => $v !== null);

        Log::channel('payment')->info("audit:{$event}", [
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'causer_id' => $causerId,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'meta' => $meta,
        ]);

        try {
            $activity = new Activity;
            $activity->log_name = 'money';
            $activity->description = $event;
            $activity->subject_type = $subject ? $subject::class : null;
            $activity->subject_id = $subject?->getKey();
            $activity->causer_type = $causerId !== null ? \App\Modules\Identity\Models\User::class : null;
            $activity->causer_id = $causerId;
            $activity->properties = $properties;
            $activity->event = $event;
            $activity->save();
        } catch (\Throwable $e) {
            // Don't let an audit-log failure mask the underlying
            // operation. Sentry will pick this up via the default
            // exception reporter.
            Log::channel('payment')->error('MoneyAuditLogger failed to persist activity', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);
        }
    }
}
