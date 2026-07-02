<?php

namespace App\Domain\Operations\Events\Derivers\Contracts;

use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Events\Derivers\DerivationContext;

/**
 * A Business Event Deriver — the layer that turns Read Model projections into immutable
 * Business Events (frozen architecture: Read Models → Calculators → Derivers → Events).
 *
 * One deriver per source aggregate. A deriver ONLY: queries Read Models, calls Calculators
 * to make every decision, and instantiates Business Events. It NEVER queries Eloquent/the
 * database, reads config/env/parameters, computes formulas or thresholds, or touches the KPI
 * Registry, Operational Intelligence, Translators, or Command Centers. Pure orchestration.
 */
interface BusinessEventDeriver
{
    /**
     * Derive the events observable in this context. Same context + same read data →
     * identical events.
     *
     * @return list<BusinessEventInterface>
     */
    public function derive(DerivationContext $context): array;
}
