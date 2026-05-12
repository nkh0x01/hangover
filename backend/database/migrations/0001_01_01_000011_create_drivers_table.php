<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->foreignId('city_id')->constrained('cities')->restrictOnDelete();
            $t->enum('status', ['pending', 'in_review', 'approved', 'rejected', 'suspended'])
                ->default('pending')->index();
            $t->text('approval_notes')->nullable();
            $t->timestamp('approved_at', 3)->nullable();
            $t->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->boolean('online')->default(false)->index();
            $t->timestamp('online_since', 3)->nullable();

            $t->unsignedBigInteger('current_vehicle_id')->nullable();

            $t->decimal('rating_avg', 3, 2)->default(0.00);
            $t->unsignedInteger('rating_count')->default(0);
            $t->unsignedInteger('trips_completed')->default(0);

            $t->decimal('commission_rate_override', 5, 4)->nullable();

            $t->binary('id_number_encrypted')->nullable();
            $t->binary('iban_encrypted')->nullable();

            $t->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
