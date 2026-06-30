<?php

namespace App\Domain\Operations\Events;

final readonly class FuelConsumptionAbnormal extends BusinessEvent
{
    public function id(): EventId { return EventId::FUEL_CONSUMPTION_ABNORMAL; }
    public function owner(): BusinessOwner { return BusinessOwner::FLEET; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::HIGH; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::FINANCIAL; }
    public function requiredAction(): string { return 'Investigate the fuel anomaly'; }
}
