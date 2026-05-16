<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 64);                       // system|comment|intent|memory_extract|...
            $t->integer('version');
            $t->boolean('is_active')->default(false);
            $t->longText('body');
            $t->string('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $t->timestamps();

            $t->unique(['slug', 'version']);
            $t->index(['slug', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
