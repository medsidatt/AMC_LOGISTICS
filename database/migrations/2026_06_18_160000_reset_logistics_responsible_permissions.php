<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reset the "Logistics Responsible" role to a focused, operational permission
 * set — exactly the sidebar areas the role should use by default:
 *   Tableau de bord, Suivi transport, Camions, Conducteurs, Maintenance,
 *   Inspections (+ créer), Programmation rotations, Suivi hebdomadaire,
 *   Planning flotte, Historique objectifs, Paramètres flotte.
 * Everything else (providers, transporters, entities, projects, invitations,
 * live-fleet, reports, the validation chain) is removed.
 */
return new class extends Migration
{
    private array $permissions = [
        'transport-tracking-list', 'transport-tracking-create', 'transport-tracking-edit',
        'truck-list', 'truck-create', 'truck-edit',
        'driver-list', 'driver-create', 'driver-edit',
        'maintenance-list', 'maintenance-create', 'maintenance-edit',
        'inspection-list', 'inspection-show', 'inspection-create', 'inspection-edit',
        'daily-dispatch-list', 'daily-dispatch-edit',
        'fleet-roster-plan',
        'objective-history-list',
        'fleet-settings-edit',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Logistics Responsible')->where('guard_name', 'web')->first();
        if ($role) {
            $existing = Permission::whereIn('name', $this->permissions)->pluck('name')->all();
            $role->syncPermissions($existing);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op: the previous (broad) permission set is not restored.
    }
};
