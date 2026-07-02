<?php

namespace App\Domain\Analytics\Reports;

/**
 * The immutable, presentation-ready response of a Report Translator — the report's identity,
 * schema version, and its view. It carries no generation timestamp: stamping "when" is the
 * (future R4.5) BI command center's job, keeping translation deterministic.
 *
 * @phpstan-consistent-constructor
 */
final readonly class ReportResponse
{
    /** Response-schema version — bump when the wire shape changes, not the data. */
    public const VERSION = 1;

    public function __construct(
        public string $reportKey,
        public ReportView $view,
        public int $version = self::VERSION,
    ) {}
}
