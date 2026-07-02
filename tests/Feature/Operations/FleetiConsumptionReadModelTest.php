<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\FleetiConsumptionReadModelInterface;
use App\Models\FleetiDailyRecord;
use App\Models\Transporter;
use App\Models\Truck;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Fleeti Consumption Read Model is a PURE projection over persisted telemetry: per-truck and
 * per-month sums/counts of stored values, no ratios (no L/100km), no thresholds. Period-scoped and
 * roster-driven (active trucks appear with zeroes when they have no telemetry).
 */
class FleetiConsumptionReadModelTest extends TestCase
{
    use DatabaseTransactions;

    private function freshTruck(): Truck
    {
        return Truck::create([
            'matricule' => 'FCTEST-'.strtoupper(substr(uniqid('', true), -8)),
            'transporter_id' => (int) Transporter::query()->value('id'),
            'is_active' => true,
        ]);
    }

    private function day(int $truckId, string $date, float $km, float $consumed, float $refills = 0, int $refillsCount = 0): void
    {
        FleetiDailyRecord::create([
            'truck_id' => $truckId, 'record_date' => $date,
            'kilometers' => $km, 'consumed' => $consumed,
            'refills_volume' => $refills, 'refills_count' => $refillsCount,
        ]);
    }

    public function test_projects_per_truck_telemetry_sums_for_the_period(): void
    {
        $truck = $this->freshTruck();
        // Far-future period → isolated from committed historical data.
        $this->day($truck->id, '2031-03-01', 150.5, 90.0, 200.0, 1);
        $this->day($truck->id, '2031-03-02', 200.0, 110.0);
        $this->day($truck->id, '2031-04-01', 999.0, 999.0); // out of range

        $row = app(FleetiConsumptionReadModelInterface::class)
            ->truckConsumption(new DateTimeImmutable('2031-03-01'), new DateTimeImmutable('2031-03-31'))
            ->firstWhere('truckId', $truck->id);

        $this->assertSame(2, $row->recordedDays);
        $this->assertSame(350.5, $row->kilometers);
        $this->assertSame(200.0, $row->consumedLitres);
        $this->assertSame(1, $row->refillsCount);
        $this->assertSame(200.0, $row->refillsVolume);
        $this->assertSame('2031-03-02', $row->lastRecordDate?->format('Y-m-d'));
    }

    public function test_truck_without_telemetry_projects_zeroes(): void
    {
        $truck = $this->freshTruck();

        $row = app(FleetiConsumptionReadModelInterface::class)
            ->truckConsumption(new DateTimeImmutable('2031-03-01'), new DateTimeImmutable('2031-03-31'))
            ->firstWhere('truckId', $truck->id);

        $this->assertNotNull($row, 'roster-driven: active truck appears even with no telemetry');
        $this->assertSame(0, $row->recordedDays);
        $this->assertSame(0.0, $row->kilometers);
        $this->assertNull($row->lastRecordDate);
    }

    public function test_monthly_consumption_groups_by_calendar_month_oldest_first(): void
    {
        $truck = $this->freshTruck();
        $this->day($truck->id, '2031-05-10', 100.0, 60.0, 80.0);
        $this->day($truck->id, '2031-05-20', 120.0, 70.0);
        $this->day($truck->id, '2031-06-01', 50.0, 30.0);

        $months = app(FleetiConsumptionReadModelInterface::class)
            ->monthlyConsumption(new DateTimeImmutable('2031-05-01'), new DateTimeImmutable('2031-06-30'));

        $this->assertSame(['2031-05', '2031-06'], $months->pluck('month')->all());
        $may = $months->firstWhere('month', '2031-05');
        $this->assertSame(2, $may->recordedDays);
        $this->assertSame(220.0, $may->kilometers);
        $this->assertSame(130.0, $may->consumedLitres);
        $this->assertSame(80.0, $may->refillsVolume);
    }
}
