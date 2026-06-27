<?php

declare(strict_types=1);

namespace App\Modules\Support\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Models\SupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Customer- or driver-side complaint. Creates a {@see SupportTicket}
 * in `open` state, attaches the first message body, sets the priority
 * based on the category ("safety" jumps straight to `urgent`).
 *
 * The action is also the entry point for the in-app "Report" button
 * on the ride card — the optional `attachments` map is preserved
 * verbatim in the first message row.
 */
final class SubmitComplaint
{
    /** @var array<string, string> */
    private const CATEGORY_PRIORITY = [
        'safety' => 'urgent',
        'driver_behaviour' => 'high',
        'payment' => 'high',
        'app_bug' => 'normal',
        'lost_item' => 'normal',
        'other' => 'normal',
    ];

    /**
     * @param array<int, array{filename: string, path: string, size: int, mime: string}> $attachments
     */
    public function execute(
        User $reporter,
        string $category,
        string $subject,
        string $body,
        ?Ride $ride = null,
        array $attachments = [],
    ): SupportTicket {
        if (! isset(self::CATEGORY_PRIORITY[$category])) {
            throw new InvalidArgumentException("Unknown complaint category: {$category}");
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('Complaint body cannot be empty.');
        }

        return DB::transaction(function () use ($reporter, $category, $subject, $body, $ride, $attachments): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id' => $reporter->id,
                'ride_id' => $ride?->id,
                'category' => $category,
                'subject' => substr($subject, 0, 140),
                'status' => 'open',
                'priority' => self::CATEGORY_PRIORITY[$category],
            ]);

            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'sender_user_id' => $reporter->id,
                'body' => $body,
                'attachments' => $attachments === [] ? null : $attachments,
                'internal_note' => false,
            ]);

            Log::channel('security')->info('complaint.submitted', [
                'ticket_id' => $ticket->id,
                'reporter_id' => $reporter->id,
                'ride_id' => $ride?->id,
                'category' => $category,
                'priority' => $ticket->priority,
            ]);

            activity('safety')
                ->causedBy($reporter)
                ->performedOn($ticket)
                ->withProperties([
                    'event' => 'complaint.submitted',
                    'category' => $category,
                    'priority' => $ticket->priority,
                    'ride_id' => $ride?->id,
                ])
                ->log('complaint.submitted');

            return $ticket;
        });
    }
}
