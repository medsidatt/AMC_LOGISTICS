<?php

namespace App\Services;

use App\Models\FleetObjective;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Hierarchical target resolution for a planning period.
 *
 * Objectives exist at WEEK / MONTH / YEAR / CUSTOM levels. For a selected view,
 * the most specific objective that *contains* the period wins; if none contains
 * it, a broader objective is prorated by date overlap, or same-level objectives
 * are aggregated. The engine never returns an empty target purely because the
 * range is not an exact match.
 *
 * Priority per view mode (first step that yields a target wins):
 *   WEEK   : exact WEEK  → containing MONTH (prorated) → containing YEAR (prorated) → aggregate WEEK
 *   MONTH  : exact MONTH  → containing YEAR (prorated)  → aggregate WEEK
 *   YEAR   : exact YEAR   → aggregate MONTH
 *   CUSTOM : aggregate WEEK → aggregate MONTH → aggregate YEAR
 */
class ObjectiveTargetResolver
{
    /** @var array<string,string[]> mode → ordered resolution steps ("exact:WEEK", ...) */
    private const CHAINS = [
        FleetObjective::PERIOD_WEEK => ['exact:WEEK', 'containing:MONTH', 'containing:YEAR', 'aggregate:WEEK'],
        FleetObjective::PERIOD_MONTH => ['exact:MONTH', 'containing:YEAR', 'aggregate:WEEK'],
        FleetObjective::PERIOD_YEAR => ['exact:YEAR', 'aggregate:MONTH'],
        FleetObjective::PERIOD_CUSTOM => ['aggregate:WEEK', 'aggregate:MONTH', 'aggregate:YEAR'],
    ];

    /**
     * @return array{
     *   source:string, period_type:?string, coverage:float,
     *   fleet:array{target_rotations:int,target_tons:float},
     *   per_truck:array<int,array{target_rotations:int,target_tons:float}>
     * }
     */
    public function __construct(private OperationsCalendarService $calendar) {}

    public function resolve(Carbon $start, Carbon $end, string $viewMode): array
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();
        $chain = self::CHAINS[$viewMode] ?? self::CHAINS[FleetObjective::PERIOD_WEEK];

        foreach ($chain as $step) {
            [$op, $period] = explode(':', $step);
            $result = match ($op) {
                'exact' => $this->exact($period, $startStr, $endStr),
                'containing' => $this->containing($period, $start, $end),
                'aggregate' => $this->aggregate($period, $start, $end),
                default => null,
            };
            if ($result !== null) {
                return $result;
            }
        }

        return $this->empty();
    }

    /**
     * Exact period-type match only — NO proration / aggregation fallback. Réalisation
     * uses this so progress is computed only against an objective of the same period
     * type (week→weekly, month→monthly, year→annual); otherwise no objective.
     */
    public function exactForMode(Carbon $start, Carbon $end, string $mode): array
    {
        return $this->exact($mode, $start->toDateString(), $end->toDateString()) ?? $this->empty();
    }

    /** Exact same-level match → use the frozen targets verbatim. */
    private function exact(string $period, string $startStr, string $endStr): ?array
    {
        $obj = FleetObjective::with('truckTargets')
            ->where('period_type', $period)
            ->active()
            ->whereDate('start_date', $startStr)
            ->whereDate('end_date', $endStr)
            ->first();

        if (! $obj) {
            return null;
        }

        return [
            'source' => 'exact',
            'period_type' => $period,
            'coverage' => 1.0,
            'fleet' => [
                'target_rotations' => (int) $obj->target_rotations,
                'target_tons' => round((float) $obj->target_tons, 2),
            ],
            'per_truck' => $obj->truckTargets
                ->mapWithKeys(fn ($t) => [(int) $t->truck_id => [
                    'target_rotations' => (int) $t->target_rotations,
                    'target_tons' => round((float) $t->target_tons, 2),
                ]])
                ->all(),
        ];
    }

    /** Broader objective that fully contains the period → prorate by day ratio. */
    private function containing(string $period, Carbon $start, Carbon $end): ?array
    {
        $obj = FleetObjective::with('truckTargets')
            ->where('period_type', $period)
            ->active()
            ->containing($start->toDateString(), $end->toDateString())
            ->orderByRaw('DATEDIFF(end_date, start_date) ASC') // smallest containing span
            ->first();

        if (! $obj) {
            return null;
        }

        $periodDays = $this->days($start, $end);
        $objDays = max(1, $this->days($obj->start_date, $obj->end_date));
        $factor = $periodDays / $objDays;

        return [
            'source' => 'derived',
            'period_type' => $period,
            'coverage' => 1.0,
            'fleet' => [
                'target_rotations' => (int) round($obj->target_rotations * $factor),
                'target_tons' => round((float) $obj->target_tons * $factor, 2),
            ],
            'per_truck' => $obj->truckTargets
                ->mapWithKeys(fn ($t) => [(int) $t->truck_id => [
                    'target_rotations' => (int) round($t->target_rotations * $factor),
                    'target_tons' => round((float) $t->target_tons * $factor, 2),
                ]])
                ->all(),
        ];
    }

    /** Same-level objectives overlapping the period → sum, weighted by overlap. */
    private function aggregate(string $period, Carbon $start, Carbon $end): ?array
    {
        /** @var Collection<int,FleetObjective> $objs */
        $objs = FleetObjective::with('truckTargets')
            ->where('period_type', $period)
            ->active()
            ->overlapping($start->toDateString(), $end->toDateString())
            ->get();

        if ($objs->isEmpty()) {
            return null;
        }

        $periodDays = $this->days($start, $end);
        $fleetRot = 0.0;
        $fleetTons = 0.0;
        $perTruck = [];
        $coveredDays = 0;

        foreach ($objs as $obj) {
            $oStart = $obj->start_date->copy();
            $oEnd = $obj->end_date->copy();
            $overlapStart = $oStart->gt($start) ? $oStart : $start->copy();
            $overlapEnd = $oEnd->lt($end) ? $oEnd : $end->copy();
            $overlapDays = $this->days($overlapStart, $overlapEnd);
            $objDays = max(1, $this->days($oStart, $oEnd));
            $factor = $overlapDays / $objDays;
            $coveredDays += $overlapDays;

            $fleetRot += $obj->target_rotations * $factor;
            $fleetTons += (float) $obj->target_tons * $factor;

            foreach ($obj->truckTargets as $t) {
                $id = (int) $t->truck_id;
                $perTruck[$id]['target_rotations'] = ($perTruck[$id]['target_rotations'] ?? 0) + $t->target_rotations * $factor;
                $perTruck[$id]['target_tons'] = ($perTruck[$id]['target_tons'] ?? 0) + (float) $t->target_tons * $factor;
            }
        }

        return [
            'source' => 'aggregated',
            'period_type' => $period,
            'coverage' => min(1.0, round($coveredDays / max(1, $periodDays), 4)),
            'fleet' => [
                'target_rotations' => (int) round($fleetRot),
                'target_tons' => round($fleetTons, 2),
            ],
            'per_truck' => collect($perTruck)
                ->map(fn ($v) => [
                    'target_rotations' => (int) round($v['target_rotations']),
                    'target_tons' => round($v['target_tons'], 2),
                ])
                ->all(),
        ];
    }

    private function empty(): array
    {
        return [
            'source' => 'none',
            'period_type' => null,
            'coverage' => 0.0,
            'fleet' => ['target_rotations' => 0, 'target_tons' => 0.0],
            'per_truck' => [],
        ];
    }

    /** Operational working-day count between two dates (calendar-aware). */
    private function days(Carbon $a, Carbon $b): int
    {
        return $this->calendar->operationalDays($a, $b);
    }
}
