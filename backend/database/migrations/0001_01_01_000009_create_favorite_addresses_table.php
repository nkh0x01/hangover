<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_addresses', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('label', 40);
            $t->string('address_text', 255);
            $t->string('place_id', 255)->nullable();
            $t->boolean('is_home')->default(false);
            $t->boolean('is_work')->default(false);
            $t->timestamps(3);

            $t->index(['user_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE favorite_addresses ADD COLUMN location POINT NOT NULL SRID 4326');
            DB::statement('ALTER TABLE favorite_addresses ADD SPATIAL INDEX favorite_addresses_location_sp (location)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_addresses');
    }
};
