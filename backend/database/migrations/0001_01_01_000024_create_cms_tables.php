<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('slug', 80);
            $t->char('locale', 2);
            $t->string('title', 180);
            $t->longText('body');
            $t->enum('status', ['draft', 'published'])->default('draft');
            $t->timestamp('published_at', 3)->nullable();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(3);
            $t->softDeletes('deleted_at', 3);

            $t->unique(['slug', 'locale']);
            $t->index(['status', 'published_at']);
        });

        Schema::create('app_configs', function (Blueprint $t): void {
            $t->string('key', 120)->primary();
            $t->json('value');
            $t->enum('scope', ['global', 'city'])->default('global');
            $t->foreignId('city_id')->nullable()->constrained('cities')->cascadeOnDelete();
            $t->string('description', 255)->nullable();
            $t->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_configs');
        Schema::dropIfExists('cms_pages');
    }
};
