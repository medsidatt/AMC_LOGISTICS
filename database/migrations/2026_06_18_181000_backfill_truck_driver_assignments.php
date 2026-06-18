<?php

use App\Models\Driver;
use App\Models\Truck;
use App\Models\TruckDriverAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed active assignments from the existing drivers.current_truck_id, respecting
 * the one-titulaire + one-assistant rule. Extra drivers on a truck (and drivers
 * pointing at an inactive truck) are released to the available pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Drivers pointing at an inactive/missing truck → release.
        $activeTruckIds = Truck::where('is_active', true)->pluck('id')->all();
        Driver::whereNotNull('current_truck_id')
            ->whereNotIn('current_truck_id', $activeTruckIds)
            ->update(['current_truck_id' => null]);

        foreach (Truck::where('is_active', true)->pluck('id') as $truckId) {
            $drivers = Driver::where('current_truck_id', $truckId)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->get(['id']);

            if ($drivers->isEmpty()) {
                continue;
            }

            $roles = [TruckDriverAssignment::ROLE_TITULAIRE, TruckDriverAssignment::ROLE_ASSISTANT];
            foreach ($drivers->values() as $i => $driver) {
                if ($i < 2) {
                    TruckDriverAssignment::create([
                        'truck_id' => $truckId,
                        'driver_id' => $driver->id,
                        'role' => $roles[$i],
                        'started_at' => $now,
                    ]);
                } else {
                    // Extra drivers go back to the available pool.
                    DB::table('drivers')->where('id', $driver->id)->update(['current_truck_id' => null]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('truck_driver_assignments')->truncate();
    }
};
