<?php

namespace App\Services;

use App\Models\ClientDemandPlan;
use App\Models\Truck;
use App\Models\TruckAssignment;
use App\Models\TruckRestWindow;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FleetOptimizerService
{
    public function __construct(
        private readonly FleetCapacityService $capacity,
        private readonly RestWindowPlannerService $restPlanner,
    ) {}

    public function planWeek(Carbon $weekStart, ?int $userId = null, bool $dryRun = false): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $period = CarbonPeriod::create($weekStart, $weekEnd);

        $demands = ClientDemandPlan::query()
            ->where('week_start_date', $weekStart->toDateString())
            ->orderBy('priority')
            ->orderByDesc('required_tons')
            ->get();

        $trucks = Truck::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->orderBy('matricule')
            ->get();

        $restWindows = TruckRestWindow::query()
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())
            ->get()
            ->groupBy('truck_id');

        $truckCapacity = [];
        foreach ($trucks as $truck) {
            $info = $this->capacity->truckDailyCapacity($truck);
            $effectiveDaily = $info['empirical_daily_capacity_t'] > 0
                ? $info['empirical_daily_capacity_t']
                : $info['theoretical_daily_capacity_t'];
            $truckCapacity[$truck->id] = max(0.0, $effectiveDaily);
        }

        $slotMatrix = [];
        foreach ($trucks as $truck) {
            foreach ($period as $day) {
                $slotMatrix[$truck->id][$day->toDateString()] = [
                    'available' => $this->isFreeSlot($truck->id, $day, $restWindows),
                    'capacity_t' => $truckCapacity[$truck->id] ?? 0.0,
                ];
            }
        }

        $plannedAssignments = [];
        $demandsResult = [];

        foreach ($demands as $demand) {
            $needTons = (float) $demand->required_tons;
            $needTrucks = $demand->required_trucks;
            $allocatedTons = 0.0;
            $allocatedTruckIds = [];

            $ranking = $this->rankTrucksForDemand($trucks, $demand, $truckCapacity);

            foreach ($ranking as $truck) {
                if ($needTrucks !== null && count($allocatedTruckIds) >= $needTrucks) {
                    break;
                }
                if ($needTrucks === null && $allocatedTons >= $needTons) {
                    break;
                }

                foreach ($period as $day) {
                    if ($needTrucks === null && $allocatedTons >= $needTons) {
                        break;
                    }
                    $dayStr = $day->toDateString();
                    if (! $slotMatrix[$truck->id][$dayStr]['available']) {
                        continue;
                    }

                    $cap = $slotMatrix[$truck->id][$dayStr]['capacity_t'];
                    if ($cap <= 0.0) {
                        continue;
                    }

                    $rotations = $this->estimateRotations($truck, $cap);

                    $plannedAssignments[] = [
                        'truck_id' => $truck->id,
                        'driver_id' => null,
                        'project_id' => $demand->project_id,
                        'provider_id' => $demand->provider_id,
                        'client_demand_plan_id' => $demand->id,
                        'planned_date' => $dayStr,
                        'planned_rotations' => $rotations,
                        'planned_tonnage' => round($cap, 2),
                        'status' => TruckAssignment::STATUS_PLANNED,
                        'notes' => null,
                        'created_by' => $userId,
                    ];

                    $slotMatrix[$truck->id][$dayStr]['available'] = false;
                    $allocatedTons += $cap;
                    $allocatedTruckIds[$truck->id] = true;
                }
            }

            $demandsResult[] = [
                'demand_id' => $demand->id,
                'project_id' => $demand->project_id,
                'priority' => $demand->priority,
                'required_tons' => round($needTons, 2),
                'required_trucks' => $needTrucks,
                'allocated_tons' => round($allocatedTons, 2),
                'allocated_trucks' => count($allocatedTruckIds),
                'coverage_rate' => $needTons > 0 ? round(min(1.0, $allocatedTons / $needTons), 4) : 1.0,
                'shortfall_t' => round(max(0.0, $needTons - $allocatedTons), 2),
            ];
        }

        $surplus = $this->collectSurplusSlots($slotMatrix, $period);

        $restProposed = $this->restPlanner->proposeForSurplus($surplus, $userId);

        $summary = [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'demands' => $demandsResult,
            'assignments_count' => count($plannedAssignments),
            'rest_proposed_count' => count($restProposed),
            'total_planned_tonnage' => round(array_sum(array_column($plannedAssignments, 'planned_tonnage')), 2),
            'total_demanded_tonnage' => round(array_sum(array_column($demandsResult, 'required_tons')), 2),
        ];

        if ($dryRun) {
            return [
                'summary' => $summary,
                'assignments' => $plannedAssignments,
                'rest_windows' => $restProposed,
            ];
        }

        DB::transaction(function () use ($weekStart, $weekEnd, $plannedAssignments, $restProposed) {
            TruckAssignment::query()
                ->whereBetween('planned_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->where('status', TruckAssignment::STATUS_PLANNED)
                ->delete();

            TruckRestWindow::query()
                ->where('reason', TruckRestWindow::REASON_SURPLUS_CAPACITY)
                ->where('start_date', '>=', $weekStart->toDateString())
                ->where('end_date', '<=', $weekEnd->toDateString())
                ->delete();

            foreach ($plannedAssignments as $row) {
                TruckAssignment::create($row);
            }
            foreach ($restProposed as $row) {
                TruckRestWindow::create($row);
            }
        });

        return [
            'summary' => $summary,
            'assignments' => $plannedAssignments,
            'rest_windows' => $restProposed,
        ];
    }

    private function isFreeSlot(int $truckId, Carbon $day, Collection $restByTruck): bool
    {
        $dayStr = $day->toDateString();
        $restList = $restByTruck->get($truckId, collect());
        foreach ($restList as $rest) {
            if ($dayStr >= $rest->start_date->toDateString() && $dayStr <= $rest->end_date->toDateString()) {
                return false;
            }
        }
        return true;
    }

    private function rankTrucksForDemand(Collection $trucks, ClientDemandPlan $demand, array $capacityMap): Collection
    {
        return $trucks->sortByDesc(fn ($t) => $capacityMap[$t->id] ?? 0.0)->values();
    }

    private function estimateRotations(Truck $truck, float $dailyCapacity): int
    {
        // Capacity is a single fleet-wide setting, identical for every truck.
        $capacity = max(0.01, (float) (\App\Models\FleetSetting::current()->default_capacity_tonnage ?: FleetCapacityService::DEFAULT_CAPACITY_TONNAGE));
        return max(1, (int) round($dailyCapacity / $capacity));
    }

    private function collectSurplusSlots(array $slotMatrix, CarbonPeriod $period): array
    {
        $surplus = [];
        foreach ($slotMatrix as $truckId => $days) {
            foreach ($days as $dayStr => $slot) {
                if ($slot['available'] && $slot['capacity_t'] > 0) {
                    $surplus[$truckId][] = $dayStr;
                }
            }
        }
        return $surplus;
    }
}
