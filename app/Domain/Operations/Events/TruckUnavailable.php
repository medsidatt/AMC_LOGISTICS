<?php

namespace App\Domain\Operations\Events;

final readonly class TruckUnavailable extends BusinessEvent
{
    public function id(): EventId { return EventId::TRUCK_UNAVAILABLE; }
    public function owner(): BusinessOwner { return BusinessOwner::DISPATCH; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::CRITICAL; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'Reassign or call the driver'; }
}
