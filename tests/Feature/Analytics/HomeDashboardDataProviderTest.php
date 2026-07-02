<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Home\HomeDashboardDataProvider;
use App\Domain\Analytics\Metrics\FleetMetricsCalculator;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Models\Transporter;
use App\Models\Truck;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Home dashboard provider is DESCRIPTIVE composition only: it reuses the existing BI metric
 * calculators (the owners) to surface an already-registered set of Business KPIs for the caller's
 * period. It must own no formula, gate strictly to registered KPIs, exclude every BLOCKED metric,
 * and surface exactly what the owning calculator computes (no re-derivation).
 */
class HomeDashboardDataProviderTest extends TestCase
{
    use DatabaseTransactions;

    private function period(): ReportingPeriod
    {
        // Far-future window isolates period-scoped metrics; point-in-time metrics ignore it.
        return new ReportingPeriod(
            CarbonImmutable::parse('2031-06-01')->startOfDay(),
            CarbonImmutable::parse('2031-06-30')->endOfDay(),
        );
    }

    public function test_headline_reuses_registered_owned_kpis_only(): void
    {
        $payload = app(HomeDashboardDataProvider::class)->headline($this->period());

        $this->assertSame(
            ['BI-FLT-001', 'BI-FLT-002', 'BI-FLT-003', 'BI-FLT-004', 'BI-OPS-001', 'BI-OPS-003', 'BI-OPS-004', 'BI-OPS-005', 'BI-PRD-001'],
            array_keys($payload),
            'only the already-registered, owned headline KPIs are surfaced',
        );

        foreach ($payload as $id => $m) {
            $this->assertSame($id, $m['id']);
            $this->assertArrayHasKey('label', $m);
            $this->assertArrayHasKey('unit', $m);
            $this->assertIsFloat($m['value']);
            $this->assertIsArray($m['components']);
        }
    }

    public function test_excludes_blocked_metrics_and_carries_no_verdict_vocabulary(): void
    {
        $payload = app(HomeDashboardDataProvider::class)->headline($this->period());

        // Reserved / dormant / unowned metrics must never appear (no owner → BLOCKED, not fabricated).
        $this->assertArrayNotHasKey('BI-OPS-050', $payload, 'driver count is RESERVED');
        $this->assertArrayNotHasKey('BI-OPS-051', $payload, 'production-target % is RESERVED');

        $json = strtolower(json_encode($payload));
        foreach (['fuel', 'yield', 'ranking', 'discipline', 'threshold', 'score', 'severity', 'good', 'bad'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json, "descriptive payload must not contain '{$forbidden}'");
        }
    }

    public function test_value_is_exactly_what_the_owning_calculator_computes(): void
    {
        Truck::create([
            'matricule' => 'HDTEST-'.strtoupper(substr(uniqid('', true), -8)),
            'transporter_id' => (int) Transporter::query()->value('id'),
            'is_active' => true,
        ]);

        $period = $this->period();
        $payload = app(HomeDashboardDataProvider::class)->headline($period);
        $ownerValue = app(FleetMetricsCalculator::class)->compute(BusinessKpiId::FLT_001, $period)->value();

        // The provider surfaces the owner's number verbatim — no re-implementation of the formula.
        $this->assertSame($ownerValue, $payload['BI-FLT-001']['value']);
        $this->assertGreaterThanOrEqual(1.0, $payload['BI-FLT-001']['value'], 'the fresh active truck is counted by the owner');
        $this->assertArrayHasKey('available', $payload['BI-FLT-003']['components']);
        $this->assertArrayHasKey('total', $payload['BI-FLT-003']['components']);
    }
}
