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
        Schema::create('support_tickets', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('ride_id')->nullable();
            $t->enum('category', ['payment', 'driver_behaviour', 'lost_item', 'app_bug', 'safety', 'other']);
            $t->string('subject', 140);
            $t->enum('status', ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])->default('open');
            $t->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $t->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(3);
            $t->timestamp('closed_at', 3)->nullable();

            $t->foreign('ride_id')->references('id')->on('rides')->nullOnDelete();
            $t->index(['user_id', 'status']);
            $t->index(['status', 'priority']);
        });

        Schema::create('support_messages', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $t->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->boolean('internal_note')->default(false);
            $t->timestamps(3);

            $t->index(['ticket_id', 'created_at']);
        });

        Schema::create('fraud_flags', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('kind', ['multi_account', 'payment_chargeback', 'manipulated_location', 'document_forgery', 'ride_fraud', 'abuse']);
            $t->enum('severity', ['info', 'warn', 'block'])->default('warn');
            $t->json('evidence')->nullable();
            $t->enum('raised_by', ['system', 'admin'])->default('system');
            $t->foreignId('raised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at', 3)->nullable();
            $t->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('resolution_notes')->nullable();
            $t->timestamps(3);

            $t->index(['user_id', 'severity']);
            $t->index(['kind', 'created_at']);
        });

        Schema::create('sos_events', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('ride_id')->nullable();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->text('body')->nullable();
            $t->enum('status', ['open', 'acknowledged', 'resolved', 'false_alarm'])->default('open');
            $t->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('acknowledged_at', 3)->nullable();
            $t->timestamp('resolved_at', 3)->nullable();
            $t->timestamps(3);

            $t->foreign('ride_id')->references('id')->on('rides')->nullOnDelete();
            $t->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            // An SOS can be raised without a GPS fix, so location is nullable.
            // A SPATIAL INDEX would force NOT NULL and is unused here (SOS rows
            // are looked up by user/status, never by proximity), so it is omitted.
            DB::statement('ALTER TABLE sos_events ADD COLUMN location POINT NULL SRID 4326');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_events');
        Schema::dropIfExists('fraud_flags');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
