<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_status_logs', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $t->string('from_status', 32);
            $t->string('to_status', 32);
            $t->enum('actor_type', ['system', 'customer', 'driver', 'admin', 'dispatcher']);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('reason', 120)->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('occurred_at', 3)->useCurrent();

            $t->index(['ride_id', 'occurred_at']);
        });

        Schema::create('ride_offers', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $t->timestamp('offered_at', 3);
            $t->timestamp('expires_at', 3);
            $t->enum('response', ['pending', 'accepted', 'rejected', 'timeout'])->default('pending');
            $t->timestamp('responded_at', 3)->nullable();
            $t->unsignedInteger('distance_to_pickup_m');
            $t->unsignedInteger('eta_seconds');
            $t->timestamps(3);

            $t->unique(['ride_id', 'driver_id']);
            $t->index(['driver_id', 'offered_at']);
        });

        Schema::create('ride_route_points', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $t->unsignedInteger('seq');
            $t->timestamp('recorded_at', 3);
            $t->decimal('speed_kmh', 5, 2)->default(0);

            $t->index(['ride_id', 'seq']);
        });

        DB::statement('ALTER TABLE ride_route_points ADD COLUMN location POINT NOT NULL SRID 4326');

        Schema::create('ride_messages', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $t->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('body', 1000)->nullable();
            $t->enum('type', ['text', 'quick', 'system', 'image'])->default('text');
            $t->string('attachment_path', 255)->nullable();
            $t->timestamp('sent_at', 3)->useCurrent();
            $t->timestamp('read_at', 3)->nullable();

            $t->index(['ride_id', 'sent_at']);
        });

        Schema::create('ratings', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $t->foreignId('rater_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('ratee_user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('score');
            $t->json('tags')->nullable();
            $t->string('comment', 500)->nullable();
            $t->timestamps(3);

            $t->unique(['ride_id', 'rater_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('ride_messages');
        Schema::dropIfExists('ride_route_points');
        Schema::dropIfExists('ride_offers');
        Schema::dropIfExists('ride_status_logs');
    }
};
