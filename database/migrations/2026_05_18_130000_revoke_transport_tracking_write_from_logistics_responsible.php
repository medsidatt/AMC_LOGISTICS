<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Logistics Responsible should only VIEW transport trackings, not modify them.
 * Revokes create / edit / delete permissions. List + show stay granted.
 */
return new class extends Migration
{
    private array $toRevoke = [
        'transport-tracking-create',
        'transport-tracking-edit',
        'transport-tracking-delete',
    ];

    public function up(): void
    {
        $role = Role::where('name', 'Logistics Responsible')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }
        $role->revokePermissionTo($this->toRevoke);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::where('name', 'Logistics Responsible')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }
        $role->givePermissionTo($this->toRevoke);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
