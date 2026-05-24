<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Support\Phone\GeorgianPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

final class OtpRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+9955\d{8}$/'],
            'purpose' => ['required', 'in:signup,login,driver_signup,rebind'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => GeorgianPhoneNumber::normalizeOrOriginal((string) $this->input('phone')),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'ტელეფონის ნომერი არასწორია.',
            'phone.required' => 'ტელეფონის ნომერი აუცილებელია.',
        ];
    }
}
