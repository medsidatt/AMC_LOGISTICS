<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Driver;
use App\Models\Truck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Transport tracking must never spawn phantom trucks/drivers from placeholder
 * identifiers. DatabaseTransactions keeps the dev DB untouched.
 */
class TransportTrackingIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_placeholder_truck_identifier_is_rejected_without_creating_a_phantom(): void
    {
        $user = User::query()->permission('transport-tracking-create')->firstOrFail();

        $trucksBefore = Truck::count();
        $driversBefore = Driver::count();

        $this->actingAs($user)
            ->post('/transport_tracking/store', [
                'truck_id' => 'N/A',
                'driver_id' => 'Some Driver',
                'product' => '0/3',
                'base' => 'mr',
            ])
            ->assertStatus(422);

        $this->assertSame($trucksBefore, Truck::count(), 'A phantom truck was created from a placeholder plate.');
        $this->assertSame($driversBefore, Driver::count(), 'A phantom driver was created during a rejected request.');
    }
}
