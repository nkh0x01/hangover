<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5: surface dry-run + live-mode controls on channel_connections.
     *
     * dry_run is the single most important safety flag: when true, providers
     * MUST build their payloads and log them, but MUST NOT issue outbound
     * HTTP. The default is TRUE — a brand-new connection cannot accidentally
     * push to a production OTA before someone explicitly disables it.
     *
     * live_confirmed_at is set by the UI right before a manual live push and
     * checked by the provider. It expires quickly so the confirmation is
     * per-action, not a permanent "yes, go live forever" flag.
     */
    public function up(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->boolean('dry_run')->default(true)->after('status');
            $table->timestamp('live_confirmed_at')->nullable()->after('dry_run');
        });
    }

    public function down(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->dropColumn(['dry_run', 'live_confirmed_at']);
        });
    }
};
