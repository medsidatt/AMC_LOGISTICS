<?php

namespace App\Domain\Analytics\Exports\Contracts;

use App\Domain\Analytics\Exports\Enums\ExportFormat;
use App\Domain\Analytics\Exports\ExportRequest;
use App\Domain\Analytics\Exports\ExportResult;

/**
 * A Report Export Engine — serializes an already-translated report view into one format.
 *
 * It receives ONLY the report view (and its cards/sections) via the request and returns an
 * {@see ExportResult}. It NEVER calculates KPIs or trends, queries Read Models, reads the
 * Business KPI Registry, or touches any Operations layer, the database, config, or env.
 * Same view + same format → identical bytes (deterministic).
 */
interface ExportEngineInterface
{
    /** Whether this engine serializes the given format. */
    public function supports(ExportFormat $format): bool;

    /** Serialize the request's report view into its format. */
    public function export(ExportRequest $request): ExportResult;
}
