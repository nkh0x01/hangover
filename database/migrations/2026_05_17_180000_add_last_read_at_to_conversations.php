<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('last_read_at')->nullable()->after('last_outbound_at');
            $table->index(['last_read_at', 'last_inbound_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['last_read_at', 'last_inbound_at']);
            $table->dropColumn('last_read_at');
        });
    }
};
