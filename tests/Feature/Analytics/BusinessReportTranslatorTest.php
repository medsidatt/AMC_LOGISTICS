<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Metrics\MetricResult;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Analytics\Reports\ExecutiveReportTranslator;
use App\Domain\Analytics\Reports\FleetReportTranslator;
use App\Domain\Analytics\Reports\MetricCard;
use App\Domain\Analytics\Reports\OperationsReportTranslator;
use App\Domain\Analytics\Reports\ReportResponse;
use App\Domain\Analytics\Reports\ReportSection;
use App\Domain\Analytics\Reports\ReportSummary;
use App\Domain\Analytics\Reports\ReportView;
use App\Domain\Analytics\Reports\TrendCard;
use App\Domain\Analytics\Trends\Enums\TrendDirection;
use App\Domain\Analytics\Trends\TrendResult;
use Carbon\CarbonImmutable;
use ReflectionClass;
use Tests\TestCase;

/**
 * R4.4 — Business Report Translators turn already-computed metrics + trends into presentation
 * DTOs. They group / order / build cards and calculate NOTHING. These tests pin determinism,
 * grouping, stable ordering, immutability, value-verbatim copying (no calculation), no dropped
 * inputs, and the layer boundary.
 */
class BusinessReportTranslatorTest extends TestCase
{
    private function metric(BusinessKpiId $id, float $value, MetricUnit $unit): MetricResult
    {
        return new MetricResult($id, $value, $unit);
    }

    private function period(): ReportingPeriod
    {
        return new ReportingPeriod(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));
    }

    private function trend(BusinessKpiId $id): TrendResult
    {
        return new TrendResult($id, 130.0, 100.0, 30.0, 30.0, TrendDirection::UP, $this->period(), $this->period());
    }

    private function fleetMetrics(): array
    {
        return [
            $this->metric(BusinessKpiId::FLT_002, 200.0, MetricUnit::TONNES),
            $this->metric(BusinessKpiId::FLT_001, 10.0, MetricUnit::COUNT),
            $this->metric(BusinessKpiId::FLT_003, 0.8, MetricUnit::PERCENT),
            $this->metric(BusinessKpiId::OPS_001, 3000.0, MetricUnit::TONNES), // unmapped for Fleet → "Other"
        ];
    }

    public function test_translation_is_deterministic(): void
    {
        $t = new FleetReportTranslator;

        $this->assertEquals(
            $t->translate($this->fleetMetrics(), [$this->trend(BusinessKpiId::FLT_001)]),
            $t->translate($this->fleetMetrics(), [$this->trend(BusinessKpiId::FLT_001)]),
        );
    }

    public function test_grouping_is_correct(): void
    {
        $response = (new FleetReportTranslator)->translate($this->fleetMetrics(), [$this->trend(BusinessKpiId::FLT_001)]);

        $byKey = [];
        foreach ($response->view->sections as $s) {
            $byKey[$s->key] = $s;
        }

        // FLT_001 + FLT_002 → "Fleet & Capacity"; FLT_003 → "Availability & Usage"; OPS_001 → "Other".
        $this->assertSame(['BI-FLT-001', 'BI-FLT-002'], $this->ids($byKey['fleet']->metrics));
        $this->assertSame(['BI-FLT-003'], $this->ids($byKey['usage']->metrics));
        $this->assertSame(['BI-OPS-001'], $this->ids($byKey['other']->metrics));

        // The FLT_001 trend lands in the same section as its metric.
        $this->assertCount(1, $byKey['fleet']->trends);
        $this->assertSame('BI-FLT-001', $byKey['fleet']->trends[0]->kpiId);
    }

    public function test_ordering_is_stable_by_declared_section_order(): void
    {
        // Input order is FLT_002 before FLT_001, but the section declares FLT_001 first.
        $response = (new FleetReportTranslator)->translate($this->fleetMetrics(), []);

        $fleet = $response->view->sections[0];
        $this->assertSame('fleet', $fleet->key);
        $this->assertSame(['BI-FLT-001', 'BI-FLT-002'], array_map(fn (MetricCard $c): string => $c->kpiId, $fleet->metrics));

        // Sections themselves appear in declared order, "Other" last.
        $this->assertSame(['fleet', 'usage', 'other'], array_map(fn (ReportSection $s): string => $s->key, $response->view->sections));
    }

    public function test_no_input_card_is_dropped(): void
    {
        $response = (new FleetReportTranslator)->translate($this->fleetMetrics(), [$this->trend(BusinessKpiId::FLT_001)]);

        $total = array_sum(array_map(fn (ReportSection $s): int => count($s->metrics), $response->view->sections));
        $this->assertSame(4, $total);                       // all 4 input metrics present
        $this->assertSame(4, $response->view->summary->metricCount);
        $this->assertSame(1, $response->view->summary->trendCount);
    }

    public function test_translators_copy_values_verbatim_and_never_calculate(): void
    {
        $metric = $this->metric(BusinessKpiId::FLT_002, 200.0, MetricUnit::TONNES);
        $trend = $this->trend(BusinessKpiId::FLT_002);

        $section = (new FleetReportTranslator)->translate([$metric], [$trend])->view->sections[0];

        // The card carries the metric's exact value + unit — no transformation.
        $this->assertSame(200.0, $section->metrics[0]->value);
        $this->assertSame('tonnes', $section->metrics[0]->unit);
        // The trend card carries the trend's exact facts — no recomputation.
        $this->assertSame(30.0, $section->trends[0]->difference);
        $this->assertSame(30.0, $section->trends[0]->percentChange);
        $this->assertSame('up', $section->trends[0]->direction);
    }

    public function test_each_translator_produces_its_own_report_identity(): void
    {
        $this->assertSame('fleet', (new FleetReportTranslator)->translate([], [])->reportKey);
        $this->assertSame('operations', (new OperationsReportTranslator)->translate([], [])->reportKey);
        $this->assertSame('executive', (new ExecutiveReportTranslator)->translate([], [])->reportKey);
        $this->assertSame(ReportResponse::VERSION, (new FleetReportTranslator)->translate([], [])->version);
    }

    public function test_all_report_dtos_are_final_readonly(): void
    {
        foreach ([MetricCard::class, TrendCard::class, ReportSection::class, ReportSummary::class, ReportView::class, ReportResponse::class] as $class) {
            $ref = new ReflectionClass($class);
            $this->assertTrue($ref->isFinal(), "{$class} must be final");
            $this->assertTrue($ref->isReadOnly(), "{$class} must be readonly");
        }
    }

    public function test_a_card_is_immutable_at_runtime(): void
    {
        $card = MetricCard::fromMetric($this->metric(BusinessKpiId::FLT_001, 10.0, MetricUnit::COUNT));

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $card->value = 0.0;
    }

    public function test_translators_have_no_forbidden_dependency(): void
    {
        // Report Translators depend ONLY on Metric/Trend DTOs + their own Report DTOs — never
        // on a calculator, registry class, Read Model, DB, or any Operations layer.
        $forbidden = [
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            '::query(' => 'a query builder',
            'config(' => 'config()',
            'env(' => 'env()',
            'Domain\\Operations' => 'any Operations layer',
            'BusinessMetricCalculator' => 'a Business KPI Calculator',
            'TrendCalculator' => 'a Trend Calculator',
            'BusinessKpiRegistry' => 'the BI registry',
        ];

        foreach ($this->reportFiles() as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }

    /** @param list<MetricCard> $cards @return list<string> the cards' KPI codes, in order */
    private function ids(array $cards): array
    {
        return array_map(fn (MetricCard $c): string => $c->kpiId, $cards);
    }

    /** @return list<string> */
    private function reportFiles(): array
    {
        $files = [];
        $dir = new \RecursiveDirectoryIterator(app_path('Domain/Analytics/Reports'), \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
