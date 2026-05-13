<?php

declare(strict_types=1);

namespace App\Modules\Support\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Soft-block + hard-ban path. Flips the user's `status` to
 * `suspended` (recoverable) or `banned` (terminal) and writes the
 * actor + reason + timestamp for the audit trail.
 *
 * Side effects (downstream):
 *   - Sanctum tokens are NOT revoked here — that's the
 *     `RevokeTokensOnSuspension` listener, queued onto the
 *     `default` lane so the response time doesn't block.
 *   - Any active driver shift is force-closed by the
 *     `EndShiftOnSuspension` listener (Driver module).
 *
 * The action is idempotent: re-suspending an already-suspended user
 * updates the reason + actor but keeps the original `suspended_at`.
 */
final class SuspendUser
{
    /** @var list<string> */
    private const ALLOWED_TRANSITIONS = ['active', 'suspended', 'banned'];

    public function suspend(User $subject, User $actor, string $reason): User
    {
        return $this->transition($subject, $actor, 'suspended', $reason);
    }

    public function ban(User $subject, User $actor, string $reason): User
    {
        return $this->transition($subject, $actor, 'banned', $reason);
    }

    public function reinstate(User $subject, User $actor, string $reason): User
    {
        return $this->transition($subject, $actor, 'active', $reason);
    }

    private function transition(User $subject, User $actor, string $to, string $reason): User
    {
        if (! in_array($to, self::ALLOWED_TRANSITIONS, true)) {
            throw new InvalidArgumentException("Unknown status transition: {$to}");
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Status change requires a reason.');
        }

        DB::transaction(function () use ($subject, $actor, $to, $reason): void {
            $updates = [
                'status' => $to,
                'suspension_reason' => $reason,
                'suspended_by_user_id' => $actor->id,
            ];

            if ($to === 'active') {
                $updates['suspended_at'] = null;
                $updates['suspension_reason'] = null;
                $updates['suspended_by_user_id'] = null;
            } elseif ($subject->suspended_at === null) {
                $updates['suspended_at'] = now();
            }

            $subject->update($updates);
        });

        $event = match ($to) {
            'suspended' => 'user.suspended',
            'banned' => 'user.banned',
            'active' => 'user.reinstated',
        };

        Log::channel('security')->warning($event, [
            'subject_user_id' => $subject->id,
            'actor_user_id' => $actor->id,
            'reason' => $reason,
        ]);

        activity('safety')
            ->causedBy($actor)
            ->performedOn($subject)
            ->withProperties(['event' => $event, 'reason' => $reason])
            ->log($event);

        return $subject->refresh();
    }
}
