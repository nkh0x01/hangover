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
        Schema::create('rides', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();

            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('driver_id')->nullable();
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->foreignId('city_id')->constrained('cities')->restrictOnDelete();

            $t->enum('status', [
                'requested', 'searching', 'offered',
                'accepted', 'driver_arriving', 'driver_arrived',
                'in_progress',
                'completed', 'cancelled', 'no_drivers', 'failed',
            ])->default('requested');

            $t->enum('cancellation_reason', [
                'customer_cancelled', 'driver_cancelled', 'no_driver_found',
                'timeout', 'payment_failed', 'admin_cancelled', 'sos',
            ])->nullable();
            $t->foreignId('cancellation_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('pickup_address', 255);
            $t->string('dropoff_address', 255);

            $t->unsignedBigInteger('fare_estimate_id')->nullable();
            $t->decimal('quoted_amount', 12, 2);
            $t->decimal('final_amount', 12, 2)->nullable();
            $t->decimal('surge_multiplier', 3, 2)->default(1.00);
            $t->decimal('distance_km', 8, 3)->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->unsignedInteger('waiting_seconds')->nullable();
            $t->char('currency', 3);

            $t->enum('payment_method', ['cash', 'card', 'wallet', 'apple_pay', 'google_pay']);
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->unsignedBigInteger('promo_code_id')->nullable();

            $t->decimal('commission_amount', 12, 2)->nullable();
            $t->decimal('driver_earnings', 12, 2)->nullable();

            $t->timestamp('requested_at', 3);
            $t->timestamp('accepted_at', 3)->nullable();
            $t->timestamp('arriving_at', 3)->nullable();
            $t->timestamp('arrived_at', 3)->nullable();
            $t->timestamp('started_at', 3)->nullable();
            $t->timestamp('completed_at', 3)->nullable();
            $t->timestamp('cancelled_at', 3)->nullable();

            $t->unsignedTinyInteger('customer_rating')->nullable();
            $t->unsignedTinyInteger('driver_rating')->nullable();

            $t->timestamps(3);

            $t->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
            $t->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
            $t->foreign('fare_estimate_id')->references('id')->on('fare_estimates')->nullOnDelete();

            $t->index(['customer_id', 'requested_at']);
            $t->index(['driver_id', 'requested_at']);
            $t->index(['city_id', 'status', 'requested_at']);
            $t->index(['status', 'requested_at']);
        });

        DB::statement('ALTER TABLE rides ADD COLUMN pickup_location POINT NOT NULL SRID 4326');
        DB::statement('ALTER TABLE rides ADD COLUMN dropoff_location POINT NOT NULL SRID 4326');
        DB::statement('ALTER TABLE rides ADD SPATIAL INDEX rides_pickup_sp (pickup_location)');
        DB::statement('ALTER TABLE rides ADD SPATIAL INDEX rides_dropoff_sp (dropoff_location)');

        // Generated locking columns: NULL when ride is in a terminal state,
        // the driver/customer id otherwise. Unique indexes on these columns
        // prevent the cardinal "two active rides" bug at the DB level.
        DB::statement(<<<'SQL'
            ALTER TABLE rides
                ADD COLUMN active_driver_lock BIGINT UNSIGNED AS (
                    CASE
                        WHEN status IN ('offered','accepted','driver_arriving','driver_arrived','in_progress')
                        THEN driver_id
                        ELSE NULL
                    END
                ) VIRTUAL,
                ADD COLUMN active_customer_lock BIGINT UNSIGNED AS (
                    CASE
                        WHEN status IN ('requested','searching','offered','accepted','driver_arriving','driver_arrived','in_progress')
                        THEN customer_id
                        ELSE NULL
                    END
                ) VIRTUAL
        SQL);

        DB::statement('CREATE UNIQUE INDEX rides_active_driver_uq   ON rides (active_driver_lock)');
        DB::statement('CREATE UNIQUE INDEX rides_active_customer_uq ON rides (active_customer_lock)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
