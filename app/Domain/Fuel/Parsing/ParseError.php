<?php

namespace App\Domain\Fuel\Parsing;

/**
 * An immutable SYNTACTIC parsing problem detected while reading a source row/file — e.g. a missing
 * required field or an unreadable value. This is a *parsing fact*, NOT a business finding: the parser
 * records "I could not read X"; ClassificationPolicy (R7) decides what that means (INVALID_DATE, etc.).
 */
final class ParseError
{
    public const MALFORMED_ROW = 'malformed_row';
    public const UNKNOWN_FORMAT = 'unknown_format';
    public const MISSING_TRANSACTION_REF = 'missing_transaction_ref';
    public const UNPARSEABLE_DATE = 'unparseable_date';
    public const UNPARSEABLE_AMOUNT = 'unparseable_amount';

    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $field = null,
        public readonly ?int $lineNumber = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'field' => $this->field,
            'line_number' => $this->lineNumber,
        ];
    }
}
