<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('reason', 64);
            $t->string('urgency', 16);                   // low|medium|high
            $t->float('confidence', 8, 4)->nullable();
            $t->text('summary')->nullable();
            $t->text('suggested_reply')->nullable();
            $t->string('notified_to')->nullable();        // phone or employee id
            $t->boolean('acknowledged')->default(false);
            $t->timestamp('acknowledged_at')->nullable();
            $t->foreignId('acknowledged_by')->nullable()->constrained('employees')->nullOnDelete();
            $t->timestamps();

            $t->index(['acknowledged', 'urgency', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
