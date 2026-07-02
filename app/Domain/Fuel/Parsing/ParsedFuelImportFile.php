<?php

namespace App\Domain\Fuel\Parsing;

use App\Enums\Fuel\FuelSource;

/**
 * Immutable result of parsing one source file: the detected provenance (source), the normalized
 * fact rows, and any file-level parsing errors (e.g. unrecognised format). Facts only.
 */
final class ParsedFuelImportFile
{
    /**
     * @param  list<ParsedFuelImportRow>  $rows
     * @param  list<ParseError>  $fileErrors
     */
    public function __construct(
        public readonly FuelSource $source,
        public readonly array $rows,
        public readonly array $fileErrors = [],
    ) {}

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function hasFileErrors(): bool
    {
        return $this->fileErrors !== [];
    }

    /** @return list<ParsedFuelImportRow> */
    public function rowsWithErrors(): array
    {
        return array_values(array_filter($this->rows, fn (ParsedFuelImportRow $r) => $r->hasErrors()));
    }
}
