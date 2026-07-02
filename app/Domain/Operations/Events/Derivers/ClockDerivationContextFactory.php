<?php

namespace App\Domain\Operations\Events\Derivers;

use App\Domain\Operations\Events\Derivers\Contracts\DerivationContextFactory;
use Carbon\CarbonImmutable;

/**
 * Builds the derivation context from the system clock. It owns the observation instant and
 * the reporting period (the current calendar month), derived purely from the clock — it reads
 * no parameter and touches no database, so a fiscal-period variant would receive its start day
 * by injection rather than query. Knows nothing of derivers, read models, calculators, or any
 * downstream layer.
 */
final class ClockDerivationContextFactory implements DerivationContextFactory
{
    public function current(): DerivationContext
    {
        $asOf = CarbonImmutable::now();

        return new DerivationContext($asOf, $asOf->startOfMonth(), $asOf->endOfMonth());
    }
}
