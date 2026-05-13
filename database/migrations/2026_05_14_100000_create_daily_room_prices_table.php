<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-day override table for prices + stay restrictions. Two distinct
     * jobs:
     *   1. Override the engine-computed price for a (room_type, date) — the
     *      `price` column, only set when `source = 'manual'`.
     *   2. Per-day restrictions: min_stay, max_stay, closed_to_arrival,
     *      closed_to_departure. Apply to all rooms of a room type.
     *
     * A row exists when at least one of price/restrictions is set; the
     * engine treats an absent row as "no override, no restriction".
     */
    public function up(): void
    {
        Schema::create('daily_room_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            // null = applies to whole room type; non-null = per-room override
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedSmallInteger('min_stay')->nullable();
            $table->unsignedSmallInteger('max_stay')->nullable();
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->unsignedSmallInteger('available_inventory')->nullable();
            $table->string('source')->default('manual'); // manual | rule | channel
            $table->foreignId('rule_id')->nullable(); // FK added in next migration
            $table->timestamps();

            // (room_type, room=NULL, date) and (room_type, room, date) are both unique.
            // MySQL/SQLite enforce uniqueness even with NULL because we coalesce to 0.
            $table->index(['property_id', 'date']);
            $table->index(['room_type_id', 'date']);
            $table->unique(['room_type_id', 'room_id', 'date'], 'daily_room_prices_rt_room_date_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_room_prices');
    }
};
