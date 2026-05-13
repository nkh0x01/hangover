<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_room_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            // OTA usually maps one external id per room TYPE; allow per-room
            // overrides too via the nullable room_id.
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('external_room_id');
            $table->string('external_room_name')->nullable();
            $table->timestamps();

            $table->unique(['channel_connection_id', 'external_room_id'], 'crm_conn_extid_uniq');
            $table->index(['channel_connection_id', 'room_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_room_mappings');
    }
};
