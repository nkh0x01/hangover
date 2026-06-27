<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gadget ERP — S2 POS.
 *
 * Shifts bracket a cashier's session (X/Z reports). Sales are idempotent on
 * sale_uuid so an offline retry never double-rings. Each sale item snapshots
 * the product weighted-average cost at sale time (COGS, from S2). Payments
 * cover cash here; card + RS.ge fiscalization layer on in S3, so fiscal_status
 * starts pending and is only marked verified once the receipt is confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['open', 'closed'])->default('open');
            $t->decimal('opening_cash', 12, 2)->default(0);
            $t->decimal('closing_cash', 12, 2)->nullable();
            $t->json('x_report')->nullable();
            $t->json('z_report')->nullable();
            $t->timestamp('opened_at', 3)->useCurrent();
            $t->timestamp('closed_at', 3)->nullable();
            $t->timestamps(3);

            $t->index(['branch_id', 'status']);
        });

        Schema::create('pos_sales', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('sale_uuid', 36)->unique();
            $t->foreignId('shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            // Customers table arrives in S6; kept as a nullable id until then.
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->enum('channel', ['retail', 'glovo', 'wolt', 'b2b'])->default('retail');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('vat', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->enum('status', ['completed', 'voided'])->default('completed');
            // Set/verified by the S3 fiscalization gateway, never trusted early.
            $t->enum('fiscal_status', ['pending', 'sent', 'verified', 'failed'])->default('pending');
            $t->string('fiscal_receipt_no', 64)->nullable();
            $t->unsignedBigInteger('waybill_id')->nullable();
            $t->timestamps(3);

            $t->index(['branch_id', 'status']);
            $t->index('fiscal_status');
        });

        Schema::create('pos_sale_items', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $t->foreignId('serial_item_id')->nullable()->constrained('serial_items')->nullOnDelete();
            $t->integer('qty');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('vat', 12, 2)->default(0);
            // COGS snapshot: product weighted-average cost at sale time.
            $t->decimal('cost', 12, 2)->default(0);
            $t->timestamps(3);
        });

        Schema::create('pos_payments', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $t->enum('method', ['cash', 'card', 'tbc_cashback']);
            $t->decimal('amount', 12, 2);
            $t->string('terminal_txn_id', 80)->nullable();
            $t->enum('status', ['captured', 'verified', 'failed'])->default('captured');
            $t->timestamps(3);
        });

        Schema::create('cash_movements', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->enum('type', ['in', 'out', 'payout', 'deposit']);
            $t->decimal('amount', 12, 2);
            $t->string('reason', 255)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at', 3)->useCurrent();

            $t->index(['shift_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('pos_shifts');
    }
};
