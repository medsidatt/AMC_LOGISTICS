<?php

namespace App\Domain\Analytics\Trends;

use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use InvalidArgumentException;

/**
 * An ordered series of already-computed metric values for ONE BI KPI, oldest → newest. The
 * input a trend reads to measure the latest movement. Immutable; it stores values produced
 * upstream (R4.2) and never recomputes or reads anything.
 *
 * @phpstan-consistent-constructor
 */
final readonly class HistorySeries
{
    /**
     * @param  list<HistoryPoint>  $points  ordered oldest → newest
     */
    public function __construct(
        private BusinessKpiId $id,
        private array $points,
    ) {}

    public function id(): BusinessKpiId
    {
        return $this->id;
    }

    /** @return list<HistoryPoint> */
    public function points(): array
    {
        return $this->points;
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function latest(): HistoryPoint
    {
        return $this->points[$this->requireTwo() - 1];
    }

    public function previous(): HistoryPoint
    {
        return $this->points[$this->requireTwo() - 2];
    }

    private function requireTwo(): int
    {
        $count = count($this->points);
        if ($count < 2) {
            throw new InvalidArgumentException("A trend needs at least two points; [{$this->id->value}] has {$count}.");
        }

        return $count;
    }
}
