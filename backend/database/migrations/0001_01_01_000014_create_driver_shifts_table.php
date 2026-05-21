<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_shifts', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $t->timestamp('started_at', 3);
            $t->timestamp('ended_at', 3)->nullable();
            $t->decimal('started_lat', 9, 6);
            $t->decimal('started_lng', 9, 6);
            $t->decimal('ended_lat', 9, 6)->nullable();
            $t->decimal('ended_lng', 9, 6)->nullable();
            $t->decimal('total_distance_km', 8, 2)->nullable();
            $t->decimal('total_earnings', 12, 2)->nullable();
            $t->timestamps(3);

            $t->index(['driver_id', 'started_at']);
        });

        // online_duration_seconds as a MySQL generated column.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE driver_shifts
                    ADD COLUMN online_duration_seconds INT AS (
                        CASE WHEN ended_at IS NULL THEN NULL
                        ELSE TIMESTAMPDIFF(SECOND, started_at, ended_at)
                        END
                    ) STORED
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_shifts');
    }
};
