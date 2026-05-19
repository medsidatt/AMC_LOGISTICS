<?php

namespace App\Services;

use App\Models\ClientDemandPlan;
use App\Models\FleetSetting;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Models\TruckAssignment;
use App\Models\TruckRestWindow;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class FleetCapacityService
{
    public const DEFAULT_WORK_HOURS = 12.0;
    public const DEFAULT_BUFFER_RATIO = 0.15;
    public const DEFAULT_CAPACITY_TONNAGE = 45.0;     // Fallback if no FleetSetting
    public const TARGET_ROTATIONS_PER_WEEK = 3;       // Fallback if no FleetSetting
    public const HISTORY_DAYS = 60;

    /**
     * Global default rotation target from FleetSetting (fallback to constant).
     */
    public function defaultTargetRotationsPerWeek(): int
    {
        $value = FleetSetting::current()->target_rotations_per_week ?? null;
        return (int) ($value ?: self::TARGET_ROTATIONS_PER_WEEK);
    }

    /**
     * Global default capacity (tonnes/rotation) from FleetSetting (fallback to constant).
     */
    public function defaultCapacityTonnage(): float
    {
        $value = FleetSetting::current()->default_capacity_tonnage ?? null;
        return (float) ($value ?: self::DEFAULT_CAPACITY_TONNAGE);
    }

    /**
     * Resolve the weekly target for a single truck. Cascade:
     *   1. truck.target_rotations_per_week (per-truck override)
     *   2. FleetSetting global default
     *   3. Hard-coded constant fallback
     */
    public function targetRotationsForTruck(Truck $truck): int
    {
        if ($truck->target_rotations_per_week !== null) {
            return (int) $truck->target_rotations_per_week;
        }
        return $this->defaultTargetRotationsPerWeek();
    }

    /**
     * Resolve the aggregate weekly target for the whole fleet for a given week.
     * Cascade:
     *   - If client_demand_plans exist for the week: their sum overrides everything
     *   - Otherwise: per-truck target × per-truck capacity (with global default fallback)
     */
    public function resolveWeeklyTarget(?Carbon $weekStart = null): array
    {
        $weekStart = ($weekStart ?? Carbon::now())->copy()->startOfWeek(Carbon::MONDAY);

        // Always walk active trucks first so the relation (tons = rotations × capacity)
        // is computed from real per-truck values, not just the global defaults.
        $globalRotations = $this->defaultTargetRotationsPerWeek();
        $globalCapacity = $this->defaultCapacityTonnage();
        $activeTrucks = Truck::where('is_active', true)->get();

        $totalTons = 0.0;
        $totalRotations = 0;
        $customTrucks = 0;
        $sumCapacity = 0.0;
        $countCapacity = 0;

        foreach ($activeTrucks as $truck) {
            $rot = $truck->target_rotations_per_week !== null ? (int) $truck->target_rotations_per_week : $globalRotations;
            $cap = ((float) ($truck->capacity_tonnage ?: 0)) > 0 ? (float) $truck->capacity_tonnage : $globalCapacity;
            $totalRotations += $rot;
            $totalTons += $rot * $cap;
            $sumCapacity += $cap;
            $countCapacity++;
            if ($truck->target_rotations_per_week !== null) {
                $customTrucks++;
            }
        }

        $avgCapacity = $countCapacity > 0 ? $sumCapacity / $countCapacity : $globalCapacity;

        // Niveau 2 — client demand plans override the tons target. Rotations are
        // then derived from the average per-truck capacity so tons = rot × cap stays true.
        $clientTotal = (float) ClientDemandPlan::query()
            ->where('week_start_date', $weekStart->toDateString())
            ->sum('required_tons');

        if ($clientTotal > 0) {
            $derivedRotations = $avgCapacity > 0 ? (int) ceil($clientTotal / $avgCapacity) : 0;
            return [
                'source' => 'client_demand',
                'target_tons' => round($clientTotal, 2),
                'target_rotations' => $derivedRotations,
                'avg_capacity_t' => round($avgCapacity, 2),
                'week_start' => $weekStart->toDateString(),
                'custom_truck_count' => $customTrucks,
                'global_rotations' => $globalRotations,
                'global_capacity_t' => $globalCapacity,
            ];
        }

        return [
            'source' => $customTrucks > 0 ? 'mixed' : 'default',
            'target_tons' => round($totalTons, 2),
            'target_rotations' => $totalRotations,
            'avg_capacity_t' => round($avgCapacity, 2),
            'week_start' => $weekStart->toDateString(),
            'custom_truck_count' => $customTrucks,
            'global_rotations' => $globalRotations,
            'global_capacity_t' => $globalCapacity,
        ];
    }

    public function truckDailyCapacity(Truck $truck): array
    {
        $from = Carbon::now()->subDays(self::HISTORY_DAYS)->startOfDay();
        $to = Carbon::now()->endOfDay();
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $rotations = TransportTracking::query()
            ->where('truck_id', $truck->id)
            ->whereBetween('client_date', [$from, $to])
            ->get(['client_date', 'client_net_weight']);

        $activeDays = $rotations->pluck('client_date')->map(
            fn ($d) => Carbon::parse($d)->toDateString()
        )->unique()->count();

        $rotationsCount = $rotations->count();
        $tonnage = (float) $rotations->sum('client_net_weight');

        $avgRotationsPerDay = $activeDays > 0
            ? round($rotationsCount / $activeDays, 2)
            : 0.0;

        $avgWeightPerRotation = $rotationsCount > 0
            ? round($tonnage / $rotationsCount, 2)
            : 0.0;

        // Empirical rotations/week from the last 60 days (60 / 7 ≈ 8.57 weeks)
        $weeksInWindow = max(1.0, self::HISTORY_DAYS / 7);
        $avgRotationsPerWeek = round($rotationsCount / $weeksInWindow, 2);

        $capacityTonnage = max(0.01, (float) ($truck->capacity_tonnage ?: $this->defaultCapacityTonnage()));
        $targetRotationsForTruck = $this->targetRotationsForTruck($truck);

        $targetWeeklyCapacity = round($targetRotationsForTruck * $capacityTonnage, 2);
        $empiricalWeeklyCapacity = round($avgRotationsPerWeek * ($avgWeightPerRotation > 0 ? $avgWeightPerRotation : $capacityTonnage), 2);

        // This week's actuals
        $thisWeekRotations = (int) TransportTracking::query()
            ->where('truck_id', $truck->id)
            ->where('client_date', '>=', $weekStart->toDateString())
            ->count();

        $thisWeekTonnage = (float) TransportTracking::query()
            ->where('truck_id', $truck->id)
            ->where('client_date', '>=', $weekStart->toDateString())
            ->sum('client_net_weight');

        return [
            'truck_id' => $truck->id,
            'matricule' => $truck->matricule,
            'capacity_tonnage' => round($capacityTonnage, 2),
            'history_days' => self::HISTORY_DAYS,
            'active_days' => $activeDays,
            'avg_rotations_per_day' => $avgRotationsPerDay,
            'avg_rotations_per_week' => $avgRotationsPerWeek,
            'avg_weight_per_rotation' => $avgWeightPerRotation,
            'empirical_daily_capacity_t' => round($avgRotationsPerDay * ($avgWeightPerRotation > 0 ? $avgWeightPerRotation : $capacityTonnage), 2),
            'theoretical_daily_capacity_t' => round($this->theoreticalRotationsPerDay() * $capacityTonnage, 2),
            'target_weekly_capacity_t' => $targetWeeklyCapacity,
            'target_rotations_per_week' => $targetRotationsForTruck,
            'target_is_custom' => $truck->target_rotations_per_week !== null,
            'empirical_weekly_capacity_t' => $empiricalWeeklyCapacity,
            'this_week_rotations' => $thisWeekRotations,
            'this_week_tonnage_t' => round($thisWeekTonnage, 2),
        ];
    }

    public function fleetWeeklyCapacity(Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $period = CarbonPeriod::create($weekStart, $weekEnd);

        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get();

        $rest = TruckRestWindow::query()
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())
            ->get()
            ->groupBy('truck_id');

        $assignments = TruckAssignment::query()
            ->whereBetween('planned_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereIn('status', [TruckAssignment::STATUS_PLANNED, TruckAssignment::STATUS_ACTIVE])
            ->get()
            ->groupBy('truck_id');

        $trucksOut = [];
        $totalCapacity = 0.0;
        $totalAllocated = 0.0;

        foreach ($trucks as $truck) {
            $daily = $this->truckDailyCapacity($truck);
            $effectiveDaily = $daily['empirical_daily_capacity_t'] > 0
                ? $daily['empirical_daily_capacity_t']
                : $daily['theoretical_daily_capacity_t'];

            $daysAvailable = 0;
            $daysRest = 0;
            $daysAssigned = 0;
            $allocatedForTruck = 0.0;

            $truckRest = $rest->get($truck->id, collect());
            $truckAssign = $assignments->get($truck->id, collect());

            foreach ($period as $day) {
                $dayStr = $day->toDateString();
                $isRest = $truckRest->contains(fn ($r) => $dayStr >= $r->start_date->toDateString() && $dayStr <= $r->end_date->toDateString());
                $dayAssign = $truckAssign->firstWhere(fn ($a) => $a->planned_date->toDateString() === $dayStr);

                if ($isRest) {
                    $daysRest++;
                    continue;
                }

                $daysAvailable++;

                if ($dayAssign) {
                    $daysAssigned++;
                    $allocatedForTruck += (float) $dayAssign->planned_tonnage;
                }
            }

            $weeklyCapacity = round($effectiveDaily * $daysAvailable, 2);
            $totalCapacity += $weeklyCapacity;
            $totalAllocated += $allocatedForTruck;

            $trucksOut[] = [
                'truck_id' => $truck->id,
                'matricule' => $truck->matricule,
                'is_available' => (bool) $truck->is_available,
                'effective_daily_capacity_t' => $effectiveDaily,
                'days_available' => $daysAvailable,
                'days_rest' => $daysRest,
                'days_assigned' => $daysAssigned,
                'weekly_capacity_t' => $weeklyCapacity,
                'allocated_t' => round($allocatedForTruck, 2),
                'utilization_rate' => $daysAvailable > 0
                    ? round($daysAssigned / max(1, $daysAvailable + $daysRest), 4)
                    : 0.0,
                'daily_breakdown' => $daily,
            ];
        }

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'active_trucks_count' => $trucks->count(),
            'total_weekly_capacity_t' => round($totalCapacity, 2),
            'total_allocated_t' => round($totalAllocated, 2),
            'capacity_margin_t' => round($totalCapacity - $totalAllocated, 2),
            'trucks' => $trucksOut,
        ];
    }

    public function isTruckAvailableOnDate(Truck $truck, Carbon $date): bool
    {
        if (! $truck->is_active) {
            return false;
        }

        $dayStr = $date->toDateString();

        $hasRest = TruckRestWindow::query()
            ->where('truck_id', $truck->id)
            ->where('start_date', '<=', $dayStr)
            ->where('end_date', '>=', $dayStr)
            ->exists();

        if ($hasRest) {
            return false;
        }

        $hasAssignment = TruckAssignment::query()
            ->where('truck_id', $truck->id)
            ->whereDate('planned_date', $dayStr)
            ->whereIn('status', [TruckAssignment::STATUS_PLANNED, TruckAssignment::STATUS_ACTIVE])
            ->exists();

        return ! $hasAssignment;
    }

    private function theoreticalRotationsPerDay(): float
    {
        $workHours = self::DEFAULT_WORK_HOURS;
        $buffer = self::DEFAULT_BUFFER_RATIO;
        $cycleHours = 4.0;

        return round(($workHours * (1 - $buffer)) / $cycleHours, 2);
    }
}
