<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->char('device_uuid', 36);
            $t->enum('platform', ['ios', 'android', 'web']);
            $t->string('app_version', 20)->nullable();
            $t->string('os_version', 40)->nullable();
            $t->string('fcm_token', 255)->nullable();
            $t->string('voip_token', 255)->nullable();
            $t->boolean('push_enabled')->default(true);
            $t->timestamp('last_active_at', 3)->nullable();
            $t->timestamp('revoked_at', 3)->nullable();
            $t->timestamps(3);

            $t->unique(['user_id', 'device_uuid']);
            $t->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
