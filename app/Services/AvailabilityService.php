<?php

namespace App\Services;

use App\Models\Truck;
use App\Models\TruckAvailabilityWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Availability engine. Truck availability is driven by REAL downtime windows; the
 * per-truck availability/maintenance factors are the fallback used only when no
 * windows exist for the period.
 *
 *   Available Capacity = Operational Capacity − Lost Capacity
 *   Operational Capacity = planned_rotations × rated_capacity
 *   Lost Capacity (windows)  = planned_rotations × (downtime_op_days ÷ op_days) × rated_capacity
 *   Lost Capacity (fallback) = Operational Capacity × (1 − availability_factor × maintenance_factor)
 */
class AvailabilityService
{
    public function __construct(
        private readonly OperationsCalendarService $calendar,
        private readonly FleetCapacityService $capacity,
    ) {}

    /**
     * @return array{
     *   source:string, operational_days:int, lost_days:int,
     *   available_rotations:float, lost_rotations:float,
     *   operational_capacity:float, available_capacity:float, lost_capacity:float,
     *   availability_pct:?int, downtime_impact:array<string,float>
     * }
     */
    public function forTruck(Truck $truck, Carbon $start, Carbon $end, float $plannedRotations): array
    {
        $opDays = $this->calendar->operationalDays($start, $end);
        $ratedCap = ((float) $truck->capacity_tonnage > 0)
            ? (float) $truck->capacity_tonnage
            : $this->capacity->defaultCapacityTonnage();
        $opCapacity = round($plannedRotations * $ratedCap, 2);

        $windows = TruckAvailabilityWindow::query()
            ->where('truck_id', $truck->id)
            ->overlapping($start, $end)
            ->get();

        if ($windows->isEmpty()) {
            // Fallback: static planning factors (availability × maintenance).
            $factor = $this->clamp((float) ($truck->availability_factor ?? 0.95))
                * $this->clamp((float) ($truck->maintenance_factor ?? 0.98));
            $availableRotations = $plannedRotations * $factor;
            $lostRotations = $plannedRotations - $availableRotations;
            $lostDays = 0;
            $source = 'factors';
            $downtimeImpact = [];
        } else {
            // Real windows: count operational downtime days, attributed by category.
            $downtimeByCategory = $this->operationalDowntimeDays($windows, $start, $end);
            $lostDays = array_sum($downtimeByCategory);
            $availDays = max(0, $opDays - $lostDays);

            $availableRotations = $opDays > 0 ? $plannedRotations * ($availDays / $opDays) : 0.0;
            $lostRotations = $opDays > 0 ? $plannedRotations * ($lostDays / $opDays) : 0.0;
            $source = 'windows';

            $downtimeImpact = [];
            foreach ($downtimeByCategory as $type => $days) {
                $catLostRot = $opDays > 0 ? $plannedRotations * ($days / $opDays) : 0.0;
                $downtimeImpact[$type] = round($catLostRot * $ratedCap, 2);
            }
        }

        $availableCapacity = round($availableRotations * $ratedCap, 2);
        $lostCapacity = round($lostRotations * $ratedCap, 2);

        return [
            'source' => $source,
            'operational_days' => $opDays,
            'lost_days' => $lostDays,
            'available_rotations' => round($availableRotations, 2),
            'lost_rotations' => round($lostRotations, 2),
            'operational_capacity' => $opCapacity,
            'available_capacity' => $availableCapacity,
            'lost_capacity' => $lostCapacity,
            'availability_pct' => $opCapacity > 0 ? (int) round($availableCapacity / $opCapacity * 100) : null,
            'downtime_impact' => $downtimeImpact,
        ];
    }

    /**
     * Fleet roll-up. $plannedRotations is keyed by truck id.
     *
     * @param  array<int,float>  $plannedRotations
     */
    public function forFleet(Collection $trucks, Carbon $start, Carbon $end, array $plannedRotations): array
    {
        $opCap = 0.0; $availCap = 0.0; $lostCap = 0.0;
        $impact = [];
        $perTruck = [];

        foreach ($trucks as $truck) {
            $r = $this->forTruck($truck, $start, $end, (float) ($plannedRotations[$truck->id] ?? 0));
            $opCap += $r['operational_capacity'];
            $availCap += $r['available_capacity'];
            $lostCap += $r['lost_capacity'];
            foreach ($r['downtime_impact'] as $type => $tons) {
                $impact[$type] = round(($impact[$type] ?? 0) + $tons, 2);
            }
            $perTruck[$truck->id] = $r;
        }

        return [
            'operational_capacity' => round($opCap, 2),
            'available_capacity' => round($availCap, 2),
            'lost_capacity' => round($lostCap, 2),
            'availability_pct' => $opCap > 0 ? (int) round($availCap / $opCap * 100) : null,
            'downtime_impact' => $impact,
            'per_truck' => $perTruck,
        ];
    }

    /**
     * Operational downtime days within [start, end], attributed by window type.
     * A day is counted once (first overlapping window wins for attribution).
     *
     * @return array<string,int>  type => operational days lost
     */
    private function operationalDowntimeDays(Collection $windows, Carbon $start, Carbon $end): array
    {
        $seen = [];          // date => true (so a day is counted once)
        $byCategory = [];    // type => count

        $periodStart = $start->copy()->startOfDay();
        $periodEnd = $end->copy()->startOfDay();

        foreach ($windows as $window) {
            $ws = $window->start_at->copy()->startOfDay();
            $we = $window->end_at->copy()->startOfDay();
            if ($ws->lt($periodStart)) $ws = $periodStart->copy();
            if ($we->gt($periodEnd)) $we = $periodEnd->copy();

            for ($d = $ws->copy(); $d->lte($we); $d->addDay()) {
                $key = $d->toDateString();
                if (isset($seen[$key]) || ! $this->calendar->isOperational($d)) {
                    continue;
                }
                $seen[$key] = true;
                $byCategory[$window->type] = ($byCategory[$window->type] ?? 0) + 1;
            }
        }

        return $byCategory;
    }

    private function clamp(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }
}
