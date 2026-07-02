<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Reports\ReportView;

/**
 * The input to an export: the already-translated report view plus the requested format and an
 * optional base filename. Immutable. It carries a presentation VO only — never a Read Model,
 * Calculator, or Registry.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExportRequest
{
    public function __construct(
        public ExportFormat $format,
        public ReportView $view,
        public ?string $filename = null,
    ) {}
}
