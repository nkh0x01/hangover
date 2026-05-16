<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 16);                  // whatsapp|messenger|instagram|facebook
            $t->string('platform_user_id', 191);
            $t->string('display_name')->nullable();
            $t->string('phone', 32)->nullable()->index();
            $t->string('locale', 16)->default('ka');
            $t->json('profile_json')->nullable();        // memory document
            $t->boolean('is_vip')->default(false);
            $t->boolean('is_blocked')->default(false);
            $t->boolean('is_spam')->default(false);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();

            $t->unique(['platform', 'platform_user_id']);
            $t->index(['is_vip', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
