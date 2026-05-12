<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->timestamps();

            $table->unique(['reservation_id', 'guest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_guests');
    }
};
