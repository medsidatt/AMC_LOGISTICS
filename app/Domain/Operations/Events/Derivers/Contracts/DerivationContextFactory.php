<?php

namespace App\Domain\Operations\Events\Derivers\Contracts;

use App\Domain\Operations\Events\Derivers\DerivationContext;

/**
 * The single owner of derivation-context creation — the "when" of a derivation pass (the
 * observation instant and the reporting/fiscal/replay period).
 *
 * It owns the clock and the period policy and NOTHING else: it never queries Eloquent or the
 * database, and knows nothing of derivers, read models, calculators, KPIs, Operational
 * Intelligence, Translators, or Command Centers. Separating this from the event source keeps
 * the source a pure orchestrator.
 */
interface DerivationContextFactory
{
    /** The context for a derivation pass observed now (the current reporting period). */
    public function current(): DerivationContext;
}
