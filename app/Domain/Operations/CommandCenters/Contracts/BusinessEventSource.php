<?php

namespace App\Domain\Operations\CommandCenters\Contracts;

use App\Domain\Operations\Contracts\BusinessEventInterface;

/**
 * The source of already-derived operational FACTS (Business Events) that Operational
 * Intelligence turns into conclusions — the input port of a Command Center pipeline.
 *
 * A Command Center may NOT derive events, instantiate calculators, or read the database, so
 * it never produces facts itself: it receives them from this source. Where the facts come
 * from (a deriver, a queue, a cache) is deliberately opaque to the Command Center.
 *
 * The events are returned exactly as produced upstream; the source neither calculates,
 * filters, ranks, nor persists — it is not a repository.
 */
interface BusinessEventSource
{
    /** @return iterable<BusinessEventInterface> */
    public function events(): iterable;
}
