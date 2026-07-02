<?php

namespace App\Domain\Operations\Intelligence;

use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Intelligence\Contracts\OperationalIntelligenceInterface;
use App\Domain\Operations\Intelligence\Exceptions\OwnershipMismatchException;
use App\Domain\Operations\KPI\Contracts\KpiDefinitionInterface;
use App\Domain\Operations\KPI\KpiRegistry;

/**
 * The Operational Intelligence engine (frozen architecture L5). A pure, deterministic
 * transform: it maps each Business Event to its Operational Conclusion, enriched with
 * KPI Registry metadata. Same events in → identical conclusions out.
 *
 * It consumes Business Events + the KPI Registry ONLY. It never:
 *   - calculates a business formula        - reads config()/env()/FleetSetting
 *   - queries Eloquent or SQL              - instantiates a calculator or read model
 *   - chooses between KPIs                 - filters/sorts for presentation
 *   - emits events / touches the bus       - contains UI / translation
 *
 * It does NOT decide which KPI an event belongs to — the KPI Registry owns the
 * event→KPI mapping (one event, one emitter) and is consulted via {@see KpiRegistry::byEvent()}.
 * Ownership is verified, never chosen: a fact whose owner disagrees with its KPI fails fast.
 */
final class OperationalIntelligence implements OperationalIntelligenceInterface
{
    public function __construct(
        private readonly KpiRegistry $registry,
    ) {}

    public function conclude(iterable $events): array
    {
        $conclusions = [];

        foreach ($events as $event) {
            $kpi = $this->registry->byEvent($event->id());
            if ($kpi === null) {
                // The event carries no catalog decision; there is nothing to conclude.
                continue;
            }

            $this->assertOwnerConsistent($event, $kpi);
            $conclusions[] = $this->concludeOne($event, $kpi);
        }

        return $conclusions;
    }

    /**
     * Build one conclusion from an operational fact and its catalog KPI. Pure mapping:
     * the fact supplies severity / impact / entity / evidence; the KPI supplies owner /
     * decision / action / drill-down / question. Nothing is calculated or chosen.
     */
    private function concludeOne(BusinessEventInterface $event, KpiDefinitionInterface $kpi): OperationalConclusion
    {
        $evidence = new OperationalEvidence(
            $event->id(),
            $event->entityType(),
            $event->entityId(),
            $event->occurredAt(),
            $event->payload(),
        );

        $finding = new OperationalFinding(
            $kpi->id(),
            $event->id(),
            $kpi->owner(),
            $event->severity(),
            $event->businessImpact(),
            $kpi->businessQuestion(),
            $evidence,
        );

        $recommendation = new OperationalRecommendation(
            $kpi->businessDecision(),
            $kpi->requiredAction(),
            $kpi->drillDown(),
        );

        return new OperationalConclusion(
            $this->conclusionId($event),
            $finding,
            $recommendation,
            OperationalPriority::fromSeverity($event->severity()),
            $this->explanationFor($kpi, $evidence),
            $event->occurredAt(),
        );
    }

    /** Verify — never choose — that the fact and its KPI agree on the owner (ADR-004). */
    private function assertOwnerConsistent(BusinessEventInterface $event, KpiDefinitionInterface $kpi): void
    {
        if ($event->owner()->value !== $kpi->owner()->value) {
            throw OwnershipMismatchException::between(
                $event->id(),
                $event->owner()->value,
                $kpi->id(),
                $kpi->owner()->value,
            );
        }
    }

    /** Stable, deterministic id: event type + affected entity. No time, no randomness. */
    private function conclusionId(BusinessEventInterface $event): string
    {
        $id = $event->id()->value.':'.$event->entityType();

        return $event->entityId() === null ? $id : $id.':'.$event->entityId();
    }

    /** Business sentence built from the KPI name, the pre-computed evidence, and the catalog action. */
    private function explanationFor(KpiDefinitionInterface $kpi, OperationalEvidence $evidence): string
    {
        return sprintf('%s — %s. Action: %s.', $kpi->name(), $evidence->summary(), $kpi->requiredAction());
    }
}
