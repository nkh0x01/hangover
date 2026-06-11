<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ka');
            $table->string('name_en')->nullable();
            $table->string('provider', 32);
            $table->string('program_type', 32);
            $table->longText('description_ka');
            $table->text('summary_ka');
            $table->decimal('min_amount_gel', 14, 2)->nullable();
            $table->decimal('max_amount_gel', 14, 2)->nullable();
            $table->unsignedInteger('co_financing_required_pct')->nullable();
            $table->string('application_url')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(true);
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'closes_at']);
            $table->index('provider');
            $table->index('program_type');
        });

        Schema::create('funding_program_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_program_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 48);
            $table->json('criteria');
            $table->unsignedInteger('weight')->default(10);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['funding_program_id', 'rule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_program_rules');
        Schema::dropIfExists('funding_programs');
    }
};
