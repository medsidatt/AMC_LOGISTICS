<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $newPermissions = [
            'maintenance-assign',
            'maintenance-approve',
        ];

        foreach ($newPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        foreach (['Logistics Responsible', 'Super Admin', 'Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) continue;
            $existing = $role->permissions()->pluck('name')->all();
            $role->syncPermissions(array_values(array_unique(array_merge($existing, $newPermissions))));
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $newPermissions = [
            'maintenance-assign',
            'maintenance-approve',
        ];

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
