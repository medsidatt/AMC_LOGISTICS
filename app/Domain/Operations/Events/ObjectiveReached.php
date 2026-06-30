<?php

namespace App\Domain\Operations\Events;

final readonly class ObjectiveReached extends BusinessEvent
{
    public function id(): EventId { return EventId::OBJECTIVE_REACHED; }
    public function owner(): BusinessOwner { return BusinessOwner::OPERATIONS; }
    public function severity(): BusinessEventSeverity { return BusinessEventSeverity::INFORMATIONAL; }
    public function businessImpact(): BusinessImpact { return BusinessImpact::OPERATIONAL; }
    public function requiredAction(): string { return 'None'; }
}
