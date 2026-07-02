<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Contracts\ExportEngineInterface;
use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Reports\ReportView;
use InvalidArgumentException;

/**
 * Shared export flow: validate the requested format, serialize the view (concrete), and wrap
 * the bytes with the format's transport metadata from the {@see ExportRegistry}. The registry
 * here is the EXPORT catalog — not the Business KPI Registry. No calculation, no query.
 */
abstract class AbstractExportEngine implements ExportEngineInterface
{
    public function __construct(private readonly ExportRegistry $registry) {}

    abstract protected function format(): ExportFormat;

    /** Serialize the already-translated view into this engine's format. */
    abstract protected function serialize(ReportView $view): string;

    public function supports(ExportFormat $format): bool
    {
        return $format === $this->format();
    }

    public function export(ExportRequest $request): ExportResult
    {
        if (! $this->supports($request->format)) {
            throw new InvalidArgumentException("This engine cannot export [{$request->format->value}].");
        }

        $definition = $this->registry->find($this->format());
        $base = $request->filename ?? $request->view->summary->reportKey;

        return new ExportResult(
            $this->format(),
            $definition->mimeType(),
            $definition->extension(),
            $this->serialize($request->view),
            $base.'.'.$definition->extension(),
        );
    }
}
