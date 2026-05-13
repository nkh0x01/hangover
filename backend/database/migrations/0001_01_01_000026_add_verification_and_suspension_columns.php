<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.4 — verification + suspension columns.
 *
 *   drivers.verification_status   — pilot-pinned approval workflow
 *                                   (pending → in_review → verified | rejected).
 *   drivers.verified_at           — first moment all required documents
 *                                   were approved + the vehicle was verified.
 *   drivers.verification_notes    — free-text admin note on rejection.
 *
 *   vehicles.verified_at, verified_by_user_id, verification_notes.
 *
 *   users.suspended_at, suspension_reason, suspended_by_user_id —
 *   compliments the existing `users.status` enum so an audit reader
 *   can see who suspended whom and why (the enum alone loses context).
 *
 * Non-destructive: all columns are nullable + indexed where it makes
 * sense for the safety dashboard query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $t): void {
            $t->enum('verification_status', ['pending', 'in_review', 'verified', 'rejected'])
                ->default('pending')
                ->after('approval_notes')
                ->index();
            $t->timestamp('verified_at', 3)->nullable()->after('verification_status');
            $t->text('verification_notes')->nullable()->after('verified_at');
        });

        Schema::table('vehicles', function (Blueprint $t): void {
            $t->timestamp('verified_at', 3)->nullable()->after('photos');
            $t->unsignedBigInteger('verified_by_user_id')->nullable()->after('verified_at');
            $t->text('verification_notes')->nullable()->after('verified_by_user_id');
            $t->foreign('verified_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $t): void {
            $t->timestamp('suspended_at', 3)->nullable()->after('status')->index();
            $t->string('suspension_reason', 255)->nullable()->after('suspended_at');
            $t->unsignedBigInteger('suspended_by_user_id')->nullable()->after('suspension_reason');
            $t->foreign('suspended_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->dropForeign(['suspended_by_user_id']);
            $t->dropColumn(['suspended_at', 'suspension_reason', 'suspended_by_user_id']);
        });

        Schema::table('vehicles', function (Blueprint $t): void {
            $t->dropForeign(['verified_by_user_id']);
            $t->dropColumn(['verified_at', 'verified_by_user_id', 'verification_notes']);
        });

        Schema::table('drivers', function (Blueprint $t): void {
            $t->dropColumn(['verification_status', 'verified_at', 'verification_notes']);
        });
    }
};
