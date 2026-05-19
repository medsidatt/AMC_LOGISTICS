<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Grant the HSE Agent role read-only access to all data relevant to
 * ISO 9001 (quality) and ISO 45001 (occupational health & safety):
 *
 *  - Fleet (trucks, drivers, transporters, providers, places)
 *  - Operations (transport_trackings, maintenances, weekly checklists)
 *  - Safety (theft incidents, fuel anomalies — gated by logistics-dashboard)
 *  - Configuration (projects, entities)
 *
 * No write permissions are granted. HSE remains observer-only.
 */
return new class extends Migration
{
    private array $hseReadPermissions = [
        'truck-list',
        'driver-list',
        'transporter-list',
        'provider-list',
        'transport-tracking-list',
        'maintenance-list',
        'inspection-list',
        'inspection-show',
        'project-list',
        'project-show',
        'entity-list',
        'entity-show',
        'logistics-dashboard',
    ];

    public function up(): void
    {
        foreach ($this->hseReadPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $hse = Role::where('name', 'HSE Agent')->where('guard_name', 'web')->first();
        if (! $hse) {
            return;
        }

        $existing = $hse->permissions()->pluck('name')->all();
        $merged = array_values(array_unique(array_merge($existing, $this->hseReadPermissions)));
        $hse->syncPermissions($merged);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $hse = Role::where('name', 'HSE Agent')->where('guard_name', 'web')->first();
        if (! $hse) {
            return;
        }

        // Keep the original baseline: list/show inspection + truck/driver only.
        $hse->syncPermissions([
            'truck-list',
            'driver-list',
            'inspection-list',
            'inspection-show',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
