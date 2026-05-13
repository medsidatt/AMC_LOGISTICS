<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MonthlyTonnageTarget extends Model
{
    public const DEFAULT_TARGET = 2000.0;

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'target_tonnage' => 'float',
    ];

    public static function forMonth(int $year, int $month): float
    {
        $row = static::query()
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        return $row ? (float) $row->target_tonnage : self::defaultTarget();
    }

    public static function defaultTarget(): float
    {
        $setting = FleetSetting::current();
        $global = (float) ($setting->monthly_target_tonnage ?? 0);

        return $global > 0 ? $global : self::DEFAULT_TARGET;
    }

    /**
     * Sum the prorated target across a date range, accounting for partial months.
     */
    public static function sumForPeriod(Carbon $from, Carbon $to): float
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $cursor = $from->copy()->startOfMonth();
        $total = 0.0;

        while ($cursor->lessThanOrEqualTo($to)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $daysInMonth = $monthStart->daysInMonth;

            $overlapStart = $from->greaterThan($monthStart) ? $from : $monthStart;
            $overlapEnd = $to->lessThan($monthEnd) ? $to : $monthEnd;

            if ($overlapStart->lessThanOrEqualTo($overlapEnd)) {
                $overlapDays = $overlapStart->copy()->startOfDay()
                    ->diffInDays($overlapEnd->copy()->startOfDay()) + 1;
                $overlapDays = (int) round($overlapDays);
                $monthTarget = self::forMonth($cursor->year, $cursor->month);
                $total += ($monthTarget / $daysInMonth) * $overlapDays;
            }

            $cursor->addMonth();
        }

        return $total;
    }
}
