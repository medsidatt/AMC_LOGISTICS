<?php

namespace App\Domain\Operations\Events\Derivers;

use App\Domain\Operations\Contracts\MaintenanceCalculatorInterface;
use App\Domain\Operations\Contracts\MaintenanceReadModelInterface;
use App\Domain\Operations\Events\Derivers\Contracts\BusinessEventDeriver;
use App\Domain\Operations\Events\MaintenanceOverdue;
use App\Domain\Operations\ReadModels\Data\TruckMaintenanceProjection;

/**
 * Derives {@see MaintenanceOverdue} from the Maintenance Read Model.
 *
 * For each active truck it asks the MaintenanceCalculator whether the truck has passed its
 * next service; when it has, it emits the event. It computes nothing itself — the odometer,
 * last-service km and interval are raw projections, and the overdue decision is the
 * calculator's.
 *
 * Kilometre-tracked trucks only: rotation-tracked overdue needs a "rotations since last
 * service" read the Read Model layer does not yet expose, so those are deferred (skipped).
 */
final class MaintenanceEventDeriver implements BusinessEventDeriver
{
    public function __construct(
        private readonly MaintenanceReadModelInterface $maintenance,
        private readonly MaintenanceCalculatorInterface $calculator,
    ) {}

    public function derive(DerivationContext $context): array
    {
        $events = [];

        foreach ($this->maintenance->activeTrucksMaintenance() as $truck) {
            /** @var TruckMaintenanceProjection $truck */
            if ($truck->maintenanceType !== 'kilometers') {
                continue; // rotation-tracked overdue deferred (no rotations-since read yet)
            }

            if (! $this->calculator->isKilometersOverdue($truck->totalKilometers, $truck->lastMaintenanceKm, $truck->kmMaintenanceInterval)) {
                continue;
            }

            $events[] = new MaintenanceOverdue(
                $context->asOf,
                $truck->truckId,
                'truck',
                [
                    'matricule' => $truck->matricule,
                    'total_kilometers' => $truck->totalKilometers,
                ],
            );
        }

        return $events;
    }
}
