<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')
                ->constrained('properties')->nullOnDelete();
            $table->string('locale', 5)->default('en')->after('password');
            $table->boolean('active')->default(true)->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
            $table->dropColumn(['locale', 'active', 'last_login_at']);
        });
    }
};
