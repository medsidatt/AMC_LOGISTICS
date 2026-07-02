<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\CommandCenters\Contracts\BusinessEventSource;
use App\Domain\Operations\CommandCenters\DerivedBusinessEventSource;
use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Events\Derivers\ClockDerivationContextFactory;
use App\Domain\Operations\Events\Derivers\Contracts\BusinessEventDeriver;
use App\Domain\Operations\Events\Derivers\Contracts\DerivationContextFactory;
use App\Domain\Operations\Events\Derivers\DerivationContext;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\Events\InspectionExpired;
use App\Domain\Operations\Events\MaintenanceOverdue;
use App\Domain\Operations\Events\TruckUnavailable;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * R3.2 / R3.2.1 — DerivedBusinessEventSource is the single producer of Business Events and a
 * PURE orchestrator: it takes one context from the DerivationContextFactory (it no longer
 * creates it), invokes every deriver exactly once, and returns the merged, de-duplicated
 * stream in stable order. Filters/prioritises/groups/sorts nothing. These tests use fake
 * derivers + a fixed factory to isolate that composition (DB-free, clock-free).
 */
class DerivedBusinessEventSourceTest extends TestCase
{
    private const AT = '2026-06-30T08:00:00+00:00';

    private const NOW = '2026-07-01T12:00:00+00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::AT);
    }

    private function context(): DerivationContext
    {
        return new DerivationContext(
            CarbonImmutable::parse(self::NOW),
            CarbonImmutable::parse('2026-07-01T00:00:00+00:00'),
            CarbonImmutable::parse('2026-07-31T23:59:59+00:00'),
        );
    }

    /** A fixed factory that hands out one shared context and counts its calls. */
    private function factory(): DerivationContextFactory
    {
        return new class($this->context()) implements DerivationContextFactory
        {
            public int $calls = 0;

            public function __construct(private DerivationContext $context) {}

            public function current(): DerivationContext
            {
                $this->calls++;

                return $this->context;
            }
        };
    }

    /** A fake deriver that records its invocations + the context it received. */
    private function deriver(array $events): BusinessEventDeriver
    {
        return new class($events) implements BusinessEventDeriver
        {
            public int $calls = 0;

            /** @var list<DerivationContext> */
            public array $contexts = [];

            /** @param list<BusinessEventInterface> $events */
            public function __construct(private array $events) {}

            public function derive(DerivationContext $context): array
            {
                $this->calls++;
                $this->contexts[] = $context;

                return $this->events;
            }
        };
    }

    // ── Container wiring ────────────────────────────────────────────────────────────

    public function test_source_and_factory_resolve_through_the_container(): void
    {
        $this->assertInstanceOf(DerivedBusinessEventSource::class, app(BusinessEventSource::class));
        $this->assertInstanceOf(BusinessEventSource::class, app(BusinessEventSource::class));
        $this->assertInstanceOf(ClockDerivationContextFactory::class, app(DerivationContextFactory::class));
    }

    // ── Orchestration ───────────────────────────────────────────────────────────────

    public function test_context_is_created_by_the_factory_exactly_once_and_shared(): void
    {
        $factory = $this->factory();
        $a = $this->deriver([new MaintenanceOverdue($this->at(), 1, 'truck', [])]);
        $b = $this->deriver([new InspectionExpired($this->at(), 2, 'truck', [])]);

        $this->toList((new DerivedBusinessEventSource($factory, [$a, $b]))->events());

        $this->assertSame(1, $factory->calls, 'the source asks the factory for the context exactly once');
        $this->assertSame(1, $a->calls, 'deriver A invoked once');
        $this->assertSame(1, $b->calls, 'deriver B invoked once');
        // The one factory-built context is shared across every deriver.
        $this->assertSame($a->contexts[0], $b->contexts[0]);
        $this->assertSame($this->context()->asOf->toIso8601String(), $a->contexts[0]->asOf->toIso8601String());
    }

    public function test_events_are_merged_in_stable_deriver_order(): void
    {
        $a = $this->deriver([new MaintenanceOverdue($this->at(), 1, 'truck', [])]);
        $b = $this->deriver([new TruckUnavailable($this->at(), 5, 'truck', [])]);

        $events = $this->toList((new DerivedBusinessEventSource($this->factory(), [$a, $b]))->events());

        $ids = array_map(fn (BusinessEventInterface $e): EventId => $e->id(), $events);
        $this->assertSame([EventId::MAINTENANCE_OVERDUE, EventId::TRUCK_UNAVAILABLE], $ids);
    }

    public function test_duplicate_events_are_removed_keeping_the_first(): void
    {
        // Two derivers surface the same fact (same event + entity); it must appear once.
        $a = $this->deriver([new MaintenanceOverdue($this->at(), 1, 'truck', ['from' => 'a'])]);
        $b = $this->deriver([
            new MaintenanceOverdue($this->at(), 1, 'truck', ['from' => 'b']), // duplicate key
            new InspectionExpired($this->at(), 2, 'truck', []),
        ]);

        $events = $this->toList((new DerivedBusinessEventSource($this->factory(), [$a, $b]))->events());

        $this->assertCount(2, $events);
        $this->assertSame(EventId::MAINTENANCE_OVERDUE, $events[0]->id());
        $this->assertSame(['from' => 'a'], $events[0]->payload()); // first one kept
        $this->assertSame(EventId::INSPECTION_EXPIRED, $events[1]->id());
    }

    public function test_output_is_deterministic(): void
    {
        $build = fn (): DerivedBusinessEventSource => new DerivedBusinessEventSource($this->factory(), [
            $this->deriver([new MaintenanceOverdue($this->at(), 1, 'truck', [])]),
            $this->deriver([new InspectionExpired($this->at(), 2, 'truck', [])]),
        ]);

        $this->assertEquals($this->toList($build()->events()), $this->toList($build()->events()));
    }

    public function test_empty_derivers_yield_no_events(): void
    {
        $events = $this->toList((new DerivedBusinessEventSource($this->factory(), [$this->deriver([]), $this->deriver([])]))->events());

        $this->assertSame([], $events);
    }

    // ── Factory (owns the clock + period) ───────────────────────────────────────────

    public function test_clock_factory_builds_the_current_month_context_from_the_clock(): void
    {
        Carbon::setTestNow(self::NOW);

        $context = (new ClockDerivationContextFactory)->current();

        $this->assertSame('2026-07-01T12:00:00+00:00', $context->asOf->toIso8601String());
        $this->assertSame('2026-07-01T00:00:00+00:00', $context->periodFrom->toIso8601String());
        $this->assertSame('2026-07-31T23:59:59+00:00', $context->periodTo->toIso8601String());
    }

    public function test_source_does_not_create_the_context_itself(): void
    {
        // Boundary guard: the orchestrator must not read the clock or build periods.
        $source = file_get_contents(app_path('Domain/Operations/CommandCenters/DerivedBusinessEventSource.php'));
        foreach (['CarbonImmutable', 'now(', 'startOfMonth', 'new DerivationContext'] as $needle) {
            $this->assertStringNotContainsString($needle, $source, "DerivedBusinessEventSource must not reference {$needle}");
        }
    }

    /** @return list<BusinessEventInterface> */
    private function toList(iterable $events): array
    {
        return is_array($events) ? array_values($events) : iterator_to_array($events, false);
    }
}
