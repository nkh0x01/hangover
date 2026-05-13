<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // reception | storage | room_minibar
            $table->string('type');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            // For room_minibar locations, room_id must be unique; for shared
            // locations (reception, storage) room_id is null. We let the unique
            // index be on (property_id, type, room_id, name) so we can still
            // distinguish, while the room_minibar rows are pinned 1:1 with room.
            $table->unique(['property_id', 'type', 'room_id'], 'inv_locations_property_type_room_uniq');
            $table->index(['property_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
