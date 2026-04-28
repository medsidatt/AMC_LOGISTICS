<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $newPermissions = [
            'weekly-checklist-validate',
            'inspection-list',
            'inspection-show',
            'inspection-create',
            'inspection-edit',
            'inspection-validate',
            'checklist-issue-resolve',
        ];

        foreach ($newPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        $logisticsResponsible = Role::firstOrCreate(['name' => 'Logistics Responsible', 'guard_name' => 'web']);
        $hseAgent = Role::firstOrCreate(['name' => 'HSE Agent', 'guard_name' => 'web']);

        $logisticsScopePerms = [
            'truck-list', 'truck-create', 'truck-edit', 'truck-delete',
            'driver-list', 'driver-create', 'driver-edit', 'driver-delete',
            'transport-tracking-list', 'transport-tracking-create', 'transport-tracking-edit', 'transport-tracking-delete',
            'provider-list', 'provider-create', 'provider-edit', 'provider-delete',
            'transporter-list', 'transporter-create', 'transporter-edit', 'transporter-delete',
            'maintenance-list', 'maintenance-create',
            'logistics-dashboard',
            'entity-list', 'entity-show',
            'project-list', 'project-show',
            'invitation-list', 'invitation-show',
            'weekly-checklist-validate',
            'inspection-list', 'inspection-show', 'inspection-validate',
            'checklist-issue-resolve',
        ];
        $logisticsResponsible->syncPermissions(array_values(array_unique($logisticsScopePerms)));

        $hseScopePerms = [
            'truck-list',
            'driver-list',
            'inspection-list', 'inspection-show', 'inspection-create', 'inspection-edit',
        ];
        $hseAgent->syncPermissions($hseScopePerms);

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $existing = $superAdmin->permissions()->pluck('name')->all();
            $superAdmin->syncPermissions(array_values(array_unique(array_merge($existing, $newPermissions))));
        }

        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $existing = $admin->permissions()->pluck('name')->all();
            $admin->syncPermissions(array_values(array_unique(array_merge($existing, $newPermissions))));
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $newPermissions = [
            'weekly-checklist-validate',
            'inspection-list',
            'inspection-show',
            'inspection-create',
            'inspection-edit',
            'inspection-validate',
            'checklist-issue-resolve',
        ];

        foreach (['Logistics Responsible', 'HSE Agent'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) continue;
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            $role->delete();
        }

        foreach ($newPermissions as $permName) {
            $perm = Permission::where('name', $permName)->first();
            if (!$perm) continue;
            DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $perm->id)->delete();
            $perm->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
