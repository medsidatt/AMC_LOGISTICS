<?php

namespace App\Domain\Operations\KPI\Contracts;

use App\Domain\Operations\Events\BusinessImpact;
use App\Domain\Operations\Events\EventId;
use App\Domain\Operations\KPI\Enums\CommandCenter;
use App\Domain\Operations\KPI\Enums\KpiCategory;
use App\Domain\Operations\KPI\Enums\KpiDataSource;
use App\Domain\Operations\KPI\Enums\KpiId;
use App\Domain\Operations\KPI\Enums\KpiOwner;
use App\Domain\Operations\KPI\Enums\KpiRefreshStrategy;
use App\Domain\Operations\KPI\Enums\KpiSeverity;
use App\Domain\Operations\KPI\Enums\KpiUnit;
use App\Enums\OperationalParameterKey;

/**
 * The metadata of one KPI — and nothing else. A KPI definition DESCRIBES a KPI: it
 * never calculates, never queries, never reads Eloquent, never resolves a service,
 * never instantiates an event. It points at the calculator, read models, parameters
 * and events that DO the work, by stable identity only.
 *
 * Every accessor returns an immutable scalar / enum / list of enums.
 */
interface KpiDefinitionInterface
{
    public function id(): KpiId;

    public function name(): string;

    /** Why this KPI exists (catalog "Purpose"). */
    public function description(): string;

    /** The question it answers (catalog "Business question"). */
    public function businessQuestion(): string;

    /** The decision made after seeing it (catalog "Business decision"). */
    public function businessDecision(): string;

    public function category(): KpiCategory;

    public function owner(): KpiOwner;

    /** FQN of the single Domain Calculator interface that owns the calculation (ADR-003). */
    public function calculatorInterface(): string;

    /** @return list<KpiDataSource> the Read Models that supply the data (ADR-005). */
    public function readModels(): array;

    /** @return list<OperationalParameterKey> configurable values that influence it. */
    public function parameters(): array;

    /** @return list<OperationalParameterKey> the decision-boundary subset of parameters. */
    public function thresholds(): array;

    /** @return list<EventId> Business Events this KPI feeds — reference only (R1.4, unconsumed). */
    public function businessEvents(): array;

    public function refreshStrategy(): KpiRefreshStrategy;

    public function severity(): KpiSeverity;

    public function unit(): KpiUnit;

    /** @return list<CommandCenter> command centers that display this KPI. */
    public function commandCenters(): array;

    /** Where the user goes next (catalog "Drill-down"). */
    public function drillDown(): string;

    /** Exactly what should happen (catalog "Required action"). */
    public function requiredAction(): string;

    /** When the KPI is considered healthy (catalog "Success criteria"). */
    public function successCriteria(): string;

    /** @return list<BusinessImpact> what is lost if ignored (catalog "Failure impact"). */
    public function failureImpact(): array;

    /** @return list<KpiId> other KPIs this one consumes (catalog "Depends on"). */
    public function dependencies(): array;

    public function version(): int;

    public function deprecated(): bool;

    /** A definition is active while it is not deprecated. */
    public function isActive(): bool;

    public function notes(): string;
}
