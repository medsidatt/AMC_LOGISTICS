<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Four features were gated by hardcoded role checks with no permission. They now
 * use real permissions. Grant those permissions to the roles that currently have
 * access so nobody loses it the moment the gates switch.
 *
 *   fleet-settings-edit, driver-discipline-view, driver-discipline-manage
 *       → Admin, Super Admin, Logistics Responsible
 *   fuel-import, fleet-map-view
 *       → Admin, Super Admin
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $map = [
            'fleet-settings-edit'      => ['Admin', 'Super Admin', 'Logistics Responsible'],
            'driver-discipline-view'   => ['Admin', 'Super Admin', 'Logistics Responsible'],
            'driver-discipline-manage' => ['Admin', 'Super Admin', 'Logistics Responsible'],
            'fuel-import'              => ['Admin', 'Super Admin'],
            'fleet-map-view'           => ['Admin', 'Super Admin'],
        ];

        foreach ($map as $permName => $roleNames) {
            $permission = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

            foreach ($roleNames as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                $role?->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['fleet-settings-edit', 'driver-discipline-view', 'driver-discipline-manage', 'fuel-import', 'fleet-map-view'] as $permName) {
            $permission = Permission::where('name', $permName)->where('guard_name', 'web')->first();
            if ($permission) {
                foreach (Role::all() as $role) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
