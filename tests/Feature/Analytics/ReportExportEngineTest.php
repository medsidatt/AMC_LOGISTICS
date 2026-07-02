<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Exports\CsvExportEngine;
use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Exports\ExportDefinition;
use App\Domain\Analytics\Exports\ExportRegistry;
use App\Domain\Analytics\Exports\ExportRequest;
use App\Domain\Analytics\Exports\ExportResult;
use App\Domain\Analytics\Exports\HtmlExportEngine;
use App\Domain\Analytics\Exports\JsonExportEngine;
use App\Domain\Analytics\Reports\MetricCard;
use App\Domain\Analytics\Reports\ReportSection;
use App\Domain\Analytics\Reports\ReportSummary;
use App\Domain\Analytics\Reports\ReportView;
use App\Domain\Analytics\Reports\TrendCard;
use ReflectionClass;
use Tests\TestCase;

/**
 * R5.0 — the Report Export Engine serializes an already-translated ReportView into HTML/CSV/
 * JSON. It calculates nothing and queries nothing. These tests pin registry uniqueness + mime/
 * extension metadata, format routing, deterministic serialization, immutable DTOs, reserved-
 * format deferral, and the layer boundary.
 */
class ReportExportEngineTest extends TestCase
{
    private function reportView(): ReportView
    {
        return new ReportView(
            new ReportSummary('fleet', 'Fleet Report', 1, 1, 1),
            [new ReportSection('fleet', 'Fleet & Capacity',
                [new MetricCard('BI-FLT-001', 10.0, 'count', ['available' => 8])],
                [new TrendCard('BI-FLT-001', 12.0, 10.0, 2.0, 20.0, 'up')],
            )],
        );
    }

    private function request(ExportFormat $format): ExportRequest
    {
        return new ExportRequest($format, $this->reportView());
    }

    // ── Registry ────────────────────────────────────────────────────────────────────

    public function test_registry_formats_are_unique_with_correct_metadata(): void
    {
        $registry = new ExportRegistry;

        $formats = array_map(fn (ExportDefinition $d): string => $d->format()->value, $registry->all());
        $this->assertSame(array_values(array_unique($formats)), $formats);
        $this->assertSame(['html', 'csv', 'json'], $formats);

        $this->assertSame('text/html', $registry->find(ExportFormat::HTML)->mimeType());
        $this->assertSame('text/csv', $registry->find(ExportFormat::CSV)->mimeType());
        $this->assertSame('application/json', $registry->find(ExportFormat::JSON)->mimeType());
        $this->assertSame('html', $registry->find(ExportFormat::HTML)->extension());
        $this->assertSame('csv', $registry->find(ExportFormat::CSV)->extension());
        $this->assertSame('json', $registry->find(ExportFormat::JSON)->extension());
    }

    public function test_reserved_formats_stay_reserved(): void
    {
        $registry = new ExportRegistry;

        $this->assertFalse($registry->has(ExportFormat::PDF));
        $this->assertFalse($registry->has(ExportFormat::EXCEL));

        $this->expectException(\InvalidArgumentException::class);
        $registry->find(ExportFormat::PDF);
    }

    // ── Routing ─────────────────────────────────────────────────────────────────────

    public function test_each_engine_supports_only_its_format(): void
    {
        $registry = new ExportRegistry;

        $this->assertTrue((new HtmlExportEngine($registry))->supports(ExportFormat::HTML));
        $this->assertFalse((new HtmlExportEngine($registry))->supports(ExportFormat::CSV));
        $this->assertTrue((new CsvExportEngine($registry))->supports(ExportFormat::CSV));
        $this->assertTrue((new JsonExportEngine($registry))->supports(ExportFormat::JSON));
    }

    public function test_engine_rejects_a_mismatched_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new HtmlExportEngine(new ExportRegistry))->export($this->request(ExportFormat::CSV));
    }

    // ── Result metadata ─────────────────────────────────────────────────────────────

    public function test_results_carry_correct_mime_extension_and_filename(): void
    {
        $registry = new ExportRegistry;

        $json = (new JsonExportEngine($registry))->export($this->request(ExportFormat::JSON));
        $this->assertSame(ExportFormat::JSON, $json->format);
        $this->assertSame('application/json', $json->mimeType);
        $this->assertSame('json', $json->extension);
        $this->assertSame('fleet.json', $json->filename);

        $csv = (new CsvExportEngine($registry))->export(new ExportRequest(ExportFormat::CSV, $this->reportView(), 'custom'));
        $this->assertSame('custom.csv', $csv->filename);
        $this->assertSame('text/csv', $csv->mimeType);
    }

    // ── Serialization content ───────────────────────────────────────────────────────

    public function test_json_serialization_is_structural(): void
    {
        $result = (new JsonExportEngine(new ExportRegistry))->export($this->request(ExportFormat::JSON));
        $decoded = json_decode($result->content, true);

        $this->assertSame('fleet', $decoded['reportKey']);
        $this->assertSame('BI-FLT-001', $decoded['sections'][0]['metrics'][0]['kpiId']);
        $this->assertSame(10.0, $decoded['sections'][0]['metrics'][0]['value']);
        $this->assertSame('up', $decoded['sections'][0]['trends'][0]['direction']);
    }

    public function test_csv_has_a_header_and_one_row_per_card(): void
    {
        $content = (new CsvExportEngine(new ExportRegistry))->export($this->request(ExportFormat::CSV))->content;
        $lines = explode("\n", $content);

        $this->assertStringStartsWith('reportKey,section,kind,kpiId', $lines[0]);
        $this->assertStringContainsString('metric,BI-FLT-001', $content);
        $this->assertStringContainsString('trend,BI-FLT-001', $content);
        $this->assertCount(3, $lines); // header + 1 metric + 1 trend
    }

    public function test_html_is_a_plain_escaped_fragment(): void
    {
        $content = (new HtmlExportEngine(new ExportRegistry))->export($this->request(ExportFormat::HTML))->content;

        $this->assertStringContainsString('<h1>Fleet Report</h1>', $content);
        $this->assertStringContainsString('<td>BI-FLT-001</td>', $content);
        $this->assertStringNotContainsString('<script', $content);
    }

    // ── Determinism ─────────────────────────────────────────────────────────────────

    public function test_serialization_is_deterministic(): void
    {
        foreach ([new HtmlExportEngine(new ExportRegistry), new CsvExportEngine(new ExportRegistry), new JsonExportEngine(new ExportRegistry)] as $engine) {
            $format = $engine->supports(ExportFormat::HTML) ? ExportFormat::HTML : ($engine->supports(ExportFormat::CSV) ? ExportFormat::CSV : ExportFormat::JSON);
            $this->assertSame(
                $engine->export($this->request($format))->content,
                $engine->export($this->request($format))->content,
            );
        }
    }

    // ── Immutability ────────────────────────────────────────────────────────────────

    public function test_export_dtos_are_final_readonly(): void
    {
        foreach ([ExportDefinition::class, ExportRequest::class, ExportResult::class] as $class) {
            $ref = new ReflectionClass($class);
            $this->assertTrue($ref->isFinal(), "{$class} must be final");
            $this->assertTrue($ref->isReadOnly(), "{$class} must be readonly");
        }

        $result = (new JsonExportEngine(new ExportRegistry))->export($this->request(ExportFormat::JSON));
        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $result->content = 'mutated';
    }

    // ── Architecture boundary ───────────────────────────────────────────────────────

    public function test_exports_have_no_forbidden_dependency(): void
    {
        $forbidden = [
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            '::query(' => 'a query builder',
            'config(' => 'config()',
            'env(' => 'env()',
            'Domain\\Operations' => 'any Operations layer',
            'BusinessKpiRegistry' => 'the Business KPI registry',
            'MetricCalculator' => 'a KPI calculator',
            'TrendCalculator' => 'a trend calculator',
            'ReportTranslator' => 'a report translator',
            'ReadModel' => 'a Read Model',
        ];

        foreach ($this->exportFiles() as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }

    /** @return list<string> */
    private function exportFiles(): array
    {
        $files = [];
        $dir = new \RecursiveDirectoryIterator(app_path('Domain/Analytics/Exports'), \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
