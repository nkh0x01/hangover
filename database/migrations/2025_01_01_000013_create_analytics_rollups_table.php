<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_rollups', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->unsignedTinyInteger('hour');               // 0..23
            $t->string('platform', 16);
            $t->unsignedInteger('conversations_started')->default(0);
            $t->unsignedInteger('messages_inbound')->default(0);
            $t->unsignedInteger('messages_outbound_ai')->default(0);
            $t->unsignedInteger('messages_outbound_human')->default(0);
            $t->unsignedInteger('escalations')->default(0);
            $t->unsignedInteger('orders_created')->default(0);
            $t->unsignedInteger('orders_paid')->default(0);
            $t->unsignedInteger('comments_handled')->default(0);
            $t->float('avg_response_seconds')->nullable();
            $t->timestamps();

            $t->unique(['day', 'hour', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_rollups');
    }
};
