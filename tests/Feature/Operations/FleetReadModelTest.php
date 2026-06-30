<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\FleetReadModelInterface;
use App\Domain\Operations\ReadModels\Data\TruckProjection;
use App\Models\Truck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R1.2 characterization — FleetReadModel must return EXACTLY the active-fleet roster
 * the existing inline `Truck::where('is_active', true)` queries return.
 */
class FleetReadModelTest extends TestCase
{
    use DatabaseTransactions;

    private function rm(): FleetReadModelInterface
    {
        return app(FleetReadModelInterface::class);
    }

    public function test_active_trucks_match_inline_query(): void
    {
        $inlineIds = Truck::where('is_active', true)->orderBy('matricule')->pluck('id')->all();
        $rmIds = $this->rm()->activeTrucks()->map(fn (TruckProjection $t) => $t->id)->all();

        $this->assertSame($inlineIds, $rmIds);
        $this->assertSame(count($inlineIds), $this->rm()->activeTruckCount());
    }

    public function test_active_available_subset_and_capacity_sum_match_inline(): void
    {
        $rm = $this->rm();

        $available = $rm->activeAvailableTrucks();
        foreach ($available as $t) {
            $this->assertTrue($t->isAvailable);
        }
        $this->assertLessThanOrEqual($rm->activeTrucks()->count(), $available->count());

        $inlineCapacity = (float) Truck::where('is_active', true)->where('is_available', true)->sum('capacity_tonnage');
        $this->assertEqualsWithDelta($inlineCapacity, $rm->availableCapacityTonnage(), 0.001);
    }

    public function test_projection_carries_raw_per_truck_values(): void
    {
        $truck = Truck::where('is_active', true)->firstOrFail();
        $truck->update(['capacity_tonnage' => 47, 'target_rotations_per_week' => 5, 'is_available' => true]);

        $projection = $this->rm()->activeTrucks()->firstWhere(fn (TruckProjection $t) => $t->id === $truck->id);

        $this->assertNotNull($projection);
        $this->assertEqualsWithDelta(47.0, $projection->capacityTonnage, 0.001);
        $this->assertSame(5, $projection->targetRotationsPerWeek);
    }

    public function test_projection_is_immutable(): void
    {
        $p = new TruckProjection(1, 'AMC-TEST', 45.0, 3, true, 0.95, 0.98, null);

        $this->expectException(\Error::class);
        $p->capacityTonnage = 99.0; // readonly — must throw
    }
}
