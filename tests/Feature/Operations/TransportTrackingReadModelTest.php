<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Models\Driver;
use App\Models\TransportTracking;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R1.2 characterization — TransportTrackingReadModel must return EXACTLY the data the
 * existing inline aggregations produce. Fixtures live in an isolated far-future period
 * (2030) so the asserts are deterministic and no live data interferes.
 *
 * DatabaseTransactions keeps the dev DB clean (no RefreshDatabase).
 */
class TransportTrackingReadModelTest extends TestCase
{
    use DatabaseTransactions;

    private Truck $truck;
    private Driver $driver;
    private Carbon $from;
    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truck = Truck::where('is_active', true)->firstOrFail();
        $this->driver = Driver::query()->firstOrFail();
        $this->from = Carbon::parse('2030-01-01')->startOfDay();
        $this->to = Carbon::parse('2030-02-28')->endOfDay();

        // A: 2030-01-10 (DAY 10 < 22 → 2030-01) | B: 2030-01-25 (>=22 → 2030-02) | C: 2030-02-05 (<22 → 2030-02)
        $rows = [
            ['2030-01-10', 40, 41],
            ['2030-01-25', 42, 40],
            ['2030-02-05', 45, 45],
        ];
        foreach ($rows as [$date, $prov, $client]) {
            TransportTracking::create([
                'truck_id' => $this->truck->id,
                'driver_id' => $this->driver->id,
                'product' => '8/16',
                'base' => 'mr',
                'provider_date' => $date,
                'client_date' => $date,
                'provider_net_weight' => $prov,
                'client_net_weight' => $client,
            ]);
        }
    }

    private function rm(): TransportTrackingReadModelInterface
    {
        return app(TransportTrackingReadModelInterface::class);
    }

    public function test_aggregate_by_truck_matches_inline_query(): void
    {
        $inline = TransportTracking::query()
            ->select('truck_id', DB::raw('COUNT(*) as rotations'), DB::raw('SUM(client_net_weight) as tonnage'))
            ->whereBetween('client_date', [$this->from, $this->to])
            ->whereNotNull('truck_id')
            ->groupBy('truck_id')
            ->get()
            ->keyBy('truck_id');

        $rm = $this->rm()->aggregateByTruck($this->from, $this->to)->keyBy(fn ($d) => $d->truckId);

        $this->assertSame($inline->keys()->sort()->values()->all(), $rm->keys()->sort()->values()->all());
        foreach ($inline as $truckId => $row) {
            $this->assertSame((int) $row->rotations, $rm[$truckId]->rotations);
            $this->assertEqualsWithDelta((float) $row->tonnage, $rm[$truckId]->clientTonnage, 0.001);
        }

        // Deterministic fixture values for our truck.
        $mine = $rm[$this->truck->id];
        $this->assertSame(3, $mine->rotations);
        $this->assertEqualsWithDelta(126.0, $mine->clientTonnage, 0.001);   // 41+40+45
        $this->assertEqualsWithDelta(127.0, $mine->providerTonnage, 0.001); // 40+42+45
        $this->assertEqualsWithDelta(-1.0, $mine->gapTonnage, 0.001);
    }

    public function test_aggregate_by_driver_matches_inline_query(): void
    {
        $inline = TransportTracking::query()
            ->select('driver_id', DB::raw('COUNT(*) as rotations'), DB::raw('SUM(client_net_weight) as tonnage'))
            ->whereBetween('client_date', [$this->from, $this->to])
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        $rm = $this->rm()->aggregateByDriver($this->from, $this->to)->keyBy(fn ($d) => $d->driverId);

        $this->assertSame($inline->keys()->sort()->values()->all(), $rm->keys()->sort()->values()->all());
        $this->assertSame(3, $rm[$this->driver->id]->rotations);
        $this->assertEqualsWithDelta(126.0, $rm[$this->driver->id]->clientTonnage, 0.001);
    }

    public function test_period_totals_match_inline_sums(): void
    {
        $totals = $this->rm()->periodTotals($this->from, $this->to);

        $this->assertSame(3, $totals->trips);
        $this->assertEqualsWithDelta(127.0, $totals->providerTonnage, 0.001);
        $this->assertEqualsWithDelta(126.0, $totals->clientTonnage, 0.001);
        $this->assertEqualsWithDelta(-1.0, $totals->gapTonnage, 0.001);
    }

    public function test_monthly_tonnage_matches_inline_fiscal_grouping(): void
    {
        $inline = TransportTracking::query()
            ->selectRaw("CASE WHEN DAY(client_date) >= 22 THEN DATE_FORMAT(DATE_ADD(client_date, INTERVAL 1 MONTH), '%Y-%m') ELSE DATE_FORMAT(client_date, '%Y-%m') END as ym, SUM(client_net_weight) as client, COUNT(*) as trips")
            ->where('client_date', '>=', $this->from)
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $rm = $this->rm()->monthlyTonnage(22, $this->from)->keyBy(fn ($d) => $d->month);

        foreach ($inline as $ym => $row) {
            $this->assertArrayHasKey($ym, $rm->all(), "month {$ym}");
            $this->assertSame((int) $row->trips, $rm[$ym]->trips);
            $this->assertEqualsWithDelta((float) $row->client, $rm[$ym]->clientTonnage, 0.001);
        }

        // Fiscal boundary: A → 2030-01 (1 trip), B+C → 2030-02 (2 trips).
        $this->assertSame(1, $rm['2030-01']->trips);
        $this->assertSame(2, $rm['2030-02']->trips);
        $this->assertEqualsWithDelta(85.0, $rm['2030-02']->clientTonnage, 0.001); // 40+45
    }
}
