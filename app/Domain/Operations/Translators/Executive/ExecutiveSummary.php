<?php

namespace App\Domain\Operations\Translators\Executive;

use App\Domain\Operations\Translators\Presentation\SeverityTally;

/**
 * The executive at-a-glance summary — an aggregate of the conclusions by severity, owner,
 * and impact. Aggregation only; no calculation. Backed by the shared {@see SeverityTally}
 * so every conclusion is counted (nothing dropped).
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExecutiveSummary
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

    /** How many need action now (critical/high). */
    public function immediate(): int
    {
        return $this->tally->immediate();
    }

    /** @return array<string, int> owner value => count */
    public function byOwner(): array
    {
        return $this->tally->byOwner();
    }

    /** @return array<string, int> severity value => count */
    public function bySeverity(): array
    {
        return $this->tally->bySeverity();
    }
}
