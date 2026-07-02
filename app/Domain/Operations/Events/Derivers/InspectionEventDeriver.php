<?php

namespace App\Domain\Operations\Events\Derivers;

use App\Domain\Operations\Contracts\InspectionCalculatorInterface;
use App\Domain\Operations\Contracts\InspectionReadModelInterface;
use App\Domain\Operations\Events\Derivers\Contracts\BusinessEventDeriver;
use App\Domain\Operations\Events\InspectionExpired;
use App\Domain\Operations\ReadModels\Data\TruckInspectionProjection;

/**
 * Derives {@see InspectionExpired} from the Inspection Read Model.
 *
 * For each active truck it asks the InspectionCalculator whether the last inspection is
 * expired against the fleet SLA; when it is, it emits the event. The SLA-days threshold is
 * read by the calculator, never here — the deriver only supplies the raw last-inspection date
 * and the observation instant.
 */
final class InspectionEventDeriver implements BusinessEventDeriver
{
    public function __construct(
        private readonly InspectionReadModelInterface $inspection,
        private readonly InspectionCalculatorInterface $calculator,
    ) {}

    public function derive(DerivationContext $context): array
    {
        $events = [];

        foreach ($this->inspection->lastInspectionByActiveTruck() as $truck) {
            /** @var TruckInspectionProjection $truck */
            if (! $this->calculator->isExpiredForFleet($truck->lastInspectionDate, $context->asOf)) {
                continue;
            }

            $events[] = new InspectionExpired(
                $context->asOf,
                $truck->truckId,
                'truck',
                [
                    'matricule' => $truck->matricule,
                    'last_inspection_date' => $truck->lastInspectionDate?->format('Y-m-d'),
                ],
            );
        }

        return $events;
    }
}
