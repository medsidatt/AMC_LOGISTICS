<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\FuelReadModelInterface;
use App\Domain\Operations\ReadModels\Data\TruckFuelProjection;
use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;
use App\Models\FuelCardTransaction;
use App\Models\Transporter;
use App\Models\Truck;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Fuel Read Model is a PURE projection over the persisted fuel ledger: it sums stored facts
 * (recharge amount, the policy's stored kpi_eligible flag, estimated litres) per truck for a
 * period and derives nothing. It must scope to FUEL_RECHARGE rows attributed to a truck, read the
 * stored kpi_eligible flag verbatim (never re-decide it), and be N+1-free.
 */
class FuelReadModelTest extends TestCase
{
    use DatabaseTransactions;

    /** A fresh isolated active truck (avoids the committed historical fuel data on real trucks). */
    private function freshTruck(): Truck
    {
        return Truck::create([
            'matricule' => 'RMTEST-'.strtoupper(substr(uniqid('', true), -8)),
            'transporter_id' => (int) Transporter::query()->value('id'),
            'is_active' => true,
        ]);
    }

    private function tx(int $truckId, array $over = []): void
    {
        FuelCardTransaction::create(array_merge([
            'source' => FuelSource::EDK_CARD->value,
            'transaction_type' => TransactionType::FUEL_RECHARGE->value,
            'truck_id' => $truckId,
            'transaction_ref' => 'RM-'.uniqid('', true),
            'amount_fcfa' => 210000,
            'estimated_litres' => 287.67,
            'price_per_litre' => 730,
            'occurred_at' => '2026-06-15 10:00:00',
            'kpi_eligible' => true,
            'review_status' => 'NONE',
        ], $over));
    }

    public function test_projects_stored_fuel_facts_per_truck_for_the_period(): void
    {
        $truck = $this->freshTruck();
        // Two eligible recharges + one NOT eligible (still counted in total spend, excluded from KPI spend).
        $this->tx($truck->id, ['occurred_at' => '2026-06-10 08:00:00']);
        $this->tx($truck->id, ['occurred_at' => '2026-06-20 08:00:00']);
        $this->tx($truck->id, ['amount_fcfa' => 150000, 'kpi_eligible' => false, 'occurred_at' => '2026-06-25 08:00:00']);
        // Out of range — must be excluded.
        $this->tx($truck->id, ['occurred_at' => '2026-05-01 08:00:00']);

        $projection = app(FuelReadModelInterface::class)->truckFuelSpend(
            new DateTimeImmutable('2026-06-01 00:00:00'),
            new DateTimeImmutable('2026-06-30 23:59:59'),
        );

        $row = $projection->firstWhere('truckId', $truck->id);
        $this->assertInstanceOf(TruckFuelProjection::class, $row);
        $this->assertSame(3, $row->rechargeCount, 'in-range recharges only');
        $this->assertSame(570000.0, $row->totalSpendFcfa, '210k+210k+150k in range');
        $this->assertSame(420000.0, $row->kpiEligibleSpendFcfa, 'only the stored kpi_eligible rows');
        $this->assertSame('2026-06-25 08:00:00', $row->lastRechargeAt?->format('Y-m-d H:i:s'), 'latest in-range recharge, regardless of eligibility');
    }

    public function test_active_truck_without_fuel_projects_zeroes(): void
    {
        $truck = $this->freshTruck();
        $row = app(FuelReadModelInterface::class)->truckFuelSpend(
            new DateTimeImmutable('2026-06-01 00:00:00'),
            new DateTimeImmutable('2026-06-30 23:59:59'),
        )->firstWhere('truckId', $truck->id);

        $this->assertNotNull($row, 'roster-driven: truck appears even with no fuel');
        $this->assertSame(0, $row->rechargeCount);
        $this->assertSame(0.0, $row->totalSpendFcfa);
        $this->assertNull($row->lastRechargeAt);
    }
}
