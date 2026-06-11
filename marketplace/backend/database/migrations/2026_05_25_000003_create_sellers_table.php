<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('business_name');
            $table->string('legal_form', 32);
            $table->string('tax_id', 32)->nullable();
            $table->string('sector', 32);
            $table->string('region', 64);
            $table->string('municipality')->nullable();
            $table->unsignedInteger('business_age_months')->default(0);
            $table->decimal('annual_revenue_gel', 14, 2)->nullable();
            $table->unsignedInteger('employees_count')->default(0);
            $table->boolean('is_woman_owned')->default(false);
            $table->boolean('is_youth_owned')->default(false);
            $table->boolean('is_mountainous_region')->default(false);
            $table->boolean('is_startup')->default(false);
            $table->boolean('is_agriculture')->default(false);
            $table->boolean('is_made_in_georgia_verified')->default(false);
            $table->longText('story')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('verification_status', 16)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('verification_status');
            $table->index('sector');
            $table->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
