<?php

namespace App\Domain\Channels\Services;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Channels\Exceptions\ChannelMappingException;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\MoveReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\ChannelReservation;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operator-facing tools for fixing staged reservations stuck in 'conflict'
 * or 'failed'. Two resolutions are supported in Phase 4:
 *
 *   - dismiss(): mark the channel row as failed/processed manually and stop
 *     trying to import it. We never overwrite a local booking, so this is
 *     the safe default when the OTA double-booked us.
 *
 *   - retry(): re-runs the importer once a human has resolved the underlying
 *     conflict (moved the local reservation out of the way, fixed a mapping,
 *     etc.).
 */
class ChannelConflictService
{
    public function __construct(
        private readonly ChannelReservationImportService $importer,
        private readonly ChannelMappingService $mapper,
        private readonly AvailabilityService $availability,
        private readonly CreateReservation $createReservation,
        private readonly MoveReservation $moveReservation,
    ) {
    }

    public function dismiss(ChannelReservation $row, ?string $note = null): ChannelReservation
    {
        $row->update([
            'status' => ChannelReservation::STATUS_FAILED,
            'error' => $note ?: 'Manually dismissed by operator.',
            'processed_at' => now(),
        ]);
        return $row->refresh();
    }

    public function retry(ChannelReservation $row): ChannelReservation
    {
        // Reset to received so the importer will try again.
        $row->update([
            'status' => ChannelReservation::STATUS_RECEIVED,
            'error' => null,
            'processed_at' => null,
        ]);
        return $this->importer->process($row->refresh());
    }
}
