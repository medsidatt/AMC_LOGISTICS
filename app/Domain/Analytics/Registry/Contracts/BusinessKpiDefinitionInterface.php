<?php

namespace App\Domain\Analytics\Registry\Contracts;

use App\Domain\Analytics\Registry\Enums\Aggregation;
use App\Domain\Analytics\Registry\Enums\BusinessKpiCategory;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Analytics\Registry\Enums\RefreshCadence;

/**
 * The metadata of one descriptive (BI) KPI — and nothing else. It DESCRIBES what a metric
 * measures and where its data comes from; it never calculates, queries, or decides.
 *
 * It answers "what happened / how much / what's trending?" — NOT "what requires action?".
 * It therefore deliberately carries NONE of the operational action fields (severity, owner,
 * businessDecision, requiredAction, drillDown, failureImpact, businessEvents, thresholds,
 * successCriteria, commandCenters). Read-model / calculator / consumer references are opaque
 * string identifiers (resolved by the future BI calculators in R4.2), so this contract
 * imports no Read Model, Calculator, Intelligence, Event, Translator, or Command Center.
 */
interface BusinessKpiDefinitionInterface
{
    public function id(): BusinessKpiId;

    public function name(): string;

    public function category(): BusinessKpiCategory;

    /** The descriptive question it answers ("how much / how many / what trend?"). */
    public function businessQuestion(): string;

    /** Why the metric is tracked (descriptive value — never a decision or action). */
    public function businessValue(): string;

    public function unit(): MetricUnit;

    public function aggregation(): Aggregation;

    public function refreshCadence(): RefreshCadence;

    /** Whether the metric supports time-series / period-over-period reporting. */
    public function trendSupport(): bool;

    /** @return list<string> Read Model identifiers this metric reads (reuse; resolved in R4.2). */
    public function readModels(): array;

    /** @return list<string> Calculator identifiers this metric reuses (resolved in R4.2). */
    public function calculators(): array;

    /** @return list<string> Report / dashboard identifiers that consume this metric. */
    public function reportConsumers(): array;

    public function version(): int;

    public function deprecated(): bool;

    /** A definition is active while it is not deprecated. */
    public function active(): bool;

    public function notes(): string;
}
