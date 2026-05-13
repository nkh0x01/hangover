<?php

namespace App\Domain\Pricing;

use App\Domain\Availability\Period;
use App\Domain\Pricing\Rules\HolidayRule;
use App\Domain\Pricing\Rules\LastMinuteRule;
use App\Domain\Pricing\Rules\LengthOfStayRule;
use App\Domain\Pricing\Rules\OccupancyRule;
use App\Domain\Pricing\Rules\Rule;
use App\Domain\Pricing\Rules\SeasonalRule;
use App\Domain\Pricing\Rules\WeekendRule;
use App\Models\AvailabilityCalendar;
use App\Models\DailyRoomPrice;
use App\Models\PricingRule;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;

/**
 * Phase 3 pricing engine. Per night the engine does:
 *
 *   1. Take the BASE price:
 *      a) daily_room_prices.price WHERE source='manual' for that (room_type, date)
 *      b) otherwise room_types.base_price
 *
 *   2. Walk active pricing_rules in priority ASC; each rule that
 *      applies() adjusts the running price via apply().
 *
 *   3. If a manual override was used in step 1, the rules are SKIPPED —
 *      the manual price wins absolutely.
 *
 *   4. Restrictions (min_stay / CTA / CTD / max_stay) come from
 *      daily_room_prices and are returned as a StayRestrictions roll-up.
 *
 * Channel markup is intentionally NOT applied here; that belongs on
 * the OUT path of ChannelSyncService (Phase 4).
 */
class PricingService
{
    private const RULE_MAP = [
        PricingRule::TYPE_WEEKEND        => WeekendRule::class,
        PricingRule::TYPE_SEASONAL       => SeasonalRule::class,
        PricingRule::TYPE_HOLIDAY        => HolidayRule::class,
        PricingRule::TYPE_OCCUPANCY      => OccupancyRule::class,
        PricingRule::TYPE_LAST_MINUTE    => LastMinuteRule::class,
        PricingRule::TYPE_LENGTH_OF_STAY => LengthOfStayRule::class,
    ];

    public function priceForNight(
        RoomType $roomType,
        CarbonImmutable|\DateTimeInterface|string $date,
        ?Room $room = null,
        ?PricingContext $ctxHint = null,
    ): NightlyRate {
        $date = $date instanceof CarbonImmutable
            ? $date
            : CarbonImmutable::parse((string) ($date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date));

        $ctx = $ctxHint ?? new PricingContext(
            roomType: $roomType,
            date: $date->startOfDay(),
            stayLength: 1,
            daysToArrival: max(0, (int) CarbonImmutable::today()->diffInDays($date, false)),
            occupancyPercent: 0.0,
        );

        [$base, $isManual] = $this->basePriceFor($roomType, $date, $room);

        if (! $isManual) {
            foreach ($this->rulesFor($roomType) as $rule) {
                if ($rule->applies($ctx)) {
                    $base = $rule->apply($base, $ctx);
                }
            }
        }

        return new NightlyRate(
            date: $date->startOfDay(),
            amount: round($base, 2),
            currency: $roomType->property->base_currency,
            weekendUplift: in_array($date->dayOfWeekIso, [5, 6], true),
        );
    }

    public function priceForStay(RoomType $roomType, Period $period, ?Room $room = null): StayQuote
    {
        $stayLength = $period->nightCount();
        $today = CarbonImmutable::today();
        $occupancy = $this->occupancyForPeriod($roomType, $period);

        $nights = [];
        foreach ($period->nights() as $date) {
            $ctx = new PricingContext(
                roomType: $roomType,
                date: $date,
                stayLength: $stayLength,
                daysToArrival: max(0, (int) $today->diffInDays($date, false)),
                occupancyPercent: $occupancy,
            );
            $nights[] = $this->priceForNight($roomType, $date, $room, $ctx);
        }

        return new StayQuote(
            nights: $nights,
            currency: $roomType->property->base_currency,
        );
    }

    /**
     * Build the restriction roll-up for a candidate stay. CreateReservation
     * uses this to refuse stays that violate min_stay / CTA / CTD.
     */
    public function restrictionsForStay(RoomType $roomType, Period $period, ?Room $room = null): StayRestrictions
    {
        $relevantDates = array_unique(array_merge(
            $period->nightDates(),
            [$period->checkOut->toDateString()],
        ));

        $rows = DailyRoomPrice::query()
            ->where('room_type_id', $roomType->id)
            ->whereIn('date', $relevantDates)
            ->where(function ($q) use ($room) {
                $q->whereNull('room_id');
                if ($room) {
                    $q->orWhere('room_id', $room->id);
                }
            })
            ->get();

        $maxMin = 0;
        $minMax = null;
        $arrivalClosed = false;
        $departureClosed = false;

        $arrivalDate   = $period->checkIn->toDateString();
        $departureDate = $period->checkOut->toDateString();
        $nightDates    = $period->nightDates();

        foreach ($rows as $row) {
            $dateStr = $row->date->toDateString();

            // min/max only apply to NIGHTS within the stay, not the departure-only day.
            if (in_array($dateStr, $nightDates, true)) {
                if ($row->min_stay) {
                    $maxMin = max($maxMin, (int) $row->min_stay);
                }
                if ($row->max_stay) {
                    $minMax = $minMax === null
                        ? (int) $row->max_stay
                        : min($minMax, (int) $row->max_stay);
                }
            }
            if ($row->closed_to_arrival && $dateStr === $arrivalDate) {
                $arrivalClosed = true;
            }
            if ($row->closed_to_departure && $dateStr === $departureDate) {
                $departureClosed = true;
            }
        }

        return new StayRestrictions($maxMin, $minMax, $arrivalClosed, $departureClosed);
    }

    /**
     * @return list<Rule>
     */
    private function rulesFor(RoomType $roomType): array
    {
        return PricingRule::query()
            ->where('property_id', $roomType->property_id)
            ->where('active', true)
            ->where(function ($q) use ($roomType) {
                $q->where('scope', PricingRule::SCOPE_PROPERTY)
                  ->orWhere(function ($q2) use ($roomType) {
                      $q2->where('scope', PricingRule::SCOPE_ROOM_TYPE)
                         ->where('room_type_id', $roomType->id);
                  });
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(function (PricingRule $row) {
                $class = self::RULE_MAP[$row->type] ?? null;
                return $class ? new $class($row) : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{0: float, 1: bool}  [base price, isManualOverride]
     */
    private function basePriceFor(RoomType $roomType, CarbonImmutable $date, ?Room $room): array
    {
        $override = DailyRoomPrice::query()
            ->where('room_type_id', $roomType->id)
            ->where(function ($q) use ($room) {
                $q->whereNull('room_id');
                if ($room) {
                    $q->orWhere('room_id', $room->id);
                }
            })
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('price')
            ->where('source', DailyRoomPrice::SOURCE_MANUAL)
            // per-room override beats per-room-type — order so NOT-NULL first.
            ->orderByRaw('room_id IS NULL')
            ->first();

        if ($override) {
            return [(float) $override->price, true];
        }

        return [(float) $roomType->base_price, false];
    }

    /**
     * Property-wide occupancy fraction across the period — used by
     * OccupancyRule. We count booked nights / total room nights.
     */
    private function occupancyForPeriod(RoomType $roomType, Period $period): float
    {
        $totalRooms = $roomType->property->rooms()->count();
        if ($totalRooms === 0) {
            return 0.0;
        }
        $totalNights = $totalRooms * $period->nightCount();
        if ($totalNights === 0) {
            return 0.0;
        }

        $booked = AvailabilityCalendar::query()
            ->where('property_id', $roomType->property_id)
            ->where('status', AvailabilityCalendar::STATUS_BOOKED)
            ->whereIn('date', $period->nightDates())
            ->count();

        return round($booked / $totalNights, 4);
    }
}
