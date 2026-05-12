<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Identity\Models\User
 */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'type' => $this->type,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone_e164,
            'phone_verified' => (bool) $this->phone_verified_at,
            'email' => $this->email,
            'email_verified' => (bool) $this->email_verified_at,
            'avatar_url' => $this->avatar_path
                ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->temporaryUrl($this->avatar_path, now()->addMinutes(10))
                : null,
            'locale' => $this->locale,
            'status' => $this->status,
            'referral_code' => $this->referral_code,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
