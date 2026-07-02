<?php

namespace App\Domain\Operations\Events\Derivers;

use App\Domain\Operations\Contracts\TransportTrackingReadModelInterface;
use App\Domain\Operations\Contracts\WeightCalculatorInterface;
use App\Domain\Operations\Events\Derivers\Contracts\BusinessEventDeriver;
use App\Domain\Operations\Events\WeightAnomalyDetected;
use App\Domain\Operations\ReadModels\Data\LoadProjection;

/**
 * Derives {@see WeightAnomalyDetected} from the TransportTracking Read Model (per-load
 * weights over the context period).
 *
 * For each load with both weights recorded it asks the WeightCalculator whether the
 * provider↔client gap violates the operational tolerance; when it does, it emits the event.
 * The tolerance and the gap value both come from the calculator — the deriver only reads raw
 * weights and skips loads it cannot test.
 */
final class TransportTrackingEventDeriver implements BusinessEventDeriver
{
    public function __construct(
        private readonly TransportTrackingReadModelInterface $transport,
        private readonly WeightCalculatorInterface $calculator,
    ) {}

    public function derive(DerivationContext $context): array
    {
        $events = [];

        foreach ($this->transport->loads($context->periodFrom, $context->periodTo) as $load) {
            /** @var LoadProjection $load */
            if ($load->providerNetWeight === null || $load->clientNetWeight === null) {
                continue; // cannot test a gap without both weights
            }

            if (! $this->calculator->isGapViolation($load->providerNetWeight, $load->clientNetWeight)) {
                continue;
            }

            $events[] = new WeightAnomalyDetected(
                $load->clientDate ?? $context->asOf,
                $load->loadId,
                'load',
                [
                    'reference' => $load->reference,
                    'truck_id' => $load->truckId,
                    'provider_net_weight' => $load->providerNetWeight,
                    'client_net_weight' => $load->clientNetWeight,
                    'gap' => $this->calculator->gap($load->providerNetWeight, $load->clientNetWeight),
                ],
            );
        }

        return $events;
    }
}
