<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sms_log')) {
            return;
        }

        Schema::table('sms_log', function (Blueprint $t): void {
            if (! Schema::hasColumn('sms_log', 'destination')) {
                $t->string('destination', 32)->nullable()->after('phone_e164');
            }
            if (! Schema::hasColumn('sms_log', 'masked_phone')) {
                $t->string('masked_phone', 32)->nullable()->after('destination');
            }
            if (! Schema::hasColumn('sms_log', 'message_type')) {
                $t->string('message_type', 40)->default('sms')->after('masked_phone');
            }
            if (! Schema::hasColumn('sms_log', 'provider_response')) {
                $t->text('provider_response')->nullable()->after('provider_msg_id');
            }
            if (! Schema::hasColumn('sms_log', 'error_reason')) {
                $t->text('error_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('sms_log', 'skip_reason')) {
                $t->string('skip_reason', 160)->nullable()->after('error_reason');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sms_log')) {
            return;
        }

        Schema::table('sms_log', function (Blueprint $t): void {
            foreach (['skip_reason', 'error_reason', 'provider_response', 'message_type', 'masked_phone', 'destination'] as $column) {
                if (Schema::hasColumn('sms_log', $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }
};
