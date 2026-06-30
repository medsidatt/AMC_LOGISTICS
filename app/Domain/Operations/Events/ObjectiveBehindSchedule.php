<?php

namespace App\Domain\Operations\Events;

final readonly class ObjectiveBehindSchedule extends BusinessEvent
{
    public function id(): EventId { return EventId::OBJECTIVE_BEHIND_SCHEDULE; }
    public function owner(): BusinessOwner { return BusinessOwner::OPERATIONS; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::CRITICAL; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'Allocate reserve trucks'; }
}
