<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();

            $t->index(['notifiable_id', 'notifiable_type', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $t): void {
            $t->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $t->boolean('channel_push')->default(true);
            $t->boolean('channel_sms')->default(true);
            $t->boolean('channel_email')->default(true);
            $t->boolean('marketing')->default(true);
            $t->timestamps(3);
        });

        Schema::create('sms_log', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('phone_e164', 20);
            $t->string('purpose', 60);
            $t->string('provider', 40);
            $t->string('provider_msg_id', 120)->nullable();
            $t->decimal('cost', 8, 4)->nullable();
            $t->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $t->timestamp('sent_at', 3)->nullable();
            $t->timestamp('delivered_at', 3)->nullable();
            $t->timestamps(3);

            $t->index(['phone_e164', 'created_at']);
            $t->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_log');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
