<?php

namespace App\Domain\Operations\Translators\Presentation;

use App\Domain\Operations\Intelligence\OperationalConclusion;

/**
 * An aggregate of conclusions by severity and by owner — a presentation SUMMARY, not a
 * calculation. Aggregation (counting already-decided severities/owners) is explicitly a
 * translator responsibility; no formula, threshold, score, DB, config, or UI is involved.
 *
 * The tally counts EVERY conclusion it is given, so a view backed by a tally never drops
 * a conclusion.
 *
 * @phpstan-consistent-constructor
 */
final readonly class SeverityTally
{
    /**
     * @param  array<string, int>  $bySeverity  severity value => count
     * @param  array<string, int>  $byOwner  owner value => count
     * @param  array<string, int>  $byImpact  business-impact value => count
     */
    public function __construct(
        private int $total,
        private array $bySeverity,
        private array $byOwner,
        private array $byImpact,
        private int $immediate,
    ) {}

    /**
     * Count a list of conclusions. Pure tallying of values that already exist on each
     * conclusion — no derivation, no ranking formula.
     *
     * @param  list<OperationalConclusion>  $conclusions
     */
    public static function of(array $conclusions): self
    {
        $bySeverity = [];
        $byOwner = [];
        $byImpact = [];
        $immediate = 0;

        foreach ($conclusions as $c) {
            $severity = $c->severity()->value;
            $owner = $c->owner()->value;
            $impact = $c->businessImpact()->value;

            $bySeverity[$severity] = ($bySeverity[$severity] ?? 0) + 1;
            $byOwner[$owner] = ($byOwner[$owner] ?? 0) + 1;
            $byImpact[$impact] = ($byImpact[$impact] ?? 0) + 1;

            if ($c->priority()->isImmediate()) {
                $immediate++;
            }
        }

        return new self(count($conclusions), $bySeverity, $byOwner, $byImpact, $immediate);
    }

    public function total(): int
    {
        return $this->total;
    }

    /** @return array<string, int> */
    public function bySeverity(): array
    {
        return $this->bySeverity;
    }

    /** @return array<string, int> */
    public function byOwner(): array
    {
        return $this->byOwner;
    }

    /** @return array<string, int> */
    public function byImpact(): array
    {
        return $this->byImpact;
    }

    /** How many conclusions are immediate (critical/high) — reused urgency policy. */
    public function immediate(): int
    {
        return $this->immediate;
    }

    public function severity(string $value): int
    {
        return $this->bySeverity[$value] ?? 0;
    }
}
