<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Http\Resources;

use App\Modules\Pricing\Models\FareEstimate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FareEstimate
 */
final class FareEstimateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'currency' => $this->currency,
            'total_amount' => (float) $this->total_amount,
            'base_fare' => (float) $this->base_fare,
            'surge_multiplier' => (float) $this->surge_multiplier,
            'distance_km' => (float) $this->distance_km,
            'duration_min' => (int) $this->duration_min,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
