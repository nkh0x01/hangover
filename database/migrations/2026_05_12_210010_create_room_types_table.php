<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->decimal('base_price', 12, 2);
            $table->unsignedTinyInteger('capacity_adults')->default(2);
            $table->unsignedTinyInteger('capacity_children')->default(0);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->string('bed_type')->nullable();
            $table->unsignedSmallInteger('size_sqm')->nullable();
            $table->text('description')->nullable();
            $table->time('default_check_in_time')->default('14:00:00');
            $table->time('default_check_out_time')->default('11:00:00');
            $table->timestamps();

            $table->unique(['property_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
