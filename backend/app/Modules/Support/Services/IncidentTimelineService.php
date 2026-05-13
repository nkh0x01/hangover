<?php

declare(strict_types=1);

namespace App\Modules\Support\Services;

use App\Modules\Riding\Models\Ride;
use App\Modules\Support\Models\FraudFlag;
use App\Modules\Support\Models\SosEvent;
use App\Modules\Support\Models\SupportTicket;
use Illuminate\Support\Carbon;

/**
 * Composes a unified per-ride safety + incident timeline by querying
 * the four contributor tables: `ride_status_logs`, `support_tickets`
 * (with the ride_id back-ref), `sos_events`, `fraud_flags`
 * (filtered to the ride's customer + driver).
 *
 * Returned in chronological order. The shape is intentionally flat
 * so the Filament view can render it as one Livewire timeline + the
 * mobile-app receipt screen can render it as a single list.
 */
final class IncidentTimelineService
{
    /**
     * @return list<array{
     *   at: string,
     *   kind: string,
     *   severity: string,
     *   subject: ?string,
     *   description: string,
     *   meta: array<string, mixed>,
     * }>
     */
    public function forRide(Ride $ride): array
    {
        $events = [];

        $ride->loadMissing(['statusLogs']);
        foreach ($ride->statusLogs as $log) {
            $events[] = [
                'at' => Carbon::parse((string) $log->created_at)->toIso8601String(),
                'kind' => 'ride.status_changed',
                'severity' => 'info',
                'subject' => null,
                'description' => sprintf(
                    'Status: %s → %s',
                    (string) ($log->from_status ?? '∅'),
                    (string) $log->to_status,
                ),
                'meta' => [
                    'from' => (string) ($log->from_status ?? ''),
                    'to' => (string) $log->to_status,
                    'actor_type' => (string) ($log->actor_type ?? ''),
                ],
            ];
        }

        foreach (SupportTicket::query()->where('ride_id', $ride->id)->orderBy('created_at')->get() as $ticket) {
            $events[] = [
                'at' => Carbon::parse((string) $ticket->created_at)->toIso8601String(),
                'kind' => 'support.ticket_opened',
                'severity' => $this->severityForPriority((string) $ticket->priority),
                'subject' => (string) $ticket->subject,
                'description' => "Complaint: {$ticket->category} (priority {$ticket->priority})",
                'meta' => [
                    'ticket_id' => $ticket->id,
                    'category' => (string) $ticket->category,
                    'status' => (string) $ticket->status,
                ],
            ];
        }

        foreach (SosEvent::query()->where('ride_id', $ride->id)->orderBy('created_at')->get() as $sos) {
            $events[] = [
                'at' => Carbon::parse((string) $sos->created_at)->toIso8601String(),
                'kind' => 'safety.sos_raised',
                'severity' => 'critical',
                'subject' => 'SOS event',
                'description' => $sos->body ?? '(no body)',
                'meta' => [
                    'sos_event_id' => $sos->id,
                    'status' => (string) $sos->status,
                ],
            ];
            if ($sos->resolved_at !== null) {
                $events[] = [
                    'at' => $sos->resolved_at->toIso8601String(),
                    'kind' => 'safety.sos_resolved',
                    'severity' => 'info',
                    'subject' => 'SOS resolved',
                    'description' => "Status: {$sos->status}",
                    'meta' => ['sos_event_id' => $sos->id],
                ];
            }
        }

        // Fraud flags raised against the ride's customer OR driver
        // around the time of the ride.
        $window = [
            $ride->requested_at?->copy()->subMinutes(5),
            $ride->completed_at ?? $ride->cancelled_at ?? now(),
        ];
        if ($window[0] !== null) {
            $subjects = [];
            if ($ride->customer_id !== null) {
                $subjects[] = $ride->customer_id;
            }
            if ($ride->driver?->user_id !== null) {
                $subjects[] = $ride->driver->user_id;
            }
            if ($subjects !== []) {
                $flags = FraudFlag::query()
                    ->whereIn('user_id', $subjects)
                    ->whereBetween('created_at', $window)
                    ->orderBy('created_at')
                    ->get();
                foreach ($flags as $flag) {
                    $events[] = [
                        'at' => Carbon::parse((string) $flag->created_at)->toIso8601String(),
                        'kind' => 'safety.fraud_flag',
                        'severity' => match ((string) $flag->severity) {
                            'block' => 'critical',
                            'warn' => 'warning',
                            default => 'info',
                        },
                        'subject' => "Fraud flag: {$flag->kind}",
                        'description' => (string) $flag->kind,
                        'meta' => [
                            'flag_id' => $flag->id,
                            'subject_user_id' => $flag->user_id,
                            'severity' => (string) $flag->severity,
                        ],
                    ];
                }
            }
        }

        usort($events, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $events;
    }

    private function severityForPriority(string $priority): string
    {
        return match ($priority) {
            'urgent' => 'critical',
            'high' => 'warning',
            default => 'info',
        };
    }
}
