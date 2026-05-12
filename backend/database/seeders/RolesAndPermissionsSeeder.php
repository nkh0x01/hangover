<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The canonical permission list. Mirrors the matrix in
     * docs/architecture/08-admin-panel-structure.md.
     */
    private const PERMISSIONS = [
        'dashboard.view',
        'livemap.view',
        'user.view',
        'user.suspend',
        'driver.view',
        'driver.approve',
        'driver.suspend',
        'ride.view',
        'ride.cancel',
        'ride.dispatch',
        'refund.create',
        'payout.manage',
        'pricing.manage',
        'promo.manage',
        'support.view',
        'support.respond',
        'fraud.manage',
        'sos.manage',
        'cms.manage',
        'config.manage',
        'audit.view',
        'transaction.view',
    ];

    /**
     * Role -> permission(s) mapping. '*' = all permissions.
     */
    private const ROLES = [
        'super_admin'    => ['*'],
        'ops_admin'      => ['dashboard.view', 'livemap.view', 'user.view', 'user.suspend', 'driver.view', 'driver.approve', 'driver.suspend', 'ride.view', 'ride.cancel', 'promo.manage', 'support.view', 'support.respond', 'fraud.manage', 'sos.manage'],
        'finance_admin'  => ['dashboard.view', 'transaction.view', 'refund.create', 'payout.manage', 'ride.view', 'user.view'],
        'support_agent'  => ['dashboard.view', 'support.view', 'support.respond', 'user.view', 'ride.view'],
        'dispatcher'     => ['dashboard.view', 'livemap.view', 'ride.view', 'ride.dispatch'],
        'driver'         => [],
        'customer'       => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $perm) {
            foreach (['web', 'sanctum'] as $guard) {
                Permission::findOrCreate($perm, $guard);
            }
        }

        foreach (self::ROLES as $role => $perms) {
            foreach (['web', 'sanctum'] as $guard) {
                $r = Role::findOrCreate($role, $guard);
                $r->syncPermissions(
                    $perms === ['*'] ? Permission::where('guard_name', $guard)->get() : $perms,
                );
            }
        }
    }
}
