<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Driver\Services\DriverProfileSummary;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $attrs = $user->getAttributes();
        $avatarPath = $attrs['avatar_path'] ?? null;
        $createdAt = $attrs['created_at'] ?? null;
        $payload = [
            'id' => $attrs['ulid'] ?? (string) $user->getKey(),
            'type' => $attrs['type'] ?? null,
            'first_name' => $attrs['first_name'] ?? null,
            'last_name' => $attrs['last_name'] ?? null,
            'phone' => $attrs['phone_e164'] ?? null,
            'phone_verified' => (bool) ($attrs['phone_verified_at'] ?? null),
            'email' => $attrs['email'] ?? null,
            'email_verified' => (bool) ($attrs['email_verified_at'] ?? null),
            'avatar_url' => $avatarPath
                ? Storage::disk(config('filesystems.default'))->temporaryUrl($avatarPath, now()->addMinutes(10))
                : null,
            'locale' => $attrs['locale'] ?? null,
            'status' => $attrs['status'] ?? null,
            'referral_code' => $attrs['referral_code'] ?? null,
            'created_at' => $createdAt ? Carbon::parse($createdAt)->toIso8601String() : null,
        ];

        if (($attrs['type'] ?? null) === 'driver') {
            /** @var DriverProfileSummary $summary */
            $summary = app(DriverProfileSummary::class);
            $payload['driver_context'] = $summary->forUser($user);
        }

        return $payload;
    }
}
