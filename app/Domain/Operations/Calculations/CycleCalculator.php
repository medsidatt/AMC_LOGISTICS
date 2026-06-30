<?php

namespace App\Domain\Operations\Calculations;

use App\Domain\Operations\Contracts\CycleCalculatorInterface;
use Carbon\Carbon;

/**
 * Cycle calculations. The averageCycleDays formula is lifted verbatim from the
 * (byte-identical) private methods in DriverKpiService and TruckKpiService so the
 * single owner produces the same result. Pure — no Eloquent, SQL, config, or events.
 */
class CycleCalculator implements CycleCalculatorInterface
{
    public function averageCycleDays(iterable $rotations): ?float
    {
        $rotations = collect($rotations);

        if ($rotations->count() < 2) {
            return null;
        }

        $deltas = [];
        $previous = null;
        foreach ($rotations as $r) {
            $providerDate = $r->provider_date ? Carbon::parse($r->provider_date) : null;
            $clientDate = $r->client_date ? Carbon::parse($r->client_date) : null;
            if (! $providerDate || ! $clientDate) {
                continue;
            }
            if ($previous !== null) {
                $deltas[] = max(0.0, $previous->floatDiffInDays($providerDate));
            }
            $previous = $clientDate;
        }

        if (empty($deltas)) {
            return null;
        }

        return array_sum($deltas) / count($deltas);
    }
}
