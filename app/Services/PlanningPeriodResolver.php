<?php

namespace App\Services;

use App\Models\FleetObjective;
use Carbon\Carbon;

/**
 * Resolves a planning view (mode + anchor) into a concrete [start, end] range.
 * Week stays Mon→Sat to match the existing roster/objective convention; Month and
 * Year are calendar periods; Custom is an explicit range. start is start-of-day,
 * end is end-of-day so the achievement queries cover the whole period.
 */
class PlanningPeriodResolver
{
    public const MODES = [
        FleetObjective::PERIOD_WEEK,
        FleetObjective::PERIOD_MONTH,
        FleetObjective::PERIOD_YEAR,
        FleetObjective::PERIOD_CUSTOM,
    ];

    /**
     * @return array{mode:string,start:Carbon,end:Carbon}
     */
    public function resolve(?string $mode, ?string $anchor = null, ?string $start = null, ?string $end = null): array
    {
        $mode = in_array(strtoupper((string) $mode), self::MODES, true)
            ? strtoupper((string) $mode)
            : FleetObjective::PERIOD_WEEK;

        $a = $anchor ? Carbon::parse($anchor) : Carbon::now();

        return match ($mode) {
            FleetObjective::PERIOD_MONTH => [
                'mode' => $mode,
                'start' => $a->copy()->startOfMonth(),
                'end' => $a->copy()->endOfMonth(),
            ],
            FleetObjective::PERIOD_YEAR => [
                'mode' => $mode,
                'start' => $a->copy()->startOfYear(),
                'end' => $a->copy()->endOfYear(),
            ],
            FleetObjective::PERIOD_CUSTOM => $this->custom($start, $end),
            default => [
                'mode' => FleetObjective::PERIOD_WEEK,
                'start' => $a->copy()->startOfWeek(Carbon::MONDAY),
                'end' => $a->copy()->startOfWeek(Carbon::MONDAY)->addDays(5)->endOfDay(),
            ],
        };
    }

    /**
     * @return array{mode:string,start:Carbon,end:Carbon}
     */
    private function custom(?string $start, ?string $end): array
    {
        $s = $start ? Carbon::parse($start)->startOfDay() : Carbon::now()->startOfDay();
        $e = $end ? Carbon::parse($end)->endOfDay() : $s->copy()->endOfDay();

        // Guard against an inverted range.
        if ($e->lt($s)) {
            [$s, $e] = [$e->copy()->startOfDay(), $s->copy()->endOfDay()];
        }

        return ['mode' => FleetObjective::PERIOD_CUSTOM, 'start' => $s, 'end' => $e];
    }
}
