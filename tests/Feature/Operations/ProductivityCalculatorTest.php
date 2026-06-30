<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\ProductivityCalculator;
use Tests\TestCase;

/** R1.3 inc4 — ProductivityCalculator owns the discipline-score math (pure, lifted verbatim). */
class ProductivityCalculatorTest extends TestCase
{
    private function calc(): ProductivityCalculator
    {
        return new ProductivityCalculator();
    }

    /** manualN=0.5, checklist=1.0, issuesN=1, gapsN=1 → (0.2+0.2+0.2+0.2)*100 = 80. */
    public function test_discipline_score_snapshot(): void
    {
        $this->assertSame(80.0, $this->calc()->disciplineScore(0, 1.0, 0, 0, 10));
    }

    /** Reproduces the legacy mapping for arbitrary inputs. */
    public function test_discipline_score_matches_legacy_math(): void
    {
        $cases = [[8, 0.5, 2, 3, 12], [-10, 0.0, 20, 12, 12], [10, 1.0, 0, 0, 5]];
        foreach ($cases as [$manual, $checklist, $issues, $viol, $rot]) {
            $manualN = max(0.0, min(1.0, ($manual + 10) / 20));
            $issuesN = max(0.0, 1.0 - min(1.0, $issues / 10));
            $gapRatio = $rot > 0 ? $viol / $rot : 0.0;
            $gapsN = 1.0 - min(1.0, $gapRatio);
            $legacy = ($manualN * 0.4 + $checklist * 0.2 + $issuesN * 0.2 + $gapsN * 0.2) * 100;

            $this->assertSame($legacy, $this->calc()->disciplineScore($manual, $checklist, $issues, $viol, $rot));
        }
    }
}
