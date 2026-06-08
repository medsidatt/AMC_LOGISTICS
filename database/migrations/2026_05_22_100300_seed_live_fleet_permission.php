<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Register the `live-fleet-view` permission and grant it to the roles that
 * need to read the Live Fleet Board: Logistics Responsible (primary user),
 * Admin / Super Admin (oversight), HSE Agent (audit, read-only).
 */
return new class extends Migration
{
    private string $permission = 'live-fleet-view';

    private array $rolesGetting = [
        'Logistics Responsible',
        'Admin',
        'Super Admin',
        'HSE Agent',
    ];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => $this->permission, 'guard_name' => 'web']);

        foreach ($this->rolesGetting as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($this->permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->rolesGetting as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            $role?->revokePermissionTo($this->permission);
        }

        Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
