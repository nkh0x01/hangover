<?php

declare(strict_types=1);

namespace App\Modules\Riding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelRideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:120'],
        ];
    }
}
