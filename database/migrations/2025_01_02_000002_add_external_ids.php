<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->string('external_id', 64)->nullable()->after('platform_user_id')->index();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->string('external_order_id', 64)->nullable()->after('payment_provider_ref')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('external_id'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('external_order_id'));
    }
};
