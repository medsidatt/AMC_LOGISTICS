<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Contracts\ExportDefinitionInterface;
use App\Domain\Analytics\Exports\Enums\ExportFormat;
use InvalidArgumentException;

/**
 * The single authoritative catalog of export-format metadata (mime type, extension, download
 * support). It DESCRIBES formats and nothing else — it never serializes, calculates, queries,
 * or reads config/env. Only implemented formats are defined; reserved formats (PDF, EXCEL)
 * exist in {@see ExportFormat} but carry no definition here.
 *
 * One format. One definition. One source of truth.
 */
final class ExportRegistry
{
    /** @var array<string, ExportDefinition>|null format-value => definition, built once. */
    private ?array $definitions = null;

    public function find(ExportFormat $format): ExportDefinitionInterface
    {
        $definition = $this->definitions()[$format->value] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("No export definition registered for [{$format->value}] (reserved?).");
        }

        return $definition;
    }

    public function has(ExportFormat $format): bool
    {
        return isset($this->definitions()[$format->value]);
    }

    /** @return list<ExportDefinition> every defined format, catalog order. */
    public function all(): array
    {
        return array_values($this->definitions());
    }

    /** @return list<ExportDefinition> non-deprecated formats. */
    public function active(): array
    {
        return array_values(array_filter($this->all(), static fn (ExportDefinition $d): bool => $d->active()));
    }

    /** @return array<string, ExportDefinition> */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $map = [];
        foreach ($this->build() as $definition) {
            $map[$definition->format()->value] = $definition;
        }

        return $this->definitions = $map;
    }

    /** @return list<ExportDefinition> */
    private function build(): array
    {
        return [
            new ExportDefinition(ExportFormat::HTML, 'text/html', 'html', true),
            new ExportDefinition(ExportFormat::CSV, 'text/csv', 'csv', true),
            new ExportDefinition(ExportFormat::JSON, 'application/json', 'json', true),
        ];
    }
}
