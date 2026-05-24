<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'product.create', 'product.publish', 'product.delete', 'product.moderate',
            'seller.verify',
            'order.refund', 'order.view_all',
            'user.manage', 'cms.manage',
            'funding_program.manage', 'consultant.view_application',
            'admin.view_dashboard',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $buyer = Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'web']);
        $seller = Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
        $consultant = Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $seller->syncPermissions(['product.create']);
        $consultant->syncPermissions(['consultant.view_application']);
        $admin->syncPermissions($permissions);
    }
}
