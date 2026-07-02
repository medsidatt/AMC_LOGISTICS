<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Reports\MetricCard;
use App\Domain\Analytics\Reports\ReportSection;
use App\Domain\Analytics\Reports\ReportView;
use App\Domain\Analytics\Reports\TrendCard;

/**
 * Serializes a report view to JSON — a structural dump of the already-built cards. No
 * calculation, no formatting of values beyond JSON encoding.
 */
final class JsonExportEngine extends AbstractExportEngine
{
    protected function format(): ExportFormat
    {
        return ExportFormat::JSON;
    }

    protected function serialize(ReportView $view): string
    {
        return json_encode([
            'reportKey' => $view->summary->reportKey,
            'summary' => [
                'title' => $view->summary->title,
                'sectionCount' => $view->summary->sectionCount,
                'metricCount' => $view->summary->metricCount,
                'trendCount' => $view->summary->trendCount,
            ],
            'sections' => array_map(fn (ReportSection $s): array => [
                'key' => $s->key,
                'title' => $s->title,
                'metrics' => array_map(fn (MetricCard $m): array => [
                    'kpiId' => $m->kpiId,
                    'value' => $m->value,
                    'unit' => $m->unit,
                    'components' => $m->components,
                ], $s->metrics),
                'trends' => array_map(fn (TrendCard $t): array => [
                    'kpiId' => $t->kpiId,
                    'currentValue' => $t->currentValue,
                    'previousValue' => $t->previousValue,
                    'difference' => $t->difference,
                    'percentChange' => $t->percentChange,
                    'direction' => $t->direction,
                ], $s->trends),
            ], $view->sections),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
