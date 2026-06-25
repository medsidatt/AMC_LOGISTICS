<?php

namespace App\Services;

use App\Models\FleetObjective;
use App\Models\FleetObjectiveTruck;
use App\Models\Truck;
use App\Models\TruckRestWindow;
use Carbon\Carbon;

/**
 * Single write path for fleet objectives. Distributes a tonnage target top-down
 * across the trucks actually in service (active + available + not at repos) so
 * the fleet header always equals the sum of the per-truck plan. Reused by the
 * roster page (apply/save) and by truck availability changes (redistribution).
 */
class FleetObjectiveService
{
    public function __construct(private readonly FleetCapacityService $capacity) {}

    /**
     * Upsert the objective for a period and re-snapshot its per-truck targets.
     * Working set = active && available && not in a surplus rest window for the
     * period. When $restedIds is null it is derived from the rest windows.
     */
    public function upsert(Carbon $start, Carbon $end, float $targetTons, ?int $userId, ?string $notes, ?array $restedIds = null, string $periodType = FleetObjective::PERIOD_WEEK): FleetObjective
    {
        if ($restedIds === null) {
            $restedIds = TruckRestWindow::query()
                ->where('reason', TruckRestWindow::REASON_SURPLUS_CAPACITY)
                ->where('start_date', $start->toDateString())
                ->where('end_date', $end->toDateString())
                ->pluck('truck_id')
                ->all();
        }

        $restedIds = array_values(array_unique($restedIds));
        $restedSet = array_flip($restedIds);
        $defaultCap = max(0.01, $this->capacity->defaultCapacityTonnage());

        $activeTrucks = Truck::where('is_active', true)->get(['id', 'capacity_tonnage', 'is_available']);

        // A truck is excluded from the plan if it is at repos OR marked unavailable.
        $isExcluded = fn (Truck $t) => isset($restedSet[$t->id]) || ! $t->is_available;
        $workingTrucks = $activeTrucks->reject($isExcluded)->values();
        $excludedCount = $activeTrucks->count() - $workingTrucks->count();

        // Top-down: distribute the tonnage target across the WORKING trucks into
        // integer rotations. The fleet header is then the exact sum of the per-truck
        // plan, so tonnage and rotations always reconcile with the breakdown.
        $distribution = $this->capacity->distributeTargetRotations($targetTons, $workingTrucks);

        $plannedRotations = (int) array_sum(array_column($distribution, 'rotations'));

        // Persist the COMMITTED target the manager set — never the distributed
        // (capacity-capped) tonnage — so ambitious / under-resourced / stretch
        // targets survive intact. The per-truck distribution below is the plan
        // for that target, not a ceiling on it.
        $objective = FleetObjective::updateOrCreate(
            ['period_type' => $periodType, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            [
                'target_tons' => round($targetTons, 2),
                'target_rotations' => $plannedRotations,
                'working_trucks' => $workingTrucks->count(),
                'rested_trucks' => $excludedCount,
                'notes' => $notes,
                'created_by' => $userId,
            ],
        );

        // Snapshot per-truck targets (frozen). Excluded trucks get a 0-target row
        // so they still appear in the breakdown. Re-applying re-snapshots cleanly.
        $objective->truckTargets()->delete();

        $now = now();
        $rows = $activeTrucks->map(function (Truck $truck) use ($objective, $distribution, $defaultCap, $now) {
            $d = $distribution[$truck->id] ?? null;
            return [
                'fleet_objective_id' => $objective->id,
                'truck_id' => $truck->id,
                'target_rotations' => $d['rotations'] ?? 0,
                'target_tons' => $d['tons'] ?? 0.0,
                'capacity_tonnage' => $d['capacity'] ?? round((float) ($truck->capacity_tonnage ?: $defaultCap), 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        if ($rows) {
            FleetObjectiveTruck::insert($rows);
        }

        return $objective;
    }

    /**
     * Re-run the distribution for an existing objective using the current truck
     * availability + rest windows, keeping its tonnage target. Used when a truck
     * is made (un)available so live plans redistribute instantly.
     */
    public function resnapshot(FleetObjective $objective): FleetObjective
    {
        return $this->upsert(
            $objective->start_date->copy(),
            $objective->end_date->copy(),
            (float) $objective->target_tons,
            $objective->created_by,
            $objective->notes,
            null,
            $objective->period_type ?? FleetObjective::PERIOD_WEEK,
        );
    }

    /**
     * Redistribute every current/future objective (end_date >= today). Past
     * objectives stay frozen as a historical record. Returns the count touched.
     */
    public function redistributeOpenObjectives(): int
    {
        $objectives = FleetObjective::where('end_date', '>=', Carbon::now()->toDateString())->get();
        foreach ($objectives as $objective) {
            $this->resnapshot($objective);
        }

        return $objectives->count();
    }
}
