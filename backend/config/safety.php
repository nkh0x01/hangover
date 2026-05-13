<?php

declare(strict_types=1);

/*
 * Phase 2.4 — safety + fraud detection thresholds.
 *
 * All values are env-driven so ops can tune in production without a
 * deploy. Conservative defaults — false positives are cheap (an Ops
 * review), false negatives are expensive (a bad ride goes live).
 */

return [
    'cancellation_storm' => [
        // Flag a customer who racks up this many cancellations
        // inside the window.
        'count' => (int) env('SAFETY_CANCEL_STORM_COUNT', 5),
        'window_hours' => (int) env('SAFETY_CANCEL_STORM_WINDOW_HOURS', 2),
    ],

    // Driver heartbeats implying speeds above this number raise a
    // `manipulated_location` flag.
    'implausible_speed_kmh' => (float) env('SAFETY_IMPLAUSIBLE_SPEED_KMH', 200.0),

    'multi_device' => [
        'max_devices' => (int) env('SAFETY_MAX_DEVICES_24H', 4),
    ],

    // SOS event escalation policy.
    'sos' => [
        // After this many minutes without ops acknowledgement, the
        // event auto-pages the on-call rota (Phase 2.5).
        'ack_sla_minutes' => (int) env('SAFETY_SOS_ACK_SLA_MIN', 5),
    ],

    // Verification: documents whose `expires_on` is within this many
    // days surface in the safety dashboard's "expiring soon" widget.
    'documents' => [
        'expiry_warning_days' => (int) env('SAFETY_DOC_EXPIRY_WARNING_DAYS', 30),
    ],
];
