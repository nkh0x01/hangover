<?php

namespace App\Domain\Channels\Data;

final class RateDTO
{
    public function __construct(
        public readonly string $externalRoomId,
        public readonly ?string $externalRateId,
        public readonly string $date,    // Y-m-d
        public readonly float $amount,
        public readonly string $currency = 'GEL',
    ) {
    }

    public function toArray(): array
    {
        return [
            'external_room_id' => $this->externalRoomId,
            'external_rate_id' => $this->externalRateId,
            'date' => $this->date,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
