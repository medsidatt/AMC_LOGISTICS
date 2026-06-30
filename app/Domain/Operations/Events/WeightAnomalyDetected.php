<?php

namespace App\Domain\Operations\Events;

final readonly class WeightAnomalyDetected extends BusinessEvent
{
    public function id(): EventId { return EventId::WEIGHT_ANOMALY_DETECTED; }
    public function owner(): BusinessOwner { return BusinessOwner::OPERATIONS; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::HIGH; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::FINANCIAL; }
    public function requiredAction(): string { return 'Verify the weighbridge ticket'; }
}
