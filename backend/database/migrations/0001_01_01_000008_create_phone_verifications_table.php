<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_verifications', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('phone_e164', 20);
            $t->char('code_hash', 60);
            $t->enum('purpose', ['signup', 'login', 'rebind', 'driver_signup']);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('sent_at', 3)->useCurrent();
            $t->timestamp('expires_at', 3);
            $t->timestamp('consumed_at', 3)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamps(3);

            $t->index(['phone_e164', 'expires_at']);
            $t->index(['purpose', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');
    }
};
