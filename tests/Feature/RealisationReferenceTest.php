<?php

namespace Tests\Feature;

use App\Models\FleetObjective;
use App\Services\RotationAchievementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Réalisation hierarchical-mean reference. Read-only: the service derives a
 * reporting reference from manual planning, filling unplanned child slots with
 * the mean of the planned ones. Nothing is persisted. DatabaseTransactions keeps
 * the dev DB untouched. Far-future years (2032/2033) avoid collisions with data.
 */
class RealisationReferenceTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): RotationAchievementService
    {
        return app(RotationAchievementService::class);
    }

    private function objective(string $type, string $start, string $end, float $tons, int $rot): void
    {
        $o = new FleetObjective();
        $o->period_type = $type;
        $o->start_date = $start;
        $o->end_date = $end;
        $o->target_tons = $tons;
        $o->target_rotations = $rot;
        $o->save();
    }

    public function test_year_reference_fills_missing_months_with_the_mean(): void
    {
        // Three manual months; the remaining nine are estimated at their mean.
        $this->objective(FleetObjective::PERIOD_MONTH, '2032-01-01', '2032-01-31', 2200, 55);
        $this->objective(FleetObjective::PERIOD_MONTH, '2032-02-01', '2032-02-29', 2100, 52);
        $this->objective(FleetObjective::PERIOD_MONTH, '2032-03-01', '2032-03-31', 1900, 48);

        $r = $this->svc()->forPeriod(
            Carbon::create(2032, 1, 1),
            Carbon::create(2032, 12, 31),
            FleetObjective::PERIOD_YEAR,
        );

        $this->assertSame('estimated', $r['target_source']);
        // 6200 + 9 × mean(2200,2100,1900) = 6200 + 9 × 2066.67 = 24 800 (NOT a flat ≈2067).
        $this->assertEqualsWithDelta(24800, $r['fleet']['target_tons'], 0.5);
    }

    public function test_month_reference_estimates_missing_weeks(): void
    {
        // Two manual weeks; the month's remaining week slots are filled at the mean.
        $this->objective(FleetObjective::PERIOD_WEEK, '2032-06-07', '2032-06-12', 650, 16);
        $this->objective(FleetObjective::PERIOD_WEEK, '2032-06-14', '2032-06-19', 700, 18);

        $r = $this->svc()->forPeriod(
            Carbon::create(2032, 6, 1),
            Carbon::create(2032, 6, 30),
            FleetObjective::PERIOD_MONTH,
        );

        $this->assertSame('estimated', $r['target_source']);
        // mean = 675; estimated missing weeks lift the reference above the 1350 manual sum.
        $this->assertGreaterThan(1350.0, (float) $r['fleet']['target_tons']);
    }

    public function test_no_planning_yields_no_reference(): void
    {
        $r = $this->svc()->forPeriod(
            Carbon::create(2033, 1, 1),
            Carbon::create(2033, 12, 31),
            FleetObjective::PERIOD_YEAR,
        );

        $this->assertSame('none', $r['target_source']);
        $this->assertFalse($r['has_objective']);
    }
}
