<?php

namespace App\Domain\Operations\KPI;

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

/**
 * Immutable description of one KPI (docs/kpi-catalog.md). Pure metadata: no Eloquent,
 * SQL, config(), env(), services, calculations, or event instantiation. Holds only
 * stable identities (calculator interface FQN, read models, parameters, events) that
 * point at the components that do the work.
 *
 * final + readonly → constructed once, never mutated.
 *
 * @phpstan-consistent-constructor
 */
final readonly class KpiDefinition implements KpiDefinitionInterface
{
    /**
     * @param  list<KpiDataSource>  $readModels
     * @param  list<OperationalParameterKey>  $parameters
     * @param  list<OperationalParameterKey>  $thresholds
     * @param  list<EventId>  $businessEvents
     * @param  list<CommandCenter>  $commandCenters
     * @param  list<BusinessImpact>  $failureImpact
     * @param  list<KpiId>  $dependencies
     */
    public function __construct(
        private KpiId $id,
        private string $name,
        private string $description,
        private string $businessQuestion,
        private string $businessDecision,
        private KpiCategory $category,
        private KpiOwner $owner,
        private string $calculatorInterface,
        private array $readModels,
        private array $parameters,
        private array $thresholds,
        private array $businessEvents,
        private KpiRefreshStrategy $refreshStrategy,
        private KpiSeverity $severity,
        private KpiUnit $unit,
        private array $commandCenters,
        private string $drillDown,
        private string $requiredAction,
        private string $successCriteria,
        private array $failureImpact,
        private array $dependencies,
        private int $version,
        private bool $deprecated,
        private string $notes,
    ) {}

    public function id(): KpiId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function businessQuestion(): string
    {
        return $this->businessQuestion;
    }

    public function businessDecision(): string
    {
        return $this->businessDecision;
    }

    public function category(): KpiCategory
    {
        return $this->category;
    }

    public function owner(): KpiOwner
    {
        return $this->owner;
    }

    public function calculatorInterface(): string
    {
        return $this->calculatorInterface;
    }

    public function readModels(): array
    {
        return $this->readModels;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function thresholds(): array
    {
        return $this->thresholds;
    }

    public function businessEvents(): array
    {
        return $this->businessEvents;
    }

    public function refreshStrategy(): KpiRefreshStrategy
    {
        return $this->refreshStrategy;
    }

    public function severity(): KpiSeverity
    {
        return $this->severity;
    }

    public function unit(): KpiUnit
    {
        return $this->unit;
    }

    public function commandCenters(): array
    {
        return $this->commandCenters;
    }

    public function drillDown(): string
    {
        return $this->drillDown;
    }

    public function requiredAction(): string
    {
        return $this->requiredAction;
    }

    public function successCriteria(): string
    {
        return $this->successCriteria;
    }

    public function failureImpact(): array
    {
        return $this->failureImpact;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function deprecated(): bool
    {
        return $this->deprecated;
    }

    public function isActive(): bool
    {
        return ! $this->deprecated;
    }

    public function notes(): string
    {
        return $this->notes;
    }
}
