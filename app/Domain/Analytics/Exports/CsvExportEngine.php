<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Reports\ReportView;

/**
 * Serializes a report view to a flat CSV — one row per metric and per trend, with a `kind`
 * column so both fit one table. No calculation; values are written as-is.
 */
final class CsvExportEngine extends AbstractExportEngine
{
    private const HEADER = [
        'reportKey', 'section', 'kind', 'kpiId', 'value', 'unit',
        'currentValue', 'previousValue', 'difference', 'percentChange', 'direction',
    ];

    protected function format(): ExportFormat
    {
        return ExportFormat::CSV;
    }

    protected function serialize(ReportView $view): string
    {
        $rows = [self::HEADER];

        foreach ($view->sections as $section) {
            foreach ($section->metrics as $metric) {
                $rows[] = [$view->summary->reportKey, $section->key, 'metric', $metric->kpiId, $metric->value, $metric->unit, '', '', '', '', ''];
            }
            foreach ($section->trends as $trend) {
                $rows[] = [$view->summary->reportKey, $section->key, 'trend', $trend->kpiId, '', '', $trend->currentValue, $trend->previousValue, $trend->difference, $trend->percentChange, $trend->direction];
            }
        }

        return implode("\n", array_map([$this, 'line'], $rows));
    }

    /** @param list<int|float|string> $fields */
    private function line(array $fields): string
    {
        return implode(',', array_map(static function (int|float|string $field): string {
            $value = (string) $field;

            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                return '"'.str_replace('"', '""', $value).'"';
            }

            return $value;
        }, $fields));
    }
}
