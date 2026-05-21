<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fare_rules', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $t->enum('vehicle_type', ['scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric']);
            $t->string('name', 60);
            $t->decimal('base_fare', 8, 2)->default(0);
            $t->decimal('price_per_km', 8, 2)->default(0);
            $t->decimal('price_per_min', 8, 2)->default(0);
            $t->decimal('minimum_fare', 8, 2)->default(0);
            $t->decimal('booking_fee', 8, 2)->default(0);
            $t->decimal('commission_rate', 5, 4)->default(0.20);
            $t->unsignedTinyInteger('free_waiting_minutes')->default(3);
            $t->decimal('waiting_fee_per_min', 8, 2)->default(0);
            $t->decimal('cancellation_fee', 8, 2)->default(0);
            $t->dateTime('active_from');
            $t->dateTime('active_until')->nullable();
            $t->unsignedTinyInteger('day_of_week_mask')->default(0x7F);
            $t->time('starts_at_local')->default('00:00:00');
            $t->time('ends_at_local')->default('23:59:59');
            $t->timestamps(3);

            $t->index(['city_id', 'vehicle_type', 'active_from']);
        });

        Schema::create('surge_multipliers', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $t->decimal('multiplier', 3, 2)->default(1.00);
            $t->dateTime('valid_from');
            $t->dateTime('valid_until');
            $t->enum('source', ['manual', 'algorithm'])->default('manual');
            $t->timestamps(3);

            $t->index(['zone_id', 'valid_from']);
        });

        Schema::create('fare_estimates', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();
            $t->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $t->decimal('pickup_lat', 9, 6);
            $t->decimal('pickup_lng', 9, 6);
            $t->decimal('dropoff_lat', 9, 6);
            $t->decimal('dropoff_lng', 9, 6);
            $t->decimal('distance_km', 8, 3);
            $t->unsignedInteger('duration_min');
            $t->decimal('base_fare', 8, 2);
            $t->decimal('surge_multiplier', 3, 2)->default(1.00);
            $t->unsignedBigInteger('promo_code_id')->nullable();
            $t->decimal('total_amount', 12, 2);
            $t->char('currency', 3);
            $t->timestamp('expires_at', 3);
            $t->timestamps(3);

            $t->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fare_estimates');
        Schema::dropIfExists('surge_multipliers');
        Schema::dropIfExists('fare_rules');
    }
};
