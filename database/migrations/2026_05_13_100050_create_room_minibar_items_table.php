<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Par-level setup per room. After check-out, RefillMinibar compares the
     * current stock at the room's minibar location vs the par_level here and
     * transfers the diff from `storage` to the minibar.
     */
    public function up(): void
    {
        Schema::create('room_minibar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('par_level')->default(0);
            $table->timestamps();

            $table->unique(['room_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_minibar_items');
    }
};
