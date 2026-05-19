<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $perm = 'fleet-roster-plan';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => $this->perm, 'guard_name' => 'web']);

        foreach (['Logistics Responsible', 'Admin', 'Super Admin'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($this->perm);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['Logistics Responsible', 'Admin', 'Super Admin'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            $role?->revokePermissionTo($this->perm);
        }
        Permission::where('name', $this->perm)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
