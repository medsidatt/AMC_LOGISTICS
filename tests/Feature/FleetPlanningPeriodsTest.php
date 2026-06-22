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
                ->has('objectives'));
    }

    public function test_objective_authoring_page_renders(): void
    {
        $this->actingAs($this->planner())
            ->get('/logistics/objectives/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('logistics/objectives/Create')
                ->has('trucks')
                ->has('periodTypes')
                ->has('planCapacityT'));
    }

    public function test_legacy_fleet_roster_redirects_to_objectives(): void
    {
        $this->actingAs($this->planner())
            ->get('/logistics/fleet-roster')
            ->assertRedirect('/logistics/objectives');
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

    public function test_creating_an_objective_persists_target_and_truck_rest(): void
    {
        $truck = \App\Models\Truck::where('is_active', true)->firstOrFail();

        $this->actingAs($this->planner())
            ->post('/logistics/objectives', [
                'period_type' => 'WEEK',
                'start_date' => '2026-09-07', // any day; resolver canonicalises to Mon–Sat
                'target_tons' => 500,
                'rested_truck_ids' => [$truck->id],
                'notes' => 'merge end-to-end test',
            ])
            ->assertRedirect('/logistics/objectives');

        $objective = \App\Models\FleetObjective::where('period_type', 'WEEK')
            ->whereDate('start_date', '<=', '2026-09-07')
            ->whereDate('end_date', '>=', '2026-09-07')
            ->first();

        $this->assertNotNull($objective, 'objective created');
        $this->assertGreaterThan(0, (int) $objective->target_rotations);
        $this->assertDatabaseHas('truck_rest_windows', [
            'truck_id' => $truck->id,
            'reason' => \App\Models\TruckRestWindow::REASON_SURPLUS_CAPACITY,
        ]);
    }
}
