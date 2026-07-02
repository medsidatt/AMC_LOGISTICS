<?php

namespace App\Domain\Analytics\Registry;

use App\Domain\Analytics\Registry\Contracts\BusinessKpiDefinitionInterface;
use App\Domain\Analytics\Registry\Enums\Aggregation;
use App\Domain\Analytics\Registry\Enums\BusinessKpiCategory;
use App\Domain\Analytics\Registry\Enums\BusinessKpiId;
use App\Domain\Analytics\Registry\Enums\MetricUnit;
use App\Domain\Analytics\Registry\Enums\RefreshCadence;

/**
 * One descriptive (BI) KPI definition — an immutable metadata value object. Built once from
 * hardcoded catalog metadata, never mutated. It carries only descriptive fields and holds NO
 * calculation, query, DB, config, or action semantics. Getters only.
 *
 * @phpstan-consistent-constructor
 */
final readonly class BusinessKpiDefinition implements BusinessKpiDefinitionInterface
{
    /**
     * @param  list<string>  $readModels
     * @param  list<string>  $calculators
     * @param  list<string>  $reportConsumers
     */
    public function __construct(
        private BusinessKpiId $id,
        private string $name,
        private BusinessKpiCategory $category,
        private string $businessQuestion,
        private string $businessValue,
        private MetricUnit $unit,
        private Aggregation $aggregation,
        private RefreshCadence $refreshCadence,
        private bool $trendSupport,
        private array $readModels,
        private array $calculators,
        private array $reportConsumers,
        private int $version = 1,
        private bool $deprecated = false,
        private string $notes = '',
    ) {}

    public function id(): BusinessKpiId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function category(): BusinessKpiCategory
    {
        return $this->category;
    }

    public function businessQuestion(): string
    {
        return $this->businessQuestion;
    }

    public function businessValue(): string
    {
        return $this->businessValue;
    }

    public function unit(): MetricUnit
    {
        return $this->unit;
    }

    public function aggregation(): Aggregation
    {
        return $this->aggregation;
    }

    public function refreshCadence(): RefreshCadence
    {
        return $this->refreshCadence;
    }

    public function trendSupport(): bool
    {
        return $this->trendSupport;
    }

    /** @return list<string> */
    public function readModels(): array
    {
        return $this->readModels;
    }

    /** @return list<string> */
    public function calculators(): array
    {
        return $this->calculators;
    }

    /** @return list<string> */
    public function reportConsumers(): array
    {
        return $this->reportConsumers;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function deprecated(): bool
    {
        return $this->deprecated;
    }

    public function active(): bool
    {
        return ! $this->deprecated;
    }

    public function notes(): string
    {
        return $this->notes;
    }
}
