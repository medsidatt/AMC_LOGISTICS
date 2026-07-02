<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Enums\ExportFormat;

/**
 * The immutable result of an export: the serialized content plus its transport metadata (mime
 * type, extension, filename). Built once by an export engine, never mutated.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExportResult
{
    public function __construct(
        public ExportFormat $format,
        public string $mimeType,
        public string $extension,
        public string $content,
        public string $filename,
    ) {}
}
