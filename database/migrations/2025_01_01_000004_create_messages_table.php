<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('platform_msg_id', 191)->nullable()->index();
            $t->string('direction', 8);                  // inbound|outbound
            $t->string('kind', 16)->default('text');     // text|image|audio|video|interactive|template
            $t->text('body')->nullable();
            $t->json('media_json')->nullable();
            $t->boolean('is_ai')->default(false);
            $t->foreignId('author_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->float('confidence', 8, 4)->nullable();
            $t->integer('tokens_in')->nullable();
            $t->integer('tokens_out')->nullable();
            $t->string('intent', 32)->nullable();
            $t->json('tool_calls_json')->nullable();
            $t->json('raw_json')->nullable();            // platform raw payload
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();

            $t->index(['conversation_id', 'created_at']);
            $t->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
