<?php

namespace App\Livewire\Channels\Booking;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Channels\Data\AvailabilityDTO;
use App\Domain\Channels\Data\RateDTO;
use App\Domain\Channels\Data\RestrictionDTO;
use App\Domain\Channels\Providers\Booking\BookingPayloadBuilder;
use App\Domain\Channels\Services\ChannelMappingService;
use App\Domain\Pricing\PricingService;
use App\Models\AvailabilityCalendar;
use App\Models\ChannelConnection;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\DailyRoomPrice;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Preview payload')]
#[Layout('layouts.app')]
class PreviewPayload extends Component
{
    public ChannelConnection $connection;

    public string $kind = 'availability';

    public string $windowFrom;

    public string $windowTo;

    public function mount(ChannelConnection $connection): void
    {
        abort_unless($connection->isBooking(), 404);
        $this->connection = $connection;
        $this->windowFrom = CarbonImmutable::today()->toDateString();
        $this->windowTo = CarbonImmutable::today()->addDays(7)->toDateString();
    }

    public function render()
    {
        $window = new Period($this->windowFrom, $this->windowTo);
        $builder = app(BookingPayloadBuilder::class);

        $payload = match ($this->kind) {
            'rates' => $builder->rates($this->connection, $this->buildRateRows($window)),
            'restrictions' => $builder->restrictions($this->connection, $this->buildRestrictionRows($window)),
            default => $builder->availability($this->connection, $this->buildAvailabilityRows($window)),
        };

        $pretty = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return view('livewire.channels.booking.preview-payload', [
            'payloadJson' => $pretty,
            'rowCount' => count($payload['availability'] ?? $payload['rates'] ?? $payload['restrictions'] ?? []),
        ]);
    }

    /** @return array<int, AvailabilityDTO> */
    private function buildAvailabilityRows(Period $window): array
    {
        $mappings = ChannelRoomMapping::where('channel_connection_id', $this->connection->id)->get();
        if ($mappings->isEmpty()) {
            return [];
        }

        $property = $this->connection->property()->firstOrFail();
        $matrix = app(AvailabilityService::class)->matrix($property, $window);

        $byRoomTypeNight = [];
        foreach ($property->rooms()->get() as $room) {
            foreach ($window->nightDates() as $date) {
                $row = $matrix[$room->id][$date] ?? null;
                $isOpen = $row && $row->status === AvailabilityCalendar::STATUS_OPEN;
                $byRoomTypeNight[$room->room_type_id][$date]
                    = ($byRoomTypeNight[$room->room_type_id][$date] ?? 0) + ($isOpen ? 1 : 0);
            }
        }

        $out = [];
        foreach ($mappings as $mapping) {
            foreach ($window->nightDates() as $date) {
                $out[] = new AvailabilityDTO(
                    externalRoomId: (string) $mapping->external_room_id,
                    date: $date,
                    available: (int) ($byRoomTypeNight[$mapping->room_type_id][$date] ?? 0),
                );
            }
        }
        return $out;
    }

    /** @return array<int, RateDTO> */
    private function buildRateRows(Period $window): array
    {
        $mappings = ChannelRoomMapping::where('channel_connection_id', $this->connection->id)->get();
        $ratesByType = ChannelRateMapping::where('channel_connection_id', $this->connection->id)
            ->get()
            ->keyBy('room_type_id');

        $out = [];
        foreach ($mappings as $mapping) {
            $roomType = $mapping->roomType()->first();
            if (! $roomType) {
                continue;
            }
            $rateMap = $ratesByType->get($mapping->room_type_id);
            $quote = app(PricingService::class)->priceForStay($roomType, $window);
            foreach ($quote->nights as $night) {
                $out[] = new RateDTO(
                    externalRoomId: (string) $mapping->external_room_id,
                    externalRateId: $rateMap?->external_rate_id,
                    date: $night->date->toDateString(),
                    amount: app(ChannelMappingService::class)->applyMarkup($night->amount, $rateMap),
                    currency: $night->currency,
                );
            }
        }
        return $out;
    }

    /** @return array<int, RestrictionDTO> */
    private function buildRestrictionRows(Period $window): array
    {
        $mappings = ChannelRoomMapping::where('channel_connection_id', $this->connection->id)->get();
        $roomTypeIds = $mappings->pluck('room_type_id')->unique()->all();

        $byKey = DailyRoomPrice::query()
            ->whereIn('room_type_id', $roomTypeIds)
            ->whereIn('date', $window->nightDates())
            ->whereNull('room_id')
            ->get()
            ->keyBy(fn ($r) => $r->room_type_id.'|'.$r->date->toDateString());

        $out = [];
        foreach ($mappings as $mapping) {
            foreach ($window->nightDates() as $date) {
                $r = $byKey->get($mapping->room_type_id.'|'.$date);
                $out[] = new RestrictionDTO(
                    externalRoomId: (string) $mapping->external_room_id,
                    date: $date,
                    minStay: $r?->min_stay,
                    maxStay: $r?->max_stay,
                    closedToArrival: (bool) ($r?->closed_to_arrival ?? false),
                    closedToDeparture: (bool) ($r?->closed_to_departure ?? false),
                );
            }
        }
        return $out;
    }
}
