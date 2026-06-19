<?php

use App\Models\FleetSetting;
use App\Models\Truck;
use Illuminate\Database\Migrations\Migration;

/**
 * Capacity becomes a single fleet-wide setting. Align every truck's stored
 * capacity_tonnage with the global "Capacité par défaut" so all the existing
 * per-truck reads return the one configured value.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cap = (float) (FleetSetting::current()->default_capacity_tonnage ?? 0);
        if ($cap > 0) {
            Truck::query()->update(['capacity_tonnage' => $cap]);
        }
    }

    public function down(): void
    {
        // No-op: previous per-truck capacities are not restorable.
    }
};
