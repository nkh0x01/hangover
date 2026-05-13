<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Declarative pricing rules. Each row is one rule of a known `type`
     * whose `conditions` json says when to apply and whose `action` json
     * says how to adjust the price. The engine walks active rules in
     * `priority` ASC, applying any whose `applies(ctx)` returns true.
     */
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // weekend | seasonal | holiday | occupancy | last_minute | length_of_stay
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('scope')->default('property'); // property | room_type | room
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->json('conditions')->nullable();
            $table->json('action');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['property_id', 'type', 'active']);
            $table->index(['property_id', 'priority']);
        });

        // Add the deferred FK on daily_room_prices.rule_id now that the
        // table exists.
        Schema::table('daily_room_prices', function (Blueprint $table) {
            $table->foreign('rule_id')
                ->references('id')->on('pricing_rules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_room_prices', function (Blueprint $table) {
            $table->dropForeign(['rule_id']);
        });
        Schema::dropIfExists('pricing_rules');
    }
};
