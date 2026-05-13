<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_rate_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            // We don't have rate_plans table yet (Phase 5/6). Reserved for future.
            $table->unsignedBigInteger('rate_plan_id')->nullable();
            $table->string('external_rate_id');
            $table->string('external_rate_name')->nullable();
            $table->decimal('markup_percent', 6, 2)->nullable();
            $table->decimal('markup_abs', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['channel_connection_id', 'external_rate_id'], 'cratm_conn_extid_uniq');
            $table->index(['channel_connection_id', 'room_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_rate_mappings');
    }
};
