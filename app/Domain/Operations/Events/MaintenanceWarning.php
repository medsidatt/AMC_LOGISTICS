<?php

namespace App\Domain\Operations\Events;

final readonly class MaintenanceWarning extends BusinessEvent
{
    public function id(): EventId { return EventId::MAINTENANCE_WARNING; }
    public function owner(): BusinessOwner { return BusinessOwner::MAINTENANCE; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::MEDIUM; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'Book the workshop and parts'; }
}
