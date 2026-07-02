<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\CommandCenters\Contracts\BusinessEventSource;
use App\Domain\Operations\CommandCenters\Contracts\ExecutiveCommandCenterInterface;
use App\Domain\Operations\CommandCenters\DerivedBusinessEventSource;
use App\Domain\Operations\CommandCenters\Executive\ExecutiveCommandCenter;
use App\Domain\Operations\CommandCenters\Executive\ExecutiveDashboardResponse;
use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Events\BillingBlocked;
use App\Domain\Operations\Events\InspectionExpired;
use App\Domain\Operations\Events\MaintenanceOverdue;
use App\Domain\Operations\Events\MissingTransportTicket;
use App\Domain\Operations\Intelligence\Contracts\OperationalIntelligenceInterface;
use App\Domain\Operations\Intelligence\OperationalIntelligence;
use App\Domain\Operations\KPI\KpiRegistry;
use App\Domain\Operations\Translators\Executive\ExecutiveTranslator;
use App\Http\Controllers\ExecutiveDashboardController;
use Carbon\Carbon;
use DateTimeImmutable;
use Inertia\Response;
use ReflectionClass;
use Tests\TestCase;

/**
 * R2.1 — the Executive Command Center orchestrates the frozen pipeline
 * (facts → Operational Intelligence → Executive Translator → response) and contains ZERO
 * business logic. These characterization tests pin: deterministic + immutable output, each
 * layer invoked exactly once, no dropped / duplicated conclusions, no forbidden dependency
 * in the command center or controller, and a logic-free controller.
 */
class ExecutiveCommandCenterTest extends TestCase
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

    /** Four facts spanning four owners → four conclusions. */
    private function events(): array
    {
        return [
            new BillingBlocked($this->at(), 7, 'invoice_batch', ['count' => 14, 'subject' => 'incomplete tickets']),
            new MaintenanceOverdue($this->at(), 11, 'truck', ['count' => 3, 'subject' => 'trucks at breakdown risk']),
            new InspectionExpired($this->at(), 15, 'truck', ['summary' => '2 trucks legally blocked']),
            new MissingTransportTicket($this->at(), 2, 'load', ['count' => 5, 'subject' => 'missing tickets']),
        ];
    }

    /** @param array<BusinessEventInterface> $events */
    private function provider(array $events): BusinessEventSource
    {
        return new class($events) implements BusinessEventSource
        {
            public int $calls = 0;

            /** @param array<BusinessEventInterface> $events */
            public function __construct(private array $events) {}

            public function events(): iterable
            {
                $this->calls++;

                return $this->events;
            }
        };
    }

    /** A real engine wrapped so we can count how often conclude() is called. */
    private function countingIntelligence(): OperationalIntelligenceInterface
    {
        return new class(new OperationalIntelligence(new KpiRegistry)) implements OperationalIntelligenceInterface
        {
            public int $calls = 0;

            public function __construct(private OperationalIntelligenceInterface $inner) {}

            public function conclude(iterable $events): array
            {
                $this->calls++;

                return $this->inner->conclude($events);
            }
        };
    }

    private function center(BusinessEventSource $provider, ?OperationalIntelligenceInterface $intelligence = null): ExecutiveCommandCenter
    {
        return new ExecutiveCommandCenter(
            $provider,
            $intelligence ?? new OperationalIntelligence(new KpiRegistry),
            new ExecutiveTranslator,
        );
    }

    // ── Container / contract ────────────────────────────────────────────────────────

    /** An in-memory empty source keeps the command-center unit tests off the database. */
    private function emptySource(): BusinessEventSource
    {
        return new class implements BusinessEventSource
        {
            public function events(): iterable
            {
                return [];
            }
        };
    }

    public function test_command_center_resolves_through_the_container(): void
    {
        $this->app->instance(BusinessEventSource::class, $this->emptySource());
        $center = app(ExecutiveCommandCenterInterface::class);

        $this->assertInstanceOf(ExecutiveCommandCenter::class, $center);
        $this->assertInstanceOf(ExecutiveDashboardResponse::class, $center->dashboard());
    }

    public function test_default_event_source_is_the_derived_source(): void
    {
        // R3.2 wired the real producer as the single default source (no dashboard() call →
        // no DB): the container resolves BusinessEventSource to DerivedBusinessEventSource.
        $this->assertInstanceOf(DerivedBusinessEventSource::class, app(BusinessEventSource::class));
    }

    // ── Determinism ─────────────────────────────────────────────────────────────────

    public function test_output_is_deterministic_for_the_same_facts(): void
    {
        Carbon::setTestNow(self::NOW);
        $events = $this->events();

        $a = $this->center($this->provider($events))->dashboard();
        $b = $this->center($this->provider($events))->dashboard();

        $this->assertEquals($a, $b);
        $this->assertEquals($a->toArray(), $b->toArray());
    }

    // ── Immutability ────────────────────────────────────────────────────────────────

    public function test_response_is_final_readonly(): void
    {
        $ref = new ReflectionClass(ExecutiveDashboardResponse::class);
        $this->assertTrue($ref->isFinal());
        $this->assertTrue($ref->isReadOnly());
    }

    public function test_response_is_immutable_at_runtime(): void
    {
        Carbon::setTestNow(self::NOW);
        $response = $this->center($this->provider($this->events()))->dashboard();

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $response->version = 99;
    }

    // ── Each layer invoked exactly once ─────────────────────────────────────────────

    public function test_each_pipeline_layer_is_invoked_exactly_once(): void
    {
        Carbon::setTestNow(self::NOW);
        $provider = $this->provider($this->events());
        $intelligence = $this->countingIntelligence();

        $this->center($provider, $intelligence)->dashboard();

        $this->assertSame(1, $provider->calls, 'event source called exactly once');
        $this->assertSame(1, $intelligence->calls, 'intelligence.conclude called exactly once');
    }

    // ── No dropped / no duplicated conclusions ──────────────────────────────────────

    public function test_no_conclusion_is_dropped(): void
    {
        Carbon::setTestNow(self::NOW);
        $response = $this->center($this->provider($this->events()))->dashboard();

        // Four mapped facts → four conclusions, all present in the all-covering product.
        $this->assertSame(4, $response->total());
        $this->assertSame(4, $response->summary()->total());
        $this->assertSame(4, $response->priorities()->count());
    }

    public function test_no_conclusion_is_duplicated(): void
    {
        Carbon::setTestNow(self::NOW);
        $response = $this->center($this->provider($this->events()))->dashboard();

        $ids = array_column($response->toArray()['priorities'], 'id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'every conclusion appears once');
    }

    // ── Response shape ──────────────────────────────────────────────────────────────

    public function test_response_array_is_presentation_ready(): void
    {
        Carbon::setTestNow(self::NOW);
        $array = $this->center($this->provider($this->events()))->dashboard()->toArray();

        $this->assertSame(ExecutiveDashboardResponse::VERSION, $array['version']);
        $this->assertSame('executive', $array['commandCenter']);
        $this->assertSame('2026-07-01T12:00:00+00:00', $array['generatedAt']);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('alerts', $array);
        $this->assertArrayHasKey('priorities', $array);
        $this->assertSame(4, $array['summary']['total']);

        // Cards are primitive arrays (no value objects leak to the wire).
        foreach ($array['priorities'] as $card) {
            $this->assertIsString($card['id']);
            $this->assertIsString($card['kpi']);
            $this->assertIsInt($card['priorityRank']);
            $this->assertIsBool($card['immediate']);
        }
    }

    // ── No forbidden dependencies (command center + DTO) ─────────────────────────────

    public function test_command_center_has_no_forbidden_dependency(): void
    {
        $forbidden = [
            'Domain\\Operations\\Calculations' => 'calculators',
            'Domain\\Operations\\ReadModels' => 'read models',
            'Domain\\Operations\\Events\\' => 'business events',
            'KPI\\KpiRegistry' => 'the KPI registry',
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'DB::' => 'the DB facade',
            'config(' => 'config()',
            'env(' => 'env()',
        ];

        $files = [
            app_path('Domain/Operations/CommandCenters/Executive/ExecutiveCommandCenter.php'),
            app_path('Domain/Operations/CommandCenters/Executive/ExecutiveDashboardResponse.php'),
        ];

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString($needle, $contents, basename($path)." must not reference {$label}");
            }
        }
    }

    // ── Controller carries no business logic ────────────────────────────────────────

    public function test_controller_only_delegates_to_the_command_center(): void
    {
        $contents = file_get_contents(app_path('Http/Controllers/ExecutiveDashboardController.php'));

        // It depends on the command center abstraction and the HTTP boundary only.
        $this->assertStringContainsString('ExecutiveCommandCenterInterface', $contents);
        $this->assertStringContainsString('Inertia', $contents);

        foreach (['Models', 'DB::', 'Illuminate\\Database', '::query(', 'Calculations', 'ReadModels', 'TransportTracking', 'Truck::'] as $needle) {
            $this->assertStringNotContainsString($needle, $contents, "controller must not reference {$needle}");
        }
    }

    public function test_controller_resolves_and_produces_an_inertia_response(): void
    {
        Carbon::setTestNow(self::NOW);

        $controller = app(ExecutiveDashboardController::class);
        $this->assertInstanceOf(ExecutiveDashboardController::class, $controller);
        $this->assertInstanceOf(Response::class, $controller->index());
    }
}
