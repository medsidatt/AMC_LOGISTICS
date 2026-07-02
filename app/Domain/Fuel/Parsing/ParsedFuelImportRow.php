<?php

namespace App\Domain\Fuel\Parsing;

use App\Enums\Fuel\FuelSource;

/**
 * Immutable NORMALIZED FACTS for one source row — no business decisions. Holds normalized values
 * alongside their originals (preserved for audit) plus any syntactic ParseErrors. It deliberately
 * carries NO transaction_type / findings / kpi_eligible / review — those are produced downstream by
 * the classifier + ClassificationPolicy (R7). `source` is provenance (which export format), a fact,
 * not a classification.
 */
final class ParsedFuelImportRow
{
    /** @param list<ParseError> $errors */
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $rawLine,
        public readonly FuelSource $source,
        public readonly ?string $transactionRef,
        public readonly ?string $occurredAt,      // normalized 'Y-m-d H:i:s', or null when unreadable
        public readonly ?string $occurredAtRaw,    // original date text (audit)
        public readonly ?float $amount,           // normalized signed amount, or null when unreadable
        public readonly ?string $amountRaw,        // original amount text (audit)
        public readonly ?string $cardNumber,       // card family only
        public readonly ?string $normalizedRegistration, // extracted+normalized plate string (NOT a resolved truck)
        public readonly ?string $holderRaw,        // card family: the "Porteur" free-text (audit)
        public readonly ?string $mode,             // account family: "Mode de recharge"
        public readonly ?string $note,             // account family: "Commentaires"
        public readonly array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
