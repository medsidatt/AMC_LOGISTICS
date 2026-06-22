<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Read-only render checks for the multi-period planning scoreboard and the
 * objective management page. GET-only; DatabaseTransactions keeps the dev DB
 * untouched (the testing connection points at it).
 */
class FleetPlanningPeriodsTest extends TestCase
{
    use DatabaseTransactions;

    private function planner(): User
    {
        return User::query()
            ->permission('fleet-roster-plan')
            ->permission('daily-dispatch-list')
            ->firstOrFail();
    }

    public function test_scoreboard_renders_for_week_month_year(): void
    {
        foreach (['WEEK', 'MONTH', 'YEAR'] as $mode) {
            $this->actingAs($this->planner())
                ->get("/logistics/planning/weekly?mode={$mode}&anchor=2026-06-17")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('logistics/planning/Weekly')
                    ->where('mode', $mode)
                    ->has('period.start')
                    ->has('period.end')
                    ->has('achievement.fleet')
                    ->has('achievement.target_source'));
        }
    }

    public function test_scoreboard_renders_for_custom_range(): void
    {
        $this->actingAs($this->planner())
            ->get('/logistics/planning/weekly?mode=CUSTOM&start=2026-06-01&end=2026-06-30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('logistics/planning/Weekly')
                ->where('mode', 'CUSTOM')
                ->where('period.start', '2026-06-01')
                ->where('period.end', '2026-06-30'));
    }

    public function test_objective_management_page_renders(): void
    {
        $this->actingAs($this->planner())
            ->get('/logistics/objectives')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('logistics/objectives/Index')
                ->has('objectives')
                ->has('periodTypes'));
    }
}
