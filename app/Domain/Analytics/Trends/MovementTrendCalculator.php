<?php

namespace App\Domain\Analytics\Trends;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Trends\Contracts\TrendCalculator;
use App\Domain\Analytics\Trends\Enums\TrendDirection;
use InvalidArgumentException;

/**
 * The descriptive movement trend: absolute difference, percent change, and direction
 * (up/down/stable) between two already-computed metric values. Pure arithmetic over the values
 * it is handed — no business metric, no threshold band, no DB, no formatting.
 *
 * `EPSILON` is a float-precision guard for the stable/zero checks, not a business tolerance.
 */
final class MovementTrendCalculator implements TrendCalculator
{
    private const EPSILON = 1e-9;

    public function compare(BusinessMetric $current, BusinessMetric $previous, ReportingPeriodRange $range): TrendResult
    {
        if ($current->id() !== $previous->id()) {
            throw new InvalidArgumentException("Cannot trend two different KPIs ([{$current->id()->value}] vs [{$previous->id()->value}]).");
        }

        return $this->build($current->id(), $current->value(), $previous->value(), $range->current, $range->previous);
    }

    public function trend(HistorySeries $series): TrendResult
    {
        $latest = $series->latest();
        $previous = $series->previous();

        return $this->build($series->id(), $latest->value, $previous->value, $latest->period, $previous->period);
    }

    private function build(BusinessKpiId $id, float $current, float $previous, ReportingPeriod $currentPeriod, ReportingPeriod $previousPeriod): TrendResult
    {
        $difference = $current - $previous;
        $percentChange = abs($previous) > self::EPSILON ? ($difference / $previous) * 100.0 : 0.0;

        return new TrendResult(
            $id,
            $current,
            $previous,
            $difference,
            $percentChange,
            $this->direction($difference),
            $currentPeriod,
            $previousPeriod,
        );
    }

    private function direction(float $difference): TrendDirection
    {
        if (abs($difference) <= self::EPSILON) {
            return TrendDirection::STABLE;
        }

        return $difference > 0 ? TrendDirection::UP : TrendDirection::DOWN;
    }
}
