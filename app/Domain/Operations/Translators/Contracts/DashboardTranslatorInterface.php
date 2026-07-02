<?php

namespace App\Domain\Operations\Translators\Contracts;

use App\Domain\Operations\Intelligence\OperationalConclusion;

/**
 * A Dashboard Translator — the Presentation Translation Layer (frozen architecture L6).
 *
 * It receives ONLY a list of {@see OperationalConclusion} (already routed to this
 * command center by the caller) and returns a presentation-neutral {@see DashboardView}.
 * It NEVER calculates, scores, ranks by formula, infers business logic, derives events,
 * creates KPIs, queries the database, reads configuration or environment, or instantiates calculators,
 * read models, or the KPI Registry.
 *
 * A translator may only group, order, rename, format, and aggregate conclusions that
 * already exist. Same conclusions in → identical view out (deterministic), no conclusion
 * dropped, no conclusion duplicated.
 */
interface DashboardTranslatorInterface
{
    /**
     * Transform routed conclusions into this command center's presentation model.
     *
     * @param  iterable<OperationalConclusion>  $conclusions
     */
    public function translate(iterable $conclusions): DashboardView;
}
