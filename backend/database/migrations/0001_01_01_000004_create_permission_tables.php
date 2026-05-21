<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        Schema::create($tables['permissions'], function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create($tables['roles'], function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create($tables['model_has_permissions'], function (Blueprint $t) use ($tables, $columns): void {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger($columns['model_morph_key']);
            $t->index([$columns['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $t->foreign('permission_id')->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $t->primary(['permission_id', $columns['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $t) use ($tables, $columns): void {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger($columns['model_morph_key']);
            $t->index([$columns['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $t->foreign('role_id')->references('id')->on($tables['roles'])->cascadeOnDelete();
            $t->primary(['role_id', $columns['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tables['role_has_permissions'], function (Blueprint $t) use ($tables): void {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->foreign('permission_id')->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $t->foreign('role_id')->references('id')->on($tables['roles'])->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::drop($tables['role_has_permissions']);
        Schema::drop($tables['model_has_roles']);
        Schema::drop($tables['model_has_permissions']);
        Schema::drop($tables['roles']);
        Schema::drop($tables['permissions']);
    }
};
