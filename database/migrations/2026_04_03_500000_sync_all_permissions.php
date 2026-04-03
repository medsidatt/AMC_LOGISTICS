<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Load all permissions from app/Permissions/*.php files
        $permissionsDir = app_path('Permissions');
        $permissionFiles = glob($permissionsDir . '/*.php');
        $allPermissions = [];

        foreach ($permissionFiles as $file) {
            $permissions = include($file);
            $allPermissions = array_merge($allPermissions, $permissions);
        }

        // Create any missing permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Super Admin gets ALL permissions
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }

        // Admin gets logistics + user management + entity + project permissions
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $adminPerms = Permission::whereIn('name', [
                // Logistics
                'truck-list', 'truck-create', 'truck-edit', 'truck-delete',
                'driver-list', 'driver-create', 'driver-edit', 'driver-delete',
                'transport-tracking-list', 'transport-tracking-create', 'transport-tracking-edit', 'transport-tracking-delete',
                'provider-list', 'provider-create', 'provider-edit', 'provider-delete',
                'transporter-list', 'transporter-create', 'transporter-edit', 'transporter-delete',
                'maintenance-list', 'maintenance-create',
                'logistics-dashboard',
                // User management
                'user-list', 'user-show', 'user-create', 'user-edit', 'user-delete',
                'user-change-password', 'user-invitation', 'user-suspend',
                'role-list', 'role-show', 'role-create', 'role-edit',
                'invitation-list', 'invitation-show', 'invitation-create', 'invitation-edit', 'invitation-delete',
                // Entity/Project
                'entity-list', 'entity-show', 'entity-create', 'entity-edit', 'entity-delete',
                'project-list', 'project-create', 'project-edit', 'project-delete', 'project-show', 'project-assign-user',
            ])->pluck('id')->all();
            $admin->syncPermissions($adminPerms);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op: permissions are additive
    }
};
