<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gadget ERP — S0 core scaffold.
 *
 * Brands and branches form the multi-location backbone (16 branches across
 * a multi-brand structure). integration_logs is the audit spine that makes
 * every external call (RS.ge / FINA / terminal / Glovo / Wolt) tamper-proof
 * against silent failures: a row records both the remote "success" flag and
 * whether the real data change was independently verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('code', 32)->unique();
            $t->string('name', 120);
            $t->boolean('is_flagship')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps(3);
        });

        Schema::create('branches', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('code', 32)->unique();
            $t->string('name', 120);
            $t->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $t->string('city', 80);
            $t->string('address', 255)->nullable();
            $t->string('rs_branch_code', 64)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps(3);

            $t->index('brand_id');
        });

        Schema::table('users', function (Blueprint $t): void {
            $t->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
        });

        Schema::create('integration_logs', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();
            $t->enum('provider', [
                'rs_fiscal', 'rs_waybill', 'rs_invoice', 'fina', 'terminal', 'glovo', 'wolt',
            ]);
            $t->string('operation', 80);
            $t->json('request')->nullable();
            $t->json('response')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            // success = remote reported success. verified = we independently
            // confirmed the real data change. Both must be true to trust it.
            $t->boolean('success')->default(false);
            $t->boolean('verified')->default(false);
            $t->string('idempotency_key', 100)->nullable();
            $t->string('reference', 120)->nullable();
            $t->text('error')->nullable();
            $t->nullableMorphs('ref');
            $t->timestamp('created_at', 3)->useCurrent();

            $t->index(['provider', 'operation']);
            $t->index('idempotency_key');
            $t->index(['success', 'verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');

        Schema::table('users', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
        Schema::dropIfExists('brands');
    }
};
