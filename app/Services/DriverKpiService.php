<?php

namespace App\Services;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\Driver;
use App\Models\DriverDisciplineRecord;
use App\Models\FleetSetting;
use App\Models\FuelEvent;
use App\Models\MonthlyTonnageTarget;
use App\Models\TransportTracking;
use App\Models\Truck;
use Carbon\Carbon;

class DriverKpiService
{
    private const WEIGHT_ROTATIONS = 0.20;
    private const WEIGHT_CYCLE = 0.20;
    private const WEIGHT_FUEL_GAP = 0.20;
    private const WEIGHT_WEIGHT_GAP = 0.20;
    private const WEIGHT_DISCIPLINE = 0.20;

    public function compute(Driver $driver, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $settings = FleetSetting::current();
        $gapThreshold = (float) ($settings->weight_gap_threshold ?? 0.5);

        $rotations = TransportTracking::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('client_date', [$from, $to])
            ->orderBy('provider_date')
            ->orderBy('id')
            ->get(['id', 'truck_id', 'provider_date', 'client_date', 'gap']);

        $done = $rotations->count();

        // 1. Rotations — planned share for this driver
        $plannedTonnage = MonthlyTonnageTarget::sumForPeriod($from, $to);
        $avgCapacity = max(0.01, (float) (Truck::where('is_active', true)->avg('capacity_tonnage') ?: 25));
        $activeDrivers = max(1, Driver::where('is_active', true)->count());
        $plannedRotations = ($plannedTonnage / $avgCapacity) / $activeDrivers;

        $rotationsScore = $plannedRotations > 0
            ? min(100.0, ($done / $plannedRotations) * 100.0)
            : 0.0;

        // 2. Cycle moyen entre 2 rotations consécutives
        $avgCycleDays = $this->averageCycleDays($rotations);
        $cycleScore = $avgCycleDays === null
            ? 0.0
            : max(0.0, 100.0 - $avgCycleDays * 20.0);

        // 3. Écart carburant — anomalies sur les camions conduits par ce chauffeur
        $truckIds = $rotations->pluck('truck_id')->filter()->unique()->values();
        $fuelAnomalies = $truckIds->isEmpty() ? collect() : FuelEvent::query()
            ->whereIn('truck_id', $truckIds)
            ->whereIn('event_type', [FuelEvent::TYPE_DROP, FuelEvent::TYPE_THEFT_SUSPECTED])
            ->whereBetween('detected_at', [$from, $to])
            ->get(['event_type', 'litres_delta']);

        $fuelAnomaliesCount = $fuelAnomalies->count();
        $fuelAnomaliesLitres = (float) $fuelAnomalies->sum(fn ($e) => abs((float) ($e->litres_delta ?? 0)));
        $fuelGapScore = max(0.0, 100.0 - $fuelAnomaliesCount * 20.0);

        // 4. Écart tonnage
        $gapSum = (float) $rotations->sum('gap');
        $gapViolations = $rotations->filter(fn ($r) => abs((float) ($r->gap ?? 0)) > $gapThreshold)->count();
        $weightGapScore = $done > 0
            ? max(0.0, 100.0 - ($gapViolations / $done) * 100.0)
            : 100.0;

        // 5. Discipline (manuel + checklists à temps + issues critiques + écarts de poids)
        $disciplineScore = $this->disciplineScore($driver, $from, $to, $rotations, $gapViolations, $done);

        $globalScore = ($rotationsScore * self::WEIGHT_ROTATIONS)
            + ($cycleScore * self::WEIGHT_CYCLE)
            + ($fuelGapScore * self::WEIGHT_FUEL_GAP)
            + ($weightGapScore * self::WEIGHT_WEIGHT_GAP)
            + ($disciplineScore * self::WEIGHT_DISCIPLINE);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'rotations' => [
                'done' => $done,
                'planned' => round($plannedRotations, 1),
                'score' => round($rotationsScore, 1),
                'weight' => self::WEIGHT_ROTATIONS,
            ],
            'cycle' => [
                'avg_days' => $avgCycleDays !== null ? round($avgCycleDays, 2) : null,
                'score' => round($cycleScore, 1),
                'weight' => self::WEIGHT_CYCLE,
            ],
            'fuel_gap' => [
                'anomalies' => $fuelAnomaliesCount,
                'litres' => round($fuelAnomaliesLitres, 2),
                'score' => round($fuelGapScore, 1),
                'weight' => self::WEIGHT_FUEL_GAP,
            ],
            'weight_gap' => [
                'sum' => round($gapSum, 2),
                'violations' => $gapViolations,
                'threshold' => $gapThreshold,
                'score' => round($weightGapScore, 1),
                'weight' => self::WEIGHT_WEIGHT_GAP,
            ],
            'discipline' => [
                'score' => round($disciplineScore, 1),
                'weight' => self::WEIGHT_DISCIPLINE,
            ],
            'global_score' => round($globalScore, 1),
        ];
    }

    private function averageCycleDays($rotations): ?float
    {
        if ($rotations->count() < 2) {
            return null;
        }

        $deltas = [];
        $previous = null;
        foreach ($rotations as $r) {
            $providerDate = $r->provider_date ? Carbon::parse($r->provider_date) : null;
            $clientDate = $r->client_date ? Carbon::parse($r->client_date) : null;
            if (! $providerDate || ! $clientDate) {
                continue;
            }
            if ($previous !== null) {
                $deltas[] = max(0.0, $previous->floatDiffInDays($providerDate));
            }
            $previous = $clientDate;
        }

        if (empty($deltas)) {
            return null;
        }

        return array_sum($deltas) / count($deltas);
    }

    private function disciplineScore(Driver $driver, Carbon $from, Carbon $to, $rotations, int $gapViolations, int $rotationsCount): float
    {
        $manualPoints = (int) DriverDisciplineRecord::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('recorded_at', [$from->toDateString(), $to->toDateString()])
            ->sum('points');

        $weeklyChecklists = DailyChecklist::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('week_start_date', [$from->copy()->startOfWeek(Carbon::MONDAY), $to])
            ->get();

        $expectedWeeks = max(1, $from->copy()->startOfWeek(Carbon::MONDAY)
            ->diffInWeeks($to->copy()->endOfWeek(Carbon::SUNDAY)) + 1);
        $onTimeCount = $weeklyChecklists->filter(function (DailyChecklist $c) {
            if (! $c->created_at || ! $c->week_start_date) {
                return false;
            }
            $deadline = Carbon::parse($c->week_start_date)->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            return $c->created_at->lte($deadline);
        })->count();
        $checklistOnTimeRate = $expectedWeeks > 0 ? $onTimeCount / $expectedWeeks : 0.0;

        $flaggedIssues = DailyChecklistIssue::query()
            ->where('flagged', true)
            ->whereHas('dailyChecklist', fn ($q) => $q->where('driver_id', $driver->id)
                ->whereBetween('week_start_date', [$from, $to]))
            ->count();

        // Normalize manual points around 0 (positive = good, negative = bad).
        // Mapping: -10 pts → 0, 0 → 50, +10 pts → 100, clamped.
        $manualN = max(0.0, min(1.0, ($manualPoints + 10) / 20));

        $issuesN = max(0.0, 1.0 - min(1.0, $flaggedIssues / 10));
        $gapRatio = $rotationsCount > 0 ? $gapViolations / $rotationsCount : 0.0;
        $gapsN = 1.0 - min(1.0, $gapRatio);

        $score = ($manualN * 0.4 + $checklistOnTimeRate * 0.2 + $issuesN * 0.2 + $gapsN * 0.2) * 100;

        return $score;
    }
}
