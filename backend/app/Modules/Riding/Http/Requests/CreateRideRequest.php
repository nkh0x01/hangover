<?php

declare(strict_types=1);

namespace App\Modules\Riding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'fare_estimate_id' => ['required', 'string', 'size:26'],
            'pickup.lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng' => ['required', 'numeric', 'between:-180,180'],
            'pickup.address' => ['required', 'string', 'max:255'],
            'dropoff.lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff.address' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'wallet', 'apple_pay', 'google_pay'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
