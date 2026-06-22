<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Truck;
use App\Models\TruckAvailabilityWindow;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Availability engine. DatabaseTransactions keeps the dev DB clean. Period
 * 2026-06-15 (Mon) → 2026-06-20 (Sat) = 6 operational days (default calendar).
 */
class AvailabilityServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Carbon $start;
    private Carbon $end;

    protected function setUp(): void
    {
        parent::setUp();
        $this->start = Carbon::parse('2026-06-15');
        $this->end = Carbon::parse('2026-06-20')->endOfDay();
    }

    private function svc(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    private function truck(): Truck
    {
        $t = Truck::where('is_active', true)->firstOrFail();
        $t->update(['capacity_tonnage' => 40, 'availability_factor' => 0.95, 'maintenance_factor' => 0.98]);
        return $t->fresh();
    }

    public function test_falls_back_to_factors_when_no_windows(): void
    {
        $r = $this->svc()->forTruck($this->truck(), $this->start, $this->end, 10.0);

        $this->assertSame('factors', $r['source']);
        $this->assertSame(0, $r['lost_days']);
        // 10 × 0.95 × 0.98 = 9.31
        $this->assertSame(9.31, $r['available_rotations']);
        $this->assertSame(round(10 - 9.31, 2), $r['lost_rotations']);
    }

    public function test_windows_reduce_availability_and_attribute_downtime(): void
    {
        $truck = $this->truck();
        TruckAvailabilityWindow::create([
            'truck_id' => $truck->id,
            'start_at' => '2026-06-16 00:00:00', // Tue
            'end_at' => '2026-06-17 23:59:59',   // Wed → 2 operational days
            'type' => TruckAvailabilityWindow::TYPE_MAINTENANCE,
            'source' => TruckAvailabilityWindow::SOURCE_MANUAL,
        ]);

        $r = $this->svc()->forTruck($truck, $this->start, $this->end, 12.0);

        $this->assertSame('windows', $r['source']);
        $this->assertSame(6, $r['operational_days']);
        $this->assertSame(2, $r['lost_days']);
        $this->assertSame(8.0, $r['available_rotations']);  // 12 × 4/6
        $this->assertSame(4.0, $r['lost_rotations']);       // 12 × 2/6
        $this->assertSame(160.0, $r['downtime_impact']['MAINTENANCE']); // 4 rot × 40 t
        $this->assertSame(67, $r['availability_pct']);      // 320 / 480
    }

    public function test_non_operational_days_in_a_window_are_not_lost(): void
    {
        $truck = $this->truck();
        $end = Carbon::parse('2026-06-21')->endOfDay(); // period now includes the Sunday
        TruckAvailabilityWindow::create([
            'truck_id' => $truck->id,
            'start_at' => '2026-06-21 00:00:00', // Sunday — non-operational, inside period
            'end_at' => '2026-06-21 23:59:59',
            'type' => TruckAvailabilityWindow::TYPE_BREAKDOWN,
        ]);

        $r = $this->svc()->forTruck($truck, $this->start, $end, 12.0);
        $this->assertSame('windows', $r['source']);
        $this->assertSame(6, $r['operational_days']); // Mon–Sat; Sunday excluded
        $this->assertSame(0, $r['lost_days']);          // the Sunday window costs nothing
    }

    public function test_availability_page_renders(): void
    {
        $user = User::query()->permission('fleet-roster-plan')->firstOrFail();

        $this->actingAs($user)
            ->get('/logistics/availability')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('logistics/Availability')
                ->has('fleet')
                ->has('trucks')
                ->has('windows')
                ->has('types'));
    }
}
