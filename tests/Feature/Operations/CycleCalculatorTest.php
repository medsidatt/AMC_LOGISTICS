<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Calculations\CycleCalculator;
use Tests\TestCase;

/**
 * R1.3 inc3 — CycleCalculator owns averageCycleDays (lifted verbatim from the
 * byte-identical Driver/Truck KPI private methods). Pure; characterized by snapshot.
 */
class CycleCalculatorTest extends TestCase
{
    private function calc(): CycleCalculator
    {
        return new CycleCalculator();
    }

    private function row(string $provider, string $client): object
    {
        return (object) ['provider_date' => $provider, 'client_date' => $client];
    }

    public function test_average_cycle_days_snapshot(): void
    {
        // deltas: (client 01-02 → provider 01-05)=3 ; (client 01-06 → provider 01-10)=4 ; avg=3.5
        $rotations = collect([
            $this->row('2030-01-01', '2030-01-02'),
            $this->row('2030-01-05', '2030-01-06'),
            $this->row('2030-01-10', '2030-01-11'),
        ]);

        $this->assertSame(3.5, $this->calc()->averageCycleDays($rotations));
    }

    public function test_fewer_than_two_rotations_is_null(): void
    {
        $this->assertNull($this->calc()->averageCycleDays(collect([$this->row('2030-01-01', '2030-01-02')])));
        $this->assertNull($this->calc()->averageCycleDays(collect([])));
    }

    public function test_rows_missing_dates_are_skipped(): void
    {
        // Only two fully-dated rows → one delta (01-02 → 01-05) = 3.0
        $rotations = collect([
            $this->row('2030-01-01', '2030-01-02'),
            (object) ['provider_date' => null, 'client_date' => '2030-01-03'],
            $this->row('2030-01-05', '2030-01-06'),
        ]);

        $this->assertSame(3.0, $this->calc()->averageCycleDays($rotations));
    }
}
