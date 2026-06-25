<?php

namespace Tests\Feature;

use App\Models\FleetObjective;
use App\Services\PlanningWorkspaceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Planning overview shows the COMPLETE active objective hierarchy (year + month +
 * week) — a finer objective never hides a broader one. Manual objectives only;
 * Planning never estimates. DatabaseTransactions keeps the dev DB untouched.
 */
class PlanningOverviewHierarchyTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function objective(string $type, string $start, string $end, float $tons): void
    {
        $o = new FleetObjective();
        $o->period_type = $type;
        $o->start_date = $start;
        $o->end_date = $end;
        $o->target_tons = $tons;
        $o->target_rotations = 10;
        $o->working_trucks = 5;
        $o->save();
    }

    /** @return array<string, array{planned: bool, target_tons: int|null}> */
    private function hierarchy(): array
    {
        return collect(app(PlanningWorkspaceService::class)->commandCenterData()['hierarchy'])
            ->keyBy('period_type')->all();
    }

    public function test_all_three_levels_are_shown_together(): void
    {
        Carbon::setTestNow('2032-06-17'); // Wednesday
        $this->objective('YEAR', '2032-01-01', '2032-12-31', 24000);
        $this->objective('MONTH', '2032-06-01', '2032-06-30', 3000);
        $this->objective('WEEK', '2032-06-14', '2032-06-19', 800);

        $h = $this->hierarchy();
        $this->assertSame(24000, $h['YEAR']['target_tons']);
        $this->assertSame(3000, $h['MONTH']['target_tons']);
        $this->assertSame(800, $h['WEEK']['target_tons']);
    }

    public function test_year_is_not_hidden_when_only_month_and_week_exist(): void
    {
        Carbon::setTestNow('2032-06-17');
        $this->objective('MONTH', '2032-06-01', '2032-06-30', 3000);
        $this->objective('WEEK', '2032-06-14', '2032-06-19', 800);

        $h = $this->hierarchy();
        $this->assertFalse($h['YEAR']['planned']);     // Année — Non planifiée
        $this->assertNull($h['YEAR']['target_tons']);
        $this->assertSame(3000, $h['MONTH']['target_tons']);
        $this->assertSame(800, $h['WEEK']['target_tons']);
    }

    public function test_week_only(): void
    {
        Carbon::setTestNow('2032-06-17');
        $this->objective('WEEK', '2032-06-14', '2032-06-19', 800);

        $h = $this->hierarchy();
        $this->assertFalse($h['YEAR']['planned']);
        $this->assertFalse($h['MONTH']['planned']);
        $this->assertSame(800, $h['WEEK']['target_tons']);
    }

    public function test_no_objective_is_all_unplanned(): void
    {
        Carbon::setTestNow('2035-03-14'); // far-future date with no objectives
        $h = $this->hierarchy();
        $this->assertFalse($h['YEAR']['planned']);
        $this->assertFalse($h['MONTH']['planned']);
        $this->assertFalse($h['WEEK']['planned']);
    }
}
