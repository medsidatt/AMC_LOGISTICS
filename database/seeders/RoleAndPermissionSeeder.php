<?php

namespace Database\Seeders;

use App\Models\Auth\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ──
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $driver = Role::firstOrCreate(['name' => 'Driver']);

        // ── Super Admin: all permissions ──
        $superAdmin->syncPermissions(Permission::all());

        // ── Admin: all except role-delete, role-create ──
        $adminPerms = Permission::whereNotIn('name', ['role-delete', 'role-create'])->pluck('id')->all();
        $admin->syncPermissions($adminPerms);

        // ── Manager: logistics data only (no user/role/invitation management) ──
        $managerPermNames = [
            'truck-list', 'truck-create', 'truck-edit', 'truck-delete',
            'driver-list', 'driver-create', 'driver-edit', 'driver-delete',
            'transport-tracking-list', 'transport-tracking-create', 'transport-tracking-edit', 'transport-tracking-delete',
            'provider-list', 'provider-create', 'provider-edit', 'provider-delete',
            'transporter-list', 'transporter-create', 'transporter-edit', 'transporter-delete',
            'maintenance-list', 'maintenance-create',
            'logistics-dashboard',
            'entity-list', 'entity-show', 'entity-create', 'entity-edit', 'entity-delete',
            'project-list', 'project-show', 'project-create', 'project-edit', 'project-delete',
            'project-assign-user', 'project-remove-user',
        ];
        $manager->syncPermissions(Permission::whereIn('name', $managerPermNames)->pluck('id')->all());

        // ── Driver: no global permissions ──
        $driver->syncPermissions([]);

        // ── Default super admin user ──
        $user = User::firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'super.admin@amcct.com')],
            [
                'name' => 'Super Admin',
                'password' => bcrypt(env('SUPER_ADMIN_PASSWORD', 'change-me-immediately')),
            ]
        );
        $user->syncRoles([$superAdmin->id]);
    }
}
