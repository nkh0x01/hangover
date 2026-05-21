<?php

declare(strict_types=1);

namespace App\Modules\Riding\Dto;

use App\Support\Geo\Point;

final readonly class RideRequestData
{
    public function __construct(
        public string $fareEstimateUlid,
        public Point $pickup,
        public string $pickupAddress,
        public Point $dropoff,
        public string $dropoffAddress,
        public string $paymentMethod,
        public ?string $note = null,
    ) {}
}
