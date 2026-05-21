<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\Rules\Phone;

final class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', (new Phone)->international()],
            'code' => ['required', 'string', 'digits:6'],
            'purpose' => ['required', 'in:signup,login,driver_signup,rebind'],
            'device_uuid' => ['required', 'uuid'],
            'platform' => ['required', 'in:ios,android,web'],
            'app_version' => ['required', 'string', 'max:20'],
            'os_version' => ['nullable', 'string', 'max:40'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'voip_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
