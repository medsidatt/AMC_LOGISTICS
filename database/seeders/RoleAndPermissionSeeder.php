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
        $driver = Role::firstOrCreate(['name' => 'Driver']);

        // ── Super Admin: all permissions ──
        $superAdmin->syncPermissions(Permission::all());

        // ── Admin: all except role-delete, role-create ──
        $adminPerms = Permission::whereNotIn('name', ['role-delete', 'role-create'])->pluck('id')->all();
        $admin->syncPermissions($adminPerms);

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
