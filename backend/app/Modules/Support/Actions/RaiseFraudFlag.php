<?php

declare(strict_types=1);

namespace App\Modules\Support\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Support\Models\FraudFlag;
use App\Modules\Support\Services\FraudDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Records a fraud / safety flag against a user. Used by both the
 * automatic detection service ({@see FraudDetector})
 * and the admin "Flag user" Filament action.
 *
 * Severity ladder:
 *   - `info`  — record only, no UI impact.
 *   - `warn`  — surfaced in the safety dashboard; ops decides.
 *   - `block` — automatic suspend hook in {@see SuspendUser} listener.
 *
 * Caller is expected to scope `evidence` to a serialisable map. The
 * column is JSON-cast on the model.
 */
final class RaiseFraudFlag
{
    /** @var list<string> */
    private const KINDS = [
        'multi_account',
        'payment_chargeback',
        'manipulated_location',
        'document_forgery',
        'ride_fraud',
        'abuse',
    ];

    /** @var list<string> */
    private const SEVERITIES = ['info', 'warn', 'block'];

    /**
     * @param array<string, mixed> $evidence
     */
    public function execute(
        User $subject,
        string $kind,
        string $severity,
        array $evidence = [],
        ?User $raisedBy = null,
    ): FraudFlag {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown fraud kind: {$kind}");
        }
        if (! in_array($severity, self::SEVERITIES, true)) {
            throw new InvalidArgumentException("Unknown severity: {$severity}");
        }

        $flag = DB::transaction(function () use ($subject, $kind, $severity, $evidence, $raisedBy): FraudFlag {
            return FraudFlag::create([
                'user_id' => $subject->id,
                'kind' => $kind,
                'severity' => $severity,
                'evidence' => $evidence === [] ? null : $evidence,
                'raised_by' => $raisedBy !== null ? 'admin' : 'system',
                'raised_by_user_id' => $raisedBy?->id,
            ]);
        });

        Log::channel('security')->warning("fraud.{$kind}", [
            'flag_id' => $flag->id,
            'subject_user_id' => $subject->id,
            'severity' => $severity,
            'raised_by' => $raisedBy?->id ?: 'system',
        ]);

        activity('safety')
            ->causedBy($raisedBy)
            ->performedOn($flag)
            ->withProperties([
                'event' => 'fraud.flag_raised',
                'kind' => $kind,
                'severity' => $severity,
                'subject_user_id' => $subject->id,
            ])
            ->log('fraud.flag_raised');

        return $flag;
    }

    public function resolve(FraudFlag $flag, User $by, string $notes): FraudFlag
    {
        $flag->update([
            'resolved_at' => now(),
            'resolved_by_user_id' => $by->id,
            'resolution_notes' => $notes,
        ]);

        activity('safety')
            ->causedBy($by)
            ->performedOn($flag)
            ->withProperties(['event' => 'fraud.flag_resolved', 'notes' => $notes])
            ->log('fraud.flag_resolved');

        return $flag->refresh();
    }
}
