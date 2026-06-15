<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The maintenance edit action moved from `maintenance-create` to its own
 * `maintenance-edit` permission. Grant `maintenance-edit` to every role and
 * user that already had `maintenance-create` so edit access is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'maintenance-edit', 'guard_name' => 'web']);

        foreach (Role::with('permissions')->get() as $role) {
            if ($role->permissions->pluck('name')->contains('maintenance-create')) {
                $role->givePermissionTo('maintenance-edit');
            }
        }

        // Per-user permission overrides.
        foreach (\App\Models\Auth\User::all() as $user) {
            if ($user->getDirectPermissions()->pluck('name')->contains('maintenance-create')) {
                $user->givePermissionTo('maintenance-edit');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::all() as $role) {
            $role->revokePermissionTo('maintenance-edit');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
