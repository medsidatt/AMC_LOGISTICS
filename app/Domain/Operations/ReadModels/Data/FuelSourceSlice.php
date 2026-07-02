<?php

namespace App\Domain\Operations\ReadModels\Data;

/**
 * Immutable projection of one (source, transaction_type) slice of the fuel ledger — a stored
 * count + FCFA sum for distribution charts. Descriptive only; no share-of-total, no ranking,
 * no verdict.
 */
final readonly class FuelSourceSlice
{
    public function __construct(
        public string $source,           // FuelSource value (stored fact)
        public string $transactionType,  // TransactionType value (stored fact)
        public int $transactionCount,
        public float $amountFcfa,
    ) {}
}
