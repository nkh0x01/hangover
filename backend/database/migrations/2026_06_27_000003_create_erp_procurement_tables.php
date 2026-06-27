<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gadget ERP — S1 procurement.
 *
 * Suppliers → purchase orders → goods receipts. Confirming a goods receipt
 * is what brings stock on hand and recomputes the product weighted-average
 * cost (COGS source). The inbound RS.ge purchase waybill confirm layers on
 * in S4 via goods_receipts.waybill_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('name', 200);
            $t->string('tax_id', 32)->nullable()->index();
            $t->string('phone', 32)->nullable();
            $t->string('email', 120)->nullable();
            $t->unsignedSmallInteger('payment_terms_days')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps(3);
        });

        Schema::create('purchase_orders', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->enum('status', ['draft', 'sent', 'partial', 'received', 'closed', 'cancelled'])->default('draft');
            $t->decimal('total', 12, 2)->default(0);
            $t->timestamp('expected_at', 3)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(3);

            $t->index(['branch_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $t->integer('qty_ordered');
            $t->integer('qty_received')->default(0);
            $t->decimal('unit_cost', 12, 2);
            $t->timestamps(3);
        });

        Schema::create('goods_receipts', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();
            $t->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            // RS.ge inbound purchase waybill — confirmed in S4 before posting.
            $t->unsignedBigInteger('waybill_id')->nullable();
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at', 3)->nullable();
            $t->timestamps(3);

            $t->index(['branch_id', 'status']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $t->integer('qty');
            $t->decimal('unit_cost', 12, 2);
            $t->json('serial_nos')->nullable();
            $t->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
