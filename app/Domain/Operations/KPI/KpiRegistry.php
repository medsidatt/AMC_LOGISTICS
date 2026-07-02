<?php

namespace App\Domain\Operations\KPI;

use App\Domain\Operations\Contracts\BillingCalculatorInterface;
use App\Domain\Operations\Contracts\CapacityCalculatorInterface;
use App\Domain\Operations\Contracts\CycleCalculatorInterface;
use App\Domain\Operations\Contracts\DispatchCalculatorInterface;
use App\Domain\Operations\Contracts\InspectionCalculatorInterface;
use App\Domain\Operations\Contracts\MaintenanceCalculatorInterface;
use App\Domain\Operations\Contracts\ObjectiveCalculatorInterface;
use App\Domain\Operations\Contracts\ProductivityCalculatorInterface;
use App\Domain\Operations\Contracts\RotationCalculatorInterface;
use App\Domain\Operations\Contracts\UtilizationCalculatorInterface;
use App\Domain\Operations\Contracts\WeightCalculatorInterface;
use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\KPI\Contracts\KpiDefinitionInterface;
use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\KPI\Enums\KpiCategory;
use App\Domain\Operations\KPI\Enums\KpiDataSource;
use App\Domain\Operations\KPI\Enums\KpiId;
use App\Domain\Operations\KPI\Enums\KpiOwner;
use App\Domain\Operations\KPI\Enums\KpiRefreshStrategy;
use App\Domain\Operations\KPI\Enums\KpiSeverity;
use App\Domain\Operations\KPI\Enums\KpiUnit;
use App\Enums\OperationalParameterKey;
use InvalidArgumentException;

/**
 * The single authoritative source of KPI metadata (docs/kpi-catalog.md, frozen
 * architecture L4). It DESCRIBES KPIs and nothing else:
 *
 *   - never calculates            - never resolves a service
 *   - never executes a query      - never instantiates an event
 *   - never reads Eloquent        - never touches config()/env()
 *   - never reads a parameter     - never touches the UI / dashboards
 *
 * Definitions are immutable value objects built from hardcoded catalog metadata.
 * The Registry holds them in a process memo and returns them by stable identity.
 *
 * One KPI ID. One owner. One calculator. One source of truth.
 */
final class KpiRegistry
{
    /** @var array<string, KpiDefinition>|null id-value => definition, built once. */
    private ?array $definitions = null;

    /** @var array<string, KpiDefinition>|null event-value => emitting KPI, built once. */
    private ?array $eventIndex = null;

    /** Resolve one KPI by its stable id. */
    public function find(KpiId $id): KpiDefinitionInterface
    {
        $definition = $this->definitions()[$id->value] ?? null;

        if ($definition === null) {
            // Unreachable while the catalog is complete; guards against a future id gap.
            throw new InvalidArgumentException("No KPI definition registered for [{$id->value}].");
        }

        return $definition;
    }

    public function has(KpiId $id): bool
    {
        return isset($this->definitions()[$id->value]);
    }

    /**
     * The single active KPI that emits this Business Event, or null if none does.
     * The Registry owns the event→KPI mapping (one event, one emitter); consumers of
     * the same fact relate to the emitter through dependencies, never by also emitting.
     * A second emitter is an authoring error and fails fast.
     */
    public function byEvent(EventId $event): ?KpiDefinitionInterface
    {
        return $this->eventIndex()[$event->value] ?? null;
    }

    /** @return list<KpiDefinition> every KPI, catalog order. */
    public function all(): array
    {
        return array_values($this->definitions());
    }

    /** @return list<KpiDefinition> non-deprecated KPIs. */
    public function active(): array
    {
        return array_values(array_filter($this->all(), static fn (KpiDefinition $d): bool => $d->isActive()));
    }

    /** @return list<KpiDefinition> deprecated KPIs. */
    public function deprecated(): array
    {
        return array_values(array_filter($this->all(), static fn (KpiDefinition $d): bool => $d->deprecated()));
    }

    /** @return list<KpiDefinition> KPIs of CRITICAL severity. */
    public function critical(): array
    {
        return $this->bySeverity(KpiSeverity::CRITICAL);
    }

    /** @return list<KpiDefinition> */
    public function bySeverity(KpiSeverity $severity): array
    {
        return array_values(array_filter($this->all(), static fn (KpiDefinition $d): bool => $d->severity() === $severity));
    }

    /** @return list<KpiDefinition> KPIs owned by one department. */
    public function ownedBy(KpiOwner $owner): array
    {
        return array_values(array_filter($this->all(), static fn (KpiDefinition $d): bool => $d->owner() === $owner));
    }

    /** @return list<KpiDefinition> KPIs in one category. */
    public function inCategory(KpiCategory $category): array
    {
        return array_values(array_filter($this->all(), static fn (KpiDefinition $d): bool => $d->category() === $category));
    }

    /** @return list<KpiDefinition> KPIs displayed in one command center. */
    public function inCommandCenter(CommandCenter $center): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (KpiDefinition $d): bool => in_array($center, $d->commandCenters(), true),
        ));
    }

    /** @return array<string, list<KpiDefinition>> grouped by owner value. */
    public function byOwner(): array
    {
        return $this->groupBy(static fn (KpiDefinition $d): string => $d->owner()->value);
    }

    /** @return array<string, list<KpiDefinition>> grouped by category value. */
    public function byCategory(): array
    {
        return $this->groupBy(static fn (KpiDefinition $d): string => $d->category()->value);
    }

    /** @return array<string, list<KpiDefinition>> grouped by each command center it appears in. */
    public function byDashboard(): array
    {
        $groups = [];
        foreach ($this->all() as $definition) {
            foreach ($definition->commandCenters() as $center) {
                $groups[$center->value][] = $definition;
            }
        }

        return $groups;
    }

    /**
     * @param  callable(KpiDefinition): string  $key
     * @return array<string, list<KpiDefinition>>
     */
    private function groupBy(callable $key): array
    {
        $groups = [];
        foreach ($this->all() as $definition) {
            $groups[$key($definition)][] = $definition;
        }

        return $groups;
    }

    /** @return array<string, KpiDefinition> */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $map = [];
        foreach ($this->build() as $definition) {
            $map[$definition->id()->value] = $definition;
        }

        return $this->definitions = $map;
    }

    /** @return array<string, KpiDefinition> event-value => the one active KPI that emits it. */
    private function eventIndex(): array
    {
        if ($this->eventIndex !== null) {
            return $this->eventIndex;
        }

        $map = [];
        foreach ($this->active() as $kpi) {
            foreach ($kpi->businessEvents() as $event) {
                if (isset($map[$event->value])) {
                    throw new InvalidArgumentException(sprintf(
                        'Event [%s] is emitted by two KPIs ([%s] and [%s]); the catalog must name a single emitter.',
                        $event->value,
                        $map[$event->value]->id()->value,
                        $kpi->id()->value,
                    ));
                }
                $map[$event->value] = $kpi;
            }
        }

        return $this->eventIndex = $map;
    }

    /**
     * Every KPI in docs/kpi-catalog.md, defined exactly once. Hardcoded metadata only.
     *
     * Parameters reference EXISTING OperationalParameterKey cases. Catalog parameters
     * that are not yet provisioned (confidence/pace/productivity/utilization bands,
     * billable window, revenue rate, start deadline) are introduced with their KPI's
     * calculator in a later increment (ADR-008 — bands/rates are never invented early)
     * and are recorded in each KPI's notes until then.
     *
     * @return list<KpiDefinition>
     */
    private function build(): array
    {
        return [
            // ── Operations ──────────────────────────────────────────────────────────
            new KpiDefinition(
                id: KpiId::OPS_001,
                name: 'Objective Confidence',
                description: 'Tell Operations early whether the period objective will be met while there is still time to act.',
                businessQuestion: 'Will we reach the period objective?',
                businessDecision: 'Whether to allocate reserve trucks / reallocate capacity now.',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: ObjectiveCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING, KpiDataSource::FLEET],
                parameters: [OperationalParameterKey::TARGET_ROTATIONS, OperationalParameterKey::FISCAL_MONTH_START_DAY],
                thresholds: [],
                businessEvents: [EventId::OBJECTIVE_BEHIND_SCHEDULE],
                refreshStrategy: KpiRefreshStrategy::HOURLY,
                severity: KpiSeverity::CRITICAL,
                unit: KpiUnit::PERCENT,
                commandCenters: [CommandCenter::OPERATIONS, CommandCenter::EXECUTIVE],
                drillDown: 'Per-truck realisation vs target',
                requiredAction: 'Allocate reserve trucks or reallocate capacity',
                successCriteria: 'Projected volume ≥ objective within confidence band',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::FINANCIAL],
                dependencies: [KpiId::FLT_200, KpiId::DSP_300, KpiId::OPS_003],
                version: 1,
                deprecated: false,
                notes: 'Future parameter: objective_confidence_bands (introduced with the Objective Calculator confidence band).',
            ),
            new KpiDefinition(
                id: KpiId::OPS_002,
                name: 'Capacity Gap (Uncovered Volume)',
                description: 'Quantify the volume the current fleet cannot cover against the objective.',
                businessQuestion: 'How much volume is uncovered versus the objective?',
                businessDecision: 'Whether to add or reallocate capacity, or accept the shortfall.',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: CapacityCalculatorInterface::class,
                readModels: [KpiDataSource::FLEET, KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::DEFAULT_CAPACITY, OperationalParameterKey::TARGET_ROTATIONS, OperationalParameterKey::CYCLE_TIME_HOURS],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::HOURLY,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::TONNES,
                commandCenters: [CommandCenter::OPERATIONS],
                drillDown: 'Capacity breakdown by truck / lane',
                requiredAction: 'Add capacity, reallocate, or escalate',
                successCriteria: 'Uncovered volume = 0',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::PLANNING],
                dependencies: [KpiId::FLT_200, KpiId::FLT_201],
                version: 1,
                deprecated: false,
                notes: 'Consumes the CapacityReduced fact via its dependency on KPI-FLT-201 (the single emitter); it does not emit its own event (one event, one emitter).',
            ),
            new KpiDefinition(
                id: KpiId::OPS_003,
                name: 'Production Pace Today',
                description: "Show whether today's run-rate keeps the day on track.",
                businessQuestion: 'Are we on pace today?',
                businessDecision: 'Whether to push the quarry / dispatch more trucks now.',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: RotationCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::TARGET_ROTATIONS],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::REALTIME,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::PERCENT,
                commandCenters: [CommandCenter::OPERATIONS],
                drillDown: "Today's loads timeline",
                requiredAction: 'Push quarry or add dispatch',
                successCriteria: 'Run-rate ≥ daily pace band',
                failureImpact: [BusinessImpact::OPERATIONAL],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: 'Refresh is real-time (15 min). Future parameter: pace_band (introduced with the daily pace calculation).',
            ),
            new KpiDefinition(
                id: KpiId::OPS_004,
                name: 'Missing Loads (Unticketed)',
                description: 'Surface delivered loads seen by tracking that have no transport ticket and therefore cannot be billed.',
                businessQuestion: 'Which delivered loads have no ticket?',
                businessDecision: 'Whether to create the missing tickets before the billing window closes.',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: RotationCalculatorInterface::class,
                readModels: [KpiDataSource::DISPATCH, KpiDataSource::TRANSPORT_TRACKING],
                parameters: [],
                thresholds: [],
                businessEvents: [EventId::MISSING_TRANSPORT_TICKET],
                refreshStrategy: KpiRefreshStrategy::HOURLY,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::COUNT,
                commandCenters: [CommandCenter::OPERATIONS, CommandCenter::FINANCE],
                drillDown: 'Missing-loads queue (seeded ticket)',
                requiredAction: 'Create the missing ticket',
                successCriteria: '0 delivered loads without a ticket inside the billing window',
                failureImpact: [BusinessImpact::FINANCIAL],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: 'Also recomputed on ticket write. Future parameter: billable_window_days (introduced with the billing window calculation).',
            ),
            new KpiDefinition(
                id: KpiId::OPS_005,
                name: 'Weight Discrepancy Exposure',
                description: 'Flag loads whose loaded vs delivered weight differs beyond the daily operational tolerance — a billing and integrity risk.',
                businessQuestion: 'Which loads show a weighing problem?',
                businessDecision: 'Whether to verify the weighbridge ticket before invoicing.',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: WeightCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD],
                thresholds: [OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD],
                businessEvents: [EventId::WEIGHT_ANOMALY_DETECTED],
                refreshStrategy: KpiRefreshStrategy::REALTIME,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::COUNT,
                commandCenters: [CommandCenter::OPERATIONS],
                drillDown: 'Anomaly list',
                requiredAction: 'Verify the weighbridge ticket',
                successCriteria: '0 loads above the operational discrepancy threshold',
                failureImpact: [BusinessImpact::FINANCIAL, BusinessImpact::CUSTOMER],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: 'Operational threshold only (0.5 t). Fraud (300 kg) and sensor (150 kg) thresholds are distinct parameters owned by the same Weight Calculator (catalog §9 / ADR-002).',
            ),
            new KpiDefinition(
                id: KpiId::OPS_006,
                name: 'Driver Productivity',
                description: 'Identify drivers who need coaching or reassignment, based on outcome not activity.',
                businessQuestion: 'Which drivers need coaching or reassignment?',
                businessDecision: 'Whether to coach, retrain, or reassign a driver.',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: ProductivityCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::CYCLE_TIME_HOURS, OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD],
                thresholds: [OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::WEEKLY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::PERCENT,
                commandCenters: [CommandCenter::OPERATIONS],
                drillDown: 'Driver detail',
                requiredAction: 'Coach or reassign',
                successCriteria: 'Driver within productivity band',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::PLANNING],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: 'Future parameter: productivity_band (introduced with the productivity scoring band).',
            ),
            new KpiDefinition(
                id: KpiId::OPS_007,
                name: 'Provider (Quarry) Performance',
                description: 'Reveal which loading source slows the cycle.',
                businessQuestion: 'Which quarry slows loading?',
                businessDecision: 'Whether to call the provider or shift volume to another source.',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: ProductivityCalculatorInterface::class,
                readModels: [KpiDataSource::DISPATCH, KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::CYCLE_TIME_HOURS],
                thresholds: [OperationalParameterKey::CYCLE_TIME_HOURS],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::HOURS,
                commandCenters: [CommandCenter::OPERATIONS],
                drillDown: 'Provider detail',
                requiredAction: 'Call the provider or rebalance volume',
                successCriteria: 'Loading turnaround within target',
                failureImpact: [BusinessImpact::OPERATIONAL],
                dependencies: [KpiId::OPS_008],
                version: 1,
                deprecated: false,
                notes: '',
            ),
            new KpiDefinition(
                id: KpiId::OPS_008,
                name: 'Average Turnaround',
                description: 'Measure the round-trip time that governs how many loads a truck can do.',
                businessQuestion: 'Which lane or route is slow?',
                businessDecision: 'Whether to investigate a bottleneck (loading, road, unloading).',
                category: KpiCategory::OPERATIONS,
                owner: KpiOwner::OPERATIONS,
                calculatorInterface: CycleCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::CYCLE_TIME_HOURS],
                thresholds: [OperationalParameterKey::CYCLE_TIME_HOURS],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::HOURS,
                commandCenters: [CommandCenter::OPERATIONS],
                drillDown: 'Route detail',
                requiredAction: 'Investigate the bottleneck',
                successCriteria: 'Turnaround within cycle_time_hours',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::PLANNING],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: '',
            ),

            // ── Finance ─────────────────────────────────────────────────────────────
            new KpiDefinition(
                id: KpiId::FIN_100,
                name: 'Billing Readiness',
                description: 'Show how much delivered tonnage is fully ready to invoice.',
                businessQuestion: 'What share of delivered tonnage is invoice-ready?',
                businessDecision: 'Whether to complete tickets/documents before the billing run.',
                category: KpiCategory::FINANCE,
                owner: KpiOwner::FINANCE,
                calculatorInterface: BillingCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD],
                thresholds: [OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD],
                businessEvents: [EventId::BILLING_BLOCKED],
                refreshStrategy: KpiRefreshStrategy::HOURLY,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::PERCENT,
                commandCenters: [CommandCenter::FINANCE, CommandCenter::OPERATIONS],
                drillDown: 'Incomplete-tickets queue',
                requiredAction: 'Complete the tickets / documents',
                successCriteria: '≥ target % of delivered tonnage invoice-ready',
                failureImpact: [BusinessImpact::FINANCIAL],
                dependencies: [KpiId::OPS_004, KpiId::OPS_005],
                version: 1,
                deprecated: false,
                notes: 'Future parameter: billable_window_days.',
            ),
            new KpiDefinition(
                id: KpiId::FIN_101,
                name: 'Revenue Blocked',
                description: 'Put a money figure on tonnage delivered but not yet billable.',
                businessQuestion: 'How much revenue is stuck unbillable right now?',
                businessDecision: 'Whether to prioritise clearing the blocking documents/weights.',
                category: KpiCategory::FINANCE,
                owner: KpiOwner::FINANCE,
                calculatorInterface: BillingCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING],
                parameters: [],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::HOURLY,
                severity: KpiSeverity::CRITICAL,
                unit: KpiUnit::CURRENCY,
                commandCenters: [CommandCenter::FINANCE, CommandCenter::EXECUTIVE],
                drillDown: 'Blocked-tickets queue',
                requiredAction: 'Complete the blocking documents / weights',
                successCriteria: 'Blocked revenue = 0',
                failureImpact: [BusinessImpact::FINANCIAL],
                dependencies: [KpiId::FIN_100],
                version: 1,
                deprecated: false,
                notes: 'Consumes the BillingBlocked fact via its dependency on KPI-FIN-100 (the single emitter); it does not emit its own event (one event, one emitter). Future parameters: revenue_rate_per_tonne, billable_window_days.',
            ),
            new KpiDefinition(
                id: KpiId::FIN_102,
                name: 'Revenue Forecast',
                description: 'Project period revenue from confirmed + expected billable tonnage.',
                businessQuestion: 'What revenue will the period deliver?',
                businessDecision: 'Whether to escalate to protect the revenue target.',
                category: KpiCategory::FINANCE,
                owner: KpiOwner::FINANCE,
                calculatorInterface: BillingCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING, KpiDataSource::FLEET],
                parameters: [],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::CURRENCY,
                commandCenters: [CommandCenter::FINANCE, CommandCenter::EXECUTIVE],
                drillDown: 'Revenue bridge (delivered / ready / forecast / at-risk)',
                requiredAction: 'Escalate to protect the target',
                successCriteria: 'Forecast ≥ revenue target',
                failureImpact: [BusinessImpact::FINANCIAL, BusinessImpact::PLANNING],
                dependencies: [KpiId::OPS_001, KpiId::FIN_100],
                version: 1,
                deprecated: false,
                notes: 'Future parameters: revenue_rate_per_tonne, objective_confidence_bands.',
            ),

            // ── Fleet ───────────────────────────────────────────────────────────────
            new KpiDefinition(
                id: KpiId::FLT_200,
                name: 'Operational Capacity Today',
                description: 'State how much capacity can actually run today (not nominal fleet size).',
                businessQuestion: 'What capacity can operate today?',
                businessDecision: "Whether today's plan is feasible with the running fleet.",
                category: KpiCategory::FLEET,
                owner: KpiOwner::FLEET,
                calculatorInterface: CapacityCalculatorInterface::class,
                readModels: [KpiDataSource::FLEET, KpiDataSource::MAINTENANCE, KpiDataSource::INSPECTION],
                parameters: [OperationalParameterKey::DEFAULT_CAPACITY, OperationalParameterKey::TARGET_ROTATIONS],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::HOURLY,
                severity: KpiSeverity::CRITICAL,
                unit: KpiUnit::TONNES,
                commandCenters: [CommandCenter::FLEET, CommandCenter::EXECUTIVE],
                drillDown: 'Fleet status (running / down / blocked)',
                requiredAction: "Rebalance the day's plan to running capacity",
                successCriteria: 'Operational capacity ≥ planned demand',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::PLANNING],
                dependencies: [KpiId::MNT_400, KpiId::HSE_500],
                version: 1,
                deprecated: false,
                notes: '',
            ),
            new KpiDefinition(
                id: KpiId::FLT_201,
                name: 'Capacity At Risk (This Week)',
                description: 'Warn how much capacity will be lost this week to maintenance / blocks.',
                businessQuestion: 'How much capacity will we lose this week?',
                businessDecision: 'Whether to pre-empt maintenance or source replacement trucks.',
                category: KpiCategory::FLEET,
                owner: KpiOwner::FLEET,
                calculatorInterface: CapacityCalculatorInterface::class,
                readModels: [KpiDataSource::FLEET, KpiDataSource::MAINTENANCE],
                parameters: [OperationalParameterKey::MAINTENANCE_WARNING_RATIO, OperationalParameterKey::WARNING_THRESHOLD_KM, OperationalParameterKey::MAX_ROTATIONS_BEFORE_MAINTENANCE],
                thresholds: [OperationalParameterKey::WARNING_THRESHOLD_KM, OperationalParameterKey::MAX_ROTATIONS_BEFORE_MAINTENANCE, OperationalParameterKey::MAINTENANCE_WARNING_RATIO],
                businessEvents: [EventId::CAPACITY_REDUCED],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::TONNES,
                commandCenters: [CommandCenter::FLEET, CommandCenter::EXECUTIVE],
                drillDown: 'At-risk trucks',
                requiredAction: 'Pre-empt maintenance or source trucks',
                successCriteria: 'Capacity at risk within planned buffer',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::PLANNING],
                dependencies: [KpiId::MNT_400, KpiId::HSE_500],
                version: 1,
                deprecated: false,
                notes: '',
            ),
            new KpiDefinition(
                id: KpiId::FLT_202,
                name: 'Fleet Utilization',
                description: 'Show whether available trucks are actually being used.',
                businessQuestion: 'Are available trucks actually used?',
                businessDecision: 'Whether to rebalance dispatch toward idle capacity.',
                category: KpiCategory::FLEET,
                owner: KpiOwner::FLEET,
                calculatorInterface: UtilizationCalculatorInterface::class,
                readModels: [KpiDataSource::FLEET, KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::TARGET_ROTATIONS],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::PERCENT,
                commandCenters: [CommandCenter::FLEET],
                drillDown: 'Per-truck utilization',
                requiredAction: 'Rebalance dispatch',
                successCriteria: 'Utilization within band for available trucks',
                failureImpact: [BusinessImpact::OPERATIONAL],
                dependencies: [KpiId::FLT_200, KpiId::OPS_008],
                version: 1,
                deprecated: false,
                notes: 'Future parameter: utilization_band (introduced with the utilization scoring band).',
            ),
            new KpiDefinition(
                id: KpiId::FLT_203,
                name: 'Truck Productivity',
                description: 'Identify trucks underperforming on output.',
                businessQuestion: 'Which trucks underperform?',
                businessDecision: 'Whether to investigate a truck (mechanical, route, driver).',
                category: KpiCategory::FLEET,
                owner: KpiOwner::FLEET,
                calculatorInterface: ProductivityCalculatorInterface::class,
                readModels: [KpiDataSource::TRANSPORT_TRACKING],
                parameters: [OperationalParameterKey::DEFAULT_CAPACITY],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::WEEKLY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::PERCENT,
                commandCenters: [CommandCenter::FLEET],
                drillDown: 'Truck detail',
                requiredAction: 'Investigate the truck',
                successCriteria: 'Truck within productivity band',
                failureImpact: [BusinessImpact::OPERATIONAL],
                dependencies: [KpiId::FLT_202],
                version: 1,
                deprecated: false,
                notes: 'Future parameter: productivity_band (shared with KPI-OPS-006).',
            ),

            // ── Dispatch ────────────────────────────────────────────────────────────
            new KpiDefinition(
                id: KpiId::DSP_300,
                name: 'Not-Started Planned Loads',
                description: 'Catch planned trucks that have not started, while the day can still be saved.',
                businessQuestion: 'Which planned trucks have not started?',
                businessDecision: 'Whether to reassign or call the driver now.',
                category: KpiCategory::DISPATCH,
                owner: KpiOwner::DISPATCH,
                calculatorInterface: DispatchCalculatorInterface::class,
                readModels: [KpiDataSource::DISPATCH],
                parameters: [],
                thresholds: [],
                businessEvents: [EventId::TRUCK_UNAVAILABLE],
                refreshStrategy: KpiRefreshStrategy::REALTIME,
                severity: KpiSeverity::HIGH,
                unit: KpiUnit::COUNT,
                commandCenters: [CommandCenter::DISPATCH, CommandCenter::OPERATIONS],
                drillDown: 'Dispatch board',
                requiredAction: 'Reassign or call the driver',
                successCriteria: '0 planned trucks unstarted past the deadline',
                failureImpact: [BusinessImpact::OPERATIONAL],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: 'Refresh is real-time (15 min). Future parameter: start_deadline_hours.',
            ),
            new KpiDefinition(
                id: KpiId::DSP_301,
                name: 'Dispatch Efficiency',
                description: 'Show how plan converts to started and completed work.',
                businessQuestion: 'How much of the plan was started and completed?',
                businessDecision: 'Whether the planning assumptions need adjustment.',
                category: KpiCategory::DISPATCH,
                owner: KpiOwner::DISPATCH,
                calculatorInterface: DispatchCalculatorInterface::class,
                readModels: [KpiDataSource::DISPATCH, KpiDataSource::TRANSPORT_TRACKING],
                parameters: [],
                thresholds: [],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::HOURLY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::PERCENT,
                commandCenters: [CommandCenter::DISPATCH],
                drillDown: 'Dispatch detail',
                requiredAction: 'Adjust planning assumptions',
                successCriteria: 'Planned → completed conversion within target',
                failureImpact: [BusinessImpact::PLANNING],
                dependencies: [KpiId::DSP_300],
                version: 1,
                deprecated: false,
                notes: 'Future parameter: start_deadline_hours (shared with KPI-DSP-300).',
            ),

            // ── Maintenance ─────────────────────────────────────────────────────────
            new KpiDefinition(
                id: KpiId::MNT_400,
                name: 'Trucks At Breakdown Risk',
                description: 'Name the trucks likely to stop production soon.',
                businessQuestion: 'Which trucks risk stopping production?',
                businessDecision: 'Whether to schedule maintenance before failure.',
                category: KpiCategory::MAINTENANCE,
                owner: KpiOwner::MAINTENANCE,
                calculatorInterface: MaintenanceCalculatorInterface::class,
                readModels: [KpiDataSource::MAINTENANCE, KpiDataSource::FLEET],
                parameters: [OperationalParameterKey::MAINTENANCE_WARNING_RATIO, OperationalParameterKey::WARNING_THRESHOLD_KM, OperationalParameterKey::MAX_ROTATIONS_BEFORE_MAINTENANCE],
                thresholds: [OperationalParameterKey::WARNING_THRESHOLD_KM, OperationalParameterKey::MAX_ROTATIONS_BEFORE_MAINTENANCE, OperationalParameterKey::MAINTENANCE_WARNING_RATIO],
                businessEvents: [EventId::MAINTENANCE_OVERDUE],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::CRITICAL,
                unit: KpiUnit::COUNT,
                commandCenters: [CommandCenter::MAINTENANCE, CommandCenter::FLEET],
                drillDown: 'Maintenance-due list',
                requiredAction: 'Schedule maintenance',
                successCriteria: '0 trucks past the warning threshold unscheduled',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::SAFETY],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: '',
            ),
            new KpiDefinition(
                id: KpiId::MNT_401,
                name: 'Maintenance Due (Next 7 Days)',
                description: 'Give the workshop a forward view to plan workload.',
                businessQuestion: 'What maintenance is coming in the next 7 days?',
                businessDecision: 'Whether to book workshop slots and parts now.',
                category: KpiCategory::MAINTENANCE,
                owner: KpiOwner::MAINTENANCE,
                calculatorInterface: MaintenanceCalculatorInterface::class,
                readModels: [KpiDataSource::MAINTENANCE],
                parameters: [OperationalParameterKey::MAX_ROTATIONS_BEFORE_MAINTENANCE, OperationalParameterKey::WARNING_THRESHOLD_KM],
                thresholds: [OperationalParameterKey::MAX_ROTATIONS_BEFORE_MAINTENANCE, OperationalParameterKey::WARNING_THRESHOLD_KM],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::COUNT,
                commandCenters: [CommandCenter::MAINTENANCE],
                drillDown: 'Maintenance forecast',
                requiredAction: 'Book workshop / parts',
                successCriteria: 'All upcoming due items scheduled',
                failureImpact: [BusinessImpact::OPERATIONAL, BusinessImpact::PLANNING],
                dependencies: [KpiId::MNT_400],
                version: 1,
                deprecated: false,
                notes: '',
            ),

            // ── Safety / HSE ────────────────────────────────────────────────────────
            new KpiDefinition(
                id: KpiId::HSE_500,
                name: 'Trucks Legally Blocked',
                description: 'State which trucks may not legally operate (failed or expired inspection).',
                businessQuestion: 'Which trucks cannot legally operate?',
                businessDecision: 'Whether to validate / correct before the truck is dispatched.',
                category: KpiCategory::HSE,
                owner: KpiOwner::HSE,
                calculatorInterface: InspectionCalculatorInterface::class,
                readModels: [KpiDataSource::INSPECTION, KpiDataSource::FLEET],
                parameters: [OperationalParameterKey::INSPECTION_SLA_DAYS],
                thresholds: [OperationalParameterKey::INSPECTION_SLA_DAYS],
                businessEvents: [EventId::INSPECTION_EXPIRED],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::CRITICAL,
                unit: KpiUnit::COUNT,
                commandCenters: [CommandCenter::HSE, CommandCenter::FLEET],
                drillDown: 'Blocked-trucks list',
                requiredAction: 'Validate or correct the inspection',
                successCriteria: '0 trucks legally blocked in the operating fleet',
                failureImpact: [BusinessImpact::LEGAL, BusinessImpact::SAFETY],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: '',
            ),
            new KpiDefinition(
                id: KpiId::HSE_501,
                name: 'Inspections Awaiting Validation',
                description: 'Surface compliance work pending sign-off.',
                businessQuestion: 'What compliance is pending validation?',
                businessDecision: 'Whether to validate the pending inspections.',
                category: KpiCategory::HSE,
                owner: KpiOwner::HSE,
                calculatorInterface: InspectionCalculatorInterface::class,
                readModels: [KpiDataSource::INSPECTION],
                parameters: [OperationalParameterKey::INSPECTION_SLA_DAYS],
                thresholds: [OperationalParameterKey::INSPECTION_SLA_DAYS],
                businessEvents: [],
                refreshStrategy: KpiRefreshStrategy::DAILY,
                severity: KpiSeverity::MEDIUM,
                unit: KpiUnit::COUNT,
                commandCenters: [CommandCenter::HSE],
                drillDown: 'Validation queue',
                requiredAction: 'Validate the inspections',
                successCriteria: '0 inspections overdue for validation',
                failureImpact: [BusinessImpact::LEGAL, BusinessImpact::SAFETY],
                dependencies: [],
                version: 1,
                deprecated: false,
                notes: '',
            ),
        ];
    }
}
