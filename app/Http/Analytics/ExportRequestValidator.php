<?php

namespace App\Http\Analytics;

use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Exports\ExportRegistry;

/**
 * Validates the HTTP inputs of an export request — the report key, the requested format, and
 * the optional filename. It validates only; it never serializes, calculates, translates, or
 * queries. Unknown reports and unsupported/reserved formats are rejected with 404.
 */
class ExportRequestValidator
{
    /** The report keys that map to a BI command center. */
    private const REPORTS = ['executive', 'operations', 'fleet'];

    public function __construct(private readonly ExportRegistry $registry) {}

    public function report(string $report): string
    {
        abort_unless(in_array($report, self::REPORTS, true), 404, "Unknown report [{$report}].");

        return $report;
    }

    public function format(string $format): ExportFormat
    {
        $resolved = ExportFormat::tryFrom(strtolower($format));

        // Reserved formats (PDF/EXCEL) parse but carry no registry definition → rejected.
        abort_unless($resolved !== null && $this->registry->has($resolved), 404, "Unsupported export format [{$format}].");

        return $resolved;
    }

    /** Sanitize an optional filename to a safe base (no path/extension); null when absent/empty. */
    public function filename(?string $filename): ?string
    {
        if ($filename === null || $filename === '') {
            return null;
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $filename);

        return ($safe === null || $safe === '') ? null : $safe;
    }
}
