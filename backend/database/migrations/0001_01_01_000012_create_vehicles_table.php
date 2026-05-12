<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $t->enum('type', ['scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric']);
            $t->string('brand', 60);
            $t->string('model', 60);
            $t->string('plate', 20);
            $t->string('color', 30)->nullable();
            $t->smallInteger('year')->nullable();
            $t->string('vin', 40)->nullable();
            $t->boolean('is_active')->default(false);
            $t->json('photos')->nullable();
            $t->boolean('telemetry_supported')->default(false);
            $t->timestamps(3);
            $t->softDeletes('deleted_at', 3);

            $t->index(['driver_id', 'is_active']);
            $t->unique(['plate']);
        });

        Schema::table('drivers', function (Blueprint $t): void {
            $t->foreign('current_vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $t): void {
            $t->dropForeign(['current_vehicle_id']);
        });
        Schema::dropIfExists('vehicles');
    }
};
