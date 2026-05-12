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
        Schema::create('live_locations', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $t->unsignedBigInteger('ride_id')->nullable();
            $t->timestamp('recorded_at', 3);
            $t->smallInteger('heading')->default(0);
            $t->decimal('speed_kmh', 5, 2)->default(0);
            $t->decimal('accuracy_m', 5, 1)->nullable();
            $t->unsignedTinyInteger('battery_pct')->nullable();
            $t->enum('source', ['mobile_gps', 'telematics'])->default('mobile_gps');

            $t->index(['driver_id', 'recorded_at']);
        });

        DB::statement('ALTER TABLE live_locations ADD COLUMN location POINT NOT NULL SRID 4326');
        DB::statement('ALTER TABLE live_locations ADD SPATIAL INDEX live_locations_location_sp (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('live_locations');
    }
};
