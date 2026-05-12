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
        Schema::create('cities', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('country_code', 2);
            $t->string('name', 80);
            $t->string('slug', 80)->unique();
            $t->string('timezone', 50);
            $t->char('default_currency', 3);
            $t->decimal('default_commission_rate', 5, 4)->default(0.20);
            $t->boolean('is_active')->default(true);
            $t->timestamps(3);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cities ADD COLUMN center POINT NOT NULL SRID 4326');
            DB::statement('ALTER TABLE cities ADD COLUMN bounding_polygon POLYGON NULL SRID 4326');
            DB::statement('ALTER TABLE cities ADD SPATIAL INDEX cities_center_sp (center)');
        }

        Schema::create('zones', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $t->string('name', 80);
            $t->enum('kind', ['service_area', 'surge', 'no_go', 'airport', 'event']);
            $t->unsignedTinyInteger('priority')->default(0);
            $t->timestamps(3);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE zones ADD COLUMN polygon POLYGON NOT NULL SRID 4326');
            DB::statement('ALTER TABLE zones ADD SPATIAL INDEX zones_polygon_sp (polygon)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
        Schema::dropIfExists('cities');
    }
};
