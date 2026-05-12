<?php

declare(strict_types=1);

namespace App\Modules\Driver\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetOnlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'vehicle_id' => ['nullable', 'integer'],
        ];
    }
}
