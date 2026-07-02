<?php

namespace App\Domain\Operations\ReadModels\Data;

/**
 * Immutable projection of one stored fuel import batch (audit trail row) — filename, source,
 * row counters, when. Raw stored values; "success rate" style ratios are left to the
 * presentation layer as plain arithmetic over these counts, never judged here.
 */
final readonly class FuelImportBatchProjection
{
    public function __construct(
        public int $batchId,
        public ?string $filename,
        public string $source,
        public int $totalRows,
        public int $acceptedRows,
        public int $rejectedRows,
        public ?string $importedAt,   // 'Y-m-d H:i:s' | null
    ) {}
}
