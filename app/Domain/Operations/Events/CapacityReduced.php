<?php

namespace App\Domain\Operations\Events;

final readonly class CapacityReduced extends BusinessEvent
{
    public function id(): EventId { return EventId::CAPACITY_REDUCED; }
    public function owner(): BusinessOwner { return BusinessOwner::FLEET; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::HIGH; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::PLANNING; }
    public function requiredAction(): string { return 'Pre-empt maintenance or source trucks'; }
}
