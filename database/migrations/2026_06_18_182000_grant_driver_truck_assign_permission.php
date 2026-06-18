<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Create the driver-truck-assign permission and grant it to the roles that
 * manage the fleet: Admin, Super Admin, Logistics Responsible.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'driver-truck-assign', 'guard_name' => 'web']);

        foreach (['Admin', 'Super Admin', 'Logistics Responsible'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($perm);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::all() as $role) {
            if ($role->hasPermissionTo('driver-truck-assign')) {
                $role->revokePermissionTo('driver-truck-assign');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
