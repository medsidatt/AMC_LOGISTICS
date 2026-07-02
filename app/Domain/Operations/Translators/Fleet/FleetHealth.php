<?php

namespace App\Domain\Operations\Translators\Fleet;

use App\Domain\Operations\Translators\Presentation\SeverityTally;

/**
 * The fleet health snapshot — an aggregate of ALL fleet-routed conclusions by severity and
 * owner. This is the all-covering product of the fleet view: it counts every conclusion, so
 * even a conclusion that belongs to neither the capacity nor the maintenance list is still
 * represented here (nothing dropped). Aggregation only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class FleetHealth
{
    public function __construct(
        private SeverityTally $tally,
    ) {}

    public function tally(): SeverityTally
    {
        return $this->tally;
    }

    public function total(): int
    {
        return $this->tally->total();
    }

    /** How many fleet conclusions need action now (critical/high). */
    public function immediate(): int
    {
        return $this->tally->immediate();
    }

    /** @return array<string, int> severity value => count */
    public function bySeverity(): array
    {
        return $this->tally->bySeverity();
    }
}
