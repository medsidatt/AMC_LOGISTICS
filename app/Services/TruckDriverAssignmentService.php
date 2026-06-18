<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Truck;
use App\Models\TruckDriverAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Single write path for driver↔truck assignments. Enforces:
 *   - a driver has at most ONE active assignment (exclusivity),
 *   - a truck has at most one active titulaire and one active assistant,
 * and keeps drivers.current_truck_id in sync (the cache read by planning,
 * the dashboard and transport-tracking filters).
 */
class TruckDriverAssignmentService
{
    /**
     * Assign a driver to a truck in a given role. Frees the driver from any
     * other truck and frees whoever currently holds that role on the truck.
     */
    public function assign(Truck $truck, Driver $driver, string $role, ?int $userId = null): TruckDriverAssignment
    {
        $role = in_array($role, [TruckDriverAssignment::ROLE_TITULAIRE, TruckDriverAssignment::ROLE_ASSISTANT], true)
            ? $role
            : TruckDriverAssignment::ROLE_TITULAIRE;

        return DB::transaction(function () use ($truck, $driver, $role, $userId) {
            $now = now();

            // 1. Free the driver from any other active assignment.
            $this->endActiveForDriver($driver->id, $now);

            // 2. Free whoever holds this role on the truck.
            $displaced = TruckDriverAssignment::query()
                ->where('truck_id', $truck->id)
                ->where('role', $role)
                ->whereNull('ended_at')
                ->get();
            foreach ($displaced as $a) {
                $a->update(['ended_at' => $now]);
                Driver::whereKey($a->driver_id)->update(['current_truck_id' => null]);
            }

            // 3. Create the new active assignment + sync the cache.
            $assignment = TruckDriverAssignment::create([
                'truck_id' => $truck->id,
                'driver_id' => $driver->id,
                'role' => $role,
                'started_at' => $now,
                'created_by' => $userId,
            ]);

            $driver->forceFill(['current_truck_id' => $truck->id])->save();

            return $assignment;
        });
    }

    /** End a specific active assignment and clear the driver's cache. */
    public function release(TruckDriverAssignment $assignment, ?int $userId = null): void
    {
        DB::transaction(function () use ($assignment) {
            if ($assignment->ended_at === null) {
                $assignment->update(['ended_at' => now()]);
            }
            Driver::whereKey($assignment->driver_id)->update(['current_truck_id' => null]);
        });
    }

    /** End every active assignment for a driver (used when freeing a driver). */
    public function releaseDriver(Driver $driver): void
    {
        DB::transaction(function () use ($driver) {
            $this->endActiveForDriver($driver->id, now());
            $driver->forceFill(['current_truck_id' => null])->save();
        });
    }

    private function endActiveForDriver(int $driverId, $when): void
    {
        TruckDriverAssignment::query()
            ->where('driver_id', $driverId)
            ->whereNull('ended_at')
            ->update(['ended_at' => $when]);
    }
}
