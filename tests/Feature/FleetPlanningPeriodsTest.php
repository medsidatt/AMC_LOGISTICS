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
                ->get("/realisation?mode={$mode}&anchor=2026-06-17")
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
            ->get('/realisation?mode=CUSTOM&start=2026-06-01&end=2026-06-30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('logistics/planning/Weekly')
                ->where('mode', 'CUSTOM')
                ->where('period.start', '2026-06-01')
                ->where('period.end', '2026-06-30'));
    }

    public function test_objective_management_page_renders(): void
    {
        // Objectives moved into the flat Planning workflow (/planning/objectives,
        // operations/planning/Objectives); /logistics/objectives 302-redirects there.
        $this->actingAs($this->planner())
            ->get('/planning/objectives')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/planning/Objectives')
                ->has('objectives'));
    }

    public function test_objective_authoring_page_renders(): void
    {
        // Authoring is commitment-only now: per-truck allocation ('trucks') moved to
        // Planning, replaced by advisory capacity context.
        $this->actingAs($this->planner())
            ->get('/logistics/objectives/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('logistics/objectives/Create')
                ->has('periodTypes')
                ->has('planCapacityT')
                ->has('fleetWeeklyCapacityT')
                ->has('availableTruckCount'));
    }

    public function test_legacy_fleet_roster_redirects_to_planning(): void
    {
        $this->actingAs($this->planner())
            ->get('/logistics/fleet-roster')
            ->assertRedirect('/planning');
    }

    public function test_fleet_settings_renders_without_planning_objectives(): void
    {
        $user = User::query()->permission('fleet-settings-edit')->firstOrFail();

        $this->actingAs($user)
            ->get('/settings/fleet')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/FleetSettings')
                ->has('setting.default_capacity_tonnage')
                ->has('setting.weight_gap_threshold')
                ->missing('monthlyTargets')          // objectives removed from settings
                ->missing('setting.monthly_target_tonnage'));
    }

    public function test_monthly_target_route_is_gone(): void
    {
        $user = User::query()->permission('fleet-settings-edit')->firstOrFail();
        $this->actingAs($user)
            ->post('/settings/fleet/monthly-target', ['year' => 2026, 'month' => 6, 'target_tonnage' => 1000])
            ->assertNotFound(); // route removed (objectives no longer managed in settings)
    }

    public function test_creating_an_objective_persists_commitment(): void
    {
        // Authoring is commitment-only: it stores Period + Target + Notes and
        // redirects to the Planning overview. Truck allocation (rest windows) is no
        // longer set here — it lives in Planning.
        $this->actingAs($this->planner())
            ->post('/logistics/objectives', [
                'period_type' => 'WEEK',
                'start_date' => '2026-09-07', // any day; resolver canonicalises to Mon–Sat
                'target_tons' => 500,
                'notes' => 'merge end-to-end test',
            ])
            ->assertRedirect('/planning');

        $objective = \App\Models\FleetObjective::where('period_type', 'WEEK')
            ->whereDate('start_date', '<=', '2026-09-07')
            ->whereDate('end_date', '>=', '2026-09-07')
            ->first();

        $this->assertNotNull($objective, 'objective created');
        $this->assertGreaterThan(0, (int) $objective->target_rotations);
    }

    public function test_creating_a_duplicate_objective_asks_before_overwriting(): void
    {
        $payload = ['period_type' => 'WEEK', 'start_date' => '2032-09-06', 'target_tons' => 400];

        $this->actingAs($this->planner())->post('/logistics/objectives', $payload)->assertRedirect('/planning');

        $objective = \App\Models\FleetObjective::where('period_type', 'WEEK')
            ->whereDate('start_date', '<=', '2032-09-06')
            ->whereDate('end_date', '>=', '2032-09-06')
            ->firstOrFail();
        $originalTons = (float) $objective->target_tons;

        // Same period, no override → must NOT overwrite; the UI is told to confirm.
        $this->actingAs($this->planner())
            ->from('/planning')
            ->post('/logistics/objectives', [...$payload, 'target_tons' => 999])
            ->assertRedirect('/planning')
            ->assertSessionHas('objectiveConflict');

        $this->assertSame($originalTons, (float) $objective->fresh()->target_tons, 'must not overwrite without override');

        // With override → the replacement is applied.
        $this->actingAs($this->planner())
            ->post('/logistics/objectives', [...$payload, 'target_tons' => 999, 'override' => true])
            ->assertRedirect('/planning');

        $this->assertSame(999.0, (float) $objective->fresh()->target_tons);
    }
}
