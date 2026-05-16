<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoEmployeesSeeder extends Seeder
{
    public function run(): void
    {
        Employee::updateOrCreate(
            ['email' => 'owner@gadget.ge'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'whatsapp_phone' => env('ESCALATION_WHATSAPP_TO'),
                'role' => 'owner',
                'is_active' => true,
            ],
        );

        Employee::updateOrCreate(
            ['email' => 'agent@gadget.ge'],
            [
                'name' => 'Agent Demo',
                'password' => Hash::make('password'),
                'role' => 'agent',
                'is_active' => true,
            ],
        );
    }
}
