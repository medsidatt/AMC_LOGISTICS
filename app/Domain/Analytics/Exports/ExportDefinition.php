<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Contracts\ExportDefinitionInterface;
use App\Domain\Analytics\Exports\Enums\ExportFormat;

/**
 * One export-format definition — an immutable metadata value object. Getters only; no
 * serialization, no calculation.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ExportDefinition implements ExportDefinitionInterface
{
    public function __construct(
        private ExportFormat $format,
        private string $mimeType,
        private string $extension,
        private bool $supportsDownload,
        private int $version = 1,
        private bool $deprecated = false,
    ) {}

    public function format(): ExportFormat
    {
        return $this->format;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function supportsDownload(): bool
    {
        return $this->supportsDownload;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function deprecated(): bool
    {
        return $this->deprecated;
    }

    public function active(): bool
    {
        return ! $this->deprecated;
    }
}
