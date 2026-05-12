<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $t->enum('doc_type', [
                'id_front',
                'id_back',
                'license_front',
                'license_back',
                'insurance',
                'vehicle_registration',
                'selfie_with_id',
            ]);
            $t->string('file_path', 255);
            $t->char('file_sha256', 64);
            $t->date('expires_on')->nullable();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at', 3)->nullable();
            $t->text('review_notes')->nullable();
            $t->timestamps(3);

            $t->index(['driver_id', 'doc_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};
