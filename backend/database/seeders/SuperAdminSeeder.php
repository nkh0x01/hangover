<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Support\Ulid;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates an admin user for local/dev panels only. The email and
 * password come from .env and default to easy-to-rotate placeholders.
 * Prod uses a separate IaC-managed bootstrap process.
 */
final class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            return;
        }

        $email = (string) config('app.admin_email', 'admin@hangover.local');
        $password = (string) config('app.admin_password', 'change-me-on-first-login');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'ulid' => Ulid::new(),
                'type' => 'admin',
                'first_name' => 'Hangover',
                'last_name' => 'Admin',
                'status' => 'active',
                'locale' => 'en',
                'referral_code' => strtoupper(substr(Ulid::new(), -8)),
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ],
        );

        $user->syncRoles(['super_admin']);
    }
}
