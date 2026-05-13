<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staging area for inbound channel reservations. Webhooks / pulls always
     * insert here first (idempotent on external_id), then a worker tries to
     * promote each row into a real `reservations` row. If a clash with a
     * direct booking is detected, status flips to 'conflict' and the row
     * stays staged for human resolution — never overwriting local data.
     */
    public function up(): void
    {
        Schema::create('channel_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->char('hash', 64)->nullable();   // payload fingerprint, lets us skip no-op re-pulls
            $table->json('raw_payload');
            $table->foreignId('reservation_id')->nullable()
                ->constrained('reservations')->nullOnDelete();
            // received | processed | conflict | duplicate | failed
            $table->string('status')->default('received');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['channel_connection_id', 'external_id'], 'channel_res_conn_extid_uniq');
            $table->index(['channel_connection_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_reservations');
    }
};
