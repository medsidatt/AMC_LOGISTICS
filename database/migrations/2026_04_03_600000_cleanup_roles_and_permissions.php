<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 3 roles we keep: Super Admin, Admin, Driver.
     * All others are removed and their users reassigned.
     */
    public function up(): void
    {
        // ── Step 1: Define the permissions we actually need ──
        $keepPermissions = [
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
            // Roles
            'role-list', 'role-show', 'role-create', 'role-edit', 'role-delete',
            // Invitations
            'invitation-list', 'invitation-show', 'invitation-create', 'invitation-edit', 'invitation-delete',
            // Entities
            'entity-list', 'entity-show', 'entity-create', 'entity-edit', 'entity-delete',
            // Projects
            'project-list', 'project-create', 'project-edit', 'project-delete', 'project-show',
            'project-assign-user', 'project-remove-user',
        ];

        // ── Step 2: Reassign users from removed roles to Admin ──
        $removedRoles = ['Employee', 'HR Manager', 'Payroll Manager'];
        $adminRole = Role::where('name', 'Admin')->first();

        foreach ($removedRoles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) continue;

            // Move users to Admin role before deleting
            $userIds = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                // Only add Admin if they don't already have Super Admin
                $hasSuperAdmin = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_id', $userId)
                    ->where('roles.name', 'Super Admin')
                    ->exists();

                if (!$hasSuperAdmin && $adminRole) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $adminRole->id,
                        'model_type' => 'App\\Models\\Auth\\User',
                        'model_id' => $userId,
                    ]);
                }
            }

            // Remove role assignments then delete role
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            $role->delete();
        }

        // ── Step 3: Delete obsolete permissions ──
        $obsoletePermissions = Permission::whereNotIn('name', $keepPermissions)->get();
        foreach ($obsoletePermissions as $perm) {
            DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $perm->id)->delete();
            $perm->delete();
        }

        // ── Step 4: Ensure all needed permissions exist ──
        foreach ($keepPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // ── Step 5: Assign permissions to 3 roles ──

        // Super Admin: ALL permissions
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions($keepPermissions);
        }

        // Admin: logistics + entity + project + user management (no role-delete)
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $adminPerms = array_diff($keepPermissions, ['role-delete', 'role-create']);
            $admin->syncPermissions($adminPerms);
        }

        // Driver: no global permissions (uses dedicated /drivers/my-* routes)
        $driver = Role::where('name', 'Driver')->first();
        if ($driver) {
            $driver->syncPermissions([]);
        }

        // ── Step 6: Clear cache ──
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Recreate removed roles (without restoring user assignments)
        Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Payroll Manager', 'guard_name' => 'web']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
