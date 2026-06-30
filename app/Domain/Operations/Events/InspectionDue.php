<?php

namespace App\Domain\Operations\Events;

final readonly class InspectionDue extends BusinessEvent
{
    public function id(): EventId { return EventId::INSPECTION_DUE; }
    public function owner(): BusinessOwner { return BusinessOwner::HSE; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::MEDIUM; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::LEGAL; }
    public function requiredAction(): string { return 'Schedule the inspection'; }
}
