<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 introduces walk-in POS sales: a payment + invoice with no
     * reservation. Loosen the FKs on both tables. Existing reservation-based
     * rows are unaffected.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->foreignId('reservation_id')->nullable()->change();
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
            // Source of the payment, distinguishes reservation vs POS sale
            $table->string('source')->default('reservation')->after('status'); // reservation | pos
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->foreignId('reservation_id')->nullable()->change();
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
            $table->string('source')->default('reservation')->after('status'); // reservation | pos
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->dropForeign(['reservation_id']);
            $table->foreignId('reservation_id')->nullable(false)->change();
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->dropForeign(['reservation_id']);
            $table->foreignId('reservation_id')->nullable(false)->change();
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
        });
    }
};
