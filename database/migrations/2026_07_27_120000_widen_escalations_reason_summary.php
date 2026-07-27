<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The escalate_to_human tool lets Claude write a free-text reason/summary,
 * but `reason` was varchar(64) — long AI reasons overflowed and the
 * escalation INSERT crashed ("Data too long for column 'reason'"), silently
 * dropping escalations. Widen both to TEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalations', function (Blueprint $table) {
            $table->text('reason')->nullable()->change();
            $table->text('summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('escalations', function (Blueprint $table) {
            $table->string('reason', 64)->nullable()->change();
            $table->string('summary', 255)->nullable()->change();
        });
    }
};
