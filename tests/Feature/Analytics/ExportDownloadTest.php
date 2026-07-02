<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\CommandCenters\BusinessDashboardResponse;
use App\Domain\Analytics\CommandCenters\ExecutiveBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\FleetBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\OperationsBusinessCommandCenter;
use App\Domain\Analytics\Exports\CsvExportEngine;
use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Exports\ExportEngineResolver;
use App\Domain\Analytics\Exports\ExportRegistry;
use App\Domain\Analytics\Exports\ExportRequest;
use App\Domain\Analytics\Exports\HtmlExportEngine;
use App\Domain\Analytics\Exports\JsonExportEngine;
use App\Domain\Analytics\Reports\MetricCard;
use App\Domain\Analytics\Reports\ReportResponse;
use App\Domain\Analytics\Reports\ReportSection;
use App\Domain\Analytics\Reports\ReportSummary;
use App\Domain\Analytics\Reports\ReportView;
use App\Http\Analytics\ExportDownloadResponse;
use App\Http\Analytics\ExportRequestValidator;
use App\Http\Controllers\ExportController;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * R5.1 — the export download boundary exposes the R5.0 engines over HTTP. It orchestrates only
 * (validate → command center → resolve engine → download). These tests pin engine resolution,
 * input rejection, the download filename/mime/content/headers, controller delegation, and the
 * layer boundary. The command center is mocked so the boundary is tested DB-free.
 */
class ExportDownloadTest extends TestCase
{
    private function resolver(): ExportEngineResolver
    {
        return new ExportEngineResolver([
            new HtmlExportEngine(new ExportRegistry),
            new CsvExportEngine(new ExportRegistry),
            new JsonExportEngine(new ExportRegistry),
        ]);
    }

    private function reportView(): ReportView
    {
        return new ReportView(
            new ReportSummary('executive', 'Executive Report', 1, 1, 0),
            [new ReportSection('headline', 'Headline', [new MetricCard('BI-OPS-001', 3000.0, 'tonnes', [])], [])],
        );
    }

    private function controller(ExecutiveBusinessCommandCenter $executive): ExportController
    {
        return new ExportController(
            $executive,
            Mockery::mock(OperationsBusinessCommandCenter::class),
            Mockery::mock(FleetBusinessCommandCenter::class),
            $this->resolver(),
            new ExportRequestValidator(new ExportRegistry),
        );
    }

    // ── Resolver ────────────────────────────────────────────────────────────────────

    public function test_resolver_selects_the_engine_for_each_format(): void
    {
        $resolver = $this->resolver();

        $this->assertInstanceOf(HtmlExportEngine::class, $resolver->resolve(ExportFormat::HTML));
        $this->assertInstanceOf(CsvExportEngine::class, $resolver->resolve(ExportFormat::CSV));
        $this->assertInstanceOf(JsonExportEngine::class, $resolver->resolve(ExportFormat::JSON));
    }

    public function test_resolver_rejects_an_unhandled_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver()->resolve(ExportFormat::PDF);
    }

    // ── Validator ───────────────────────────────────────────────────────────────────

    public function test_validator_accepts_known_report_and_active_format(): void
    {
        $validator = new ExportRequestValidator(new ExportRegistry);

        $this->assertSame('fleet', $validator->report('fleet'));
        $this->assertSame(ExportFormat::JSON, $validator->format('json'));
        $this->assertSame(ExportFormat::CSV, $validator->format('CSV')); // case-insensitive
    }

    public function test_validator_rejects_an_unknown_report(): void
    {
        $this->expectException(NotFoundHttpException::class);
        (new ExportRequestValidator(new ExportRegistry))->report('finance');
    }

    public function test_validator_rejects_an_invalid_or_reserved_format(): void
    {
        $validator = new ExportRequestValidator(new ExportRegistry);

        try {
            $validator->format('xml');
            $this->fail('expected an invalid format to be rejected');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        // Reserved formats parse but have no registry definition → rejected.
        $this->expectException(NotFoundHttpException::class);
        $validator->format('pdf');
    }

    public function test_validator_sanitizes_the_filename(): void
    {
        $validator = new ExportRequestValidator(new ExportRegistry);

        $this->assertNull($validator->filename(null));
        $this->assertNull($validator->filename('...'));                   // only unsafe chars → null
        $this->assertSame('etcpasswd', $validator->filename('../../etc/passwd')); // path chars stripped
        $this->assertSame('myreport', $validator->filename('my report!'));
        $this->assertSame('Report_2026-Q3', $validator->filename('Report_2026-Q3')); // safe chars kept as-is
    }

    // ── Download response ───────────────────────────────────────────────────────────

    public function test_download_response_carries_content_mime_and_headers(): void
    {
        $result = (new JsonExportEngine(new ExportRegistry))->export(new ExportRequest(ExportFormat::JSON, $this->reportView()));

        $response = ExportDownloadResponse::fromResult($result);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="executive.json"', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('"reportKey": "executive"', $response->getContent());
    }

    // ── Controller (delegation, DB-free) ────────────────────────────────────────────

    public function test_controller_delegates_and_returns_a_download(): void
    {
        $dashboard = new BusinessDashboardResponse(new ReportResponse('executive', $this->reportView()), new DateTimeImmutable('2026-07-01T12:00:00+00:00'));
        $exec = Mockery::mock(ExecutiveBusinessCommandCenter::class);
        $exec->shouldReceive('dashboard')->once()->andReturn($dashboard);

        $response = $this->controller($exec)->executive(Request::create('/business/executive/export/json'), 'json');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="executive.json"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('BI-OPS-001', $response->getContent());
    }

    public function test_controller_honours_a_custom_filename(): void
    {
        $dashboard = new BusinessDashboardResponse(new ReportResponse('executive', $this->reportView()), new DateTimeImmutable('2026-07-01T12:00:00+00:00'));
        $exec = Mockery::mock(ExecutiveBusinessCommandCenter::class);
        $exec->shouldReceive('dashboard')->once()->andReturn($dashboard);

        $response = $this->controller($exec)->executive(Request::create('/x?filename=q3-report'), 'csv');

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="q3-report.csv"', $response->headers->get('Content-Disposition'));
    }

    // ── Architecture boundary ───────────────────────────────────────────────────────

    public function test_export_boundary_has_no_forbidden_dependency(): void
    {
        $forbidden = [
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            '::query(' => 'a query builder',
            'Domain\\Operations' => 'any Operations layer',
            'ReadModel' => 'a Read Model',
            'MetricCalculator' => 'a KPI calculator',
            'TrendCalculator' => 'a trend calculator',
            'ReportTranslator' => 'a report translator',
        ];

        $files = [
            app_path('Http/Controllers/ExportController.php'),
            app_path('Http/Analytics/ExportRequestValidator.php'),
            app_path('Http/Analytics/ExportDownloadResponse.php'),
            app_path('Domain/Analytics/Exports/ExportEngineResolver.php'),
        ];

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }
}
