<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Reports\ReportSection;
use App\Domain\Analytics\Reports\ReportView;

/**
 * Serializes a report view to a plain HTML fragment (headings + tables). No Blade, no
 * template, no styling, no charts — just escaped string building. No calculation.
 */
final class HtmlExportEngine extends AbstractExportEngine
{
    protected function format(): ExportFormat
    {
        return ExportFormat::HTML;
    }

    protected function serialize(ReportView $view): string
    {
        $html = '<h1>'.$this->escape($view->summary->title).'</h1>';
        $html .= '<p>'.$this->escape($view->summary->reportKey)
            .' · '.$view->summary->metricCount.' metric(s) · '.$view->summary->trendCount.' trend(s)</p>';

        foreach ($view->sections as $section) {
            $html .= $this->section($section);
        }

        return $html;
    }

    private function section(ReportSection $section): string
    {
        $html = '<h2>'.$this->escape($section->title).'</h2>';

        if ($section->metrics !== []) {
            $html .= '<table><thead><tr><th>KPI</th><th>Value</th><th>Unit</th></tr></thead><tbody>';
            foreach ($section->metrics as $metric) {
                $html .= '<tr><td>'.$this->escape($metric->kpiId).'</td><td>'.$metric->value.'</td><td>'.$this->escape($metric->unit).'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($section->trends !== []) {
            $html .= '<table><thead><tr><th>KPI</th><th>Previous</th><th>Current</th><th>Difference</th><th>Percent</th><th>Direction</th></tr></thead><tbody>';
            foreach ($section->trends as $trend) {
                $html .= '<tr><td>'.$this->escape($trend->kpiId).'</td><td>'.$trend->previousValue.'</td><td>'.$trend->currentValue
                    .'</td><td>'.$trend->difference.'</td><td>'.$trend->percentChange.'</td><td>'.$this->escape($trend->direction).'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
