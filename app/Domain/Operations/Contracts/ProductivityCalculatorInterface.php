<?php

namespace App\Domain\Operations\Contracts;

/**
 * Owns productivity / performance scoring. disciplineScore is the pure math behind a
 * driver's discipline rating; the caller supplies the already-fetched inputs (the
 * Eloquent reads stay in the service). No Eloquent, SQL, config, or events.
 */
interface ProductivityCalculatorInterface
{
    /**
     * Driver discipline score (0–100) from pre-fetched inputs.
     * Mapping preserved verbatim from the legacy DriverKpiService implementation.
     */
    public function disciplineScore(
        int $manualPoints,
        float $checklistOnTimeRate,
        int $flaggedIssues,
        int $gapViolations,
        int $rotationsCount,
    ): float;
}
