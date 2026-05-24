<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funding_program_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('draft');
            $table->json('business_profile_snapshot');
            $table->decimal('amount_requested_gel', 14, 2)->nullable();
            $table->text('purpose_ka')->nullable();
            $table->foreignId('assigned_consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('funding_program_id');
            $table->index('assigned_consultant_id');
        });

        Schema::create('funding_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index('funding_application_id');
        });

        Schema::create('funding_saved_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funding_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('match_percentage')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'funding_program_id']);
        });

        Schema::create('funding_consultant_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funding_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultant_id')->constrained('users')->cascadeOnDelete();
            $table->text('note_ka');
            $table->text('next_action')->nullable();
            $table->timestamp('next_action_due_at')->nullable();
            $table->timestamps();

            $table->index('funding_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_consultant_notes');
        Schema::dropIfExists('funding_saved_programs');
        Schema::dropIfExists('funding_application_documents');
        Schema::dropIfExists('funding_applications');
    }
};
