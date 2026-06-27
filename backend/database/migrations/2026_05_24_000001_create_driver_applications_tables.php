<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_applications', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            $t->enum('status', ['draft', 'needs_completion', 'submitted', 'pending', 'manual_review', 'approved', 'rejected', 'needs_changes'])
                ->default('draft')
                ->index();

            $t->string('first_name', 80)->nullable();
            $t->string('last_name', 80)->nullable();
            $t->string('personal_id', 32)->nullable();
            $t->string('phone_e164', 32)->nullable();
            $t->string('email')->nullable();
            $t->date('birth_date')->nullable();
            $t->string('service_zone', 80)->nullable();
            $t->enum('driver_type', ['moto', 'car', 'courier'])->nullable();

            $t->enum('vehicle_type', ['scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric', 'car'])->nullable();
            $t->string('vehicle_brand', 60)->nullable();
            $t->string('vehicle_model', 60)->nullable();
            $t->unsignedSmallInteger('vehicle_year')->nullable();
            $t->string('vehicle_color', 30)->nullable();
            $t->string('vehicle_plate', 20)->nullable();
            $t->string('engine_cc', 20)->nullable();
            $t->date('insurance_expires_on')->nullable();
            $t->date('inspection_expires_on')->nullable();

            $t->boolean('information_confirmed')->default(false);
            $t->boolean('terms_accepted')->default(false);
            $t->boolean('privacy_accepted')->default(false);

            $t->text('rejection_reason')->nullable();
            $t->text('admin_note')->nullable();
            $t->timestamp('submitted_at', 3)->nullable();
            $t->timestamp('reviewed_at', 3)->nullable();
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('metadata')->nullable();
            $t->timestamps(3);
        });

        Schema::create('driver_application_documents', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('application_id')->constrained('driver_applications')->cascadeOnDelete();
            $t->string('doc_type', 40);
            $t->string('file_path');
            $t->string('file_sha256', 64);
            $t->string('mime_type', 80)->nullable();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $t->text('review_notes')->nullable();
            $t->timestamp('reviewed_at', 3)->nullable();
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(3);

            // Explicit short name: the auto-generated name exceeds MySQL's
            // 64-char identifier limit.
            $t->index(['application_id', 'doc_type', 'status'], 'da_docs_app_doc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_application_documents');
        Schema::dropIfExists('driver_applications');
    }
};
