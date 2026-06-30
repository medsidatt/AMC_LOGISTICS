<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\RotationCalculatorInterface;
use App\Enums\OperationalParameterKey;
use App\Models\Driver;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Services\OperationalParameterService;
use Carbon\Carbon;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R1.3 inc3 — RotationCalculator aggregation must equal the legacy inline KPI queries
 * (byte-identical) and resolve the fiscal-month start day from the parameter store.
 */
class RotationCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private Truck $truck;
    private Driver $driver;
    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();
        (new OperationalParameterSeeder())->run();
        app(OperationalParameterService::class)->flush();

        $this->truck = Truck::where('is_active', true)->firstOrFail();
        $this->driver = Driver::query()->firstOrFail();
        $this->from = Carbon::parse('2030-01-01')->startOfDay();
        $this->to = Carbon::parse('2030-02-28')->endOfDay();

        foreach ([['2030-01-10', 40, 41], ['2030-01-25', 42, 40], ['2030-02-05', 45, 45]] as [$d, $p, $c]) {
            TransportTracking::create([
                'truck_id' => $this->truck->id, 'driver_id' => $this->driver->id,
                'product' => '8/16', 'base' => 'mr',
                'provider_date' => $d, 'client_date' => $d,
                'provider_net_weight' => $p, 'client_net_weight' => $c,
            ]);
        }
    }

    private function calc(): RotationCalculatorInterface
    {
        return app(RotationCalculatorInterface::class);
    }

    public function test_by_truck_matches_legacy_fleetkpi_query(): void
    {
        $legacy = TransportTracking::query()
            ->select('truck_id', DB::raw('COUNT(*) as rotations'), DB::raw('SUM(client_net_weight) as tonnage'))
            ->whereBetween('client_date', [$this->from, $this->to])
            ->whereNotNull('truck_id')
            ->groupBy('truck_id')->get()->keyBy('truck_id');

        $calc = $this->calc()->byTruck($this->from, $this->to)->keyBy(fn ($d) => $d->truckId);

        $this->assertSame($legacy->keys()->sort()->values()->all(), $calc->keys()->sort()->values()->all());
        foreach ($legacy as $id => $row) {
            $this->assertSame((int) $row->rotations, $calc[$id]->rotations);
            $this->assertEqualsWithDelta((float) $row->tonnage, $calc[$id]->clientTonnage, 0.001);
        }
        $this->assertSame(3, $calc[$this->truck->id]->rotations);
        $this->assertEqualsWithDelta(126.0, $calc[$this->truck->id]->clientTonnage, 0.001);
    }

    public function test_by_driver_matches_legacy_query(): void
    {
        $legacy = TransportTracking::query()
            ->select('driver_id', DB::raw('COUNT(*) as rotations'), DB::raw('SUM(client_net_weight) as tonnage'))
            ->whereBetween('client_date', [$this->from, $this->to])
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')->get()->keyBy('driver_id');

        $calc = $this->calc()->byDriver($this->from, $this->to)->keyBy(fn ($d) => $d->driverId);

        foreach ($legacy as $id => $row) {
            $this->assertSame((int) $row->rotations, $calc[$id]->rotations);
            $this->assertEqualsWithDelta((float) $row->tonnage, $calc[$id]->clientTonnage, 0.001);
        }
    }

    public function test_monthly_tonnage_uses_fiscal_parameter(): void
    {
        $this->calc(); // ensure resolvable
        $months = $this->calc()->monthlyTonnage($this->from)->keyBy(fn ($d) => $d->month);

        // fiscal start day 22: 01-10 → 2030-01 ; 01-25 + 02-05 → 2030-02
        $this->assertSame(1, $months['2030-01']->trips);
        $this->assertSame(2, $months['2030-02']->trips);
    }

    public function test_fleet_totals_match_period_totals(): void
    {
        $totals = $this->calc()->fleetTotals($this->from, $this->to);
        $this->assertSame(3, $totals->trips);
        $this->assertEqualsWithDelta(126.0, $totals->clientTonnage, 0.001);
    }
}
