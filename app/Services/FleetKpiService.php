<?php

namespace App\Services;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\Driver;
use App\Models\DriverDisciplineRecord;
use App\Models\FleetSetting;
use App\Models\FuelTracking;
use App\Models\TransportTracking;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FleetKpiService
{
    public function compute(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $settings = FleetSetting::current();

        $trucks = Truck::whereNull('deleted_at')->get();
        $trucksTotal = $trucks->count();
        $trucksAvailable = $trucks->filter(fn (Truck $t) => $t->isAvailable())->count();

        $rotationsPerTruck = TransportTracking::query()
            ->select('truck_id', DB::raw('COUNT(*) as rotations'), DB::raw('SUM(client_net_weight) as tonnage'))
            ->whereBetween('client_date', [$from, $to])
            ->whereNotNull('truck_id')
            ->groupBy('truck_id')
            ->get()
            ->keyBy('truck_id');

        $activeTruckIds = $rotationsPerTruck->keys()->all();
        $trucksActive = count($activeTruckIds);

        $totalRotations = (int) $rotationsPerTruck->sum('rotations');
        $totalTonnageDelivered = (float) $rotationsPerTruck->sum('tonnage');

        $fuelLitres = (float) FuelTracking::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('litres');

        $availableCapacities = $trucks->filter(fn (Truck $t) => $t->isAvailable())
            ->pluck('capacity_tonnage')
            ->map(fn ($c) => (float) ($c ?? 0));
        $avgCapacity = $availableCapacities->count() > 0
            ? max(0.01, (float) $availableCapacities->avg())
            : 25.0;

        $availabilityRate = $trucksTotal > 0 ? $trucksAvailable / $trucksTotal : 0.0;
        $saturationRate = $trucksAvailable > 0 ? $trucksActive / $trucksAvailable : 0.0;

        $periodDays = max(1, $from->diffInDays($to) + 1);
        $targetMonthly = (float) $settings->monthly_target_tonnage;
        $plannedTonnage = $targetMonthly > 0 ? ($targetMonthly / 30.0) * $periodDays : 0.0;
        $productionTarget = $plannedTonnage > 0 ? $totalTonnageDelivered / $plannedTonnage : 0.0;

        $theoreticalCapacity = $avgCapacity * $totalRotations;
        $loadRate = $theoreticalCapacity > 0 ? $totalTonnageDelivered / $theoreticalCapacity : 0.0;

        $fuelYieldLPerT = $totalTonnageDelivered > 0 ? $fuelLitres / $totalTonnageDelivered : 0.0;

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $periodDays,
            ],
            'kpis' => [
                'availability' => [
                    'rate' => round($availabilityRate, 4),
                    'available' => $trucksAvailable,
                    'total' => $trucksTotal,
                ],
                'saturation' => [
                    'rate' => round($saturationRate, 4),
                    'active' => $trucksActive,
                    'available' => $trucksAvailable,
                ],
                'production_target' => [
                    'rate' => round($productionTarget, 4),
                    'delivered' => round($totalTonnageDelivered, 2),
                    'planned' => round($plannedTonnage, 2),
                    'monthly_target' => round($targetMonthly, 2),
                ],
                'load_rate' => [
                    'rate' => round($loadRate, 4),
                    'delivered' => round($totalTonnageDelivered, 2),
                    'theoretical' => round($theoreticalCapacity, 2),
                    'avg_capacity' => round($avgCapacity, 2),
                ],
                'rotations' => [
                    'total' => $totalRotations,
                ],
                'fuel_yield' => [
                    'litres_per_tonne' => round($fuelYieldLPerT, 3),
                    'litres' => round($fuelLitres, 2),
                    'tonnage' => round($totalTonnageDelivered, 2),
                ],
            ],
            'topTrucks' => $this->topTrucks($from, $to, $trucks, $rotationsPerTruck),
            'topDrivers' => $this->topDrivers($from, $to, $settings),
        ];
    }

    private function topTrucks(Carbon $from, Carbon $to, Collection $trucks, Collection $rotationsPerTruck): array
    {
        $fuelPerTruck = FuelTracking::query()
            ->select('truck_id', DB::raw('SUM(litres) as litres'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('truck_id')
            ->get()
            ->keyBy('truck_id');

        $rows = $trucks->map(function (Truck $truck) use ($rotationsPerTruck, $fuelPerTruck) {
            $row = $rotationsPerTruck->get($truck->id);
            $rotations = (int) ($row->rotations ?? 0);
            $tonnage = (float) ($row->tonnage ?? 0);
            $capacity = max(0.01, (float) ($truck->capacity_tonnage ?: 25));
            $loadRate = $rotations > 0 ? $tonnage / ($capacity * $rotations) : 0.0;
            $litres = (float) ($fuelPerTruck->get($truck->id)->litres ?? 0);
            $yield = $tonnage > 0 ? $litres / $tonnage : null;

            return [
                'id' => $truck->id,
                'label' => $truck->matricule,
                'rotations' => $rotations,
                'tonnage' => round($tonnage, 2),
                'load_rate' => round($loadRate, 4),
                'fuel_yield' => $yield !== null ? round($yield, 3) : null,
            ];
        })->values();

        $rotMax = (float) max(1, $rows->max('rotations'));
        $tonMax = (float) max(1, $rows->max('tonnage'));
        $loadMax = (float) max(0.0001, $rows->max('load_rate'));
        $yieldVals = $rows->pluck('fuel_yield')->filter(fn ($v) => $v !== null && $v > 0);
        $yieldMin = $yieldVals->count() > 0 ? (float) $yieldVals->min() : 0;
        $yieldMax = $yieldVals->count() > 0 ? (float) $yieldVals->max() : 0;

        $scored = $rows->map(function ($r) use ($rotMax, $tonMax, $loadMax, $yieldMin, $yieldMax) {
            $rotN = $rotMax > 0 ? $r['rotations'] / $rotMax : 0;
            $tonN = $tonMax > 0 ? $r['tonnage'] / $tonMax : 0;
            $loadN = $loadMax > 0 ? $r['load_rate'] / $loadMax : 0;
            $yieldN = 0;
            if ($r['fuel_yield'] !== null && $r['fuel_yield'] > 0 && $yieldMax > $yieldMin) {
                $yieldN = 1 - (($r['fuel_yield'] - $yieldMin) / ($yieldMax - $yieldMin));
            } elseif ($r['fuel_yield'] !== null && $r['fuel_yield'] > 0) {
                $yieldN = 1;
            }
            $score = ($rotN + $tonN + $loadN + $yieldN) / 4;
            return array_merge($r, ['score' => round($score * 100, 1)]);
        });

        return $scored->sortByDesc('score')->take(5)->values()->all();
    }

    private function topDrivers(Carbon $from, Carbon $to, FleetSetting $settings): array
    {
        $drivers = Driver::whereNull('deleted_at')->get();

        $rotationsPerDriver = TransportTracking::query()
            ->select('driver_id', DB::raw('COUNT(*) as rotations'), DB::raw('SUM(client_net_weight) as tonnage'))
            ->whereBetween('client_date', [$from, $to])
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        $gapThreshold = (float) ($settings->weight_gap_threshold ?? 0.5);

        $disciplinePerDriver = DriverDisciplineRecord::query()
            ->select('driver_id', DB::raw('SUM(points) as points'))
            ->whereBetween('recorded_at', [$from->toDateString(), $to->toDateString()])
            ->groupBy('driver_id')
            ->pluck('points', 'driver_id');

        $rows = $drivers->map(function (Driver $driver) use ($from, $to, $rotationsPerDriver, $gapThreshold, $disciplinePerDriver) {
            $row = $rotationsPerDriver->get($driver->id);
            $rotations = (int) ($row->rotations ?? 0);
            $tonnage = (float) ($row->tonnage ?? 0);

            $weighedTrips = TransportTracking::query()
                ->where('driver_id', $driver->id)
                ->whereBetween('client_date', [$from, $to])
                ->select('client_net_weight', 'truck_id', 'gap')
                ->with('truck:id,capacity_tonnage')
                ->get();

            $loadSum = 0.0;
            $loadCount = 0;
            $gapPenalty = 0;
            foreach ($weighedTrips as $t) {
                $cap = max(0.01, (float) ($t->truck?->capacity_tonnage ?: 25));
                if (($t->client_net_weight ?? 0) > 0) {
                    $loadSum += ((float) $t->client_net_weight) / $cap;
                    $loadCount++;
                }
                if (abs((float) ($t->gap ?? 0)) > $gapThreshold) {
                    $gapPenalty++;
                }
            }
            $avgLoadRate = $loadCount > 0 ? $loadSum / $loadCount : 0.0;
            $gapRatio = $rotations > 0 ? $gapPenalty / $rotations : 0.0;

            $fuelLitres = (float) FuelTracking::query()
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('truck_id', $weighedTrips->pluck('truck_id')->filter()->unique())
                ->sum('litres');
            $yield = $tonnage > 0 ? $fuelLitres / $tonnage : null;

            $weeklyChecklists = DailyChecklist::query()
                ->where('driver_id', $driver->id)
                ->whereBetween('week_start_date', [$from->copy()->startOfWeek(Carbon::MONDAY), $to])
                ->get();
            $expectedWeeks = max(1, $from->copy()->startOfWeek(Carbon::MONDAY)->diffInWeeks($to->copy()->endOfWeek(Carbon::SUNDAY)) + 1);
            $onTimeCount = $weeklyChecklists->filter(function (DailyChecklist $c) {
                if (! $c->created_at || ! $c->week_start_date) {
                    return false;
                }
                $deadline = Carbon::parse($c->week_start_date)->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
                return $c->created_at->lte($deadline);
            })->count();
            $checklistRate = $expectedWeeks > 0 ? $onTimeCount / $expectedWeeks : 0.0;

            $flaggedIssues = DailyChecklistIssue::query()
                ->where('flagged', true)
                ->whereHas('dailyChecklist', fn ($q) => $q->where('driver_id', $driver->id)
                    ->whereBetween('week_start_date', [$from, $to]))
                ->count();

            $manualPoints = (int) ($disciplinePerDriver[$driver->id] ?? 0);

            return [
                'id' => $driver->id,
                'label' => $driver->name,
                'rotations' => $rotations,
                'tonnage' => round($tonnage, 2),
                'avg_load_rate' => round($avgLoadRate, 4),
                'fuel_yield' => $yield !== null ? round($yield, 3) : null,
                'manual_points' => $manualPoints,
                'checklist_on_time_rate' => round($checklistRate, 4),
                'flagged_issues' => $flaggedIssues,
                'gap_violations' => $gapPenalty,
                'gap_ratio' => round($gapRatio, 4),
            ];
        })->values();

        $rotMax = (float) max(1, $rows->max('rotations'));
        $tonMax = (float) max(1, $rows->max('tonnage'));
        $loadMax = (float) max(0.0001, $rows->max('avg_load_rate'));
        $yieldVals = $rows->pluck('fuel_yield')->filter(fn ($v) => $v !== null && $v > 0);
        $yieldMin = $yieldVals->count() > 0 ? (float) $yieldVals->min() : 0;
        $yieldMax = $yieldVals->count() > 0 ? (float) $yieldVals->max() : 0;

        $pointsVals = $rows->pluck('manual_points');
        $pointsMin = (float) $pointsVals->min();
        $pointsMax = (float) $pointsVals->max();

        $issuesMax = (float) max(1, $rows->max('flagged_issues'));

        $scored = $rows->map(function ($r) use ($rotMax, $tonMax, $loadMax, $yieldMin, $yieldMax, $pointsMin, $pointsMax, $issuesMax) {
            $rotN = $rotMax > 0 ? $r['rotations'] / $rotMax : 0;
            $tonN = $tonMax > 0 ? $r['tonnage'] / $tonMax : 0;
            $loadN = $loadMax > 0 ? $r['avg_load_rate'] / $loadMax : 0;
            $yieldN = 0;
            if ($r['fuel_yield'] !== null && $r['fuel_yield'] > 0 && $yieldMax > $yieldMin) {
                $yieldN = 1 - (($r['fuel_yield'] - $yieldMin) / ($yieldMax - $yieldMin));
            } elseif ($r['fuel_yield'] !== null && $r['fuel_yield'] > 0) {
                $yieldN = 1;
            }

            $manualN = ($pointsMax > $pointsMin)
                ? ($r['manual_points'] - $pointsMin) / ($pointsMax - $pointsMin)
                : 0.5;
            $issuesN = 1 - ($issuesMax > 0 ? $r['flagged_issues'] / $issuesMax : 0);
            $gapsN = 1 - min(1, $r['gap_ratio']);
            $disciplineN = $manualN * 0.4 + $r['checklist_on_time_rate'] * 0.2 + $issuesN * 0.2 + $gapsN * 0.2;

            $score = ($rotN + $tonN + $loadN + $yieldN + $disciplineN) / 5;
            return array_merge($r, [
                'discipline_score' => round($disciplineN * 100, 1),
                'score' => round($score * 100, 1),
            ]);
        });

        return $scored->sortByDesc('score')->take(5)->values()->all();
    }
}
