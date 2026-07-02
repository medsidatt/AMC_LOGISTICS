<?php

namespace App\Domain\Operations\Intelligence\Contracts;

use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Intelligence\OperationalConclusion;

/**
 * The company's decision engine. It transforms immutable Business Events (operational
 * FACTS) into Operational Conclusions, enriched with KPI Registry metadata. It CONSUMES
 * Business Events + KPI Registry only; it never calculates, queries Eloquent, reads
 * config/env, instantiates calculators or read models, chooses between KPIs, filters,
 * sorts, or touches the UI.
 *
 * Events arrive already derived (by an upstream deriver — out of scope here). The engine
 * is a pure transform: same events in → identical conclusions out, in input order.
 * Filtering, grouping, and ordering for display are the Dashboard Translators' job (R1.7).
 */
interface OperationalIntelligenceInterface
{
    /**
     * Transform business events into operational conclusions, one per event that maps to
     * a KPI. Events with no catalog KPI carry no documented decision and yield nothing.
     *
     * @param  iterable<BusinessEventInterface>  $events
     * @return list<OperationalConclusion>
     */
    public function conclude(iterable $events): array;
}
