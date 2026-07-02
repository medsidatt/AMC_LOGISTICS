<?php

namespace App\Domain\Fuel;

use App\Domain\Fuel\ValueObjects\FuelTransactionClassification;
use App\Domain\Fuel\ValueObjects\PolicyOutcome;
use App\Enums\Fuel\KpiEligibility;
use App\Enums\Fuel\PersistenceDecision;
use App\Enums\Fuel\ReviewDecision;

/**
 * The ONE place fuel-import business rules live. Pure function of the classification facts
 * (TransactionType · FuelSource · ValidationFindings) → three decisions. No DB, no config, no I/O.
 * Detection never calls into decisions; consumers read decisions and never re-derive them.
 *
 * Rules (spec Phase 4 decision matrix, policy v1):
 *   - Persist = REJECT  iff any technical finding forces reject; else ACCEPT.
 *   - KPI     = ELIGIBLE iff type is KPI-capable (FUEL_RECHARGE) AND no findings AND Persist = ACCEPT.
 *   - Review  = REQUIRED iff any finding forces review OR the type requires review when clean (UNKNOWN).
 */
final class ClassificationPolicy
{
    /** Bump when any rule below changes; stored per transaction as the proposal's policy_version. */
    public const VERSION = 'v1';

    public function decide(FuelTransactionClassification $classification): PolicyOutcome
    {
        $findings = $classification->findings;

        $persistence = $findings->hasRejectingFinding()
            ? PersistenceDecision::REJECT
            : PersistenceDecision::ACCEPT;

        $kpi = ($classification->type->isKpiCapable()
                && $findings->isEmpty()
                && $persistence === PersistenceDecision::ACCEPT)
            ? KpiEligibility::ELIGIBLE
            : KpiEligibility::NOT_ELIGIBLE;

        $review = ($findings->hasReviewFinding() || $classification->type->requiresReviewWhenClean())
            ? ReviewDecision::REQUIRED
            : ReviewDecision::NONE;

        return new PolicyOutcome($persistence, $kpi, $review);
    }

    public function version(): string
    {
        return self::VERSION;
    }
}
