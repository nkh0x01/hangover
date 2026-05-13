<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // booking | expedia | airbnb | ical_generic | mock
            $table->string('channel');
            $table->string('name');
            // active | paused | error
            $table->string('status')->default('active');
            // credentials are an encrypted JSON blob (Eloquent cast in the model)
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamp('last_push_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
