<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions added to app/Permissions/*.php after 2026_04_03_500000_sync_all_permissions.php
 * are never inserted into the DB on production, and Super Admin never gets them.
 * Re-run the same sync logic so Super Admin picks up everything that has been
 * added since (maintenance-rule-create, maintenance-rule-deactivate, rotation-validate,
 * and any future additions).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionsDir = app_path('Permissions');
        $permissionFiles = glob($permissionsDir . '/*.php');
        $allPermissions = [];

        foreach ($permissionFiles as $file) {
            $permissions = include($file);
            $allPermissions = array_merge($allPermissions, $permissions);
        }

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op: permissions are additive.
    }
};
