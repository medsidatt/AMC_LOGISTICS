<?php

namespace App\Domain\Analytics\Exports;

use App\Domain\Analytics\Exports\Contracts\ExportEngineInterface;
use App\Domain\Analytics\Exports\Enums\ExportFormat;
use InvalidArgumentException;

/**
 * Selects the one export engine that serializes a given format. It routes only — it never
 * serializes, calculates, or queries. Injected with the available engines; returns the first
 * that supports the format, and fails fast when none does.
 */
final class ExportEngineResolver
{
    /**
     * @param  list<ExportEngineInterface>  $engines
     */
    public function __construct(private readonly array $engines) {}

    public function resolve(ExportFormat $format): ExportEngineInterface
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($format)) {
                return $engine;
            }
        }

        throw new InvalidArgumentException("No export engine handles [{$format->value}].");
    }
}
