<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('draft');
            // draft, issued, paid, cancelled
            $table->string('pdf_path')->nullable();
            $table->json('guest_snapshot')->nullable();
            $table->string('vat_number')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'number']);
            $table->index(['reservation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
