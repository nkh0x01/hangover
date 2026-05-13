<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            // in | out
            $table->string('direction');
            // pull_reservations | push_availability | push_rates | push_restrictions | test_connection | webhook_received
            $table->string('action');
            // success | partial | failed
            $table->string('status');
            $table->json('payload_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            // schedule | event | manual | webhook
            $table->string('triggered_by')->default('manual');
            $table->timestamps();

            $table->index(['channel_connection_id', 'started_at']);
            $table->index(['action', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_sync_logs');
    }
};
