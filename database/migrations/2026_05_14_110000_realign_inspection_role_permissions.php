<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inspection-create', 'inspection-edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $logistics = Role::where('name', 'Logistics Responsible')->where('guard_name', 'web')->first();
        if ($logistics) {
            $logistics->givePermissionTo(['inspection-create', 'inspection-edit']);
        }

        $hse = Role::where('name', 'HSE Agent')->where('guard_name', 'web')->first();
        if ($hse) {
            $hse->revokePermissionTo(['inspection-create', 'inspection-edit']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $logistics = Role::where('name', 'Logistics Responsible')->where('guard_name', 'web')->first();
        if ($logistics) {
            $logistics->revokePermissionTo(['inspection-create', 'inspection-edit']);
        }

        $hse = Role::where('name', 'HSE Agent')->where('guard_name', 'web')->first();
        if ($hse) {
            $hse->givePermissionTo(['inspection-create', 'inspection-edit']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
