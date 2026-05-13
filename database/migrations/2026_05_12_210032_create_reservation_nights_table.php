<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->decimal('nightly_rate', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->unique(['reservation_id', 'date']);
            $table->index(['room_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_nights');
    }
};
