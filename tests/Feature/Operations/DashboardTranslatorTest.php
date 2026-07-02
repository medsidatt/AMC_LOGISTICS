<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Events\BusinessEventSeverity;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\Intelligence\OperationalConclusion;
use App\Domain\Operations\Intelligence\OperationalEvidence;
use App\Domain\Operations\Intelligence\OperationalFinding;
use App\Domain\Operations\Intelligence\OperationalPriority;
use App\Domain\Operations\Intelligence\OperationalRecommendation;
use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\KPI\Enums\KpiId;
use App\Domain\Operations\KPI\Enums\KpiOwner;
use App\Domain\Operations\Translators\Contracts\DashboardTranslatorInterface;
use App\Domain\Operations\Translators\Contracts\DashboardView;
use App\Domain\Operations\Translators\Dispatch\DispatchTranslator;
use App\Domain\Operations\Translators\Executive\ExecutiveTranslator;
use App\Domain\Operations\Translators\Finance\FinanceTranslator;
use App\Domain\Operations\Translators\Fleet\FleetTranslator;
use App\Domain\Operations\Translators\Hse\HSETranslator;
use App\Domain\Operations\Translators\Maintenance\MaintenanceTranslator;
use App\Domain\Operations\Translators\Operations\OperationsTranslator;
use App\Domain\Operations\Translators\Presentation\PresentationCard;
use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

/**
 * R1.7 — Dashboard Translators are the Presentation Translation Layer. They receive ONLY a
 * list of Operational Conclusions and group / order / rename / format / aggregate them into
 * immutable, presentation-neutral value objects. They never calculate, score, rank by
 * formula, derive events, read KPIs, or query anything.
 *
 * These characterization tests pin: immutable outputs, deterministic translation, urgency
 * ordering, KPI grouping, no dropped and no duplicated conclusions, and the "no forbidden
 * dependency" boundary across the whole layer.
 */
class DashboardTranslatorTest extends TestCase
{
    private const AT = '2026-06-30T08:00:00+00:00';

    /** The seven translators, one per command center. */
    private const TRANSLATORS = [
        ExecutiveTranslator::class,
        OperationsTranslator::class,
        FleetTranslator::class,
        DispatchTranslator::class,
        MaintenanceTranslator::class,
        HSETranslator::class,
        FinanceTranslator::class,
    ];

    private function at(string $offset = 'PT0S'): DateTimeImmutable
    {
        return (new DateTimeImmutable(self::AT))->add(new \DateInterval($offset));
    }

    /**
     * Build a conclusion directly (bypassing the engine) so the test controls severity,
     * owner, KPI, and timestamp — the inputs the translators group and order by.
     */
    private function conclusion(
        KpiId $kpi,
        EventId $event,
        KpiOwner $owner,
        BusinessEventSeverity $severity,
        BusinessImpact $impact,
        string $entityType,
        int $entityId,
        DateTimeImmutable $at,
        array $facts = [],
    ): OperationalConclusion {
        $evidence = new OperationalEvidence($event, $entityType, $entityId, $at, $facts);
        $finding = new OperationalFinding($kpi, $event, $owner, $severity, $impact, "Question for {$kpi->value}?", $evidence);
        $recommendation = new OperationalRecommendation('Decide something', 'Do the action', 'Go to the queue');

        return new OperationalConclusion(
            "{$event->value}:{$entityType}:{$entityId}",
            $finding,
            $recommendation,
            OperationalPriority::fromSeverity($severity),
            "{$kpi->value} — {$evidence->summary()}.",
            $at,
        );
    }

    /** A realistic mixed slice spanning every owner and both immediate and warning severities. */
    private function mixedConclusions(): array
    {
        return [
            $this->conclusion(KpiId::OPS_004, EventId::MISSING_TRANSPORT_TICKET, KpiOwner::OPERATIONS, BusinessEventSeverity::HIGH, BusinessImpact::FINANCIAL, 'load', 2, $this->at('PT2H'), ['count' => 3, 'subject' => 'missing tickets']),
            $this->conclusion(KpiId::OPS_001, EventId::OBJECTIVE_BEHIND_SCHEDULE, KpiOwner::OPERATIONS, BusinessEventSeverity::CRITICAL, BusinessImpact::OPERATIONAL, 'objective', 1, $this->at('PT1H')),
            $this->conclusion(KpiId::OPS_006, EventId::DRIVER_DISCIPLINE_LOW, KpiOwner::OPERATIONS, BusinessEventSeverity::MEDIUM, BusinessImpact::OPERATIONAL, 'driver', 5, $this->at('PT3H')),
            $this->conclusion(KpiId::FIN_100, EventId::BILLING_BLOCKED, KpiOwner::FINANCE, BusinessEventSeverity::HIGH, BusinessImpact::FINANCIAL, 'invoice_batch', 7, $this->at('PT30M')),
            $this->conclusion(KpiId::FLT_201, EventId::CAPACITY_REDUCED, KpiOwner::FLEET, BusinessEventSeverity::HIGH, BusinessImpact::PLANNING, 'fleet', 9, $this->at('PT90M')),
            $this->conclusion(KpiId::MNT_400, EventId::MAINTENANCE_OVERDUE, KpiOwner::MAINTENANCE, BusinessEventSeverity::CRITICAL, BusinessImpact::SAFETY, 'truck', 11, $this->at('PT15M')),
            $this->conclusion(KpiId::MNT_401, EventId::MAINTENANCE_WARNING, KpiOwner::MAINTENANCE, BusinessEventSeverity::MEDIUM, BusinessImpact::PLANNING, 'truck', 12, $this->at('PT4H')),
            $this->conclusion(KpiId::DSP_300, EventId::TRUCK_UNAVAILABLE, KpiOwner::DISPATCH, BusinessEventSeverity::CRITICAL, BusinessImpact::OPERATIONAL, 'truck', 13, $this->at('PT45M')),
            $this->conclusion(KpiId::HSE_500, EventId::INSPECTION_EXPIRED, KpiOwner::HSE, BusinessEventSeverity::CRITICAL, BusinessImpact::LEGAL, 'truck', 15, $this->at('PT10M')),
            $this->conclusion(KpiId::HSE_501, EventId::INSPECTION_DUE, KpiOwner::HSE, BusinessEventSeverity::MEDIUM, BusinessImpact::LEGAL, 'inspection', 16, $this->at('PT5H')),
        ];
    }

    // ── Container / contract ────────────────────────────────────────────────────────

    public function test_every_translator_resolves_and_implements_the_contract(): void
    {
        foreach (self::TRANSLATORS as $class) {
            $translator = app($class);
            $this->assertInstanceOf($class, $translator);
            $this->assertInstanceOf(DashboardTranslatorInterface::class, $translator);
            $this->assertInstanceOf(DashboardView::class, $translator->translate([]));
        }
    }

    public function test_each_translator_reports_its_command_center(): void
    {
        $expected = [
            ExecutiveTranslator::class => CommandCenter::EXECUTIVE,
            OperationsTranslator::class => CommandCenter::OPERATIONS,
            FleetTranslator::class => CommandCenter::FLEET,
            DispatchTranslator::class => CommandCenter::DISPATCH,
            MaintenanceTranslator::class => CommandCenter::MAINTENANCE,
            HSETranslator::class => CommandCenter::HSE,
            FinanceTranslator::class => CommandCenter::FINANCE,
        ];

        foreach ($expected as $class => $center) {
            $this->assertSame($center, app($class)->translate([])->commandCenter());
        }
    }

    // ── Immutability ────────────────────────────────────────────────────────────────

    public function test_every_translator_output_class_is_final_readonly(): void
    {
        $files = File::allFiles(app_path('Domain/Operations/Translators'));

        foreach ($files as $file) {
            $class = $this->classFromFile($file->getPathname());
            $ref = new ReflectionClass($class);

            if ($ref->isInterface()) {
                continue; // the contract itself is not a VO
            }

            // Translators are stateless services; every other class is an immutable VO.
            if (str_ends_with($class, 'Translator') || str_ends_with($class, 'ConclusionArranger')) {
                $this->assertTrue($ref->isFinal(), "{$class} must be final");

                continue;
            }

            $this->assertTrue($ref->isFinal(), "{$class} must be final");
            $this->assertTrue($ref->isReadOnly(), "{$class} must be readonly");
        }
    }

    public function test_a_card_is_immutable_at_runtime(): void
    {
        $card = PresentationCard::fromConclusion($this->mixedConclusions()[0]);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — proving immutability at runtime
        $card->kpiCode = 'mutated';
    }

    // ── No forbidden dependencies ───────────────────────────────────────────────────

    public function test_no_translator_touches_a_forbidden_dependency(): void
    {
        // Translators may see ONLY Intelligence conclusions, their own VOs, and the KPI/Event
        // ENUM vocabulary the conclusions already expose. Everything below is forbidden.
        $forbidden = [
            'Domain\\Operations\\Calculations' => 'calculators',
            'Domain\\Operations\\ReadModels' => 'read models',
            'Domain\\Operations\\Events\\' => 'business events',
            'KPI\\KpiRegistry' => 'the KPI registry',
            'KPI\\KpiDefinition' => 'KPI definitions',
            'App\\Models' => 'eloquent models',
            'Illuminate\\Database' => 'the database',
            'Illuminate\\Support\\Facades\\DB' => 'the DB facade',
            'Illuminate\\Http' => 'HTTP',
            'config(' => 'config()',
            'env(' => 'env()',
            'DB::' => 'the DB facade',
        ];

        foreach (File::allFiles(app_path('Domain/Operations/Translators')) as $file) {
            $contents = $file->getContents();
            foreach ($forbidden as $needle => $label) {
                $this->assertStringNotContainsString(
                    $needle,
                    $contents,
                    "{$file->getFilename()} must not reference {$label}",
                );
            }
        }
    }

    // ── Determinism ─────────────────────────────────────────────────────────────────

    public function test_translation_is_deterministic(): void
    {
        foreach (self::TRANSLATORS as $class) {
            $translator = app($class);
            $this->assertEquals(
                $translator->translate($this->mixedConclusions()),
                $translator->translate($this->mixedConclusions()),
                "{$class} must be deterministic",
            );
        }
    }

    public function test_input_iteration_order_does_not_change_the_result(): void
    {
        $forward = $this->mixedConclusions();
        $reversed = array_reverse($this->mixedConclusions());

        // Ordering is by urgency, not input order, so a shuffled input yields the same view.
        $this->assertEquals(
            app(ExecutiveTranslator::class)->translate($forward),
            app(ExecutiveTranslator::class)->translate($reversed),
        );
    }

    // ── Ordering ────────────────────────────────────────────────────────────────────

    public function test_cards_are_ordered_by_urgency_then_time(): void
    {
        $priorities = app(ExecutiveTranslator::class)->translate($this->mixedConclusions())->priorities();

        $ranks = array_map(static fn (PresentationCard $c): int => $c->priorityRank(), $priorities->cards());
        $sorted = $ranks;
        sort($sorted);
        $this->assertSame($sorted, $ranks, 'priority ranks must be non-decreasing');

        // Within the critical band (rank 1), the earliest fact comes first.
        $criticals = array_values(array_filter($priorities->cards(), static fn (PresentationCard $c): bool => $c->priorityRank() === 1));
        $times = array_map(static fn (PresentationCard $c): int => $c->occurredAt()->getTimestamp(), $criticals);
        $sortedTimes = $times;
        sort($sortedTimes);
        $this->assertSame($sortedTimes, $times, 'equal-rank cards must be oldest-first');
    }

    // ── Grouping ────────────────────────────────────────────────────────────────────

    public function test_queues_group_by_kpi_and_label_with_the_business_question(): void
    {
        $conclusions = [
            $this->conclusion(KpiId::OPS_004, EventId::MISSING_TRANSPORT_TICKET, KpiOwner::OPERATIONS, BusinessEventSeverity::HIGH, BusinessImpact::FINANCIAL, 'load', 1, $this->at('PT1H')),
            $this->conclusion(KpiId::OPS_004, EventId::MISSING_TRANSPORT_TICKET, KpiOwner::OPERATIONS, BusinessEventSeverity::HIGH, BusinessImpact::FINANCIAL, 'load', 2, $this->at('PT2H')),
            $this->conclusion(KpiId::OPS_005, EventId::WEIGHT_ANOMALY_DETECTED, KpiOwner::OPERATIONS, BusinessEventSeverity::HIGH, BusinessImpact::FINANCIAL, 'load', 3, $this->at('PT3H')),
        ];

        $queues = app(OperationsTranslator::class)->translate($conclusions)->queues();

        $this->assertSame(2, $queues->count());
        $byKey = [];
        foreach ($queues->queues() as $queue) {
            $byKey[$queue->key()] = $queue;
        }
        $this->assertSame(2, $byKey[KpiId::OPS_004->value]->count());
        $this->assertSame(1, $byKey[KpiId::OPS_005->value]->count());
        $this->assertSame('Question for '.KpiId::OPS_004->value.'?', $byKey[KpiId::OPS_004->value]->label());
    }

    // ── No dropped / no duplicated conclusions ──────────────────────────────────────

    public function test_no_conclusion_is_dropped_by_the_all_covering_product(): void
    {
        $conclusions = $this->mixedConclusions();
        $expected = count($conclusions);

        $this->assertSame($expected, app(ExecutiveTranslator::class)->translate($conclusions)->priorities()->count());
        $this->assertSame($expected, app(OperationsTranslator::class)->translate($conclusions)->actions()->count());
        $this->assertSame($expected, app(OperationsTranslator::class)->translate($conclusions)->queues()->cardCount());
        $this->assertSame($expected, app(FleetTranslator::class)->translate($conclusions)->health()->total());
        $this->assertSame($expected, app(DispatchTranslator::class)->translate($conclusions)->actions()->count());
        $this->assertSame($expected, app(DispatchTranslator::class)->translate($conclusions)->queues()->cardCount());
        $this->assertSame($expected, app(MaintenanceTranslator::class)->translate($conclusions)->queues()->cardCount());
        $this->assertSame($expected, app(HSETranslator::class)->translate($conclusions)->compliance()->total());
        $this->assertSame($expected, app(FinanceTranslator::class)->translate($conclusions)->billing()->cardCount());
        $this->assertSame($expected, app(ExecutiveTranslator::class)->translate($conclusions)->summary()->total());
    }

    public function test_no_conclusion_is_duplicated_in_a_queue_grouping(): void
    {
        $conclusions = $this->mixedConclusions();
        $ids = array_map(static fn (OperationalConclusion $c): string => $c->id(), $conclusions);

        $seen = [];
        foreach (app(OperationsTranslator::class)->translate($conclusions)->queues()->queues() as $queue) {
            foreach ($queue->cards() as $card) {
                $seen[] = $card->conclusionId();
            }
        }

        sort($ids);
        sort($seen);
        $this->assertSame($ids, $seen, 'every conclusion appears exactly once across the queues');
    }

    // ── Immediacy split (problems vs warnings) ──────────────────────────────────────

    public function test_problems_are_immediate_and_warnings_are_the_complement(): void
    {
        $conclusions = $this->mixedConclusions();

        $problems = app(OperationsTranslator::class)->translate($conclusions)->problems()->cards();
        foreach ($problems as $card) {
            $this->assertTrue($card->isImmediate(), 'a problem must be immediate (critical/high)');
        }

        $warnings = app(MaintenanceTranslator::class)->translate($conclusions)->warnings()->cards();
        foreach ($warnings as $card) {
            $this->assertFalse($card->isImmediate(), 'a warning must be non-immediate');
        }

        // Fleet maintenance-owned = MNT-400 (critical) + MNT-401 (medium) = 2 cards.
        $this->assertSame(2, app(FleetTranslator::class)->translate($conclusions)->maintenance()->count());
        // Fleet-owned capacity = FLT-201 only.
        $this->assertSame(1, app(FleetTranslator::class)->translate($conclusions)->capacity()->count());
    }

    public function test_executive_summary_aggregates_across_every_owner(): void
    {
        $summary = app(ExecutiveTranslator::class)->translate($this->mixedConclusions())->summary();

        $this->assertSame(10, $summary->total());
        $this->assertSame(3, $summary->byOwner()[KpiOwner::OPERATIONS->value]); // OPS 004/001/006
        $this->assertSame(2, $summary->byOwner()[KpiOwner::MAINTENANCE->value]);
        $this->assertSame(2, $summary->byOwner()[KpiOwner::HSE->value]);
        $this->assertSame(1, $summary->byOwner()[KpiOwner::FINANCE->value]);
        $this->assertSame(1, $summary->byOwner()[KpiOwner::FLEET->value]);
        $this->assertSame(1, $summary->byOwner()[KpiOwner::DISPATCH->value]);
    }

    // ── Empty input ─────────────────────────────────────────────────────────────────

    public function test_empty_input_yields_empty_but_valid_views(): void
    {
        foreach (self::TRANSLATORS as $class) {
            $view = app($class)->translate([]);
            $this->assertSame(0, $view->total(), "{$class} on empty input must total 0");
        }
    }

    // ── Card fidelity ───────────────────────────────────────────────────────────────

    public function test_a_card_copies_conclusion_values_verbatim(): void
    {
        $conclusion = $this->conclusion(
            KpiId::FIN_100,
            EventId::BILLING_BLOCKED,
            KpiOwner::FINANCE,
            BusinessEventSeverity::HIGH,
            BusinessImpact::FINANCIAL,
            'invoice_batch',
            7,
            $this->at(),
            ['count' => 14, 'subject' => 'incomplete tickets'],
        );

        $card = PresentationCard::fromConclusion($conclusion);

        $this->assertSame($conclusion->id(), $card->conclusionId());
        $this->assertSame('KPI-FIN-100', $card->kpiCode());
        $this->assertSame('billing_blocked', $card->eventCode());
        $this->assertSame('high', $card->severityLabel());
        $this->assertSame('financial', $card->impactLabel());
        $this->assertSame('finance', $card->ownerLabel());
        $this->assertSame(2, $card->priorityRank());
        $this->assertTrue($card->isImmediate());
        $this->assertSame('invoice_batch', $card->entityType());
        $this->assertSame(7, $card->entityId());
        $this->assertSame('14 incomplete tickets', $card->headline());
        $this->assertSame($conclusion->requiredAction(), $card->requiredAction());
        $this->assertSame($conclusion->drillDownTarget(), $card->drillDownTarget());
        $this->assertSame(['count' => 14, 'subject' => 'incomplete tickets'], $card->evidenceFacts());
    }

    private function classFromFile(string $path): string
    {
        $normalized = str_replace([app_path().DIRECTORY_SEPARATOR, '/', '\\'], ['', '\\', '\\'], $path);

        return 'App\\'.substr($normalized, 0, -strlen('.php'));
    }
}
