<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Events\BillingBlocked;
use App\Domain\Operations\Events\BusinessEvent;
use App\Domain\Operations\Events\BusinessEventSeverity;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\BusinessOwner;
use App\Domain\Operations\Events\CapacityReduced;
use App\Domain\Operations\Events\DispatchCompleted;
use App\Domain\Operations\Events\DispatchDelayed;
use App\Domain\Operations\Events\DriverDisciplineLow;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\Events\FuelConsumptionAbnormal;
use App\Domain\Operations\Events\GPSLoadWithoutTicket;
use App\Domain\Operations\Events\InspectionDue;
use App\Domain\Operations\Events\InspectionExpired;
use App\Domain\Operations\Events\MaintenanceOverdue;
use App\Domain\Operations\Events\MaintenanceWarning;
use App\Domain\Operations\Events\MissingTransportTicket;
use App\Domain\Operations\Events\ObjectiveBehindSchedule;
use App\Domain\Operations\Events\ObjectiveReached;
use App\Domain\Operations\Events\TruckUnavailable;
use App\Domain\Operations\Events\WeightAnomalyDetected;
use App\Domain\Operations\Intelligence\Contracts\OperationalIntelligenceInterface;
use App\Domain\Operations\Intelligence\Exceptions\OwnershipMismatchException;
use App\Domain\Operations\Intelligence\OperationalConclusion;
use App\Domain\Operations\Intelligence\OperationalEvidence;
use App\Domain\Operations\Intelligence\OperationalFinding;
use App\Domain\Operations\Intelligence\OperationalIntelligence;
use App\Domain\Operations\Intelligence\OperationalPriority;
use App\Domain\Operations\Intelligence\OperationalRecommendation;
use App\Domain\Operations\KPI\Enums\KpiId;
use App\Domain\Operations\KPI\Enums\KpiOwner;
use App\Domain\Operations\KPI\KpiRegistry;
use DateTimeImmutable;
use ReflectionClass;
use Tests\TestCase;

/**
 * R1.6 — Operational Intelligence is a pure transform from Business Events to
 * Operational Conclusions, using KPI Registry metadata. It never calculates, queries,
 * filters, sorts, or chooses a KPI; the Registry owns the event→KPI mapping. These
 * tests pin each required field, the verified (never chosen) ownership, immutability,
 * and determinism.
 */
class OperationalIntelligenceTest extends TestCase
{
    private const AT = '2026-06-30T08:00:00+00:00';

    /** Every concrete Business Event, to prove the catalog mapping is owner-consistent. */
    private const EVENTS = [
        TruckUnavailable::class,
        MaintenanceOverdue::class,
        MaintenanceWarning::class,
        WeightAnomalyDetected::class,
        FuelConsumptionAbnormal::class,
        InspectionExpired::class,
        InspectionDue::class,
        DispatchDelayed::class,
        DispatchCompleted::class,
        MissingTransportTicket::class,
        GPSLoadWithoutTicket::class,
        BillingBlocked::class,
        ObjectiveBehindSchedule::class,
        ObjectiveReached::class,
        CapacityReduced::class,
        DriverDisciplineLow::class,
    ];

    private function engine(): OperationalIntelligence
    {
        return new OperationalIntelligence(new KpiRegistry);
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::AT);
    }

    public function test_engine_resolves_through_the_container_to_the_interface(): void
    {
        $this->assertInstanceOf(OperationalIntelligence::class, app(OperationalIntelligenceInterface::class));
    }

    public function test_billing_blocked_becomes_a_billing_readiness_conclusion(): void
    {
        $event = new BillingBlocked($this->at(), 7, 'invoice_batch', [
            'count' => 14,
            'subject' => 'incomplete transport tickets',
        ]);

        $conclusions = $this->engine()->conclude([$event]);

        $this->assertCount(1, $conclusions);
        $c = $conclusions[0];

        $this->assertSame('billing_blocked:invoice_batch:7', $c->id());          // (1) id
        $this->assertSame('billing_blocked', $c->event()->value);                // (2) business event
        $this->assertSame(KpiId::FIN_100, $c->kpi());                            // (3) related KPI (single emitter)
        $this->assertSame(BusinessEventSeverity::HIGH, $c->severity());          // (4) severity (from fact)
        $this->assertSame(BusinessImpact::FINANCIAL, $c->businessImpact());      // (5) business impact
        $this->assertSame(KpiOwner::FINANCE, $c->owner());                       // (6) one owner
        $this->assertSame('Whether to complete tickets/documents before the billing run.', $c->decision()); // (7)
        $this->assertSame('Complete the tickets / documents', $c->requiredAction());                        // (8)
        $this->assertSame(
            'Billing Readiness — 14 incomplete transport tickets. Action: Complete the tickets / documents.',
            $c->explanation(),                                                    // (9) explanation
        );
        $this->assertSame('invoice_batch', $c->affectedEntityType());            // (10) affected entity
        $this->assertSame(7, $c->affectedEntityId());
        $this->assertSame('Incomplete-tickets queue', $c->drillDownTarget());    // (11) drill-down
        $this->assertEquals($this->at(), $c->occurredAt());                      // (12) timestamp
        $this->assertSame(['count' => 14, 'subject' => 'incomplete transport tickets'], $c->evidenceFacts()); // (13)
        $this->assertSame(2, $c->priorityRank());                                // (14) priority (HIGH = 2)
    }

    public function test_every_mapped_event_links_to_its_single_kpi_with_consistent_owner(): void
    {
        $cases = [
            [new ObjectiveBehindSchedule($this->at(), 1, 'objective', []), KpiId::OPS_001, KpiOwner::OPERATIONS, BusinessEventSeverity::CRITICAL],
            [new MissingTransportTicket($this->at(), 2, 'load', []), KpiId::OPS_004, KpiOwner::OPERATIONS, BusinessEventSeverity::HIGH],
            [new WeightAnomalyDetected($this->at(), 3, 'load', []), KpiId::OPS_005, KpiOwner::OPERATIONS, BusinessEventSeverity::HIGH],
            [new BillingBlocked($this->at(), 4, 'invoice_batch', []), KpiId::FIN_100, KpiOwner::FINANCE, BusinessEventSeverity::HIGH],
            [new CapacityReduced($this->at(), 5, 'fleet', []), KpiId::FLT_201, KpiOwner::FLEET, BusinessEventSeverity::HIGH],
            [new TruckUnavailable($this->at(), 6, 'truck', []), KpiId::DSP_300, KpiOwner::DISPATCH, BusinessEventSeverity::CRITICAL],
            [new MaintenanceOverdue($this->at(), 7, 'truck', []), KpiId::MNT_400, KpiOwner::MAINTENANCE, BusinessEventSeverity::CRITICAL],
            [new InspectionExpired($this->at(), 8, 'truck', []), KpiId::HSE_500, KpiOwner::HSE, BusinessEventSeverity::CRITICAL],
        ];

        foreach ($cases as [$event, $expectedKpi, $expectedOwner, $expectedSeverity]) {
            $c = $this->engine()->conclude([$event])[0];
            $this->assertSame($expectedKpi, $c->kpi(), "{$event->id()->value} → KPI");
            $this->assertSame($expectedOwner, $c->owner(), "{$event->id()->value} → owner");
            $this->assertSame($expectedSeverity, $c->severity(), "{$event->id()->value} → severity");
            $this->assertSame($event->owner()->value, $c->owner()->value, "{$event->id()->value} owner consistency");
        }
    }

    public function test_catalog_event_mapping_is_owner_consistent_for_every_event(): void
    {
        $registry = new KpiRegistry;

        foreach (self::EVENTS as $class) {
            /** @var BusinessEventInterface $event */
            $event = new $class($this->at(), 1, 'entity', []);
            $kpi = $registry->byEvent($event->id());

            if ($kpi !== null) {
                $this->assertSame(
                    $event->owner()->value,
                    $kpi->owner()->value,
                    "{$event->id()->value} owner must equal its emitting KPI {$kpi->id()->value}",
                );
            } else {
                // No conclusion is produced for an event with no emitting KPI.
                $this->assertSame([], $this->engine()->conclude([$event]));
            }
        }
    }

    public function test_engine_does_not_choose_between_kpis_it_asks_the_registry(): void
    {
        // CapacityReduced is emitted by exactly one KPI (FLT-201); OPS-002 consumes it
        // via dependency. The engine performs no owner/severity selection of its own.
        $c = $this->engine()->conclude([new CapacityReduced($this->at(), 5, 'fleet', [])])[0];

        $this->assertSame(KpiId::FLT_201, $c->kpi());
        $this->assertSame((new KpiRegistry)->byEvent(EventId::CAPACITY_REDUCED)->id(), $c->kpi());
    }

    public function test_events_without_a_catalog_kpi_produce_no_conclusion(): void
    {
        $conclusions = $this->engine()->conclude([
            new DispatchCompleted($this->at(), 1, 'dispatch', []),   // positive, no emitting KPI
            new MaintenanceOverdue($this->at(), 2, 'truck', []),      // emits MNT-400
        ]);

        $this->assertCount(1, $conclusions);
        $this->assertSame(KpiId::MNT_400, $conclusions[0]->kpi());
    }

    public function test_conclusions_are_returned_in_input_order(): void
    {
        $events = [
            new MissingTransportTicket($this->at(), 1, 'load', []),  // HIGH
            new MaintenanceOverdue($this->at(), 2, 'truck', []),      // CRITICAL
            new InspectionExpired($this->at(), 3, 'truck', []),       // CRITICAL
        ];

        $kpis = array_map(static fn (OperationalConclusion $c): KpiId => $c->kpi(), $this->engine()->conclude($events));

        // No sorting/filtering — the engine preserves input order (ordering is presentation).
        $this->assertSame([KpiId::OPS_004, KpiId::MNT_400, KpiId::HSE_500], $kpis);
    }

    public function test_owner_mismatch_fails_fast(): void
    {
        // A fact that claims BillingBlocked but is owned by Operations contradicts its
        // KPI (FIN-100, Finance). The engine verifies — it must throw, never silently pick.
        $rogue = new readonly class($this->at(), 1, 'invoice_batch', []) extends BusinessEvent
        {
            public function id(): EventId
            {
                return EventId::BILLING_BLOCKED;
            }

            public function owner(): BusinessOwner
            {
                return BusinessOwner::OPERATIONS;
            }

            public function severity(): BusinessEventSeverity
            {
                return BusinessEventSeverity::HIGH;
            }

            public function businessImpact(): BusinessImpact
            {
                return BusinessImpact::FINANCIAL;
            }

            public function requiredAction(): string
            {
                return 'n/a';
            }
        };

        $this->expectException(OwnershipMismatchException::class);
        $this->engine()->conclude([$rogue]);
    }

    public function test_evidence_summary_falls_back_when_payload_lacks_a_phrase(): void
    {
        $withSummary = new OperationalEvidence(EventId::MAINTENANCE_OVERDUE, 'truck', 9, $this->at(), ['summary' => '3 trucks at breakdown risk']);
        $this->assertSame('3 trucks at breakdown risk', $withSummary->summary());

        $withEntity = new OperationalEvidence(EventId::MAINTENANCE_OVERDUE, 'truck', 9, $this->at(), []);
        $this->assertSame('truck 9', $withEntity->summary());
    }

    public function test_all_value_objects_are_final_readonly(): void
    {
        foreach ([
            OperationalConclusion::class,
            OperationalFinding::class,
            OperationalRecommendation::class,
            OperationalEvidence::class,
            OperationalPriority::class,
        ] as $class) {
            $ref = new ReflectionClass($class);
            $this->assertTrue($ref->isFinal(), "{$class} must be final");
            $this->assertTrue($ref->isReadOnly(), "{$class} must be readonly");
        }
    }

    public function test_conclusion_is_immutable_at_runtime(): void
    {
        $c = $this->engine()->conclude([new MaintenanceOverdue($this->at(), 1, 'truck', [])])[0];

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $c->id = 'mutated';
    }

    public function test_recommendation_carries_catalog_metadata_verbatim(): void
    {
        $kpi = (new KpiRegistry)->find(KpiId::MNT_400);
        $c = $this->engine()->conclude([new MaintenanceOverdue($this->at(), 1, 'truck', [])])[0];

        $this->assertSame($kpi->businessDecision(), $c->decision());
        $this->assertSame($kpi->requiredAction(), $c->requiredAction());
        $this->assertSame($kpi->drillDown(), $c->drillDownTarget());
        $this->assertInstanceOf(OperationalRecommendation::class, $c->recommendation());
        $this->assertInstanceOf(OperationalPriority::class, $c->priority());
        $this->assertInstanceOf(OperationalFinding::class, $c->finding());
    }

    public function test_transform_is_pure_and_repeatable(): void
    {
        $build = fn (): array => [new BillingBlocked($this->at(), 1, 'invoice_batch', ['count' => 2, 'subject' => 'tickets'])];

        $this->assertEquals($this->engine()->conclude($build()), $this->engine()->conclude($build()));
    }
}
