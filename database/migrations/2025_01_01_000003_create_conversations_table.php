<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 16);
            $t->string('thread_id', 191);                 // platform thread/peer id
            $t->string('lead_status', 32)->default('new');
            // new|interested|product_recommended|waiting|payment_pending|order_created|converted|escalated|lost
            $t->boolean('ai_paused')->default(false);
            $t->boolean('escalated')->default(false);
            $t->string('escalation_reason', 64)->nullable();
            $t->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('pending_reply_job_id', 64)->nullable();
            $t->timestamp('last_inbound_at')->nullable();
            $t->timestamp('last_outbound_at')->nullable();
            $t->timestamp('last_followup_at')->nullable();
            $t->json('context_json')->nullable();        // working state for AI
            $t->timestamps();

            $t->unique(['platform', 'thread_id']);
            $t->index(['lead_status', 'last_inbound_at']);
            $t->index(['escalated', 'last_inbound_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
