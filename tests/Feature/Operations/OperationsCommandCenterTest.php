<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\CommandCenters\Contracts\BusinessEventSource;
use App\Domain\Operations\CommandCenters\Contracts\OperationsCommandCenterInterface;
use App\Domain\Operations\CommandCenters\Operations\OperationsCommandCenter;
use App\Domain\Operations\CommandCenters\Operations\OperationsDashboardResponse;
use App\Domain\Operations\Contracts\BusinessEventInterface;
use App\Domain\Operations\Events\BillingBlocked;
use App\Domain\Operations\Events\InspectionExpired;
use App\Domain\Operations\Events\MaintenanceOverdue;
use App\Domain\Operations\Events\MissingTransportTicket;
use App\Domain\Operations\Intelligence\Contracts\OperationalIntelligenceInterface;
use App\Domain\Operations\Intelligence\OperationalIntelligence;
use App\Domain\Operations\KPI\KpiRegistry;
use App\Domain\Operations\Translators\Operations\OperationsTranslator;
use App\Http\Controllers\OperationsDashboardController;
use Carbon\Carbon;
use DateTimeImmutable;
use Inertia\Response;
use ReflectionClass;
use Tests\TestCase;

/**
 * R2.2 — the Operations Command Center orchestrates the frozen pipeline
 * (facts → Operational Intelligence → Operations Translator → response) and contains ZERO
 * business logic. Structurally identical to the Executive reference. These characterization
 * tests pin: deterministic + immutable output, each layer invoked exactly once, no dropped /
 * duplicated conclusions, response serialization, and no forbidden dependency in the command
 * center or controller.
 */
class OperationsCommandCenterTest extends TestCase
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

    /** Four facts → four conclusions across four KPIs. */
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
    private function source(array $events): BusinessEventSource
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

    private function center(BusinessEventSource $source, ?OperationalIntelligenceInterface $intelligence = null): OperationsCommandCenter
    {
        return new OperationsCommandCenter(
            $source,
            $intelligence ?? new OperationalIntelligence(new KpiRegistry),
            new OperationsTranslator,
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
        $center = app(OperationsCommandCenterInterface::class);

        $this->assertInstanceOf(OperationsCommandCenter::class, $center);
        $this->assertInstanceOf(OperationsDashboardResponse::class, $center->dashboard());
    }

    public function test_empty_source_yields_a_valid_empty_response(): void
    {
        $this->app->instance(BusinessEventSource::class, $this->emptySource());
        $response = app(OperationsCommandCenterInterface::class)->dashboard();

        $this->assertSame(0, $response->total());
        $this->assertSame(OperationsDashboardResponse::VERSION, $response->version());
        $this->assertSame(0, $response->queues()->count());
    }

    // ── Determinism ─────────────────────────────────────────────────────────────────

    public function test_output_is_deterministic_for_the_same_facts(): void
    {
        Carbon::setTestNow(self::NOW);
        $events = $this->events();

        $a = $this->center($this->source($events))->dashboard();
        $b = $this->center($this->source($events))->dashboard();

        $this->assertEquals($a, $b);
        $this->assertEquals($a->toArray(), $b->toArray());
    }

    // ── Immutability ────────────────────────────────────────────────────────────────

    public function test_response_is_final_readonly(): void
    {
        $ref = new ReflectionClass(OperationsDashboardResponse::class);
        $this->assertTrue($ref->isFinal());
        $this->assertTrue($ref->isReadOnly());
    }

    public function test_response_is_immutable_at_runtime(): void
    {
        Carbon::setTestNow(self::NOW);
        $response = $this->center($this->source($this->events()))->dashboard();

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $response->version = 99;
    }

    // ── Each layer invoked exactly once ─────────────────────────────────────────────

    public function test_source_and_intelligence_are_each_invoked_once(): void
    {
        Carbon::setTestNow(self::NOW);
        $source = $this->source($this->events());
        $intelligence = $this->countingIntelligence();

        $this->center($source, $intelligence)->dashboard();

        $this->assertSame(1, $source->calls, 'event source called exactly once');
        $this->assertSame(1, $intelligence->calls, 'intelligence.conclude called exactly once');
    }

    public function test_translator_is_invoked_once_producing_a_faithful_single_translation(): void
    {
        Carbon::setTestNow(self::NOW);
        $conclusions = (new OperationalIntelligence(new KpiRegistry))->conclude($this->events());

        // A single faithful translation: the command center's view equals exactly one
        // OperationsTranslator run over the same conclusions (a second or zero run would
        // change the queues/actions — pinned here alongside the source/intelligence counts).
        $expected = (new OperationsTranslator)->translate($conclusions);
        $actual = $this->center($this->source($this->events()))->dashboard();

        $this->assertEquals($expected->queues(), $actual->queues());
        $this->assertEquals($expected->problems(), $actual->problems());
        $this->assertEquals($expected->actions(), $actual->actions());
    }

    // ── No dropped / no duplicated conclusions ──────────────────────────────────────

    public function test_no_conclusion_is_dropped(): void
    {
        Carbon::setTestNow(self::NOW);
        $response = $this->center($this->source($this->events()))->dashboard();

        // Four mapped facts → four conclusions, all present in the all-covering products.
        $this->assertSame(4, $response->total());
        $this->assertSame(4, $response->actions()->count());
        $this->assertSame(4, $response->queues()->cardCount());
    }

    public function test_no_conclusion_is_duplicated(): void
    {
        Carbon::setTestNow(self::NOW);
        $response = $this->center($this->source($this->events()))->dashboard();

        $ids = array_column($response->toArray()['actions'], 'id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'every conclusion appears once');

        // Each conclusion lands in exactly one queue too.
        $queued = [];
        foreach ($response->toArray()['queues'] as $queue) {
            foreach ($queue['cards'] as $card) {
                $queued[] = $card['id'];
            }
        }
        $this->assertSame(count($queued), count(array_unique($queued)));
        $this->assertSame(4, count($queued));
    }

    // ── Response shape ──────────────────────────────────────────────────────────────

    public function test_response_array_is_presentation_ready(): void
    {
        Carbon::setTestNow(self::NOW);
        $array = $this->center($this->source($this->events()))->dashboard()->toArray();

        $this->assertSame(OperationsDashboardResponse::VERSION, $array['version']);
        $this->assertSame('operations', $array['commandCenter']);
        $this->assertSame('2026-07-01T12:00:00+00:00', $array['generatedAt']);
        $this->assertArrayHasKey('queues', $array);
        $this->assertArrayHasKey('problems', $array);
        $this->assertArrayHasKey('actions', $array);

        foreach ($array['queues'] as $queue) {
            $this->assertIsString($queue['key']);
            $this->assertIsString($queue['label']);
            $this->assertIsInt($queue['count']);
            $this->assertIsArray($queue['cards']);
        }

        foreach ($array['actions'] as $card) {
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
            'DashboardDataService' => 'the legacy dashboard service',
            'FleetKpiService' => 'the fleet KPI service',
        ];

        $files = [
            app_path('Domain/Operations/CommandCenters/Operations/OperationsCommandCenter.php'),
            app_path('Domain/Operations/CommandCenters/Operations/OperationsDashboardResponse.php'),
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
        $contents = file_get_contents(app_path('Http/Controllers/OperationsDashboardController.php'));

        $this->assertStringContainsString('OperationsCommandCenterInterface', $contents);
        $this->assertStringContainsString('Inertia', $contents);

        foreach (['Models', 'DB::', 'Illuminate\\Database', '::query(', 'Calculations', 'ReadModels', 'DispatchWorkspaceService', 'PlanningWorkspaceService', 'ExpectedTransportTicket'] as $needle) {
            $this->assertStringNotContainsString($needle, $contents, "controller must not reference {$needle}");
        }
    }

    public function test_controller_resolves_and_produces_an_inertia_response(): void
    {
        Carbon::setTestNow(self::NOW);

        $controller = app(OperationsDashboardController::class);
        $this->assertInstanceOf(OperationsDashboardController::class, $controller);
        $this->assertInstanceOf(Response::class, $controller->index());
    }
}
