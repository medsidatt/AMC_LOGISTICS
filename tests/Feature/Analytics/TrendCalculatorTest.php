<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Metrics\MetricResult;
use App\Domain\Analytics\Metrics\ReportingPeriod;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Analytics\Trends\Enums\TrendDirection;
use App\Domain\Analytics\Trends\HistoryPoint;
use App\Domain\Analytics\Trends\HistorySeries;
use App\Domain\Analytics\Trends\MovementTrendCalculator;
use App\Domain\Analytics\Trends\ReportingPeriodRange;
use App\Domain\Analytics\Trends\TrendResult;
use Carbon\CarbonImmutable;
use ReflectionClass;
use Tests\TestCase;

/**
 * R4.3 — Trend Calculators measure the movement of an already-computed BI metric between two
 * periods (difference, percent change, direction). They never recompute a metric or touch data.
 * These tests pin the arithmetic, the up/down/stable detection, zero-previous handling,
 * immutability, determinism, and the layer boundary.
 */
class TrendCalculatorTest extends TestCase
{
    private function period(string $from, string $to): ReportingPeriod
    {
        return new ReportingPeriod(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    private function range(): ReportingPeriodRange
    {
        return new ReportingPeriodRange(
            $this->period('2026-06-01', '2026-06-30'),
            $this->period('2026-05-01', '2026-05-31'),
        );
    }

    private function metric(float $value): MetricResult
    {
        return new MetricResult(BusinessKpiId::OPS_001, $value, MetricUnit::TONNES);
    }

    private function calc(): MovementTrendCalculator
    {
        return new MovementTrendCalculator;
    }

    public function test_difference_and_percentage_are_computed(): void
    {
        $trend = $this->calc()->compare($this->metric(120.0), $this->metric(100.0), $this->range());

        $this->assertSame(BusinessKpiId::OPS_001, $trend->kpiId());
        $this->assertSame(120.0, $trend->currentValue());
        $this->assertSame(100.0, $trend->previousValue());
        $this->assertSame(20.0, $trend->difference());
        $this->assertEqualsWithDelta(20.0, $trend->percentChange(), 1e-9);
    }

    public function test_up_detection(): void
    {
        $this->assertSame(TrendDirection::UP, $this->calc()->compare($this->metric(120.0), $this->metric(100.0), $this->range())->direction());
    }

    public function test_down_detection(): void
    {
        $trend = $this->calc()->compare($this->metric(80.0), $this->metric(100.0), $this->range());

        $this->assertSame(TrendDirection::DOWN, $trend->direction());
        $this->assertSame(-20.0, $trend->difference());
        $this->assertEqualsWithDelta(-20.0, $trend->percentChange(), 1e-9);
    }

    public function test_stable_detection(): void
    {
        $trend = $this->calc()->compare($this->metric(100.0), $this->metric(100.0), $this->range());

        $this->assertSame(TrendDirection::STABLE, $trend->direction());
        $this->assertSame(0.0, $trend->difference());
        $this->assertSame(0.0, $trend->percentChange());
    }

    public function test_zero_previous_value_handling(): void
    {
        // Growth from zero: difference is real, percent is undefined → 0.0, direction UP.
        $fromZero = $this->calc()->compare($this->metric(5.0), $this->metric(0.0), $this->range());
        $this->assertSame(5.0, $fromZero->difference());
        $this->assertSame(0.0, $fromZero->percentChange());
        $this->assertSame(TrendDirection::UP, $fromZero->direction());

        // Zero to zero is stable.
        $zeroToZero = $this->calc()->compare($this->metric(0.0), $this->metric(0.0), $this->range());
        $this->assertSame(TrendDirection::STABLE, $zeroToZero->direction());
        $this->assertSame(0.0, $zeroToZero->percentChange());
    }

    public function test_trend_uses_the_latest_two_points_of_a_series(): void
    {
        $series = new HistorySeries(BusinessKpiId::OPS_001, [
            new HistoryPoint($this->period('2026-04-01', '2026-04-30'), 90.0),
            new HistoryPoint($this->period('2026-05-01', '2026-05-31'), 100.0),
            new HistoryPoint($this->period('2026-06-01', '2026-06-30'), 130.0),
        ]);

        $trend = $this->calc()->trend($series);

        $this->assertSame(130.0, $trend->currentValue());
        $this->assertSame(100.0, $trend->previousValue()); // second-latest, not the first
        $this->assertSame(30.0, $trend->difference());
        $this->assertSame(TrendDirection::UP, $trend->direction());
    }

    public function test_series_shorter_than_two_points_is_rejected(): void
    {
        $series = new HistorySeries(BusinessKpiId::OPS_001, [new HistoryPoint($this->period('2026-06-01', '2026-06-30'), 100.0)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->calc()->trend($series);
    }

    public function test_comparing_two_different_kpis_is_rejected(): void
    {
        $current = new MetricResult(BusinessKpiId::OPS_001, 100.0, MetricUnit::TONNES);
        $previous = new MetricResult(BusinessKpiId::FLT_001, 10.0, MetricUnit::COUNT);

        $this->expectException(\InvalidArgumentException::class);
        $this->calc()->compare($current, $previous, $this->range());
    }

    public function test_is_deterministic(): void
    {
        $a = $this->calc()->compare($this->metric(120.0), $this->metric(100.0), $this->range());
        $b = $this->calc()->compare($this->metric(120.0), $this->metric(100.0), $this->range());

        $this->assertEquals($a, $b);
    }

    public function test_trend_result_is_final_readonly_and_immutable(): void
    {
        $ref = new ReflectionClass(TrendResult::class);
        $this->assertTrue($ref->isFinal());
        $this->assertTrue($ref->isReadOnly());

        $trend = $this->calc()->compare($this->metric(120.0), $this->metric(100.0), $this->range());

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $trend->difference = 0.0;
    }

    public function test_trends_have_no_forbidden_dependency(): void
    {
        // Trend calculators depend ONLY on Metric DTOs/interfaces, ReportingPeriod, BI enums,
        // and their own Trend DTOs — never on any Operations layer, DB, or config.
        $forbidden = [
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            '::query(' => 'a query builder',
            'config(' => 'config()',
            'env(' => 'env()',
            'Domain\\Operations' => 'any Operations layer (Read Models, Calculators, Intelligence, Events, Translators, Command Centers)',
        ];

        foreach ($this->trendFiles() as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }

    /** @return list<string> */
    private function trendFiles(): array
    {
        $files = [];
        $dir = new \RecursiveDirectoryIterator(app_path('Domain/Analytics/Trends'), \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
