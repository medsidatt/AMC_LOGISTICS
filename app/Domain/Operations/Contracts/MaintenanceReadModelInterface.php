<?php

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\ReadModels\Data\TruckMaintenanceProjection;
use Illuminate\Support\Collection;

/**
 * Business projections over the active fleet's RAW maintenance fields.
 * Returns immutable DTOs of stored values only (odometer, tracking mode, interval, latest
 * maintenance km/date); never derives distance-to-service and never applies the red/warning
 * threshold (that is a Domain Calculator's job — MaintenanceCalculator). The only component
 * that reads the maintenance state of the active fleet for this concern.
 */
interface MaintenanceReadModelInterface
{
    /**
     * Raw maintenance fields for each active truck (no derived or decided values).
     *
     * @return Collection<int, TruckMaintenanceProjection>
     */
    public function activeTrucksMaintenance(): Collection;
}
