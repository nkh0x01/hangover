<?php

declare(strict_types=1);

namespace App\Modules\Driver\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lat'         => ['required', 'numeric', 'between:-90,90'],
            'lng'         => ['required', 'numeric', 'between:-180,180'],
            'heading'     => ['nullable', 'integer', 'between:0,359'],
            'speed_kmh'   => ['nullable', 'numeric', 'between:0,200'],
            'accuracy_m'  => ['nullable', 'numeric', 'between:0,1000'],
            'battery_pct' => ['nullable', 'integer', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
