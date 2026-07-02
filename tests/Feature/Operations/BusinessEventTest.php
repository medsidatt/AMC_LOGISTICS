<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Events\BusinessEventSeverity;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\BusinessOwner;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\Events\ObjectiveReached;
use App\Domain\Operations\Events\TruckUnavailable;
use DateTimeImmutable;
use ReflectionClass;
use Tests\TestCase;

/**
 * R1.4 — Business Events are immutable operational facts: construction, getters,
 * serialization, and readonly immutability. No behaviour change (pure domain layer).
 */
class BusinessEventTest extends TestCase
{
    private const EVENTS = [
        \App\Domain\Operations\Events\TruckUnavailable::class,
        \App\Domain\Operations\Events\MaintenanceOverdue::class,
        \App\Domain\Operations\Events\MaintenanceWarning::class,
        \App\Domain\Operations\Events\WeightAnomalyDetected::class,
        \App\Domain\Operations\Events\FuelConsumptionAbnormal::class,
        \App\Domain\Operations\Events\InspectionExpired::class,
        \App\Domain\Operations\Events\InspectionDue::class,
        \App\Domain\Operations\Events\DispatchDelayed::class,
        \App\Domain\Operations\Events\DispatchCompleted::class,
        \App\Domain\Operations\Events\MissingTransportTicket::class,
        \App\Domain\Operations\Events\GPSLoadWithoutTicket::class,
        \App\Domain\Operations\Events\BillingBlocked::class,
        \App\Domain\Operations\Events\ObjectiveBehindSchedule::class,
        \App\Domain\Operations\Events\ObjectiveReached::class,
        \App\Domain\Operations\Events\CapacityReduced::class,
        \App\Domain\Operations\Events\DriverDisciplineLow::class,
    ];

    public function test_construction_and_getters(): void
    {
        $at = new DateTimeImmutable('2030-01-01T08:30:00+00:00');
        $event = new TruckUnavailable($at, 12, 'truck', ['matricule' => 'AMC-001']);

        $this->assertInstanceOf(BusinessEventInterface::class, $event);
        $this->assertSame(EventId::TRUCK_UNAVAILABLE, $event->id());
        $this->assertSame($at, $event->occurredAt());
        $this->assertSame(BusinessOwner::DISPATCH, $event->owner());
        $this->assertSame(BusinessEventSeverity::CRITICAL, $event->severity());
        $this->assertSame(BusinessImpact::OPERATIONAL, $event->businessImpact());
        $this->assertSame('Reassign or call the driver', $event->requiredAction());
        $this->assertSame(12, $event->entityId());
        $this->assertSame('truck', $event->entityType());
        $this->assertSame(['matricule' => 'AMC-001'], $event->payload());
    }

    public function test_serialization_to_array(): void
    {
        $at = new DateTimeImmutable('2030-01-01T08:30:00+00:00');
        $event = new TruckUnavailable($at, 12, 'truck', ['matricule' => 'AMC-001']);

        $this->assertSame([
            'id' => 'truck_unavailable',
            'occurred_at' => '2030-01-01T08:30:00+00:00',
            'owner' => 'dispatch',
            'severity' => 'critical',
            'business_impact' => 'operational',
            'required_action' => 'Reassign or call the driver',
            'entity_id' => 12,
            'entity_type' => 'truck',
            'payload' => ['matricule' => 'AMC-001'],
        ], $event->toArray());
    }

    public function test_positive_event_severity_and_owner(): void
    {
        $event = new ObjectiveReached(new DateTimeImmutable('2030-01-01T00:00:00+00:00'), 1, 'objective', []);
        $this->assertSame(BusinessEventSeverity::INFORMATIONAL, $event->severity());
        $this->assertSame(BusinessOwner::OPERATIONS, $event->owner());
        $this->assertSame('None', $event->requiredAction());
    }

    public function test_every_event_is_final_readonly_and_unique(): void
    {
        $ids = [];
        foreach (self::EVENTS as $class) {
            $ref = new ReflectionClass($class);
            $this->assertTrue($ref->isFinal(), "{$class} must be final");
            $this->assertTrue($ref->isReadOnly(), "{$class} must be readonly");

            /** @var BusinessEventInterface $instance */
            $instance = new $class(new DateTimeImmutable('2030-01-01T00:00:00+00:00'), 1, 'entity', []);
            $this->assertInstanceOf(BusinessEventInterface::class, $instance);
            $ids[] = $instance->id()->value;
        }

        $this->assertSame(count($ids), count(array_unique($ids)), 'every event has a unique EventId');
        $this->assertCount(16, $ids);
    }

    public function test_readonly_blocks_mutation(): void
    {
        $event = new TruckUnavailable(new DateTimeImmutable('2030-01-01T00:00:00+00:00'), 1, 'truck', []);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $event->entityType = 'mutated';
    }
}
