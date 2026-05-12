<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('open');
            // open, booked, blocked, maintenance
            $table->foreignId('reservation_id')->nullable()
                ->constrained('reservations')->nullOnDelete();
            $table->string('blocked_reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // CRITICAL: this constraint is the database-level guarantee
            // that a single room cannot be booked twice on the same date.
            $table->unique(['room_id', 'date']);
            $table->index(['property_id', 'date']);
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_calendar');
    }
};
