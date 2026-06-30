<?php

namespace App\Domain\Operations\Events;

final readonly class MaintenanceOverdue extends BusinessEvent
{
    public function id(): EventId { return EventId::MAINTENANCE_OVERDUE; }
    public function owner(): BusinessOwner { return BusinessOwner::MAINTENANCE; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::CRITICAL; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'Schedule maintenance now'; }
}
