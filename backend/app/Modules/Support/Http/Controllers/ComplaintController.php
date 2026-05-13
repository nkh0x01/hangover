<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Controllers;

use App\Modules\Riding\Models\Ride;
use App\Modules\Support\Actions\SubmitComplaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /api/v1/safety/complaints`
 *
 * Opens a support ticket. Categories: safety, driver_behaviour,
 * payment, app_bug, lost_item, other. `safety` short-circuits to
 * priority `urgent`.
 */
final class ComplaintController
{
    public function __construct(private readonly SubmitComplaint $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:safety,driver_behaviour,payment,app_bug,lost_item,other'],
            'subject' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:2000'],
            'ride_ulid' => ['nullable', 'string', 'size:26'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*.filename' => ['required_with:attachments', 'string'],
            'attachments.*.path' => ['required_with:attachments', 'string'],
            'attachments.*.size' => ['required_with:attachments', 'integer'],
            'attachments.*.mime' => ['required_with:attachments', 'string'],
        ]);

        $ride = isset($data['ride_ulid'])
            ? Ride::query()->where('ulid', $data['ride_ulid'])->first()
            : null;

        $ticket = $this->action->execute(
            reporter: $request->user(),
            category: $data['category'],
            subject: $data['subject'],
            body: $data['body'],
            ride: $ride,
            attachments: $data['attachments'] ?? [],
        );

        return new JsonResponse([
            'data' => [
                'id' => $ticket->id,
                'ulid' => $ticket->ulid,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
