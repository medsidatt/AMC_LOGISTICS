<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Role::where('name', 'Driver')->first();
        if (!$driver) {
            return;
        }

        // Remove global list permissions — drivers should only see their own data
        // via dedicated /drivers/my-trips, /drivers/my-truck routes
        $driver->revokePermissionTo([
            'truck-list', 'driver-list', 'transport-tracking-list',
            'provider-list', 'transporter-list',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $driver = Role::where('name', 'Driver')->first();
        if (!$driver) {
            return;
        }

        $driver->givePermissionTo([
            'truck-list', 'driver-list', 'transport-tracking-list',
            'provider-list', 'transporter-list',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
