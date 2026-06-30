<?php

namespace App\Domain\Operations\Events;

final readonly class InspectionExpired extends BusinessEvent
{
    public function id(): EventId { return EventId::INSPECTION_EXPIRED; }
    public function owner(): BusinessOwner { return BusinessOwner::HSE; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::CRITICAL; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::LEGAL; }
    public function requiredAction(): string { return 'Validate or correct the inspection'; }
}
