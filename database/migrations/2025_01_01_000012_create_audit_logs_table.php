<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('actor_type', 16);                 // ai|employee|system
            $t->foreignId('actor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('action', 64);
            $t->string('subject_type', 64)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('payload_json')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->index(['subject_type', 'subject_id']);
            $t->index(['actor_type', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
