<?php

namespace App\Domain\Operations\Events\Derivers;

use Carbon\CarbonImmutable;

/**
 * The immutable context a deriver runs against — the "when" of a derivation pass. It carries
 * the observation instant and the period bounds, injected by the caller so derivers stay
 * deterministic and never read the clock, config, or parameters themselves.
 *
 * `CarbonImmutable` satisfies both the Read Models' `CarbonInterface` bounds and the Business
 * Events' `DateTimeImmutable` timestamps, so no conversion leaks into a deriver.
 *
 * @phpstan-consistent-constructor
 */
final readonly class DerivationContext
{
    public function __construct(
        public CarbonImmutable $asOf,
        public CarbonImmutable $periodFrom,
        public CarbonImmutable $periodTo,
    ) {}
}
