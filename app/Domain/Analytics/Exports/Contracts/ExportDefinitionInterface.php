<?php

namespace App\Domain\Analytics\Exports\Contracts;

use App\Domain\Analytics\Exports\Enums\ExportFormat;

/**
 * The metadata of one export format — and nothing else. It DESCRIBES a format (mime type,
 * extension, whether it is offered as a download); it never serializes, calculates, or queries.
 */
interface ExportDefinitionInterface
{
    public function format(): ExportFormat;

    public function mimeType(): string;

    public function extension(): string;

    public function supportsDownload(): bool;

    public function version(): int;

    public function deprecated(): bool;

    /** A definition is active while it is not deprecated. */
    public function active(): bool;
}
