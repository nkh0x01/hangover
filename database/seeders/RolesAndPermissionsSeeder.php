<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission catalog grouped by domain.
     * Convention: <resource>.<action>
     */
    public const PERMISSIONS = [
        // Dashboard
        'dashboard.view',

        // Rooms
        'rooms.view',
        'rooms.manage',
        'rooms.change_cleaning_status',

        // Room types
        'room_types.view',
        'room_types.manage',

        // Guests
        'guests.view',
        'guests.manage',
        'guests.view_pii',

        // Reservations
        'reservations.view',
        'reservations.create',
        'reservations.update',
        'reservations.cancel',
        'reservations.check_in',
        'reservations.check_out',
        'reservations.add_charge',

        // Calendar
        'calendar.view',
        'calendar.block_dates',

        // Payments
        'payments.view',
        'payments.record',
        'payments.refund',

        // Invoices
        'invoices.view',
        'invoices.issue',

        // Reports
        'reports.view',

        // Users / system
        'users.view',
        'users.manage',
        'audit.view',
        'settings.manage',
    ];

    public const ROLE_SUPER_ADMIN   = 'super_admin';
    public const ROLE_MANAGER       = 'manager';
    public const ROLE_RECEPTION     = 'reception';
    public const ROLE_HOUSEKEEPING  = 'housekeeping';
    public const ROLE_ACCOUNTANT    = 'accountant';

    public function run(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        $superAdmin = Role::findOrCreate(self::ROLE_SUPER_ADMIN);
        // Super admin permissions are bypassed via Gate::before(); no need to attach.

        $manager = Role::findOrCreate(self::ROLE_MANAGER);
        $manager->syncPermissions([
            'dashboard.view',
            'rooms.view', 'rooms.manage', 'rooms.change_cleaning_status',
            'room_types.view', 'room_types.manage',
            'guests.view', 'guests.manage', 'guests.view_pii',
            'reservations.view', 'reservations.create', 'reservations.update',
            'reservations.cancel', 'reservations.check_in', 'reservations.check_out',
            'reservations.add_charge',
            'calendar.view', 'calendar.block_dates',
            'payments.view', 'payments.record', 'payments.refund',
            'invoices.view', 'invoices.issue',
            'reports.view',
            'users.view',
            'audit.view',
        ]);

        $reception = Role::findOrCreate(self::ROLE_RECEPTION);
        $reception->syncPermissions([
            'dashboard.view',
            'rooms.view', 'rooms.change_cleaning_status',
            'guests.view', 'guests.manage',
            'reservations.view', 'reservations.create', 'reservations.update',
            'reservations.cancel', 'reservations.check_in', 'reservations.check_out',
            'reservations.add_charge',
            'calendar.view',
            'payments.view', 'payments.record',
            'invoices.view', 'invoices.issue',
        ]);

        $housekeeping = Role::findOrCreate(self::ROLE_HOUSEKEEPING);
        $housekeeping->syncPermissions([
            'dashboard.view',
            'rooms.view', 'rooms.change_cleaning_status',
        ]);

        $accountant = Role::findOrCreate(self::ROLE_ACCOUNTANT);
        $accountant->syncPermissions([
            'dashboard.view',
            'reservations.view',
            'payments.view',
            'invoices.view',
            'reports.view',
        ]);
    }
}
