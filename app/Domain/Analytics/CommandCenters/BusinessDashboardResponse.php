<?php

namespace App\Domain\Analytics\CommandCenters;

use App\Domain\Analytics\Reports\MetricCard;
use App\Domain\Analytics\Reports\ReportResponse;
use App\Domain\Analytics\Reports\ReportSection;
use App\Domain\Analytics\Reports\TrendCard;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * The immutable, presentation-ready response of a BI Command Center. It wraps the translator's
 * {@see ReportResponse} and adds only response metadata (generation time + schema version).
 *
 * `toArray()` maps the already-built report view value objects to primitive arrays for
 * Inertia — pure structural serialization, no calculation and no value formatting.
 *
 * @phpstan-consistent-constructor
 */
final readonly class BusinessDashboardResponse
{
    public const VERSION = 1;

    public function __construct(
        private ReportResponse $report,
        private DateTimeImmutable $generatedAt,
        private int $version = self::VERSION,
    ) {}

    public function report(): ReportResponse
    {
        return $this->report;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $view = $this->report->view;

        return [
            'reportKey' => $this->report->reportKey,
            'version' => $this->version,
            'generatedAt' => $this->generatedAt->format(DateTimeInterface::ATOM),
            'summary' => [
                'reportKey' => $view->summary->reportKey,
                'title' => $view->summary->title,
                'sectionCount' => $view->summary->sectionCount,
                'metricCount' => $view->summary->metricCount,
                'trendCount' => $view->summary->trendCount,
            ],
            'sections' => array_map([$this, 'sectionArray'], $view->sections),
        ];
    }

    /** @return array<string, mixed> */
    private function sectionArray(ReportSection $section): array
    {
        return [
            'key' => $section->key,
            'title' => $section->title,
            'metrics' => array_map([$this, 'metricArray'], $section->metrics),
            'trends' => array_map([$this, 'trendArray'], $section->trends),
        ];
    }

    /** @return array<string, mixed> */
    private function metricArray(MetricCard $card): array
    {
        return [
            'kpiId' => $card->kpiId,
            'value' => $card->value,
            'unit' => $card->unit,
            'components' => $card->components,
        ];
    }

    /** @return array<string, mixed> */
    private function trendArray(TrendCard $card): array
    {
        return [
            'kpiId' => $card->kpiId,
            'currentValue' => $card->currentValue,
            'previousValue' => $card->previousValue,
            'difference' => $card->difference,
            'percentChange' => $card->percentChange,
            'direction' => $card->direction,
        ];
    }
}
