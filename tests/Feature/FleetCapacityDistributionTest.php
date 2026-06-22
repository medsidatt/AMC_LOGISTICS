<?php

namespace Tests\Feature;

use App\Models\Truck;
use App\Services\FleetCapacityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Capacity-proportional rotation distribution: rot_i = round(target × cap_i / Σcap²).
 * Trucks are in-memory (not persisted); DatabaseTransactions only covers the
 * FleetSetting read for the fallback default.
 */
class FleetCapacityDistributionTest extends TestCase
{
    use DatabaseTransactions;

    private function truck(int $id, float $cap): Truck
    {
        $t = new Truck();
        $t->id = $id;
        $t->capacity_tonnage = $cap;
        return $t;
    }

    public function test_rotations_are_proportional_to_capacity(): void
    {
        // The v2 reference example: 40t→8, 32t→6, 28t→5 (target ≈ 652 t).
        $trucks = collect([$this->truck(1, 40), $this->truck(2, 32), $this->truck(3, 28)]);
        $dist = app(FleetCapacityService::class)->distributeTargetRotations(652.0, $trucks);

        $this->assertSame(8, $dist[1]['rotations']);
        $this->assertSame(6, $dist[2]['rotations']);
        $this->assertSame(5, $dist[3]['rotations']);

        // Per-truck tonnage = rotations × capacity; fleet total reconciles.
        $this->assertSame(320.0, $dist[1]['tons']);
        $this->assertSame(652.0, $dist[1]['tons'] + $dist[2]['tons'] + $dist[3]['tons']);
    }

    public function test_zero_target_yields_no_rotations(): void
    {
        $dist = app(FleetCapacityService::class)->distributeTargetRotations(0.0, collect([$this->truck(1, 40)]));
        $this->assertSame(0, $dist[1]['rotations']);
    }
}
