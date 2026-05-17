<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('status', 32)->default('new');
            // new|interested|product_recommended|waiting|payment_pending|order_created|converted|escalated|lost
            $t->json('product_skus_json')->nullable();   // candidates suggested
            $t->string('intent', 32)->nullable();
            $t->float('score', 8, 4)->nullable();         // lead score 0-1
            $t->string('lost_reason', 64)->nullable();
            $t->timestamp('last_event_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'last_event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
