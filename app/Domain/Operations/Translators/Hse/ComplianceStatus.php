<?php

namespace App\Domain\Operations\Translators\Hse;

use App\Domain\Operations\Translators\Presentation\SeverityTally;

/**
 * The HSE compliance status — an aggregate of ALL HSE-routed conclusions by severity (e.g.
 * how many trucks are legally blocked vs. inspections awaiting validation). All-covering
 * product of the HSE view: every conclusion is counted, none dropped. Aggregation only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ComplianceStatus
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

    /** How many compliance conclusions need action now (critical/high). */
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
