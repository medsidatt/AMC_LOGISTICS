<?php

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\Metrics\Contracts\BusinessMetric;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Reports\Contracts\BusinessReportTranslator;
use App\Domain\Analytics\Trends\TrendResult;

/**
 * Shared grouping for every Business Report Translator so the presentation logic lives once
 * (Reuse > Create). It builds one card per input, groups cards into the concrete report's
 * declared sections (stable, declared order), and appends any unmapped card to a trailing
 * "Other" section so nothing is dropped. It performs no calculation — only mapping, grouping,
 * ordering, and structural counting (lengths of the built collections).
 */
abstract class AbstractReportTranslator implements BusinessReportTranslator
{
    /**
     * The report's ordered sections: each a key, a title, and the KPI ids it contains.
     *
     * @return list<array{key: string, title: string, kpis: list<BusinessKpiId>}>
     */
    abstract protected function sections(): array;

    abstract protected function reportKey(): string;

    abstract protected function title(): string;

    public function translate(array $metrics, array $trends): ReportResponse
    {
        $metricCards = array_map(static fn (BusinessMetric $m): MetricCard => MetricCard::fromMetric($m), $metrics);
        $trendCards = array_map(static fn (TrendResult $t): TrendCard => TrendCard::fromTrend($t), $trends);

        $usedMetric = [];
        $usedTrend = [];
        $sections = [];

        foreach ($this->sections() as $section) {
            $sectionMetrics = [];
            $sectionTrends = [];

            foreach ($section['kpis'] as $kpi) {
                foreach ($metricCards as $i => $card) {
                    if ($card->kpiId === $kpi->value) {
                        $sectionMetrics[] = $card;
                        $usedMetric[$i] = true;
                    }
                }
                foreach ($trendCards as $i => $card) {
                    if ($card->kpiId === $kpi->value) {
                        $sectionTrends[] = $card;
                        $usedTrend[$i] = true;
                    }
                }
            }

            if ($sectionMetrics !== [] || $sectionTrends !== []) {
                $sections[] = new ReportSection($section['key'], $section['title'], $sectionMetrics, $sectionTrends);
            }
        }

        // Nothing is dropped: unmapped cards land in a trailing "Other" section, in input order.
        $otherMetrics = array_values(array_filter($metricCards, static fn (MetricCard $c, int $i): bool => ! isset($usedMetric[$i]), ARRAY_FILTER_USE_BOTH));
        $otherTrends = array_values(array_filter($trendCards, static fn (TrendCard $c, int $i): bool => ! isset($usedTrend[$i]), ARRAY_FILTER_USE_BOTH));
        if ($otherMetrics !== [] || $otherTrends !== []) {
            $sections[] = new ReportSection('other', 'Other', $otherMetrics, $otherTrends);
        }

        $summary = new ReportSummary(
            $this->reportKey(),
            $this->title(),
            count($sections),
            count($metricCards),
            count($trendCards),
        );

        return new ReportResponse($this->reportKey(), new ReportView($summary, $sections));
    }
}
