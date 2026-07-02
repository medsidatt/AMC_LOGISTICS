<?php

namespace App\Enums\Fuel;

/**
 * Lifecycle of a transaction's manual-review state. Set by FuelImportService at import
 * (NONE or PENDING) and moved to RESOLVED by the review workflow (R10). String-backed to match the
 * existing `review_status` column (kept uncast on the frozen model).
 */
enum ReviewStatus: string
{
    case NONE = 'NONE';
    case PENDING = 'PENDING';
    case RESOLVED = 'RESOLVED';
}
