<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\CommandCenters\AbstractBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\BusinessDashboardResponse;
use App\Domain\Analytics\CommandCenters\ExecutiveBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\FleetBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\OperationsBusinessCommandCenter;
use App\Domain\Analytics\Metrics\Contracts\BusinessMetricCalculator;
use App\Domain\Analytics\Metrics\MetricResult;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\BusinessKpiRegistry;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Analytics\Reports\Contracts\BusinessReportTranslator;
use App\Domain\Analytics\Reports\MetricCard;
use App\Domain\Analytics\Reports\ReportResponse;
use App\Domain\Analytics\Reports\ReportSection;
use App\Domain\Analytics\Reports\ReportSummary;
use App\Domain\Analytics\Reports\ReportView;
use App\Domain\Analytics\Reports\TrendCard;
use App\Domain\Analytics\Trends\Contracts\TrendCalculator;
use App\Domain\Analytics\Trends\Enums\TrendDirection;
use App\Domain\Analytics\Trends\TrendResult;
use App\Http\Controllers\BusinessDashboardController;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Inertia\Response;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * R4.5 — a BI Command Center orchestrates the frozen BI pipeline (registry → KPI calculators
 * → trend calculator → report translator → response) and contains ZERO business logic. These
 * tests pin the orchestration order + single invocation of each collaborator, the immutable
 * response, its serialization, the delegating controller, and the layer boundary.
 */
class BusinessCommandCenterTest extends TestCase
{
    private const NOW = '2026-07-01T12:00:00+00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(): ReportResponse
    {
        return new ReportResponse('fleet', new ReportView(new ReportSummary('fleet', 'Fleet', 0, 0, 0), []));
    }

    private function singleKpiCenter(BusinessKpiRegistry $registry, array $calculators, TrendCalculator $trends, BusinessReportTranslator $translator): AbstractBusinessCommandCenter
    {
        return new class($registry, $calculators, $trends, $translator) extends AbstractBusinessCommandCenter
        {
            protected function reportedKpis(): array
            {
                return [BusinessKpiId::FLT_001];
            }
        };
    }

    public function test_orchestrates_the_pipeline_invoking_each_collaborator_the_expected_times(): void
    {
        Carbon::setTestNow(self::NOW);

        // The real (pure) registry drives which KPIs are trended: FLT_001 is active + trended,
        // so the command center computes it for the current AND previous period, then trends.
        $registry = new BusinessKpiRegistry;
        $current = new MetricResult(BusinessKpiId::FLT_001, 12.0, MetricUnit::COUNT);
        $previous = new MetricResult(BusinessKpiId::FLT_001, 10.0, MetricUnit::COUNT);
        $window = new ReportingPeriod(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));
        $trend = new TrendResult(BusinessKpiId::FLT_001, 12.0, 10.0, 2.0, 20.0, TrendDirection::UP, $window, $window);
        $report = $this->report();

        $calc = Mockery::mock(BusinessMetricCalculator::class);
        $calc->shouldReceive('supports')->andReturn(true);
        $calc->shouldReceive('compute')->twice()->andReturn($current, $previous); // current + previous period
        $trends = Mockery::mock(TrendCalculator::class);
        $trends->shouldReceive('compare')->once()->andReturn($trend);            // trended → once
        $translator = Mockery::mock(BusinessReportTranslator::class);
        $translator->shouldReceive('translate')->once()->with([$current], [$trend])->andReturn($report); // once

        $response = $this->singleKpiCenter($registry, [$calc], $trends, $translator)->dashboard();

        $this->assertInstanceOf(BusinessDashboardResponse::class, $response);
        $this->assertSame($report, $response->report());
    }

    public function test_response_is_final_readonly_and_immutable(): void
    {
        $ref = new ReflectionClass(BusinessDashboardResponse::class);
        $this->assertTrue($ref->isFinal());
        $this->assertTrue($ref->isReadOnly());

        $response = new BusinessDashboardResponse($this->report(), new DateTimeImmutable(self::NOW));
        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $response->version = 99;
    }

    public function test_response_serializes_to_a_presentation_ready_array(): void
    {
        $view = new ReportView(
            new ReportSummary('fleet', 'Fleet Report', 1, 1, 1),
            [new ReportSection('fleet', 'Fleet & Capacity',
                [new MetricCard('BI-FLT-001', 10.0, 'count', ['available' => 8])],
                [new TrendCard('BI-FLT-001', 12.0, 10.0, 2.0, 20.0, 'up')],
            )],
        );
        $response = new BusinessDashboardResponse(new ReportResponse('fleet', $view), new DateTimeImmutable(self::NOW));

        $array = $response->toArray();

        $this->assertSame('fleet', $array['reportKey']);
        $this->assertSame(BusinessDashboardResponse::VERSION, $array['version']);
        $this->assertSame('2026-07-01T12:00:00+00:00', $array['generatedAt']);
        $this->assertSame(1, $array['summary']['metricCount']);
        $this->assertSame('BI-FLT-001', $array['sections'][0]['metrics'][0]['kpiId']);
        $this->assertSame(10.0, $array['sections'][0]['metrics'][0]['value']);
        $this->assertSame('up', $array['sections'][0]['trends'][0]['direction']);
    }

    public function test_controller_delegates_each_report_to_its_command_center(): void
    {
        $response = new BusinessDashboardResponse($this->report(), new DateTimeImmutable(self::NOW));

        $exec = Mockery::mock(ExecutiveBusinessCommandCenter::class);
        $exec->shouldReceive('dashboard')->once()->andReturn($response);
        $ops = Mockery::mock(OperationsBusinessCommandCenter::class);
        $fleet = Mockery::mock(FleetBusinessCommandCenter::class);

        $controller = new BusinessDashboardController($exec, $ops, $fleet);

        $this->assertInstanceOf(Response::class, $controller->executive());
    }

    public function test_controller_only_renders_inertia(): void
    {
        $contents = file_get_contents(app_path('Http/Controllers/BusinessDashboardController.php'));

        $this->assertStringContainsString('Inertia::render', $contents);
        foreach (['Models', 'DB::', 'Illuminate\\Database', '::query(', 'Calculator', 'ReadModel', 'Registry'] as $needle) {
            $this->assertStringNotContainsString($needle, $contents, "controller must not reference {$needle}");
        }
    }

    public function test_command_centers_have_no_forbidden_dependency(): void
    {
        $forbidden = [
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            '::query(' => 'a query builder',
            'config(' => 'config()',
            'env(' => 'env()',
            'Domain\\Operations' => 'any Operations layer',
            'ReadModelInterface' => 'a Read Model',
        ];

        foreach ($this->commandCenterFiles() as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }

    /** @return list<string> */
    private function commandCenterFiles(): array
    {
        $files = [];
        $dir = new \RecursiveDirectoryIterator(app_path('Domain/Analytics/CommandCenters'), \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
