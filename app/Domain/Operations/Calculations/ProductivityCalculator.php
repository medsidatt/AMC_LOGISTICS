<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\ProductivityCalculatorInterface;

/**
 * Productivity / performance scoring. disciplineScore is lifted verbatim from
 * DriverKpiService (the score math only; the service keeps the Eloquent fetches and
 * passes the inputs). Pure — no Eloquent, SQL, config, or events.
 */
class ProductivityCalculator implements ProductivityCalculatorInterface
{
    public function disciplineScore(
        int $manualPoints,
        float $checklistOnTimeRate,
        int $flaggedIssues,
        int $gapViolations,
        int $rotationsCount,
    ): float {
        // Normalize manual points around 0 (positive = good, negative = bad).
        // Mapping: -10 pts → 0, 0 → 50, +10 pts → 100, clamped.
        $manualN = max(0.0, min(1.0, ($manualPoints + 10) / 20));

        $issuesN = max(0.0, 1.0 - min(1.0, $flaggedIssues / 10));
        $gapRatio = $rotationsCount > 0 ? $gapViolations / $rotationsCount : 0.0;
        $gapsN = 1.0 - min(1.0, $gapRatio);

        return ($manualN * 0.4 + $checklistOnTimeRate * 0.2 + $issuesN * 0.2 + $gapsN * 0.2) * 100;
    }
}
