<?php

namespace App\Enums\Fuel;

/**
 * Data-integrity anomalies — "can we trust the bytes?". A technical finding is the ONLY kind that may
 * force the record out of the canonical ledger (REJECT). Distinct from BusinessFinding (operational).
 */
enum TechnicalFinding: string
{
    case INVALID_DATE = 'INVALID_DATE';
    case INVALID_AMOUNT = 'INVALID_AMOUNT';
    case MALFORMED_ROW = 'MALFORMED_ROW';
    case DUPLICATE_TRANSACTION = 'DUPLICATE_TRANSACTION';

    /** Every technical finding is fatal in v1 → the row is not trusted into the ledger. */
    public function forcesReject(): bool
    {
        return true;
    }

    /** A duplicate is benign (the original is the record); other corruption needs a human. */
    public function forcesReview(): bool
    {
        return $this !== self::DUPLICATE_TRANSACTION;
    }

    public function label(): string
    {
        return match ($this) {
            self::INVALID_DATE => 'Date invalide',
            self::INVALID_AMOUNT => 'Montant invalide',
            self::MALFORMED_ROW => 'Ligne malformée',
            self::DUPLICATE_TRANSACTION => 'Transaction en doublon',
        };
    }
}
