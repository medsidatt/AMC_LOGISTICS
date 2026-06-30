<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\RotationCalculatorInterface;
use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Domain\Operations\ReadModels\Data\PeriodTotals;
use App\Enums\OperationalParameterKey;
use App\Services\OperationalParameterService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rotation aggregation + fiscal-period handling. Delegates data access to the
 * TransportTrackingReadModel and resolves the fiscal-month start day from the
 * parameter store. No Eloquent, SQL, config, or events.
 */
class RotationCalculator implements RotationCalculatorInterface
{
    public function __construct(
        private readonly TransportTrackingReadModelInterface $trackings,
        private readonly OperationalParameterService $parameters,
    ) {}

    public function byTruck(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->trackings->aggregateByTruck($from, $to);
    }

    public function byDriver(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->trackings->aggregateByDriver($from, $to);
    }

    public function monthlyTonnage(CarbonInterface $from): Collection
    {
        return $this->trackings->monthlyTonnage(
            $this->parameters->int(OperationalParameterKey::FISCAL_MONTH_START_DAY),
            $from,
        );
    }

    public function fleetTotals(CarbonInterface $from, CarbonInterface $to): PeriodTotals
    {
        return $this->trackings->periodTotals($from, $to);
    }
}
