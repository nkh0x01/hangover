<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.2 — Pilot operations.
 *
 * Adds two boolean flags to `rides`:
 *
 *   is_test_ride       — Ops-issued ride from a pilot tester / shadow
 *                        driver. Filtered out of business KPIs but
 *                        still walks the full lifecycle (we want to
 *                        exercise the real dispatch path).
 *   pilot_cohort       — Free-text label so we can slice the daily
 *                        report by tester batch ("tbilisi-w1",
 *                        "batumi-w1", etc.). NULL for production rides.
 *
 * Both fields are non-destructive — backfilled NULL/false for every
 * historic row. No code path that ignores them changes its behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            $table->boolean('is_test_ride')
                ->default(false)
                ->after('payment_method')
                ->index();

            $table->string('pilot_cohort', 64)
                ->nullable()
                ->after('is_test_ride')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            $table->dropIndex(['is_test_ride']);
            $table->dropIndex(['pilot_cohort']);
            $table->dropColumn(['is_test_ride', 'pilot_cohort']);
        });
    }
};
