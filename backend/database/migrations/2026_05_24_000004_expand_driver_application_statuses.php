<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE driver_applications MODIFY status ENUM('draft','needs_completion','submitted','pending','manual_review','approved','rejected','needs_changes') NOT NULL DEFAULT 'draft'",
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('driver_applications')
            ->whereIn('status', ['needs_completion', 'manual_review'])
            ->update(['status' => 'draft']);

        DB::statement(
            "ALTER TABLE driver_applications MODIFY status ENUM('draft','submitted','pending','approved','rejected','needs_changes') NOT NULL DEFAULT 'draft'",
        );
    }
};
