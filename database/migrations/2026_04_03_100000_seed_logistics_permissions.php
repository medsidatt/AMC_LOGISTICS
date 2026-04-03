<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'truck-list', 'truck-create', 'truck-edit', 'truck-delete',
        'driver-list', 'driver-create', 'driver-edit', 'driver-delete',
        'transport-tracking-list', 'transport-tracking-create', 'transport-tracking-edit', 'transport-tracking-delete',
        'provider-list', 'provider-create', 'provider-edit', 'provider-delete',
        'transporter-list', 'transporter-create', 'transporter-edit', 'transporter-delete',
        'maintenance-list', 'maintenance-create',
        'logistics-dashboard',
    ];

    public function up(): void
    {
        // Create permissions if they don't exist
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign all new permissions to Super Admin
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($this->permissions);
        }

        // Assign all new permissions to Admin (create if needed)
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($this->permissions);

        // Assign view-only permissions to Driver role (create if needed)
        $driver = Role::firstOrCreate(['name' => 'Driver', 'guard_name' => 'web']);
        $driver->givePermissionTo([
            'truck-list', 'driver-list', 'transport-tracking-list',
            'provider-list', 'transporter-list',
        ]);

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
