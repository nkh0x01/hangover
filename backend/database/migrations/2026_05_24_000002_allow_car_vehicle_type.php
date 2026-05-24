<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE vehicles MODIFY type ENUM('scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric', 'car') NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('vehicles')->where('type', 'car')->update(['type' => 'moped']);
        DB::statement("ALTER TABLE vehicles MODIFY type ENUM('scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric') NOT NULL");
    }
};
