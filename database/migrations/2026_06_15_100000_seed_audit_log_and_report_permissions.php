<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Replace the hardcoded role checks on the audit log and reports with real
 * permissions, so access is configurable per-role instead of locked to
 * Admin / Super Admin in code.
 *
 *   audit-log-view  -> Admin, Super Admin (matches the previous role check)
 *   report-view     -> Admin, Super Admin, Logistics Responsible (matches the
 *                      sidebar audience; reports previously had NO authorization)
 */
return new class extends Migration
{
    /** permission name => roles that receive it */
    private array $grants = [
        'audit-log-view' => ['Admin', 'Super Admin'],
        'report-view' => ['Admin', 'Super Admin', 'Logistics Responsible'],
    ];

    public function up(): void
    {
        foreach ($this->grants as $permission => $roles) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

            foreach ($roles as $name) {
                $role = Role::where('name', $name)->where('guard_name', 'web')->first();
                $role?->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->grants as $permission => $roles) {
            foreach ($roles as $name) {
                $role = Role::where('name', $name)->where('guard_name', 'web')->first();
                $role?->revokePermissionTo($permission);
            }

            Permission::where('name', $permission)->where('guard_name', 'web')->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
