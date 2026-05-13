<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('language', 5)->default('en');
            $table->string('doc_type')->nullable();
            // passport, id_card, driver_license, other
            $table->string('doc_number')->nullable();
            $table->string('doc_country', 2)->nullable();
            $table->date('doc_expiry')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->boolean('vip')->default(false);
            $table->boolean('blacklisted')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('email');
            $table->index('doc_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
