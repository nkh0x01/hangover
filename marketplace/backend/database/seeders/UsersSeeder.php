<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@marketplace.local'],
            ['name' => 'პლატფორმის ადმინისტრატორი', 'password' => Hash::make('password'), 'locale' => 'ka'],
        );
        $admin->syncRoles(['admin']);

        $consultant = User::firstOrCreate(
            ['email' => 'consultant@marketplace.local'],
            ['name' => 'დაფინანსების კონსულტანტი', 'password' => Hash::make('password'), 'locale' => 'ka'],
        );
        $consultant->syncRoles(['consultant']);

        for ($i = 1; $i <= 5; $i++) {
            $buyer = User::firstOrCreate(
                ['email' => "buyer{$i}@marketplace.local"],
                ['name' => "მყიდველი $i", 'password' => Hash::make('password'), 'locale' => 'ka'],
            );
            $buyer->syncRoles(['buyer']);
        }
    }
}
