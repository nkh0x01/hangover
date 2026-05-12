<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_oauth_identities', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('provider', ['google', 'apple']);
            $t->string('provider_user_id', 190);
            $t->string('email', 190)->nullable();
            $t->timestamps(3);

            $t->unique(['provider', 'provider_user_id']);
            $t->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_oauth_identities');
    }
};
