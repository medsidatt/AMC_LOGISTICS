<?php

namespace App\Domain\Fuel\ValueObjects;

use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;

/**
 * The FACTS a classifier detected about one row — the validator's immutable *proposal*:
 * what it is (type), where it came from (source), and what is wrong with it (findings).
 * It holds no decisions; ClassificationPolicy derives those. Persisted as the proposal snapshot.
 */
final class FuelTransactionClassification
{
    public function __construct(
        public readonly TransactionType $type,
        public readonly FuelSource $source,
        public readonly ValidationFindings $findings,
    ) {}

    public static function make(TransactionType $type, FuelSource $source, ?ValidationFindings $findings = null): self
    {
        return new self($type, $source, $findings ?? ValidationFindings::none());
    }
}
