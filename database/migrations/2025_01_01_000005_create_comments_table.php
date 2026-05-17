<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 16);                  // facebook|instagram
            $t->string('post_id', 191);
            $t->string('comment_id', 191)->unique();
            $t->string('parent_comment_id', 191)->nullable();
            $t->string('platform_user_id', 191)->nullable();
            $t->string('author_name')->nullable();
            $t->text('body')->nullable();
            $t->float('sentiment', 8, 4)->nullable();
            $t->string('intent', 32)->nullable();
            $t->boolean('replied')->default(false);
            $t->boolean('escalated')->default(false);
            $t->string('reply_comment_id', 191)->nullable();
            $t->text('reply_body')->nullable();
            $t->boolean('private_reply_attempted')->default(false);
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();

            $t->index(['platform', 'replied', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
