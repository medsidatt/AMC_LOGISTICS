<?php

namespace App\Domain\Operations\Events;

final readonly class DriverDisciplineLow extends BusinessEvent
{
    public function id(): EventId { return EventId::DRIVER_DISCIPLINE_LOW; }
    public function owner(): BusinessOwner { return BusinessOwner::OPERATIONS; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::MEDIUM; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'Coach or reassign the driver'; }
}
