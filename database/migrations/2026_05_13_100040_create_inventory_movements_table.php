<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only ledger. Every change to product_stock is recorded here.
     *   purchase     null -> location          (received stock)
     *   sale         location -> null          (sold, leaves the property)
     *   transfer     location_a -> location_b  (e.g. storage -> minibar)
     *   refill       same as transfer; semantic flag for minibar restocks
     *   loss         location -> null
     *   damage       location -> null
     *   adjustment   location -> null OR null -> location, depending on sign
     *   return       null -> location          (return to supplier reversal)
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()
                ->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()
                ->constrained('inventory_locations')->nullOnDelete();
            // purchase | sale | transfer | refill | loss | damage | adjustment | return
            $table->string('type');
            $table->integer('quantity'); // always positive; direction is implied by type/from/to
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->foreignId('reservation_id')->nullable()
                ->constrained('reservations')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()
                ->constrained('payments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->index(['property_id', 'type', 'occurred_at']);
            $table->index(['reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
