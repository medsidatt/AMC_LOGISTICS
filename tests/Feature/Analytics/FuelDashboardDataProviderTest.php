<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Fuel\FuelDashboardDataProvider;
use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;
use App\Models\FleetiDailyRecord;
use App\Models\FuelCardTransaction;
use App\Models\Transporter;
use App\Models\Truck;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The fuel dashboard provider is DESCRIPTIVE composition only: it merges the Fuel and
 * Fleeti-Consumption Read Models into one payload of stored facts and plain sums. It must never
 * emit a KPI id, threshold, target, score, alert, or good/bad classification — that is the frozen
 * KPI catalog's territory, not this provider's.
 */
class FuelDashboardDataProviderTest extends TestCase
{
    use DatabaseTransactions;

    private function freshTruck(): Truck
    {
        return Truck::create([
            'matricule' => 'PVTEST-'.strtoupper(substr(uniqid('', true), -8)),
            'transporter_id' => (int) Transporter::query()->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_composes_descriptive_sections_from_both_read_models(): void
    {
        $truck = $this->freshTruck();
        FuelCardTransaction::create([
            'source' => FuelSource::EDK_CARD->value,
            'transaction_type' => TransactionType::FUEL_RECHARGE->value,
            'truck_id' => $truck->id,
            'transaction_ref' => 'PV-'.uniqid('', true),
            'amount_fcfa' => 210000, 'estimated_litres' => 287.67, 'price_per_litre' => 730,
            'occurred_at' => '2031-06-10 08:00:00',
            'kpi_eligible' => true, 'review_status' => 'NONE',
        ]);
        FleetiDailyRecord::create([
            'truck_id' => $truck->id, 'record_date' => '2031-06-10',
            'kilometers' => 300.0, 'consumed' => 180.0, 'refills_volume' => 250.0, 'refills_count' => 1,
        ]);

        $d = app(FuelDashboardDataProvider::class)
            ->dashboard(new DateTimeImmutable('2031-06-01 00:00:00'), new DateTimeImmutable('2031-06-30 23:59:59'));

        // All required descriptive sections exist.
        foreach (['period', 'totals', 'monthly_spend', 'monthly_consumption', 'by_truck', 'source_distribution', 'review_queue', 'import_history', 'reconciliation'] as $section) {
            $this->assertArrayHasKey($section, $d);
        }

        // Totals are plain sums of the period's stored facts.
        $this->assertSame(210000.0, $d['totals']['spend_fcfa']);
        $this->assertSame(287.67, $d['totals']['estimated_litres']);
        $this->assertSame(180.0, $d['totals']['consumed_litres']);
        $this->assertSame(300.0, $d['totals']['kilometers']);

        // Per-truck row juxtaposes both sources for the same truck.
        $row = collect($d['by_truck'])->firstWhere('truck_id', $truck->id);
        $this->assertSame(210000.0, $row['spend_fcfa']);
        $this->assertSame(180.0, $row['consumed_litres']);

        // Reconciliation juxtaposes monthly series without any verdict field.
        $june = collect($d['reconciliation'])->firstWhere('month', '2031-06');
        $this->assertSame(287.67, $june['edk_estimated_litres']);
        $this->assertSame(180.0, $june['fleeti_consumed_litres']);
        $this->assertTrue($june['both_sources_present']);
    }

    public function test_payload_carries_no_kpi_threshold_or_verdict_vocabulary(): void
    {
        $d = app(FuelDashboardDataProvider::class)
            ->dashboard(new DateTimeImmutable('2031-06-01'), new DateTimeImmutable('2031-06-30'));

        $json = strtolower(json_encode($d));
        foreach (['kpi-', 'bi-', 'threshold', 'target', 'score', 'alert', 'severity', 'good', 'bad', 'warning', 'critical', 'exception'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, "descriptive payload must not contain '{$forbidden}'");
        }
    }

    public function test_analytics_endpoint_is_thin_and_returns_the_payload(): void
    {
        $user = \App\Models\Auth\User::query()->firstOrFail();

        $this->actingAs($user)
            ->get('/fuel/analytics?from=2031-06-01&to=2031-06-30')
            ->assertOk()
            ->assertJsonStructure(['period', 'totals', 'monthly_spend', 'monthly_consumption', 'by_truck', 'source_distribution', 'review_queue', 'import_history', 'reconciliation']);
    }
}
